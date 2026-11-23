<?php

namespace Tests\Feature\Refactor;

use App\Models\EventCategory;
use App\Models\EventTemplate;
use App\Models\Task;
use App\Models\User;
use App\Planning\Pick\Reason;
use App\Services\Planning\Suggestions;
use App\Services\Planning\SuggestionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A62 (R5, R9, step 4.6): „Wichtig" on real rows. `tasks.is_important` reaches the pick engine, the
 * suggestion says why, the next task inside a session follows the same order, and outside pomodoro
 * blocks a marked task still never becomes a work suggestion.
 * Monday 9 November 2026, Zurich; Deep work (pomodoro) Mon–Fri 19:30–21:30.
 */
class ImportantSuggestionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Suggestions $suggestions;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');
        $this->suggestions = new Suggestions;
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);

        $deepWork = EventCategory::factory()->for($this->user)->pomodoro()->create(['name' => 'Deep work']);
        EventTemplate::factory()->for($this->user)->create([
            'category_id' => $deepWork->id, 'is_recurring' => true, 'recurrence' => '1,2,3,4,5',
            'default_start' => '19:30', 'duration' => 120,
        ]);
    }

    private function inBlock(): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-11-09 19:45', 'Europe/Zurich');
    }

    private function task(string $title, int $order, array $attributes = []): Task
    {
        return Task::factory()->for($this->user)->create(['title' => $title, 'list' => 'tasks', 'sort_order' => $order, ...$attributes]);
    }

    public function test_the_marker_reaches_the_engine_through_the_task_model(): void
    {
        $this->assertTrue($this->task('Marked', 0, ['is_important' => true])->toPlannedTask()->important);
        $this->assertFalse($this->task('Plain', 1)->toPlannedTask()->important);
    }

    public function test_a_marked_task_is_suggested_before_the_rest_of_the_list_and_says_why(): void
    {
        $this->task('Erste', 0);
        $this->task('Zweite', 1);
        $marked = $this->task('Zahnarzt anrufen', 2, ['is_important' => true]);

        $suggestion = $this->suggestions->current($this->user, $this->inBlock());

        $this->assertSame(SuggestionStatus::Pick, $suggestion->status);
        $this->assertSame($marked->id, $suggestion->task->id);
        $this->assertSame(Reason::Important, $suggestion->pick->reason);
        $this->assertSame('Von dir als wichtig markiert. Besser nicht vergessen.', $suggestion->why);
    }

    public function test_the_why_line_is_translated(): void
    {
        app()->setLocale('en');
        $this->task('Call the dentist', 0, ['is_important' => true]);

        $this->assertSame('You marked it important. Better not forget it.', $this->suggestions->current($this->user, $this->inBlock())->why);
    }

    public function test_unmarking_puts_the_task_back_into_the_board_order(): void
    {
        $first = $this->task('Erste', 0);
        $marked = $this->task('Zweite', 1, ['is_important' => true]);

        $this->assertSame($marked->id, $this->suggestions->current($this->user, $this->inBlock())->task->id);

        $marked->update(['is_important' => false]);

        $this->assertSame($first->id, $this->suggestions->current($this->user, $this->inBlock())->task->id);
    }

    public function test_a_deadline_still_comes_before_wichtig(): void
    {
        $this->task('Markiert ohne Datum', 0, ['is_important' => true]);
        $dated = $this->task('Abgabe', 1, ['hard_deadline' => '2026-11-13']);

        $suggestion = $this->suggestions->current($this->user, $this->inBlock());

        $this->assertSame($dated->id, $suggestion->task->id);
        $this->assertSame(Reason::NextDeadline, $suggestion->pick->reason);
    }

    public function test_another_moves_on_from_a_marked_task_to_the_next_one(): void
    {
        $firstMarked = $this->task('Erste markierte', 2, ['is_important' => true]);
        $secondMarked = $this->task('Zweite markierte', 3, ['is_important' => true]);
        $plain = $this->task('Normale', 0);

        $this->assertSame($firstMarked->id, $this->suggestions->current($this->user, $this->inBlock())->task->id);
        $this->assertSame($secondMarked->id, $this->suggestions->another($this->user, $this->inBlock())->task->id);
        $this->assertSame($plain->id, $this->suggestions->another($this->user, $this->inBlock())->task->id);
    }

    public function test_the_next_task_inside_a_session_is_the_marked_one(): void
    {
        $running = $this->task('Läuft gerade', 0);
        $this->task('Normale', 1);
        $marked = $this->task('Nicht vergessen', 2, ['is_important' => true]);

        $this->assertSame($marked->id, $this->suggestions->nextInSession($this->user, $running->id, $this->inBlock()));
    }

    public function test_outside_a_block_a_marked_task_is_never_turned_into_a_work_suggestion(): void
    {
        $this->task('Nicht vergessen', 0, ['is_important' => true]);

        $suggestion = $this->suggestions->current($this->user, CarbonImmutable::parse('2026-11-09 21:45', 'Europe/Zurich'));

        $this->assertSame(SuggestionStatus::OutsideBlocks, $suggestion->status);
        $this->assertFalse($suggestion->hasPick());
    }
}
