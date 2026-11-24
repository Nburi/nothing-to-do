<?php

namespace Tests\Feature\Refactor;

use App\Livewire\Shell\Crunch;
use App\Models\EventCategory;
use App\Models\EventTemplate;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Models\User;
use App\Planning\Crunch\Level;
use App\Planning\Size;
use App\Services\FocusSession;
use App\Services\Planning\Capacity;
use App\Services\Planning\CrunchDecisions;
use App\Services\Planning\CrunchOverview;
use App\Services\Planning\EveningReset;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A32–A36 (R5; risks F5, F7; refactor step 4.2): the crunch overview, the decisions move /
 * more time / undo, the crunch screen and the warning on the board.
 *
 * Deep work (pomodoro) Mon–Fri 19:30–21:30 = 4 sessions an evening. Monday 9 November 2026, 08:00.
 * "Bericht" (hard Wednesday, 350 min) and "Lesen" (soft Tuesday, Long = 75 min) need 425 min,
 * Monday to Wednesday hold 300: Lesen gives way (75 min late) and Bericht is 50 min short.
 */
class CrunchScreenTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private EventCategory $deep;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');
        $this->travelTo(CarbonImmutable::parse('2026-11-09 08:00', 'Europe/Zurich'));
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
        $this->deep = EventCategory::factory()->for($this->user)->pomodoro()->create(['name' => 'Deep work']);
        EventTemplate::factory()->for($this->user)->create([
            'category_id' => $this->deep->id, 'is_recurring' => true, 'recurrence' => '1,2,3,4,5',
            'default_start' => '19:30', 'duration' => 120,
        ]);
    }

    private function task(string $title, array $attributes): Task
    {
        return Task::factory()->for($this->user)->create(['title' => $title, 'list' => 'tasks', ...$attributes]);
    }

    /** @return array{0: Task, 1: Task} */
    private function crunch(): array
    {
        return [
            $this->task('Bericht', ['size' => Size::Block, 'duration_minutes' => 350, 'hard_deadline' => '2026-11-11']),
            $this->task('Lesen', ['size' => Size::Long, 'soft_deadline' => '2026-11-10']),
        ];
    }

    private function level(): ?Level
    {
        return (new Capacity)->forecast($this->user)->level();
    }

    // ---- A32: the overview -------------------------------------------------------------------

    public function test_the_overview_lists_soft_work_first_then_hard_within_the_window(): void
    {
        [$bericht, $lesen] = $this->crunch();
        $this->task('Später fällig', ['size' => Size::Short, 'hard_deadline' => '2026-11-30']);
        $this->task('Ohne Frist', ['size' => Size::Short]);

        $view = (new CrunchOverview)->for($this->user);

        $this->assertSame(Level::Hard, $view->level);
        $this->assertSame('2026-11-11', $view->windowEnd);
        $this->assertSame(125, $view->missingMinutes);
        $this->assertSame(5, $view->missingSessions);
        $this->assertSame([$lesen->id, $bericht->id], array_map(fn ($i) => $i->task->id, $view->items), 'soft first; later deadlines and undated work are not in the window');
        $this->assertTrue($view->items[0]->suggested);
        $this->assertSame('2026-11-17', $view->items[0]->softMoveTo);
        $this->assertSame(Size::Long, $view->items[1]->smaller);
        $this->assertSame(['2026-11-09', '2026-11-10', '2026-11-11'], array_column($view->days, 'date'));
        $this->assertSame([4, 4, 4], array_column($view->days, 'sessions'));
    }

    public function test_soft_work_the_engine_kept_planned_is_still_suggested_first(): void
    {
        // Found in the browser check: with two hard tasks the engine keeps soft work on Monday
        // (moving it would not save every hard task), so it is not "slipped". Letting it go still
        // frees sessions for the hard work, so the screen suggests it first anyway (R5).
        $this->task('Vortrag', ['size' => Size::Block, 'duration_minutes' => 400, 'hard_deadline' => '2026-11-11']);
        $this->task('Mathe', ['size' => Size::Short, 'hard_deadline' => '2026-11-11']);
        $lesen = $this->task('Lesen', ['size' => Size::Long, 'soft_deadline' => '2026-11-10']);

        $view = (new CrunchOverview)->for($this->user);

        $this->assertSame($lesen->id, $view->items[0]->task->id);
        $this->assertTrue($view->items[0]->suggested);
        $this->assertFalse($view->items[1]->suggested, 'hard deadlines are never the suggestion');
    }

    public function test_more_time_is_suggested_before_the_deadline_where_the_day_has_room(): void
    {
        $this->crunch();

        $more = (new CrunchOverview)->for($this->user)->moreTime;

        // 19:00 would overlap the 19:30 block, so it goes right before it, as long as the usual block.
        $this->assertSame(['date' => '2026-11-09', 'label' => 'heute', 'start' => '17:30', 'end' => '19:30', 'categoryId' => $this->deep->id, 'sessions' => 4], $more);
    }

    public function test_without_a_crunch_the_overview_is_empty_and_says_how_much_room_is_left(): void
    {
        $this->task('Kurz', ['size' => Size::Short, 'hard_deadline' => '2026-11-11']);

        $view = (new CrunchOverview)->for($this->user);

        $this->assertFalse($view->hasCrunch());
        $this->assertSame([], $view->items);
        $this->assertSame(275, $view->slackMinutes);
    }

    // ---- A33: move ---------------------------------------------------------------------------

    public function test_a_soft_deadline_moves_a_week_in_one_tap_and_its_slip_count_starts_over(): void
    {
        [, $lesen] = $this->crunch();
        $lesen->update(['soft_slips' => 1]);

        $moved = (new CrunchDecisions)->moveSoft($this->user, $lesen->id);

        $this->assertSame('2026-11-17', $moved->fresh()->soft_deadline->toDateString());
        $this->assertSame('2026-11-17', $moved->fresh()->due_date->toDateString(), 'old column mirrored');
        $this->assertSame(0, $moved->fresh()->soft_slips);
        $this->assertSame(Level::Hard, $this->level(), 'Bericht is still 50 min short');
    }

    public function test_a_soft_date_moved_past_the_hard_one_is_dropped_instead(): void
    {
        $both = $this->task('Beides', ['size' => Size::Short, 'soft_deadline' => '2026-11-10', 'hard_deadline' => '2026-11-13']);

        $this->assertNull((new CrunchDecisions)->moveSoft($this->user, $both->id)->fresh()->soft_deadline);
    }

    public function test_a_hard_deadline_never_moves_by_the_one_tap_rule(): void
    {
        [$bericht] = $this->crunch();

        $this->expectException(ValidationException::class);
        (new CrunchDecisions)->moveSoft($this->user, $bericht->id);
    }

    public function test_a_hard_deadline_moves_only_to_a_later_date_the_user_chose(): void
    {
        [$bericht] = $this->crunch();
        $decisions = new CrunchDecisions;

        try {
            $decisions->moveHard($this->user, $bericht->id, '2026-11-10');
            $this->fail('an earlier date was accepted');
        } catch (ValidationException $e) {
            $this->assertSame('Bitte ein Datum nach dem 11.11.2026.', $e->errors()['moveDate'][0]);
        }

        $decisions->moveHard($this->user, $bericht->id, '2026-11-16');

        $this->assertSame('2026-11-16', $bericht->fresh()->hard_deadline->toDateString());
        $this->assertSame('2026-11-16', $bericht->fresh()->deadline->toDateString(), 'old column mirrored');
    }

    public function test_a_late_hard_task_comes_with_the_first_date_it_fits(): void
    {
        [$bericht, $lesen] = $this->crunch();
        $kurz = $this->task('Kurz', ['size' => Size::Short, 'hard_deadline' => '2026-11-11']);

        $items = [];
        foreach ((new CrunchOverview)->for($this->user, withFitDates: true)->items as $item) {
            $items[$item->task->id] = $item;
        }

        // Thursday is the first day with room, as the engine plans it: soft work gives way to hard work.
        $this->assertSame('2026-11-12', $items[$bericht->id]->fitDate);
        $this->assertTrue($items[$bericht->id]->late);
        $this->assertNull($items[$kurz->id]->fitDate, 'it fits already');
        $this->assertFalse($items[$kurz->id]->late);
        $this->assertNull($items[$lesen->id]->fitDate, 'soft work slips by a week instead');

        // The board asks without them: its render does not pay for the engine runs.
        $board = array_values(array_filter((new CrunchOverview)->for($this->user)->items, fn ($i) => $i->task->id === $bericht->id));
        $this->assertNull($board[0]->fitDate);
        $this->assertFalse($board[0]->late);
    }

    // ---- A34: more time and undo ----------------------------------------------------------------

    public function test_more_time_adds_a_one_off_pomodoro_block_that_solves_the_hard_crunch(): void
    {
        $this->crunch();

        $event = (new CrunchDecisions)->addTime($this->user, '2026-11-10', '17:30', '19:30', $this->deep->id);

        $this->assertNull($event->template_id, 'this week only: a one-off, the template is untouched');
        $this->assertSame(Level::Soft, $this->level(), 'Bericht fits now; only the soft date is still tight');
    }

    public function test_more_time_needs_one_of_your_pomodoro_categories_and_a_day_in_the_next_two_weeks(): void
    {
        $plain = EventCategory::factory()->for($this->user)->create(['pomodoro_enabled' => false]);
        $foreign = EventCategory::factory()->pomodoro()->create();
        $decisions = new CrunchDecisions;

        foreach ([[$plain->id, '2026-11-10', 'addCategory'], [$foreign->id, '2026-11-10', 'addCategory'], [$this->deep->id, '2026-11-08', 'addDate'], [$this->deep->id, '2026-11-30', 'addDate']] as [$category, $date, $field]) {
            try {
                $decisions->addTime($this->user, $date, '17:00', '18:00', $category);
                $this->fail("accepted {$field}");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($field, $e->errors());
            }
        }
        $this->assertSame(0, ScheduleEvent::count());
    }

    public function test_undo_of_more_time_removes_exactly_that_block_and_only_your_own(): void
    {
        $event = (new CrunchDecisions)->addTime($this->user, '2026-11-10', '17:30', '19:30', $this->deep->id);
        $other = ScheduleEvent::factory()->create();

        (new CrunchDecisions)->removeTime($this->user, $event->id);
        $this->assertModelMissing($event);

        $this->expectException(ModelNotFoundException::class);
        (new CrunchDecisions)->removeTime($this->user, $other->id);
    }

    public function test_restore_puts_back_only_the_remembered_fields(): void
    {
        [$bericht] = $this->crunch();
        $before = CrunchDecisions::snapshot($bericht);
        $decisions = new CrunchDecisions;
        $decisions->drop($this->user, $bericht->id);
        $decisions->shrink($this->user, $bericht->id);

        $decisions->restore($this->user, $bericht->id, [...$before, 'title' => 'gehackt', 'user_id' => 999]);

        $fresh = $bericht->fresh();
        $this->assertSame('tasks', $fresh->list);
        $this->assertSame(Size::Block, $fresh->size);
        $this->assertSame('Bericht', $fresh->title);
        $this->assertSame($this->user->id, $fresh->user_id);

        $this->expectException(ValidationException::class);
        $decisions->restore($this->user, $bericht->id, ['list' => 'trash']);
    }

    // ---- A35: the crunch screen ----------------------------------------------------------------

    public function test_the_push_link_opens_the_screen_with_soft_work_first(): void
    {
        $this->crunch();

        Livewire::withQueryParams(['crunch' => 1])->actingAs($this->user)->test(Crunch::class)
            ->assertSet('open', true)
            ->assertSee('Bis Mittwoch')
            ->assertSee('Fehlt: 5 Sessions')
            ->assertSeeInOrder(['Zuerst lockern', 'Lesen', 'Auf 17.11.', 'Harte Fristen', 'Bericht', 'Auf Donnerstag …'])
            ->assertSee('Mehr Zeit: heute 17:30–19:30 (+4 Sessions)');
    }

    public function test_a_decision_on_the_screen_is_undoable_and_tells_the_board(): void
    {
        [, $lesen] = $this->crunch();

        $screen = Livewire::actingAs($this->user)->test(Crunch::class)->call('openScreen')
            ->call('moveSoft', $lesen->id)
            ->assertDispatched('crunch-changed')
            ->assertSee('«Lesen»: Frist jetzt 17.11.')->assertDontSee('17.11..');
        $this->assertSame('2026-11-17', $lesen->fresh()->soft_deadline->toDateString());

        $screen->call('drop', $lesen->id)->assertSee('«Lesen» liegt in Später.');
        $this->assertSame('later', $lesen->fresh()->list);

        $screen->call('undoAt', 1)->assertSee('Rückgängig gemacht.');
        $this->assertSame('tasks', $lesen->fresh()->list);
        $screen->call('undoAt', 0);
        $this->assertSame('2026-11-10', $lesen->fresh()->soft_deadline->toDateString());
    }

    public function test_moving_a_hard_deadline_asks_for_a_date_and_a_clear_yes(): void
    {
        [$bericht] = $this->crunch();

        $screen = Livewire::actingAs($this->user)->test(Crunch::class)->call('openScreen')
            ->call('startMove', $bericht->id)
            ->assertSet('moveDate', '2026-11-12') // prefilled with the first date it fits
            ->call('moveHard')
            ->assertHasErrors('moveSure');
        $this->assertSame('2026-11-11', $bericht->fresh()->hard_deadline->toDateString(), 'never silently');

        $screen->set('moveSure', true)->set('moveDate', '2026-11-01')->call('moveHard')->assertHasErrors('moveDate');
        $screen->set('moveDate', '2026-11-18')->call('moveHard')->assertHasNoErrors()->assertSet('moving', null);
        $this->assertSame('2026-11-18', $bericht->fresh()->hard_deadline->toDateString(), 'the user may pick another date');
    }

    public function test_the_screen_proposes_the_fit_date_and_moves_only_after_a_clear_yes(): void
    {
        [$bericht] = $this->crunch();

        $screen = Livewire::actingAs($this->user)->test(Crunch::class)->call('openScreen')
            ->assertSee('Auf Donnerstag …')
            ->assertDontSee('Verschieben …')
            ->call('startMove', $bericht->id)
            ->assertSet('moveDate', '2026-11-12')
            ->assertSee('Ab Donnerstag passt «Bericht» in deinen Plan.');

        $screen->call('moveHard')->assertHasErrors('moveSure');
        $this->assertSame('2026-11-11', $bericht->fresh()->hard_deadline->toDateString(), 'no tick, no move');

        $screen->set('moveSure', true)->call('moveHard')
            ->assertHasNoErrors()->assertSet('moving', null)
            ->assertDispatched('crunch-changed')
            ->assertSee('«Bericht»: Frist jetzt Donnerstag');
        $this->assertSame('2026-11-12', $bericht->fresh()->hard_deadline->toDateString());
        $this->assertSame(Level::Soft, $this->level(), 'the hard crunch is gone; only the soft date is still tight');

        $screen->call('undoAt', 0);
        $this->assertSame('2026-11-11', $bericht->fresh()->hard_deadline->toDateString(), 'and it can be undone');
    }

    public function test_cancelling_the_fit_date_changes_nothing(): void
    {
        [$bericht] = $this->crunch();

        Livewire::actingAs($this->user)->test(Crunch::class)->call('openScreen')
            ->call('startMove', $bericht->id)->set('moveSure', true)
            ->call('cancelMove')
            ->assertSet('moving', null)->assertSet('moveDate', '')->assertSet('moveSure', false)
            ->assertDontSee('Ab Donnerstag passt')
            ->assertSee('Auf Donnerstag …'); // the proposal is still offered

        $this->assertSame('2026-11-11', $bericht->fresh()->hard_deadline->toDateString());
        $this->assertSame(Level::Hard, $this->level());
    }

    public function test_without_a_fit_date_the_screen_says_so_and_starts_a_week_later(): void
    {
        $riesig = $this->task('Riesig', ['size' => Size::Block, 'duration_minutes' => 5000, 'hard_deadline' => '2026-11-11']);

        Livewire::actingAs($this->user)->test(Crunch::class)->call('openScreen')
            ->assertSee('Verschieben …')
            ->call('startMove', $riesig->id)
            ->assertSet('moveDate', '2026-11-18')
            ->assertSee('«Riesig» passt in den nächsten Wochen auf keinen Tag. Mehr Zeit oder eine kleinere Aufgabe helfen.');
    }

    public function test_a_hard_task_that_fits_has_no_fit_date_and_no_hint(): void
    {
        $this->crunch();
        $kurz = $this->task('Kurz', ['size' => Size::Short, 'hard_deadline' => '2026-11-11']);

        Livewire::actingAs($this->user)->test(Crunch::class)->call('openScreen')
            ->call('startMove', $kurz->id)
            ->assertSet('moveDate', '2026-11-18')
            ->assertDontSee('passt «Kurz»')
            ->assertDontSee('«Kurz» passt');
    }

    public function test_more_time_on_the_screen_is_prefilled_saved_and_undoable(): void
    {
        $this->crunch();

        $screen = Livewire::actingAs($this->user)->test(Crunch::class)->call('openScreen')
            ->call('startAdd')
            ->assertSet('addDate', '2026-11-09')->assertSet('addStart', '17:30')->assertSet('addEnd', '19:30')->assertSet('addCategory', $this->deep->id)
            ->set('addEnd', '17:00')->call('addTime')->assertHasErrors('addEnd');
        $this->assertSame(0, ScheduleEvent::count());

        $screen->set('addEnd', '19:30')->call('addTime')->assertHasNoErrors()->assertSee('heute 17:30–19:30 dazu.');
        $this->assertSame(1, ScheduleEvent::count());

        $screen->call('undoAt', 0);
        $this->assertSame(0, ScheduleEvent::count());
    }

    public function test_a_task_that_is_gone_reloads_the_screen_calmly(): void
    {
        [$bericht] = $this->crunch();
        $screen = Livewire::actingAs($this->user)->test(Crunch::class)->call('openScreen');
        $bericht->delete();

        $screen->call('shrink', $bericht->id)->assertSee('Diese Aufgabe gibt es nicht mehr.');
    }

    public function test_without_a_crunch_the_screen_says_it_fits(): void
    {
        Livewire::actingAs($this->user)->test(Crunch::class)->call('openScreen')
            ->assertSee('Passt. Keine Frist wird knapp.')
            ->assertDontSee('Mehr Zeit');
    }

    // ---- A36: the warning on the board ---------------------------------------------------------

    private function board(): string
    {
        app(EveningReset::class)->close($this->user);
        app(EveningReset::class)->answerBattery($this->user, null);

        return $this->actingAs($this->user)->get('/board')->assertOk()->getContent();
    }

    public function test_a_soft_only_crunch_is_a_quiet_line_on_both_layouts(): void
    {
        $this->task('Lesen', ['size' => Size::Block, 'duration_minutes' => 350, 'soft_deadline' => '2026-11-11']);

        $html = $this->board();

        $this->assertSame(2, substr_count($html, 'data-crunch-line="soft"'));
        $this->assertStringContainsString('Weiche Fristen passen nicht ganz', $html);
        preg_match_all('/<p [^>]*data-crunch-line="soft"[^>]*>/', $html, $tags);
        $this->assertCount(2, $tags[0]);
        foreach ($tags[0] as $tag) {
            $this->assertStringContainsString('text-ink-faint', $tag);
            $this->assertStringNotContainsString('text-warn', $tag, 'never red, not even warm (F5)');
            $this->assertStringNotContainsString('text-bad', $tag);
        }
        $this->assertStringNotContainsString('data-action="crunch" data-layout="werkbank" data-mode="session"', $html);
    }

    public function test_no_crunch_means_no_line(): void
    {
        $this->task('Kurz', ['size' => Size::Short, 'hard_deadline' => '2026-11-11']);

        $this->assertStringNotContainsString('data-crunch-line', $this->board());
    }

    public function test_during_a_session_a_hard_crunch_is_a_quiet_entry_in_the_rail_and_the_drawer(): void
    {
        [$bericht] = $this->crunch();
        $this->board();
        app(FocusSession::class)->start($this->user, $bericht->id);

        $html = $this->actingAs($this->user)->get('/board')->assertOk()->assertSee('data-shell-mode="session"', false)->getContent();

        $this->assertStringContainsString('data-action="crunch" data-layout="werkbank" data-mode="session"', $html);
        $this->assertStringContainsString('data-action="crunch" data-layout="stapel" data-mode="session"', $html);
    }
}
