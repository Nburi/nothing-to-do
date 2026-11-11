<?php

namespace Tests\Feature\Refactor;

use App\Models\EventCategory;
use App\Models\EventTemplate;
use App\Models\SchedulePause;
use App\Models\Task;
use App\Models\User;
use App\Planning\Session\Phase;
use App\Services\FocusSession;
use App\Services\Planning\Suggestions;
use App\Services\Planning\SuggestionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A19 (R9, REQUIREMENTS § 3.7): outside pomodoro blocks the idle board offers
 * no work suggestion; it shows when the next block begins; a manual start
 * still works. Deep work (pomodoro) Mon–Fri 19:30–21:30, Monday 9 November 2026.
 */
class NoSuggestionOutsideBlocksTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Suggestions $suggestions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->suggestions = new Suggestions;
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
        $deepWork = EventCategory::factory()->for($this->user)->pomodoro()->create();
        EventTemplate::factory()->for($this->user)->create([
            'category_id' => $deepWork->id, 'is_recurring' => true, 'recurrence' => '1,2,3,4,5',
            'default_start' => '19:30', 'duration' => 120,
        ]);
        Task::factory()->for($this->user)->create(['list' => 'tasks', 'hard_deadline' => '2026-11-10']);
    }

    private function at(string $dateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, 'Europe/Zurich');
    }

    public function test_before_the_block_there_is_no_suggestion_only_the_next_block(): void
    {
        $suggestion = $this->suggestions->current($this->user, $this->at('2026-11-09 17:00'));

        $this->assertSame(SuggestionStatus::OutsideBlocks, $suggestion->status);
        $this->assertNull($suggestion->task);
        $this->assertNull($suggestion->why);
        $this->assertTrue($suggestion->nextBlockAt->equalTo($this->at('2026-11-09 19:30')));
    }

    public function test_after_the_block_the_next_one_is_tomorrow_evening(): void
    {
        $suggestion = $this->suggestions->current($this->user, $this->at('2026-11-09 22:00'));

        $this->assertSame(SuggestionStatus::OutsideBlocks, $suggestion->status);
        $this->assertTrue($suggestion->nextBlockAt->equalTo($this->at('2026-11-10 19:30')));
    }

    public function test_friday_night_points_to_monday(): void
    {
        $suggestion = $this->suggestions->current($this->user, $this->at('2026-11-13 22:00'));

        $this->assertTrue($suggestion->nextBlockAt->equalTo($this->at('2026-11-16 19:30')));
    }

    public function test_a_paused_day_has_no_block_and_no_suggestion(): void
    {
        SchedulePause::factory()->for($this->user)->create(['date' => '2026-11-09']);

        $suggestion = $this->suggestions->current($this->user, $this->at('2026-11-09 20:00'));

        $this->assertSame(SuggestionStatus::OutsideBlocks, $suggestion->status);
        $this->assertTrue($suggestion->nextBlockAt->equalTo($this->at('2026-11-10 19:30')));
    }

    public function test_a_block_of_a_category_without_pomodoro_is_not_work_time(): void
    {
        $training = EventCategory::factory()->for($this->user)->create();
        EventTemplate::factory()->for($this->user)->create([
            'category_id' => $training->id, 'is_recurring' => true, 'recurrence' => '1',
            'default_start' => '17:00', 'duration' => 90,
        ]);

        $this->assertSame(SuggestionStatus::OutsideBlocks, $this->suggestions->current($this->user, $this->at('2026-11-09 17:30'))->status);
    }

    public function test_a_user_without_week_plan_never_gets_a_suggestion_or_a_next_block(): void
    {
        $empty = User::factory()->create();
        Task::factory()->for($empty)->create(['list' => 'tasks']);

        $suggestion = $this->suggestions->current($empty, $this->at('2026-11-09 20:00'));

        $this->assertSame(SuggestionStatus::OutsideBlocks, $suggestion->status);
        $this->assertNull($suggestion->nextBlockAt);
    }

    public function test_a_manual_start_outside_blocks_still_works(): void
    {
        $task = Task::factory()->for($this->user)->create(['list' => 'todos']);

        $state = (new FocusSession)->start($this->user, $task->id, $this->at('2026-11-09 17:00'));

        $this->assertSame(Phase::Work, $state->phase);
        $this->assertSame($task->id, $state->taskId);
    }
}
