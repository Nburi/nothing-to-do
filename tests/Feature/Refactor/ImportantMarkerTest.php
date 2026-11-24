<?php

namespace Tests\Feature\Refactor;

use App\Livewire\Shell\Board;
use App\Models\EventCategory;
use App\Models\Task;
use App\Models\User;
use App\Planning\Size;
use App\Services\FocusSession;
use App\Services\Planning\EveningReset;
use App\Services\Planning\WeekPlanEditor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A63 and A64 (R5, R7, R9 section 3 item 7, step 4.6): marking and unmarking from the board,
 * the highlight at the top of every list, and visibility where the app offers no work
 * suggestion (outside pomodoro blocks) and while the board is folded (rail and drawer).
 * Monday 9 November 2026, 20:00 in Zurich.
 */
class ImportantMarkerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');
        $this->travelTo(CarbonImmutable::parse('2026-11-09 20:00', 'Europe/Zurich'));
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);

        // Tonight's reset is behind us, so the board is not covered.
        app(EveningReset::class)->close($this->user);
        app(EveningReset::class)->answerBattery($this->user, null);
    }

    private function block(string $from = '19:30', string $to = '21:30'): void
    {
        $deep = EventCategory::factory()->for($this->user)->pomodoro()->create(['name' => 'Deep work']);
        (new WeekPlanEditor)->addRepeating($this->user, [1, 2, 3, 4, 5], $from, $to, $deep->id);
    }

    private function task(string $title, string $list = 'todos', array $extra = [], ?User $owner = null): Task
    {
        return Task::forceCreate(array_merge([
            'user_id' => ($owner ?? $this->user)->id, 'title' => $title, 'list' => $list, 'size' => Size::Short,
        ], $extra));
    }

    private function board()
    {
        return Livewire::actingAs($this->user)->test(Board::class);
    }

    // ── A63: marking, unmarking, the top of the list ─────────────────────

    public function test_a_task_is_marked_and_unmarked_with_one_call(): void
    {
        $task = $this->task('Zahnarzt anrufen');

        $this->board()->call('setImportant', $task->id, true);
        $this->assertTrue($task->fresh()->is_important);

        $this->board()->call('setImportant', $task->id, false);
        $this->assertFalse($task->fresh()->is_important);
    }

    public function test_a_double_tap_does_not_flip_the_marker_back(): void
    {
        $task = $this->task('Zahnarzt anrufen');
        $board = $this->board();

        $board->call('setImportant', $task->id, true)->call('setImportant', $task->id, true);

        $this->assertTrue($task->fresh()->is_important);
    }

    public function test_marking_works_while_a_session_runs(): void
    {
        $this->block();
        $running = $this->task('Läuft gerade');
        $other = $this->task('Später dran');
        app(FocusSession::class)->start($this->user, $running->id);

        $this->board()->assertSee('data-shell-mode="session"', false)->call('setImportant', $other->id, true);

        $this->assertTrue($other->fresh()->is_important);
    }

    public function test_only_your_own_open_tasks_can_be_marked(): void
    {
        $stranger = User::factory()->create();
        $foreign = $this->task('Fremde Aufgabe', 'todos', [], $stranger);
        $done = $this->task('Schon erledigt', 'todos', ['is_completed' => true, 'completed_at' => now()]);

        $this->board()->call('setImportant', $foreign->id, true)
            ->assertSee('Diese Aufgabe gibt es nicht mehr. Die Liste ist jetzt aktuell.');
        $this->board()->call('setImportant', $done->id, true)
            ->assertSee('Diese Aufgabe gibt es nicht mehr.');
        $this->board()->call('setImportant', 999999, true)
            ->assertSee('Diese Aufgabe gibt es nicht mehr.');

        $this->assertFalse($foreign->fresh()->is_important);
        $this->assertFalse($done->fresh()->is_important);
    }

    public function test_marked_tasks_sit_at_the_top_of_their_list_and_the_pile_shows_them_first(): void
    {
        $this->task('Erste', 'todos', ['sort_order' => 0, 'size' => Size::Quick]);
        $this->task('Zweite', 'todos', ['sort_order' => 1, 'size' => Size::Quick]);
        $marked = $this->task('Dritte, markiert', 'todos', ['sort_order' => 2, 'is_important' => true, 'size' => Size::Quick]);
        $this->task('Eine Task', 'tasks', ['sort_order' => 0]);

        $board = $this->board();
        // The To-do session (2026-11-24, 25 min) is the top of the To-dos list: two Quick To-dos fit, the third waits.
        $todos = $board->instance()->todoSession->tasks;

        $this->assertSame($marked->id, $todos->first()->id, 'the top card of the pile and the first row of the column');
        $this->assertSame($marked->id, $board->instance()->topOf('todos')->id);
        $this->assertSame(['Dritte, markiert', 'Erste'], $todos->pluck('title')->all(), 'the rest keeps the board order');
        $this->assertSame(['Zweite'], $board->instance()->lists['todos']->pluck('title')->all(), 'what did not fit waits below the card');
    }

    public function test_a_marked_row_says_so_in_words_and_the_button_reports_its_state(): void
    {
        $this->task('Zahnarzt anrufen', 'todos', ['is_important' => true]);
        $this->task('Brot kaufen');

        $this->board()
            ->assertSeeHtml('data-important-tag')
            ->assertSeeHtml('aria-pressed="true"')
            ->assertSeeHtml('aria-pressed="false"')
            ->assertSee('Wichtig');
    }

    public function test_the_marker_survives_inbox_sorting_and_completion_is_untouched(): void
    {
        $task = $this->task('Frisch erfasst', 'inbox', ['is_important' => true, 'size' => null]);

        $this->assertTrue($task->fresh()->is_important);
        $this->assertSame(['inbox' => 1, 'todos' => 0, 'tasks' => 0, 'later' => 0], $this->board()->instance()->counts);
    }

    // ── A64: visible where there is no work suggestion, and while folded ─

    public function test_outside_a_block_the_marked_tasks_stay_visible_as_a_reminder_without_a_start_here_card(): void
    {
        $this->block('21:00', '22:00'); // 20:00 is outside the block
        $this->task('Zahnarzt anrufen', 'todos', ['is_important' => true]);
        $this->task('Brot kaufen');

        $this->board()
            ->assertSee('Gerade kein Arbeitsblock')
            ->assertSeeHtml('data-important-reminder')
            ->assertDontSee('Hier starten');
    }

    public function test_the_reminder_can_start_a_marked_task_by_hand(): void
    {
        $this->block('21:00', '22:00');
        $task = $this->task('Zahnarzt anrufen', 'todos', ['is_important' => true]);

        $this->board()->call('start', $task->id)->assertSee('data-shell-mode="session"', false);
    }

    public function test_inside_a_block_the_suggestion_card_is_enough_and_there_is_no_reminder(): void
    {
        $this->block();
        $this->task('Zahnarzt anrufen', 'todos', ['is_important' => true]);

        $this->board()
            ->assertSee('Hier starten')
            ->assertSee('Von dir als wichtig markiert. Besser nicht vergessen.')
            ->assertDontSeeHtml('data-important-reminder');
    }

    public function test_without_marked_tasks_there_is_no_reminder(): void
    {
        $this->block('21:00', '22:00');
        $this->task('Brot kaufen');

        $this->board()->assertSee('Gerade kein Arbeitsblock')->assertDontSeeHtml('data-important-reminder');
    }

    public function test_the_reminder_leaves_parked_ideas_out_and_caps_a_long_list(): void
    {
        $this->block('21:00', '22:00');
        $this->task('Geparkte Idee', 'later', ['is_important' => true]);
        foreach (range(1, 6) as $i) {
            $this->task("Markiert {$i}", 'todos', ['is_important' => true, 'sort_order' => $i]);
        }

        $board = $this->board();

        $this->assertCount(6, $board->instance()->important, 'the parked idea is not part of the reminder');
        $board->assertSeeHtml('data-important-reminder')->assertSee('+ 3 weitere');
    }

    public function test_while_the_board_is_folded_the_rail_and_the_drawer_show_how_many_are_marked(): void
    {
        $this->block();
        $running = $this->task('Läuft gerade');
        $this->task('Zahnarzt anrufen', 'todos', ['is_important' => true]);
        $this->task('Steuererklärung', 'tasks', ['is_important' => true]);
        app(FocusSession::class)->start($this->user, $running->id);

        $this->board()
            ->assertSeeHtml('data-important-rail')
            ->assertSeeHtml('data-important-drawer')
            ->assertSeeHtml('data-peek-tab="important"')
            ->assertSeeHtml('data-peek-panel="important"');
    }

    public function test_while_folded_nothing_marked_means_no_rail_or_drawer_entry(): void
    {
        $this->block();
        $running = $this->task('Läuft gerade');
        app(FocusSession::class)->start($this->user, $running->id);

        $this->board()->assertDontSeeHtml('data-important-rail')->assertDontSeeHtml('data-important-drawer');
    }
}
