<?php

namespace Tests\Feature\Refactor;

use App\Models\CrunchAlert;
use App\Models\EventCategory;
use App\Models\EventTemplate;
use App\Models\SchedulePause;
use App\Models\Task;
use App\Models\User;
use App\Planning\Crunch\Level;
use App\Planning\Size;
use App\Services\Planning\Capacity;
use App\Services\Planning\CrunchDecisions;
use App\Services\Planning\CrunchWatch;
use App\Services\Planning\Suggestions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Step 1.4 on real rows (R5, R10; risks F5, F7, C4): capacity from the week
 * plan, the stored early warning, and drop → Later. Deep work (pomodoro)
 * Mon–Fri 19:30–21:30 = 4 sessions an evening, Monday 9 November 2026.
 */
class CrunchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
        $deepWork = EventCategory::factory()->for($this->user)->pomodoro()->create();
        EventTemplate::factory()->for($this->user)->create([
            'category_id' => $deepWork->id, 'is_recurring' => true, 'recurrence' => '1,2,3,4,5',
            'default_start' => '19:30', 'duration' => 120,
        ]);
    }

    private function at(string $dateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, 'Europe/Zurich');
    }

    private function task(array $attributes = []): Task
    {
        return Task::factory()->for($this->user)->create(['list' => 'tasks', ...$attributes]);
    }

    public function test_capacity_per_day_comes_from_the_week_plan(): void
    {
        SchedulePause::factory()->for($this->user)->create(['date' => '2026-11-11']);

        $days = (new Capacity)->forecast($this->user, $this->at('2026-11-09 08:00'))->days;

        $this->assertSame([4, 4, 0, 4, 4, 0, 0], array_map(fn ($d) => $d->capacitySessions, array_slice($days, 0, 7)));
    }

    public function test_an_overloaded_day_is_flagged_and_warned_about_once(): void
    {
        $this->task(['size' => Size::Block, 'duration_minutes' => 250, 'hard_deadline' => '2026-11-10']);
        $watch = new CrunchWatch;

        $first = $watch->check($this->user, $this->at('2026-11-09 08:00'));
        $second = $watch->check($this->user, $this->at('2026-11-09 09:00'));

        $this->assertTrue($first->notify);
        $this->assertFalse($second->notify, 'at most one warning a day');
        $alert = CrunchAlert::sole();
        $this->assertTrue($alert->active);
        $this->assertSame(Level::Hard, $alert->level);
        $this->assertSame('2026-11-10', $alert->crunch_date->toDateString());
        $this->assertSame(50, $alert->missing_minutes);
        $this->assertSame('2026-11-09', $alert->raised_on->toDateString());
    }

    public function test_shrinking_clears_the_warning_once_there_is_room(): void
    {
        $task = $this->task(['size' => Size::Block, 'duration_minutes' => 250, 'hard_deadline' => '2026-11-10']);
        $watch = new CrunchWatch;
        $watch->check($this->user, $this->at('2026-11-09 08:00'));

        (new CrunchDecisions)->shrink($this->user, $task->id); // Block → Long: 75 of 200 minutes
        $decision = $watch->check($this->user, $this->at('2026-11-09 08:05'));

        $this->assertTrue($decision->cleared);
        $this->assertFalse(CrunchAlert::sole()->active);
        $this->assertNotNull(CrunchAlert::sole()->cleared_at);
    }

    public function test_the_hourly_check_clears_a_warning_once_the_work_is_gone(): void
    {
        $task = $this->task(['size' => Size::Block, 'duration_minutes' => 250, 'hard_deadline' => now()->addDay()->toDateString()]);
        (new CrunchWatch)->check($this->user);
        $this->assertTrue(CrunchAlert::sole()->active, 'precondition');

        $task->update(['is_completed' => true, 'completed_at' => now()]); // no open work left at all
        $this->artisan('app:run-planning-checks')->assertSuccessful();

        $this->assertFalse(CrunchAlert::sole()->active);
    }

    public function test_a_user_without_crunch_gets_no_alert_row(): void
    {
        $this->task(['hard_deadline' => '2026-11-12']);

        $decision = (new CrunchWatch)->check($this->user, $this->at('2026-11-09 08:00'));

        $this->assertFalse($decision->state->active);
        $this->assertDatabaseCount('crunch_alerts', 0);
    }

    public function test_drop_moves_the_task_to_later_and_out_of_the_plan(): void
    {
        $task = $this->task(['size' => Size::Block, 'duration_minutes' => 250, 'hard_deadline' => '2026-11-10', 'is_today' => true]);

        $dropped = (new CrunchDecisions)->drop($this->user, $task->id);

        $this->assertSame('later', $dropped->list);
        $this->assertFalse($dropped->is_today);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'list' => 'later', 'is_completed' => false]);
        $this->assertFalse((new Capacity)->forecast($this->user, $this->at('2026-11-09 08:00'))->hasCrunch());
        $this->assertNull((new Suggestions)->current($this->user, $this->at('2026-11-09 20:00'))->task, 'Later is not suggested as work');
    }

    public function test_shrink_goes_one_size_down_and_stops_at_quick(): void
    {
        $task = $this->task(['size' => Size::Long]);
        $decisions = new CrunchDecisions;

        $this->assertSame(Size::Short, $decisions->shrink($this->user, $task->id)->size);
        $this->assertSame(Size::Quick, $decisions->shrink($this->user, $task->id)->size);
        $this->assertSame(Size::Quick, $decisions->shrink($this->user, $task->id)->size);
    }

    public function test_an_unsized_task_shrinks_from_short(): void
    {
        $task = $this->task(['size' => null]);

        $this->assertSame(Size::Quick, (new CrunchDecisions)->shrink($this->user, $task->id)->size);
    }

    public function test_crunch_decisions_refuse_someone_elses_task(): void
    {
        $foreign = Task::factory()->create(['list' => 'tasks']);

        $this->expectException(ModelNotFoundException::class);
        (new CrunchDecisions)->drop($this->user, $foreign->id);
    }
}
