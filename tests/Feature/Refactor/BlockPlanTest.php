<?php

namespace Tests\Feature\Refactor;

use App\Livewire\Shell\Board;
use App\Models\EventCategory;
use App\Models\Task;
use App\Models\User;
use App\Planning\Size;
use App\Services\Planning\Suggestions;
use App\Services\Planning\WeekPlanEditor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The list under the To-do session card (Niels, 2026-11-27): everything the engine plans for the
 * rest of today's work block, session after session. Block 19:30–21:30 holds the slots 19:30,
 * 20:00, 20:30 and 21:00; the clock stands at 20:00 (the 19:30 slot is over).
 */
class BlockPlanTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-09 20:00', 'Europe/Zurich'));
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
        $deep = EventCategory::factory()->for($this->user)->pomodoro()->create(['name' => 'Arbeiten']);
        (new WeekPlanEditor)->addRepeating($this->user, [1, 2, 3, 4, 5], '19:30', '21:30', $deep->id);
    }

    private function task(string $title, Size $size = Size::Short, string $list = 'tasks', array $attrs = []): Task
    {
        return Task::forceCreate(array_merge(['user_id' => $this->user->id, 'title' => $title, 'list' => $list, 'size' => $size], $attrs));
    }

    private function plan(): ?\App\Services\Planning\BlockPlan
    {
        return app(Suggestions::class)->todoSession($this->user)->block;
    }

    /** @return list<string> "20:00 A, B" per session */
    private function lines(): array
    {
        return array_map(
            fn (array $s) => $s['start']->format('H:i').' '.implode(', ', array_map(fn (array $r) => $r['task']->title, $s['rows'])),
            $this->plan()->sessions,
        );
    }

    public function test_the_engines_ranking_is_filled_into_the_slots_still_ahead(): void
    {
        $this->task('Eins', attrs: ['sort_order' => 0]);
        $this->task('Zwei', attrs: ['sort_order' => 1]);
        $this->task('Drei', attrs: ['sort_order' => 2]);
        $this->task('Vier', attrs: ['sort_order' => 3]);

        $plan = $this->plan();

        $this->assertSame('Arbeiten', $plan->name);
        $this->assertSame(['20:00 Eins', '20:30 Zwei', '21:00 Drei'], $this->lines()); // the 19:30 slot is over
        $this->assertSame(1, $plan->waiting);
        $this->assertTrue($plan->running);
        $this->assertSame('19:30', $plan->from->format('H:i'));
        $this->assertSame('21:30', $plan->to->format('H:i'));
    }

    public function test_small_tasks_share_a_session_and_a_long_one_takes_the_slots_after_it(): void
    {
        $this->task('Kurz A', Size::Quick, attrs: ['sort_order' => 0]);
        $this->task('Kurz B', Size::Quick, attrs: ['sort_order' => 1]);
        $this->task('Gross', Size::Long, attrs: ['sort_order' => 2]); // 75 minutes = three slots
        $this->task('Danach', attrs: ['sort_order' => 3]);

        $this->assertSame(['20:00 Kurz A, Kurz B', '20:30 Gross'], $this->lines());
        $this->assertSame(1, $this->plan()->waiting); // Danach: the long task used the slots up
    }

    public function test_deadlines_come_first_like_in_the_pick(): void
    {
        $this->task('Ohne', attrs: ['sort_order' => 0]);
        $this->task('Fällig', attrs: ['sort_order' => 1, 'due_date' => '2026-11-10']);

        $this->assertSame('20:00 Fällig', $this->lines()[0]);
        $this->assertSame('Fällig', app(Suggestions::class)->current($this->user)->task->title);
    }

    public function test_the_steps_of_a_resting_or_hidden_card_are_not_planned(): void
    {
        $this->task('Lose');
        $project = \App\Models\Project::forceCreate(['user_id' => $this->user->id, 'name' => 'P', 'sort_order' => 1]);
        foreach (['S1', 'S2', 'S3'] as $i => $title) {
            $this->task($title, attrs: ['project_id' => $project->id, 'sort_order' => $i]);
        }

        $titles = $this->plan()->tasks()->pluck('title')->all();

        $this->assertContains('S1', $titles);
        $this->assertContains('S2', $titles);
        $this->assertNotContains('S3', $titles); // the board shows two steps of a project, no more
    }

    public function test_no_plan_without_a_work_block_left_today(): void
    {
        $this->task('Eins');
        $this->travelTo(CarbonImmutable::parse('2026-11-09 22:00', 'Europe/Zurich'));
        $this->assertNull($this->plan());

        $this->travelTo(CarbonImmutable::parse('2026-11-14 12:00', 'Europe/Zurich')); // Saturday, no block
        $this->assertNull($this->plan());
    }

    public function test_the_next_block_of_the_day_is_planned_while_none_is_running(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-09 18:00', 'Europe/Zurich'));
        $this->task('Eins');

        $plan = $this->plan();

        $this->assertFalse($plan->running);
        $this->assertSame('19:30 Eins', $this->lines()[0]);
    }

    public function test_an_empty_plan_has_nothing_to_show(): void
    {
        $this->assertTrue($this->plan()->isEmpty());
    }

    public function test_the_card_lists_the_plan_in_both_layouts_and_stays_quiet_without_one(): void
    {
        $this->task('Erledigen', Size::Quick, 'todos');
        $this->task('Später', attrs: ['sort_order' => 5]);

        $html = Livewire::actingAs($this->user)->test(Board::class)->html();

        $this->assertSame(2, substr_count($html, 'data-block-plan-toggle'));
        $this->assertStringContainsString('Heute im Block „Arbeiten“', $html);
        $this->assertStringContainsString('19:30–21:30', $html);
        $this->assertStringContainsString('Später', $html);

        Task::query()->update(['is_completed' => true]);
        $this->assertStringNotContainsString('data-block-plan', Livewire::actingAs($this->user)->test(Board::class)->html());
    }
}
