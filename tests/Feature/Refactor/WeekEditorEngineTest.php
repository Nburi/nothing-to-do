<?php

namespace Tests\Feature\Refactor;

use App\Models\CategoryAttribute;
use App\Models\EventCategory;
use App\Models\EventTemplate;
use App\Models\ScheduleEvent;
use App\Models\SchedulePause;
use App\Models\User;
use App\Planning\Block;
use App\Planning\ChangeScope;
use App\Services\Planning\WeekPlanEditor;
use App\Services\Planning\WeekPlanJournal;
use App\Services\Planning\WeekPlanReader;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * A21 (step 4.1; R4, risk C3): what the week plan editor adds to the write side of step 1.2:
 * one-off blocks changed and removed, the Wochenplan editing a repeating block directly, the
 * row identity the editor reads, and "RÃ¼ckgÃ¤ngig" for every edit (WeekPlanJournal).
 * "Today" is Monday 9 November 2026, Zurich.
 */
class WeekEditorEngineTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private EventCategory $deep;

    private EventTemplate $template;

    private WeekPlanEditor $editor;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');
        $this->travelTo(CarbonImmutable::parse('2026-11-09 12:00', 'Europe/Zurich'));
        $this->editor = new WeekPlanEditor;
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
        $this->deep = EventCategory::factory()->for($this->user)->pomodoro()->create(['name' => 'Deep work', 'color' => 'forest']);
        $this->template = $this->editor->addRepeating($this->user, [1, 2, 3, 4, 5], '19:30', '21:30', $this->deep->id);
    }

    /** @return list<string> "HH:MM–HH:MM title" per block */
    private function day(string $date): array
    {
        return array_map(
            fn (Block $b) => $b->start->format('H:i').'–'.$b->end->format('H:i').' '.($b->title ?? ''),
            (new WeekPlanReader)->blocks($this->user, $date, $date)[$date],
        );
    }

    /** Every row of the user's week plan, to compare before and after an undo. */
    private function state(): array
    {
        return [
            'templates' => DB::table('event_templates')->where('user_id', $this->user->id)->orderBy('id')->get(['id', 'category_id', 'name', 'color', 'duration', 'default_start', 'is_recurring', 'recurrence'])->map(fn ($r) => (array) $r)->all(),
            'events' => DB::table('schedule_events')->where('user_id', $this->user->id)->orderBy('id')->get(['id', 'template_id', 'category_id', 'title', 'color', 'date', 'start_time', 'end_time', 'is_cancelled'])->map(fn ($r) => (array) $r)->all(),
            'pauses' => DB::table('schedule_pauses')->where('user_id', $this->user->id)->orderBy('date')->get(['date', 'note'])->map(fn ($r) => (array) $r)->all(),
        ];
    }

    private function undoable(\Closure $op): void
    {
        $before = $this->state();
        [, $journal] = WeekPlanJournal::record($op);
        $this->assertNotSame($before, $this->state(), 'the operation changed nothing');

        WeekPlanJournal::undo($this->user, json_decode(json_encode($journal), true));
        $this->assertSame($before, $this->state());
    }

    public function test_a_one_off_block_can_be_changed_and_removed(): void
    {
        $row = $this->editor->addOnce($this->user, '2026-11-11', '16:00', '17:00', null, 'Zahnarzt');

        $this->editor->updateOnce($this->user, $row->id, '2026-11-12', '15:00', '15:45', null, 'Zahnarzt Kontrolle');
        $this->assertSame(['19:30–21:30 Deep work'], $this->day('2026-11-11'));
        $this->assertSame(['15:00–15:45 Zahnarzt Kontrolle', '19:30–21:30 Deep work'], $this->day('2026-11-12'));

        $this->editor->updateOnce($this->user, $row->id, '2026-11-12', '15:00', '15:45', $this->deep->id);
        $this->assertSame('Deep work', $row->fresh()->title);

        $this->editor->deleteOnce($this->user, $row->id);
        $this->assertSame(['19:30–21:30 Deep work'], $this->day('2026-11-12'));
    }

    public function test_an_occurrence_is_not_a_one_off(): void
    {
        $this->editor->changeOccurrence($this->user, $this->template->id, '2026-11-10', '20:00', '21:00', $this->deep->id, ChangeScope::ThisWeekOnly);
        $row = ScheduleEvent::where('template_id', $this->template->id)->first();

        $this->expectException(ModelNotFoundException::class);
        $this->editor->deleteOnce($this->user, $row->id);
    }

    public function test_one_offs_of_another_user_are_out_of_reach(): void
    {
        $other = User::factory()->create();
        $row = $this->editor->addOnce($other, '2026-11-11', '16:00', '17:00', null, 'Fremd');

        $this->expectException(ModelNotFoundException::class);
        $this->editor->updateOnce($this->user, $row->id, '2026-11-11', '16:00', '17:00', null, 'Meins');
    }

    public function test_the_wochenplan_changes_weekdays_and_time_and_keeps_one_week_edits(): void
    {
        // Thursday 12.11. was changed on purpose; Friday 13.11. was materialised by the old screens.
        $this->editor->changeOccurrence($this->user, $this->template->id, '2026-11-12', '18:00', '19:00', $this->deep->id, ChangeScope::ThisWeekOnly);
        ScheduleEvent::materializeRange($this->user, Carbon::parse('2026-11-09'), Carbon::parse('2026-11-09'));
        ScheduleEvent::materializeRange($this->user, Carbon::parse('2026-11-13'), Carbon::parse('2026-11-13'));

        $this->editor->updateTemplate($this->user, $this->template->id, [2, 4, 5], '20:00', '22:00', $this->deep->id);

        $this->assertSame([], $this->day('2026-11-11'), 'Wednesday is no longer in the plan');
        $this->assertSame(['20:00–22:00 Deep work'], $this->day('2026-11-10'));
        $this->assertSame(['18:00–19:00 Deep work'], $this->day('2026-11-12'), 'the one-week edit stays');
        $this->assertSame(['20:00–22:00 Deep work'], $this->day('2026-11-13'), 'the materialised row follows');
        $this->assertSame(['19:30–21:30 Deep work'], $this->day('2026-11-09'), 'the row of today is never rewritten');
    }

    public function test_a_repeating_termin_is_renamed_in_the_wochenplan(): void
    {
        $guitar = $this->editor->addRepeating($this->user, [3], '18:00', '18:45', null, 'Gitarre');

        $this->editor->updateTemplate($this->user, $guitar->id, [3], '18:00', '18:45', null, 'Gitarrenstunde');

        $this->assertSame(['18:00–18:45 Gitarrenstunde', '19:30–21:30 Deep work'], $this->day('2026-11-11'));
    }

    public function test_the_wochenplan_validates_like_the_form(): void
    {
        $this->expectException(ValidationException::class);
        $this->editor->updateTemplate($this->user, $this->template->id, [], '20:00', '22:00', $this->deep->id);
    }

    public function test_stopping_a_block_keeps_its_history(): void
    {
        ScheduleEvent::materializeRange($this->user, Carbon::parse('2026-11-02'), Carbon::parse('2026-11-13'));
        $past = ScheduleEvent::where('template_id', $this->template->id)->whereDate('date', '<=', '2026-11-09')->count();

        $this->editor->stopTemplate($this->user, $this->template->id);

        $this->assertSame([], $this->day('2026-11-10'));
        $this->assertNotNull($this->template->fresh());
        $this->assertSame($past, ScheduleEvent::where('template_id', $this->template->id)->count());
    }

    public function test_blocks_carry_the_row_they_come_from(): void
    {
        $once = $this->editor->addOnce($this->user, '2026-11-10', '16:00', '17:00', null, 'Zahnarzt');
        $this->editor->changeOccurrence($this->user, $this->template->id, '2026-11-10', '20:00', '21:00', $this->deep->id, ChangeScope::ThisWeekOnly);

        [$termin, $changed] = (new WeekPlanReader)->blocks($this->user, '2026-11-10', '2026-11-10')['2026-11-10'];
        $this->assertSame([$once->id, null, false], [$termin->eventId, $termin->templateId, $termin->changed]);
        $this->assertSame($this->template->id, $changed->templateId);
        $this->assertTrue($changed->changed);
        $this->assertNotNull($changed->eventId);

        [$plain] = (new WeekPlanReader)->blocks($this->user, '2026-11-11', '2026-11-11')['2026-11-11'];
        $this->assertSame([null, false], [$plain->eventId, $plain->changed]);

        // A row the old screens materialised unchanged is not "changed this week".
        ScheduleEvent::materializeRange($this->user, Carbon::parse('2026-11-12'), Carbon::parse('2026-11-12'));
        [$materialised] = (new WeekPlanReader)->blocks($this->user, '2026-11-12', '2026-11-12')['2026-11-12'];
        $this->assertNotNull($materialised->eventId);
        $this->assertFalse($materialised->changed);
    }

    public function test_every_edit_can_be_undone_exactly(): void
    {
        $training = EventCategory::factory()->for($this->user)->create(['name' => 'Training']);
        $once = $this->editor->addOnce($this->user, '2026-11-11', '16:00', '17:00', null, 'Zahnarzt');
        ScheduleEvent::materializeRange($this->user, Carbon::parse('2026-11-10'), Carbon::parse('2026-11-20'));
        SchedulePause::pauseRange($this->user, Carbon::parse('2026-11-19'), Carbon::parse('2026-11-19'), 'frei');
        $tid = $this->template->id;

        $this->undoable(fn () => $this->editor->addRepeating($this->user, [6], '09:00', '11:00', $this->deep->id));
        $this->undoable(fn () => $this->editor->addOnce($this->user, '2026-11-12', '08:00', '09:00', null, 'Arzt'));
        $this->undoable(fn () => $this->editor->changeOccurrence($this->user, $tid, '2026-11-10', '20:00', '21:00', $training->id, ChangeScope::ThisWeekOnly));
        $this->undoable(fn () => $this->editor->changeOccurrence($this->user, $tid, '2026-11-10', '18:00', '20:00', $this->deep->id, ChangeScope::EveryWeek));
        $this->undoable(fn () => $this->editor->moveOccurrence($this->user, $tid, '2026-11-12', '2026-11-14', '10:00', '12:00'));
        $this->undoable(fn () => $this->editor->cancelOccurrence($this->user, $tid, '2026-11-13', ChangeScope::ThisWeekOnly));
        $this->undoable(fn () => $this->editor->cancelOccurrence($this->user, $tid, '2026-11-13', ChangeScope::EveryWeek));
        $this->undoable(fn () => $this->editor->updateTemplate($this->user, $tid, [1, 3], '07:00', '08:00', null, 'Laufen'));
        $this->undoable(fn () => $this->editor->stopTemplate($this->user, $tid));
        $this->undoable(fn () => $this->editor->updateOnce($this->user, $once->id, '2026-11-12', '10:00', '11:00', null, 'Anders'));
        $this->undoable(fn () => $this->editor->deleteOnce($this->user, $once->id));
        $this->undoable(fn () => $this->editor->pause($this->user, '2026-11-16', '2026-11-22', 'Ferien'));
        $this->undoable(fn () => $this->editor->resume($this->user, '2026-11-19'));

        $this->editor->changeOccurrence($this->user, $tid, '2026-11-17', '20:00', '21:00', $this->deep->id, ChangeScope::ThisWeekOnly);
        $this->undoable(fn () => $this->editor->restoreOccurrence($this->user, $tid, '2026-11-17'));
    }

    public function test_undo_brings_back_what_a_deleted_row_carried(): void
    {
        $attribute = CategoryAttribute::forceCreate(['event_category_id' => $this->deep->id, 'name' => 'Fokus', 'type' => 'text', 'sort_order' => 0]);
        ScheduleEvent::materializeRange($this->user, Carbon::parse('2026-11-13'), Carbon::parse('2026-11-13'));
        $friday = ScheduleEvent::where('template_id', $this->template->id)->whereDate('date', '2026-11-13')->firstOrFail();
        DB::table('schedule_event_attribute_values')->insert(['schedule_event_id' => $friday->id, 'category_attribute_id' => $attribute->id, 'value' => 'Bericht']);

        [, $journal] = WeekPlanJournal::record(fn () => $this->editor->cancelOccurrence($this->user, $this->template->id, '2026-11-13', ChangeScope::EveryWeek));
        $this->assertNull(ScheduleEvent::find($friday->id), 'removing Friday every week drops the future Friday row');

        WeekPlanJournal::undo($this->user, $journal);
        $this->assertSame('Bericht', DB::table('schedule_event_attribute_values')->where('schedule_event_id', $friday->id)->value('value'));
    }

    public function test_an_undo_never_touches_another_account(): void
    {
        $other = User::factory()->create();
        $row = $this->editor->addOnce($other, '2026-11-11', '16:00', '17:00', null, 'Fremd');

        WeekPlanJournal::undo($this->user, [
            ['kind' => 'created', 'type' => 'event', 'id' => $row->id],
            ['kind' => 'updated', 'type' => 'event', 'id' => $row->id, 'before' => ['title' => 'Gekapert']],
        ]);

        $this->assertSame('Fremd', $row->fresh()->title);
    }
}
