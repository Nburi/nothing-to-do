<?php

namespace Tests\Feature\Refactor;

use App\Livewire\Shell\Week;
use App\Models\EventCategory;
use App\Models\EventTemplate;
use App\Models\ScheduleEvent;
use App\Models\SchedulePause;
use App\Models\User;
use App\Planning\Block;
use App\Services\Planning\WeekPlanEditor;
use App\Services\Planning\WeekPlanReader;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A23 (step 4.1; R4, R10): the week plan editor pages. Zeitplan (/board/week) with "Nur diese Woche" /
 * "Jede Woche" as two buttons, drawn blocks over several days, pause days and undo; Wochenplan
 * (/board/week/plan) editing the template directly. "Today" is Tuesday 17 November 2026, Zurich.
 */
class WeekEditorPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private EventCategory $deep;

    private EventTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');
        $this->travelTo(CarbonImmutable::parse('2026-11-17 12:00', 'Europe/Zurich'));
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
        $this->deep = EventCategory::factory()->for($this->user)->pomodoro()->create(['name' => 'Deep work', 'color' => 'forest']);
        $this->template = (new WeekPlanEditor)->addRepeating($this->user, [1, 2, 3, 4, 5], '19:30', '21:30', $this->deep->id);
        $this->actingAs($this->user);
    }

    /** @return list<string> "HH:MM–HH:MM title" per block */
    private function day(string $date): array
    {
        return array_map(
            fn (Block $b) => $b->start->format('H:i').'–'.$b->end->format('H:i').' '.($b->title ?? ''),
            (new WeekPlanReader)->blocks($this->user, $date, $date)[$date],
        );
    }

    public function test_guests_are_sent_to_the_login(): void
    {
        auth()->logout();
        $this->get('/board/week')->assertRedirect('/login');
        $this->get('/board/week/plan')->assertRedirect('/login');
    }

    public function test_the_zeitplan_shows_this_week_with_sessions(): void
    {
        $this->get('/board/week')->assertOk()
            ->assertSee('data-week-view="zeit"', false)
            ->assertSee('Zeitplan')->assertSee('16.11. – 22.11.')
            ->assertSee('19:30–21:30 Deep work')
            ->assertSee('4 Sessions')
            ->assertDontSee('data-week-empty', false);
    }

    public function test_another_week_and_a_garbage_week_parameter(): void
    {
        $this->get('/board/week?w=2026-11-25')->assertOk()->assertSee('23.11. – 29.11.');
        $this->get('/board/week?w=nonsense')->assertOk()->assertSee('16.11. – 22.11.');
    }

    public function test_the_wochenplan_shows_what_repeats_and_the_week_in_numbers(): void
    {
        (new WeekPlanEditor)->addRepeating($this->user, [3], '18:00', '18:45', null, 'Gitarrenstunde');

        $this->get('/board/week/plan')->assertOk()
            ->assertSee('data-week-view="plan"', false)
            ->assertSee('Wochenplan')->assertSee('Gitarrenstunde')
            ->assertSee('data-week-summary', false)
            ->assertSee('10 h · 20 S.');
    }

    public function test_a_fresh_account_sees_a_finished_empty_state(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/board/week')->assertOk()->assertSee('data-week-empty', false)->assertSee('Noch keine Woche geplant')
            ->assertSee('Zuerst Kategorien anlegen');
        $this->get('/board/week/plan')->assertOk()->assertSee('data-week-empty', false);
    }

    public function test_a_block_drawn_over_three_days_this_week_only(): void
    {
        Livewire::test(Week::class)
            ->call('openNew', [2, 3, 4], '07:00', '08:30')
            ->assertSet('form.mode', 'new')->assertSet('form.days', [2, 3, 4])
            ->assertSee('Nur diese Woche')->assertSee('Jede Woche')
            ->call('save', 'this')
            ->assertSet('form', null)->assertSee('3 Blöcke, nur diese Woche.');

        $this->assertSame('07:00–08:30 Deep work', $this->day('2026-11-18')[0]);
        $this->assertSame('19:30–21:30 Deep work', $this->day('2026-11-25')[0], 'next week is untouched');
    }

    public function test_a_drawn_block_every_week_becomes_a_repeating_block(): void
    {
        Livewire::test(Week::class)
            ->call('openNew', [6], '09:00', '11:00')
            ->assertSee('Nur am Sa 21.11.')->assertSee('Jeden Samstag')
            ->call('save', 'every');

        $this->assertSame(['09:00–11:00 Deep work'], $this->day('2026-11-28'));
    }

    /**
     * C17 (Niels, 2026-11-17): in a week with one normal day and every other day paused, a click on a day chip of
     * the form made the popover vanish. The state was fine; the cause was client side: weekEditor.place() writes
     * left / top / data-placement onto the section, and Livewire's morph reset them on every re-render, so the
     * popover fell to the bottom of the page. The section must be `wire:ignore.self`; a PHP test cannot run the
     * morph, so this pins the markup and the state the chips act on.
     */
    public function test_the_form_stays_open_and_placed_when_day_chips_are_toggled_in_a_mostly_paused_week(): void
    {
        SchedulePause::pauseRange($this->user, \Illuminate\Support\Carbon::parse('2026-11-16'), \Illuminate\Support\Carbon::parse('2026-11-16'), 'Ferien');
        SchedulePause::pauseRange($this->user, \Illuminate\Support\Carbon::parse('2026-11-18'), \Illuminate\Support\Carbon::parse('2026-11-22'), 'Ferien');

        $page = Livewire::test(Week::class)
            ->call('openNew', [2], '18:00', '19:00')
            ->call('toggleDay', 3)->call('toggleDay', 6)
            ->assertSet('form.mode', 'new')->assertSet('form.days', [2, 3, 6])
            ->assertSee('Nur diese Woche')->assertSee('Jede Woche')
            ->call('toggleDay', 3)->assertSet('form.days', [2, 6]);

        $this->assertMatchesRegularExpression('/<section[^>]*\swire:ignore\.self[^>]*\sdata-week-form="new"/s', $page->html());

        // One-offs may stand on paused days, so the chips of paused days work like any other.
        $page->call('save', 'this')->assertSet('form', null);
        $this->assertSame(['18:00–19:00 Deep work'], $this->day('2026-11-21'));
    }

    public function test_a_new_block_never_saves_without_the_scope_choice(): void
    {
        Livewire::test(Week::class)->call('openNew', [6], '09:00', '11:00')->call('save')->assertNotSet('form', null);

        $this->assertSame([], $this->day('2026-11-21'));
    }

    public function test_an_occurrence_changed_this_week_or_every_week(): void
    {
        $page = Livewire::test(Week::class)
            ->call('openBlock', 'occ', $this->template->id, '2026-11-18')
            ->assertSet('form.start', '19:30')
            ->set('form.start', '20:00')->set('form.end', '21:00')
            ->call('save', 'this')
            ->assertSee('Nur für 18.11. geändert.')->assertSee('data-undo', false);

        $this->assertSame(['20:00–21:00 Deep work'], $this->day('2026-11-18'));
        $this->assertSame(['19:30–21:30 Deep work'], $this->day('2026-11-19'));

        $page->call('openBlock', 'occ', $this->template->id, '2026-11-18')->assertSee('Zurück zum Wochenplan')
            ->call('openBlock', 'occ', $this->template->id, '2026-11-19')
            ->set('form.start', '18:00')->set('form.end', '20:00')
            ->call('save', 'every');

        $this->assertSame(['18:00–20:00 Deep work'], $this->day('2026-11-26'));
        $this->assertSame(['20:00–21:00 Deep work'], $this->day('2026-11-18'), 'the one-week change stays');
    }

    public function test_a_block_dragged_to_another_day(): void
    {
        // This week only: Wednesday's block moves to Saturday morning.
        Livewire::test(Week::class)
            ->call('openBlock', 'occ', $this->template->id, '2026-11-18', '2026-11-21', '10:00', '12:00')
            ->assertSet('form.newDate', '2026-11-21')->assertSet('form.start', '10:00')
            ->assertSee('Jede Woche, neu am Samstag')
            ->call('save', 'this');

        $this->assertSame([], $this->day('2026-11-18'));
        $this->assertSame(['10:00–12:00 Deep work'], $this->day('2026-11-21'));

        // Every week: Thursday becomes Sunday in the Wochenplan.
        Livewire::test(Week::class)
            ->call('openBlock', 'occ', $this->template->id, '2026-11-19', '2026-11-22', '19:30', '21:30')
            ->call('save', 'every');

        $this->assertSame([1, 2, 3, 5, 7], $this->template->fresh()->recurrenceDays());
    }

    public function test_an_occurrence_removed_this_week_and_undone(): void
    {
        Livewire::test(Week::class)
            ->call('openBlock', 'occ', $this->template->id, '2026-11-20')
            ->call('remove', 'this')->assertSee('Fällt am 20.11. aus.')
            ->tap(fn () => $this->assertSame([], $this->day('2026-11-20')))
            ->call('undoLast')->assertSee('Rückgängig gemacht.');

        $this->assertSame(['19:30–21:30 Deep work'], $this->day('2026-11-20'));
    }

    public function test_a_termin_is_drawn_changed_and_removed_with_undo(): void
    {
        $page = Livewire::test(Week::class)
            ->call('openNew', [4], '16:00', '17:00')
            ->set('form.kind', 'termin')->set('form.title', 'Zahnarzt')
            ->call('save', 'this');
        $row = ScheduleEvent::where('title', 'Zahnarzt')->sole();

        $page->call('openBlock', 'once', $row->id, '2026-11-19')->assertSet('form.title', 'Zahnarzt')
            ->set('form.newDate', '2026-11-20')->call('save');
        $this->assertSame('16:00–17:00 Zahnarzt', $this->day('2026-11-20')[0]);

        $page->call('openBlock', 'once', $row->id, '2026-11-20')->call('remove');
        $this->assertNull(ScheduleEvent::find($row->id));
        $page->call('undoLast');
        $this->assertNotNull(ScheduleEvent::find($row->id));
    }

    public function test_errors_stand_under_the_field_in_german(): void
    {
        Livewire::test(Week::class)
            ->call('openNew', [4], '16:00', '17:00')
            ->set('form.end', '15:00')->call('save', 'this')
            ->assertHasErrors(['form.end'])->assertNotSet('form', null);

        Livewire::test(Week::class)
            ->call('openNew', [4], '16:00', '17:00')
            ->set('form.kind', 'termin')->set('form.title', '   ')->call('save', 'this')
            ->assertHasErrors(['form.title']);
    }

    public function test_the_wochenplan_edits_the_template_directly(): void
    {
        $page = Livewire::test(Week::class, ['view' => 'plan'])
            ->call('openBlock', 'tpl', $this->template->id, '1')
            ->assertSet('form.days', [1, 2, 3, 4, 5])
            ->call('toggleDay', 3)->set('form.start', '20:00')->set('form.end', '22:00')
            ->call('save')->assertSee('Im Wochenplan, ab morgen jede Woche.');

        $this->assertSame([], $this->day('2026-11-25'));
        $this->assertSame(['20:00–22:00 Deep work'], $this->day('2026-11-24'));

        // Dragged from Monday to Saturday: the weekday moves.
        $page->call('openBlock', 'tpl', $this->template->id, '1', '6', '20:00', '22:00')->assertSet('form.days', [2, 4, 5, 6])->call('save');
        $this->assertSame([2, 4, 5, 6], $this->template->fresh()->recurrenceDays());

        $page->call('openBlock', 'tpl', $this->template->id, '2')->call('remove')->assertSee('Aus dem Wochenplan entfernt.');
        $this->assertFalse($this->template->fresh()->is_recurring);
        $page->call('undoLast');
        $this->assertTrue($this->template->fresh()->is_recurring);
    }

    public function test_a_new_block_in_the_wochenplan_needs_no_scope(): void
    {
        Livewire::test(Week::class, ['view' => 'plan'])
            ->call('openNew', [6, 7], '09:00', '10:00')->assertSet('form.mode', 'tnew')
            ->assertDontSee('Nur diese Woche')
            ->call('save');

        $this->assertSame(['09:00–10:00 Deep work'], $this->day('2026-11-29'));
    }

    public function test_a_holiday_in_two_taps_and_one_day_back_on(): void
    {
        $this->get('/board/week?pause=1')->assertOk()->assertSee('data-pause-sheet', false);

        $page = Livewire::test(Week::class)
            ->call('openPause')->assertSet('pauseOpen', true)
            ->call('pauseRange', '2026-11-23', '2026-12-06', 'Ferien')
            ->assertSet('pauseOpen', false)->assertSet('week', '2026-11-23')
            ->assertSee('Pausiert: 23.11. – 6.12.')->assertSee('Pause · Ferien');

        $this->assertSame(14, SchedulePause::forUser($this->user)->count());
        $this->assertSame([], $this->day('2026-11-24'));

        $page->call('togglePause', '2026-11-25')->assertSee('Mittwoch 25.11. ist wieder eingeschaltet.');
        $this->assertSame(['19:30–21:30 Deep work'], $this->day('2026-11-25'));
    }

    /** C6 / C17 (Niels, 2026-11-17): the pause hatch covers the day header (desktop) and the day strip tab (phone), not the whole day column. */
    public function test_a_paused_day_is_marked_on_its_header_only(): void
    {
        SchedulePause::pauseRange($this->user, \Illuminate\Support\Carbon::parse('2026-11-18'), \Illuminate\Support\Carbon::parse('2026-11-18'), 'Arzt');

        $html = Livewire::test(Week::class)->html();

        preg_match_all('/<div data-col[^>]*>/', $html, $columns);
        $this->assertCount(7, $columns[0]);
        $this->assertStringContainsString('data-paused="1"', implode('', $columns[0]));
        $this->assertStringNotContainsString('repeating-linear-gradient', implode('', $columns[0]), 'the day column stays plain');

        $this->assertMatchesRegularExpression('/<button[^>]*title="Diesen Tag wieder einschalten"[^>]*repeating-linear-gradient/s', $html, 'desktop header');
        $this->assertMatchesRegularExpression('/<button type="button" role="tab"[^>]*repeating-linear-gradient/s', $html, 'phone day strip');
        $this->assertStringContainsString('Pause · Arzt', $html);
    }

    public function test_a_pause_is_undone_without_touching_older_pauses(): void
    {
        SchedulePause::pauseRange($this->user, \Illuminate\Support\Carbon::parse('2026-11-24'), \Illuminate\Support\Carbon::parse('2026-11-24'), 'Arzt');

        Livewire::test(Week::class)->call('pauseRange', '2026-11-23', '2026-11-27')->call('undoLast');

        $this->assertSame(['2026-11-24'], SchedulePause::forUser($this->user)->get()->map(fn ($p) => $p->date->toDateString())->all());
    }

    public function test_a_wrong_pause_range_says_so(): void
    {
        Livewire::test(Week::class)->call('openPause')->call('pauseRange', '2026-11-23', '2027-12-23')
            ->assertHasErrors(['pause.to'])->assertSet('pauseOpen', true);
    }

    public function test_another_users_blocks_are_out_of_reach(): void
    {
        $other = User::factory()->create();
        $foreign = (new WeekPlanEditor)->addOnce($other, '2026-11-19', '10:00', '11:00', null, 'Fremd');
        $foreignTemplate = (new WeekPlanEditor)->addRepeating($other, [4], '10:00', '11:00', null, 'Fremd');

        Livewire::test(Week::class)
            ->call('openBlock', 'once', $foreign->id, '2026-11-19')->assertSet('form', null)->assertSee('Diesen Block gibt es nicht mehr.')
            ->set('form', ['mode' => 'once', 'id' => $foreign->id, 'date' => '2026-11-19', 'newDate' => '2026-11-19', 'start' => '10:00', 'end' => '11:00', 'kind' => 'termin', 'title' => 'Meins', 'categoryId' => null])
            ->call('save')
            ->set('form', ['mode' => 'tpl', 'id' => $foreignTemplate->id, 'days' => [4], 'start' => '10:00', 'end' => '11:00', 'kind' => 'termin', 'title' => 'Meins', 'categoryId' => null])
            ->call('remove');

        $this->assertSame('Fremd', $foreign->fresh()->title);
        $this->assertTrue($foreignTemplate->fresh()->is_recurring);
    }

    public function test_a_long_title_with_umlauts_renders_in_the_block(): void
    {
        $title = str_repeat('Prüfungsvorbereitung Ölmalerei Ärztehaus ', 10);
        (new WeekPlanEditor)->addOnce($this->user, '2026-11-19', '16:00', '17:00', null, trim(mb_substr($title, 0, 255)));

        $this->get('/board/week')->assertOk()->assertSee('Prüfungsvorbereitung Ölmalerei', false)->assertSee('line-clamp-1', false);
    }

    public function test_the_shell_navigation_opens_the_new_editor(): void
    {
        $this->get('/board')->assertOk()->assertSee(route('shell.week'), false)->assertDontSee('href="'.route('weekplan').'"', false);
        $this->get('/board/ideas')->assertOk()->assertSee(route('shell.week'), false);
    }
}
