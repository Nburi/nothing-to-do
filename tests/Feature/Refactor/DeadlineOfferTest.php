<?php

namespace Tests\Feature\Refactor;

use App\Livewire\Shell\Board;
use App\Models\EventCategory;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Planning\Size;
use App\Services\FocusSession;
use App\Services\Planning\DeadlineOffer;
use App\Services\Planning\EveningReset;
use App\Services\Planning\PlanningContext;
use App\Services\Planning\WeekPlanEditor;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Step d-bis-wann (A144 rules, A145 screen): marking an undated task „Wichtig" offers one quiet,
 * optional „Bis wann?" (today, tomorrow, this week) that writes a soft deadline. Skippable, undoable,
 * and asked once per task, ever. Monday 9 November 2026, 20:00 in Zurich.
 */
class DeadlineOfferTest extends TestCase
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

    private function task(string $title, string $list = 'todos', array $extra = []): Task
    {
        return Task::forceCreate(array_merge(['user_id' => $this->user->id, 'title' => $title, 'list' => $list, 'size' => Size::Short], $extra));
    }

    private function board()
    {
        return Livewire::actingAs($this->user)->test(Board::class);
    }

    private function at(string $dateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, 'Europe/Zurich');
    }

    // ── A144: when it is asked and what the answers are ──────────────────

    public function test_marking_an_undated_task_offers_the_question_and_stamps_it_as_asked(): void
    {
        $task = $this->task('Zahnarzt anrufen');

        $this->board()->call('setImportant', $task->id, true)
            ->assertSet('deadlineOffer.task', $task->id)
            ->assertSet('deadlineOffer.set', null)
            ->assertSee('data-deadline-offer="ask"', false)
            ->assertSee('Bis wann?')
            ->assertSee('Zahnarzt anrufen');

        $this->assertNotNull($task->fresh()->deadline_asked_at);
        $this->assertNull($task->fresh()->soft_deadline, 'nothing is written until the user picks');
    }

    public function test_the_answers_are_today_tomorrow_and_the_sunday_of_this_week(): void
    {
        $choices = app(DeadlineOffer::class)->choices($this->user, $this->at('2026-11-09 20:00')); // Monday

        $this->assertSame(['today' => '2026-11-09', 'tomorrow' => '2026-11-10', 'week' => '2026-11-15'], $choices);
    }

    public function test_this_week_is_left_out_when_it_would_repeat_tomorrow_or_today(): void
    {
        $offer = app(DeadlineOffer::class);

        $this->assertSame(['today' => '2026-11-14', 'tomorrow' => '2026-11-15'], $offer->choices($this->user, $this->at('2026-11-14 12:00')), 'Saturday: Sunday is tomorrow');
        $this->assertSame(['today' => '2026-11-15', 'tomorrow' => '2026-11-16'], $offer->choices($this->user, $this->at('2026-11-15 12:00')), 'Sunday: the week ends today');
        $this->assertArrayHasKey('week', $offer->choices($this->user, $this->at('2026-11-13 12:00')), 'Friday: Sunday is two days away');
    }

    public function test_the_day_follows_the_users_clock_not_the_servers(): void
    {
        // 23:30 in Zurich is 22:30 UTC: the same instant is still Monday for this user, so "today" must not jump a day.
        $this->assertSame('2026-11-09', app(DeadlineOffer::class)->choices($this->user, $this->at('2026-11-09 23:30'))['today']);
    }

    public function test_a_task_with_any_date_is_not_asked(): void
    {
        $hard = $this->task('Hart', extra: ['hard_deadline' => '2026-11-20']);
        $soft = $this->task('Weich', extra: ['soft_deadline' => '2026-11-20']);

        $project = new Project(['name' => 'Projekt']);
        $project->user_id = $this->user->id;
        $project->save();
        $stage = $project->stages()->create(['name' => 'Etappe', 'target_date' => '2026-11-30', 'sort_order' => 0]);
        $step = $this->task('Schritt', 'tasks', ['project_id' => $project->id, 'stage_id' => $stage->id]);

        $board = $this->board();
        foreach ([$hard, $soft, $step] as $task) {
            $board->call('setImportant', $task->id, true)->assertSet('deadlineOffer', null)->assertDontSee('data-deadline-offer', false);
            $this->assertNull($task->fresh()->deadline_asked_at, $task->title.' is dated, so it is not even stamped');
        }
    }

    public function test_a_task_in_later_is_not_asked(): void
    {
        $task = $this->task('Irgendwann', 'later');

        $this->board()->call('setImportant', $task->id, true)->assertSet('deadlineOffer', null);
        $this->assertNull($task->fresh()->deadline_asked_at);
    }

    public function test_the_question_is_asked_once_per_task_however_it_ended(): void
    {
        $task = $this->task('Zahnarzt anrufen');

        $board = $this->board();
        $board->call('setImportant', $task->id, true)->assertSet('deadlineOffer.task', $task->id);
        $board->call('dismissDeadline')->assertSet('deadlineOffer', null);
        $board->call('setImportant', $task->id, false);
        $board->call('setImportant', $task->id, true)->assertSet('deadlineOffer', null)->assertDontSee('data-deadline-offer', false);

        // A second device or a fresh page does not ask again either.
        $this->board()->call('setImportant', $task->id, false)->call('setImportant', $task->id, true)->assertSet('deadlineOffer', null);
    }

    public function test_a_double_tap_shows_the_question_once(): void
    {
        $task = $this->task('Zahnarzt anrufen');

        $board = $this->board()->call('setImportant', $task->id, true)->call('dismissDeadline')->call('setImportant', $task->id, true);

        $board->assertSet('deadlineOffer', null);
    }

    public function test_choosing_writes_a_soft_deadline_and_never_a_hard_one(): void
    {
        $task = $this->task('Zahnarzt anrufen');

        $this->board()->call('setImportant', $task->id, true)->call('chooseDeadline', 'tomorrow')
            ->assertSet('deadlineOffer.set', '2026-11-10')
            ->assertSet('deadlineOffer.label', 'morgen')
            ->assertSee('data-deadline-offer="set"', false)
            ->assertSee('Weiche Frist: morgen.')
            ->assertSee('Rückgängig');

        $task->refresh();
        $this->assertSame('2026-11-10', $task->soft_deadline->toDateString());
        $this->assertNull($task->hard_deadline);
        $this->assertTrue($task->is_important);
        $this->assertSame('2026-11-10', PlanningContext::for($this->user)->plannedTasks()[0]->softDeadline, 'the deadline tiers and the forecast see it');
    }

    public function test_each_of_the_three_answers_sets_its_date(): void
    {
        foreach (['today' => '2026-11-09', 'tomorrow' => '2026-11-10', 'week' => '2026-11-15'] as $choice => $date) {
            $task = $this->task('Aufgabe '.$choice);
            $this->board()->call('setImportant', $task->id, true)->call('chooseDeadline', $choice);
            $this->assertSame($date, $task->fresh()->soft_deadline->toDateString(), $choice);
        }
    }

    public function test_an_unknown_answer_changes_nothing(): void
    {
        $task = $this->task('Zahnarzt anrufen');

        $this->board()->call('setImportant', $task->id, true)->call('chooseDeadline', 'next-year')
            ->assertSet('deadlineOffer.set', null);
        $this->assertNull($task->fresh()->soft_deadline);
    }

    public function test_undo_takes_the_date_back_and_the_question_does_not_return(): void
    {
        $task = $this->task('Zahnarzt anrufen');

        $board = $this->board()->call('setImportant', $task->id, true)->call('chooseDeadline', 'today')->call('undoDeadline')
            ->assertSet('deadlineOffer', null)
            ->assertSet('notice', 'Wieder ohne Datum.');

        $this->assertNull($task->fresh()->soft_deadline);
        $board->call('setImportant', $task->id, false)->call('setImportant', $task->id, true)->assertSet('deadlineOffer', null);
    }

    public function test_undo_leaves_a_date_that_was_changed_in_the_meantime(): void
    {
        $task = $this->task('Zahnarzt anrufen');

        $board = $this->board()->call('setImportant', $task->id, true)->call('chooseDeadline', 'today');
        $task->update(['soft_deadline' => '2026-11-20']);
        $board->call('undoDeadline')->assertSet('notice', 'Das Datum wurde inzwischen geändert. Ich lasse es so.');

        $this->assertSame('2026-11-20', $task->fresh()->soft_deadline->toDateString());
    }

    public function test_a_date_the_task_got_while_the_question_was_open_is_not_overwritten(): void
    {
        $task = $this->task('Zahnarzt anrufen');

        $board = $this->board()->call('setImportant', $task->id, true);
        $task->update(['hard_deadline' => '2026-11-12']);
        $board->call('chooseDeadline', 'today')
            ->assertSet('deadlineOffer', null)
            ->assertSet('notice', 'Die Aufgabe hat inzwischen ein Datum. Ich lasse es so.');

        $this->assertNull($task->fresh()->soft_deadline);
    }

    public function test_a_task_deleted_while_the_question_was_open_closes_it_calmly(): void
    {
        $task = $this->task('Zahnarzt anrufen');

        $board = $this->board()->call('setImportant', $task->id, true);
        $task->delete();
        $board->call('chooseDeadline', 'today')->assertSet('deadlineOffer', null)->assertSet('notice', __('shell.errors.task_gone'));
    }

    public function test_unmarking_closes_its_own_question(): void
    {
        $task = $this->task('Zahnarzt anrufen');

        $this->board()->call('setImportant', $task->id, true)->call('setImportant', $task->id, false)->assertSet('deadlineOffer', null);
    }

    public function test_the_service_never_touches_somebody_elses_task(): void
    {
        $other = User::factory()->create();
        $task = Task::forceCreate(['user_id' => $other->id, 'title' => 'Fremd', 'list' => 'todos', 'size' => Size::Short]);

        $this->expectException(ModelNotFoundException::class);
        app(DeadlineOffer::class)->choose($this->user, $task->id, '2026-11-09');
    }

    public function test_the_stamp_is_additive_and_old_rows_have_none(): void
    {
        $task = $this->task('Alt');

        $this->assertNull($task->fresh()->deadline_asked_at);
    }

    // ── A145: the screen ─────────────────────────────────────────────────

    public function test_the_card_shows_three_answers_and_a_way_to_skip_in_german_and_english(): void
    {
        $task = $this->task('Zahnarzt anrufen');
        $board = $this->board()->call('setImportant', $task->id, true);

        $board->assertSeeInOrder(['Bis wann?', 'Heute', 'Morgen', 'Diese Woche', 'Ohne Datum'])
            ->assertSee('data-choice="today"', false)->assertSee('data-choice="tomorrow"', false)->assertSee('data-choice="week"', false)
            ->assertSee('data-deadline-skip', false);

        app()->setLocale('en');
        $this->board()->call('setImportant', $this->task('Call dentist')->id, true)
            ->assertSeeInOrder(['By when?', 'Today', 'Tomorrow', 'This week', 'No date']);
    }

    public function test_the_card_is_a_quiet_group_not_a_dialog_and_has_a_live_region(): void
    {
        $task = $this->task('Zahnarzt anrufen');

        $html = $this->board()->call('setImportant', $task->id, true)->html();

        $this->assertStringContainsString('data-deadline-offer="ask"', $html);
        $this->assertMatchesRegularExpression('/<div[^>]*data-deadline-offer="ask"[^>]*role="group"/s', $html);
        $this->assertDoesNotMatchRegularExpression('/data-deadline-offer[^>]*(aria-modal|x-trap)/s', $html);
        $this->assertStringContainsString('data-deadline-live', $html);
    }

    public function test_there_is_no_card_until_a_task_is_marked(): void
    {
        $this->task('Zahnarzt anrufen');

        $this->board()->assertDontSee('data-deadline-offer', false)->assertSee('data-deadline-live', false);
    }

    public function test_the_question_also_comes_while_a_session_runs(): void
    {
        $deep = EventCategory::factory()->for($this->user)->pomodoro()->create(['name' => 'Deep work']);
        (new WeekPlanEditor)->addRepeating($this->user, [1, 2, 3, 4, 5], '19:30', '21:30', $deep->id);
        $running = $this->task('Mathe');
        $other = $this->task('Zahnarzt anrufen');
        app(FocusSession::class)->start($this->user, $running->id);

        $this->board()->assertSee('data-shell-mode="session"', false)
            ->call('setImportant', $other->id, true)
            ->assertSee('data-deadline-offer="ask"', false);
    }

    public function test_a_page_load_never_shows_the_question_by_itself(): void
    {
        $task = $this->task('Schon markiert', extra: ['is_important' => true]);

        $this->actingAs($this->user)->get('/board')->assertOk()->assertDontSee('data-deadline-offer', false);
        $this->assertNull($task->fresh()->deadline_asked_at);
    }
}
