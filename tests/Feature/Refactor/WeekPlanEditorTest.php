<?php

namespace Tests\Feature\Refactor;

use App\Models\EventCategory;
use App\Models\EventTemplate;
use App\Models\ScheduleEvent;
use App\Models\SchedulePause;
use App\Models\User;
use App\Planning\Block;
use App\Planning\ChangeScope;
use App\Services\Planning\WeekPlanEditor;
use App\Services\Planning\WeekPlanReader;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Step 1.2 (R4; risks C2, C3; A3, A4 on real rows): the write side of the
 * week plan. "Today" is Monday 9 November 2026, Zurich. Deep work (pomodoro)
 * Mon–Fri 19:30–21:30 as a template.
 */
class WeekPlanEditorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private EventCategory $deepWork;

    private EventCategory $training;

    private EventTemplate $template;

    private WeekPlanEditor $editor;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');
        $this->travelTo(CarbonImmutable::parse('2026-11-09 12:00', 'Europe/Zurich'));
        $this->editor = new WeekPlanEditor;
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
        $this->deepWork = EventCategory::factory()->for($this->user)->pomodoro()->create(['name' => 'Deep work']);
        $this->training = EventCategory::factory()->for($this->user)->create(['name' => 'Training']);
        $this->template = $this->editor->addRepeating($this->user, [1, 2, 3, 4, 5], '19:30', '21:30', $this->deepWork->id);
    }

    /** @return list<string> "HH:MM–HH:MM" per block */
    private function times(string $date): array
    {
        return array_map(
            fn (Block $b) => $b->start->format('H:i').'–'.$b->end->format('H:i'),
            (new WeekPlanReader)->blocks($this->user, $date, $date)[$date],
        );
    }

    public function test_a_new_repeating_block_takes_its_name_and_colour_from_the_category(): void
    {
        $this->assertSame('Deep work', $this->template->name);
        $this->assertSame('1,2,3,4,5', $this->template->recurrence);
        $this->assertSame(120, $this->template->duration);
        $this->assertSame(['19:30–21:30'], $this->times('2026-11-10'));
    }

    public function test_this_week_only_changes_one_date_and_leaves_the_template(): void
    {
        $this->editor->changeOccurrence($this->user, $this->template->id, '2026-11-11', '20:00', '21:00', $this->deepWork->id, ChangeScope::ThisWeekOnly);

        $this->assertSame(['20:00–21:00'], $this->times('2026-11-11'));
        $this->assertSame(['19:30–21:30'], $this->times('2026-11-10'));
        $this->assertSame(['19:30–21:30'], $this->times('2026-11-18'));
        $this->assertSame('19:30', $this->template->fresh()->default_start);
    }

    public function test_every_week_changes_the_series_from_tomorrow_but_keeps_one_week_edits(): void
    {
        // The old screens already materialised this week; Wednesday was changed on purpose.
        ScheduleEvent::materializeRange($this->user, Carbon::parse('2026-11-09'), Carbon::parse('2026-11-15'));
        $this->editor->changeOccurrence($this->user, $this->template->id, '2026-11-11', '20:00', '21:00', $this->deepWork->id, ChangeScope::ThisWeekOnly);

        $this->editor->changeOccurrence($this->user, $this->template->id, '2026-11-10', '18:00', '20:00', $this->deepWork->id, ChangeScope::EveryWeek);

        $this->assertSame(['19:30–21:30'], $this->times('2026-11-09'), 'today is never rewritten');
        $this->assertSame(['18:00–20:00'], $this->times('2026-11-10'), 'materialised, untouched: follows');
        $this->assertSame(['20:00–21:00'], $this->times('2026-11-11'), 'changed on purpose: kept');
        $this->assertSame(['18:00–20:00'], $this->times('2026-11-24'), 'not materialised yet: the template');
    }

    public function test_moving_an_occurrence_leaves_a_tombstone_and_a_one_off(): void
    {
        $this->editor->moveOccurrence($this->user, $this->template->id, '2026-11-10', '2026-11-14', '10:00', '12:00');

        $this->assertSame([], $this->times('2026-11-10'));
        $this->assertSame(['10:00–12:00'], $this->times('2026-11-14'));

        $this->editor->pause($this->user, '2026-11-14', '2026-11-14');
        $this->assertSame(['10:00–12:00'], $this->times('2026-11-14'), 'a pause never hides a one-off');
    }

    public function test_cancel_this_week_only_and_restore(): void
    {
        $this->editor->cancelOccurrence($this->user, $this->template->id, '2026-11-12', ChangeScope::ThisWeekOnly);
        $this->assertSame([], $this->times('2026-11-12'));
        $this->assertSame(['19:30–21:30'], $this->times('2026-11-19'));

        $this->editor->restoreOccurrence($this->user, $this->template->id, '2026-11-12');
        $this->assertSame(['19:30–21:30'], $this->times('2026-11-12'));
    }

    public function test_cancel_every_week_removes_the_weekday_and_never_deletes_history(): void
    {
        ScheduleEvent::materializeRange($this->user, Carbon::parse('2026-11-02'), Carbon::parse('2026-11-15'));
        $past = ScheduleEvent::query()->where('template_id', $this->template->id)->whereDate('date', '2026-11-06')->sole();

        $this->editor->cancelOccurrence($this->user, $this->template->id, '2026-11-13', ChangeScope::EveryWeek);

        $this->assertSame('1,2,3,4', $this->template->fresh()->recurrence);
        $this->assertSame([], $this->times('2026-11-13'));
        $this->assertSame([], $this->times('2026-11-20'));
        $this->assertNotNull($past->fresh(), 'last Friday stays in the history');

        foreach (['2026-11-16', '2026-11-17', '2026-11-18', '2026-11-19'] as $date) {
            $this->editor->cancelOccurrence($this->user, $this->template->id, $date, ChangeScope::EveryWeek);
        }
        $this->assertFalse($this->template->fresh()->is_recurring, 'no weekday left: it stops, it is not deleted');
        $this->assertDatabaseHas('event_templates', ['id' => $this->template->id]);
    }

    public function test_a_holiday_pauses_the_blocks_and_one_day_can_be_switched_back_on(): void
    {
        $this->editor->pause($this->user, '2026-12-21', '2027-01-03', 'Ferien');

        $this->assertSame([], $this->times('2026-12-22'));
        $this->assertSame([], $this->times('2027-01-01'));

        $this->editor->resume($this->user, '2026-12-29');
        $this->assertSame(['19:30–21:30'], $this->times('2026-12-29'));
        $this->assertSame([], $this->times('2026-12-30'));
    }

    public function test_a_pause_of_more_than_a_year_is_refused_with_a_german_message(): void
    {
        try {
            $this->editor->pause($this->user, '2026-11-10', '2028-01-01');
            $this->fail('expected a validation error');
        } catch (ValidationException $e) {
            $this->assertSame(['Eine Pause darf höchstens ein Jahr lang sein.'], $e->errors()['to']);
        }
        $this->assertDatabaseCount('schedule_pauses', 0);
    }

    public function test_a_change_to_another_category_this_week_only_moves_the_capacity(): void
    {
        $this->editor->changeOccurrence($this->user, $this->template->id, '2026-11-10', '19:30', '21:30', $this->training->id, ChangeScope::ThisWeekOnly);

        $slots = (new WeekPlanReader)->slots($this->user, '2026-11-09', '2026-11-11');
        $this->assertSame([4, 0, 4], array_values(array_map('count', $slots)));
    }

    public function test_a_termin_once_and_its_validation(): void
    {
        $termin = $this->editor->addOnce($this->user, '2026-11-10', '20:00', '20:30', null, 'Zahnarzt', 'signal');
        $this->assertSame('Zahnarzt', $termin->title);
        $this->assertSame(3, count((new WeekPlanReader)->slots($this->user, '2026-11-10', '2026-11-10')['2026-11-10']), 'E1: the Termin cuts the block');

        $this->assertInvalid(fn () => $this->editor->addOnce($this->user, '2026-11-10', '20:00', '19:00', null, 'Falsch'), 'end');
        $this->assertInvalid(fn () => $this->editor->addOnce($this->user, '2026-11-10', '20:00', '20:05', null, 'Kurz'), 'end');
        $this->assertInvalid(fn () => $this->editor->addOnce($this->user, '2026-11-10', '20:00', '21:00', null, ''), 'title');
        $this->assertInvalid(fn () => $this->editor->addRepeating($this->user, [], '20:00', '21:00', $this->deepWork->id), 'weekdays');
        $this->assertInvalid(fn () => $this->editor->addRepeating($this->user, [8], '20:00', '21:00', $this->deepWork->id), 'weekdays.0');
        $this->assertInvalid(fn () => $this->editor->addOnce($this->user, '10.11.2026', '20:00', '21:00', $this->deepWork->id), 'date');
    }

    public function test_someone_elses_category_or_template_is_refused(): void
    {
        $stranger = User::factory()->create();
        $foreignCategory = EventCategory::factory()->for($stranger)->create();
        $foreignTemplate = EventTemplate::factory()->for($stranger)->recurring('1')->create();

        $this->assertInvalid(fn () => $this->editor->addRepeating($this->user, [1], '08:00', '09:00', $foreignCategory->id), 'categoryId');

        $this->expectException(ModelNotFoundException::class);
        $this->editor->changeOccurrence($this->user, $foreignTemplate->id, '2026-11-10', '08:00', '09:00', null, ChangeScope::ThisWeekOnly);
    }

    public function test_the_old_screens_hide_an_occurrence_edited_in_the_form_on_a_paused_date(): void
    {
        // Written through Eloquent, so SQLite stores the date as "Y-m-d 00:00:00"; the pause row is "Y-m-d".
        $this->editor->changeOccurrence($this->user, $this->template->id, '2026-11-10', '20:00', '21:00', $this->deepWork->id, ChangeScope::ThisWeekOnly);
        $this->editor->pause($this->user, '2026-11-10', '2026-11-10');

        $this->assertSame(0, ScheduleEvent::forUser($this->user)->visible()->forDay('2026-11-10')->count());
        $this->assertSame([], $this->times('2026-11-10'));
    }

    /**
     * Risk C3 (property-style): after any sequence of edits, the new reader
     * shows exactly what the old screens show (materialise, then the visible
     * rows), for every date of three weeks. 40 seeded random sequences.
     */
    public function test_after_random_edits_the_reader_agrees_with_the_old_screens(): void
    {
        mt_srand(20261109);
        $dates = [];
        for ($d = CarbonImmutable::parse('2026-11-05'); $d->lte(CarbonImmutable::parse('2026-11-25')); $d = $d->addDay()) {
            $dates[] = $d->toDateString();
        }
        $times = [['18:00', '19:00'], ['19:30', '21:30'], ['20:00', '21:00'], ['07:00', '08:30']];
        $categories = [$this->deepWork->id, $this->training->id];

        for ($run = 0; $run < 40; $run++) {
            DB::beginTransaction();
            $templates = [
                $this->editor->addRepeating($this->user, [mt_rand(1, 3), mt_rand(4, 7)], '19:30', '21:00', $this->deepWork->id),
                $this->editor->addRepeating($this->user, [mt_rand(1, 7)], '18:00', '19:00', $this->training->id),
            ];

            for ($step = 0; $step < 12; $step++) {
                $template = $templates[mt_rand(0, 1)]->fresh();
                $date = $dates[mt_rand(0, count($dates) - 1)];
                [$start, $end] = $times[mt_rand(0, count($times) - 1)];
                $scope = mt_rand(0, 1) ? ChangeScope::ThisWeekOnly : ChangeScope::EveryWeek;
                $occurs = $template->is_recurring && in_array(CarbonImmutable::parse($date)->dayOfWeekIso, $template->recurrenceDays(), true);

                match (mt_rand(0, 6)) {
                    0, 1 => $occurs && $this->editor->changeOccurrence($this->user, $template->id, $date, $start, $end, $categories[mt_rand(0, 1)], $scope) === null,
                    2 => $occurs && $this->editor->cancelOccurrence($this->user, $template->id, $date, $scope) === null,
                    3 => $occurs && $this->editor->moveOccurrence($this->user, $template->id, $date, $dates[mt_rand(0, count($dates) - 1)], $start, $end),
                    4 => $this->editor->pause($this->user, $date, $dates[min(count($dates) - 1, array_search($date, $dates, true) + mt_rand(0, 3))]),
                    5 => $this->editor->resume($this->user, $date),
                    6 => $this->materialiseLikeTheOldScreens($dates[0], $dates[count($dates) - 1]),
                };
            }

            foreach ($dates as $date) {
                $this->assertSame($this->oldScreens($date), $this->reader($date), "run {$run}, {$date} "
                    .json_encode(ScheduleEvent::forUser($this->user)->whereDate('date', $date)->get(['template_id', 'category_id', 'start_time', 'end_time', 'is_cancelled']))
                    .json_encode(EventTemplate::forUser($this->user)->get(['id', 'category_id', 'recurrence', 'is_recurring', 'default_start', 'duration']))
                    .' paused='.SchedulePause::forUser($this->user)->whereDate('date', $date)->count());
            }
            DB::rollBack();
        }
    }

    private function materialiseLikeTheOldScreens(string $from, string $to): void
    {
        ScheduleEvent::materializeRange($this->user, Carbon::parse($from), Carbon::parse($to));
    }

    /** What the old Zeitplan shows on a date, without leaving its materialised rows behind. */
    private function oldScreens(string $date): array
    {
        DB::beginTransaction();
        ScheduleEvent::materializeRange($this->user, Carbon::parse($date), Carbon::parse($date));
        $rows = ScheduleEvent::forUser($this->user)->visible()->forDay($date)->get()
            ->map(fn (ScheduleEvent $e) => $e->start_time.'–'.$e->end_time.'#'.($e->category_id ?? '-'))
            ->sort()->values()->all();
        DB::rollBack();

        return $rows;
    }

    private function reader(string $date): array
    {
        return collect((new WeekPlanReader)->blocks($this->user, $date, $date)[$date])
            ->map(fn (Block $b) => $b->start->format('H:i').'–'.$b->end->format('H:i').'#'.($b->categoryId ?? '-'))
            ->sort()->values()->all();
    }

    private function assertInvalid(callable $call, string $field): void
    {
        try {
            $call();
            $this->fail("expected a validation error on {$field}");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }
}
