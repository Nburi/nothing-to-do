<?php

namespace Tests\Feature\Refactor;

use App\Models\Area;
use App\Models\Evening;
use App\Models\EventCategory;
use App\Models\EventTemplate;
use App\Models\SchedulePause;
use App\Models\Task;
use App\Models\User;
use App\Planning\Size;
use App\Services\Planning\SundayReview;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Step 4.4, the quiet Sunday review (test A47): which week, what moved, what comes next.
 * Deep work (pomodoro) Mon–Fri 19:30–21:30 = 4 sessions an evening. Sunday 22 November 2026,
 * reviewed week Mon 16 – Sun 22, next week Mon 23 – Sun 29. User in Zurich (UTC+1 in November).
 */
class SundayReviewTest extends TestCase
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

    private function at(string $local): CarbonImmutable
    {
        return CarbonImmutable::parse($local, 'Europe/Zurich');
    }

    private function review(string $local): array
    {
        $this->travelTo($this->at($local));

        return (new SundayReview)->for($this->user, $this->at($local));
    }

    /** A finished task, completed at a local Zurich time. */
    private function done(string $local, array $attributes = []): Task
    {
        return Task::factory()->for($this->user)->create([
            'list' => 'tasks', 'is_completed' => true,
            'completed_at' => $this->at($local)->utc(), ...$attributes,
        ]);
    }

    public function test_on_sunday_it_reviews_the_week_ending_today(): void
    {
        $review = $this->review('2026-11-22 20:00');

        $this->assertSame('2026-11-16', $review['monday']);
        $this->assertSame('2026-11-22', $review['sunday']);
        $this->assertTrue($review['isSunday']);
        $this->assertSame(['2026-11-23', '2026-11-29'], [$review['next']['days'][0]['date'], $review['next']['days'][6]['date']]);
        $this->assertFalse($review['next']['days'][0]['past']);
    }

    public function test_on_monday_and_later_it_reviews_the_last_full_week(): void
    {
        $monday = $this->review('2026-11-23 07:30');
        $this->assertSame('2026-11-16', $monday['monday']);
        $this->assertFalse($monday['isSunday']);
        $this->assertFalse($monday['next']['days'][0]['past'], 'today is not past');

        $wednesday = $this->review('2026-11-25 07:30');
        $this->assertSame('2026-11-16', $wednesday['monday']);
        $this->assertSame([true, true, false], array_column(array_slice($wednesday['next']['days'], 0, 3), 'past'));
        $this->assertSame(0, $wednesday['next']['days'][0]['capacity'], 'a gone day has no room left');
    }

    public function test_it_offers_the_review_on_sunday_and_monday_only(): void
    {
        $offered = array_map(fn (string $d) => SundayReview::offeredOn(CarbonImmutable::parse($d)),
            ['2026-11-22', '2026-11-23', '2026-11-24', '2026-11-27', '2026-11-28']);

        $this->assertSame([true, true, false, false, false], $offered);
    }

    public function test_finished_work_is_counted_per_local_day_and_judged_against_the_users_own_average(): void
    {
        // Four earlier weeks with 2 each: the average is 2.
        foreach (['2026-10-20', '2026-10-27', '2026-11-03', '2026-11-10'] as $day) {
            $this->done("{$day} 18:00");
            $this->done("{$day} 19:00");
        }
        $this->done('2026-11-16 00:30');  // Sunday 23:30 UTC, but Monday locally
        $this->done('2026-11-19 20:00');
        $this->done('2026-11-19 21:00');
        $this->done('2026-11-22 23:30');  // Sunday night locally, Sunday 22:30 UTC
        $this->done('2026-11-23 00:10');  // already the next week

        $past = $this->review('2026-11-23 07:30')['past'];

        $this->assertSame([1, 0, 0, 2, 0, 0, 1], array_column($past['days'], 'count'));
        $this->assertSame(4, $past['total']);
        $this->assertSame(3, $past['activeDays']);
        $this->assertSame(2, $past['average']);
        $this->assertSame('best', $past['kind'], 'more than any week before');
    }

    public function test_the_running_sunday_is_judged_like_a_finished_week(): void
    {
        foreach (['2026-10-20', '2026-10-27', '2026-11-03', '2026-11-10'] as $day) {
            foreach (range(1, 4) as $i) {
                $this->done("{$day} 1{$i}:00");
            }
        }
        $this->done('2026-11-17 20:00');

        $past = $this->review('2026-11-22 19:00')['past'];

        $this->assertSame(4, $past['average']);
        $this->assertSame('below', $past['kind'], 'never "running" on the review day');
    }

    public function test_an_empty_week_says_nothing_happened(): void
    {
        $past = $this->review('2026-11-22 20:00')['past'];

        $this->assertSame(0, $past['total']);
        $this->assertSame('nothing', $past['kind']);
        $this->assertSame([], $past['done']);
        $this->assertSame([], $past['carried']);
    }

    public function test_it_lists_the_newest_finished_titles_with_their_area_and_counts_later_ideas(): void
    {
        $area = Area::factory()->for($this->user)->create(['name' => 'Zuhause', 'color' => 'overprint']);
        foreach (range(1, 6) as $i) {
            $this->done('2026-11-'.(15 + $i).' 20:00', ['title' => "Aufgabe {$i}"]);  // Mon 16 … Sat 21
        }
        $this->done('2026-11-15 20:00', ['title' => 'Letzte Woche']);
        $this->done('2026-11-21 15:00', ['title' => 'Fahrradlicht ersetzt', 'area_id' => $area->id, 'list' => Task::LATER]);

        $past = $this->review('2026-11-22 20:00')['past'];

        $this->assertSame(['Aufgabe 6', 'Fahrradlicht ersetzt', 'Aufgabe 5', 'Aufgabe 4', 'Aufgabe 3'], array_column($past['done'], 'title'));
        $this->assertSame(['Zuhause', 'overprint', '2026-11-21'], [$past['done'][1]['area'], $past['done'][1]['color'], $past['done'][1]['date']]);
        $this->assertNull($past['done'][0]['area']);
        $this->assertSame(2, $past['doneMore'], 'Aufgabe 1 and 2; last week is not counted');
        $this->assertSame(7, $past['total']);
        $this->assertSame(1, $past['ideas']);
    }

    public function test_it_counts_what_was_captured_the_resets_and_what_still_waits_in_the_inbox(): void
    {
        Task::factory()->for($this->user)->create(['list' => 'inbox', 'created_at' => $this->at('2026-11-17 12:00')->utc()]);
        Task::factory()->for($this->user)->create(['list' => 'tasks', 'created_at' => $this->at('2026-11-20 12:00')->utc()]);
        Task::factory()->for($this->user)->create(['list' => 'inbox', 'created_at' => $this->at('2026-11-12 12:00')->utc()]);
        foreach (['2026-11-17', '2026-11-18', '2026-11-12'] as $date) {
            Evening::forWrite($this->user, $date)->update(['reset_started_at' => $this->at("{$date} 18:00")]);
        }
        Evening::forWrite($this->user, '2026-11-19'); // battery only, no reset

        $past = $this->review('2026-11-22 20:00')['past'];

        $this->assertSame(2, $past['captured']);
        $this->assertSame(2, $past['inboxNow']);
        $this->assertSame(2, $past['resets']);
    }

    public function test_open_tasks_due_in_the_reviewed_week_are_carried_over_hard_dates_first(): void
    {
        $this->task('Bewerbung abschicken', soft: '2026-11-20');
        $this->task('Abnahme', hard: '2026-11-20');
        $this->task('Später fällig', hard: '2026-11-25');
        $this->task('Schon erledigt', hard: '2026-11-18', attributes: ['is_completed' => true, 'completed_at' => now()]);

        $carried = $this->review('2026-11-22 20:00')['past']['carried'];

        $this->assertSame([
            ['title' => 'Abnahme', 'date' => '2026-11-20', 'hard' => true],
            ['title' => 'Bewerbung abschicken', 'date' => '2026-11-20', 'hard' => false],
        ], $carried);
    }

    public function test_next_week_shows_room_against_planned_work_and_pause_days(): void
    {
        SchedulePause::factory()->for($this->user)->create(['date' => '2026-11-25']);
        $this->task('Bericht', hard: '2026-11-26', attributes: ['size' => Size::Long, 'duration_minutes' => 75]);
        $this->task('Übungsblatt', soft: '2026-11-27');

        $next = $this->review('2026-11-22 20:00')['next'];

        $this->assertSame([4, 4, 0, 4, 4, 0, 0], array_column($next['days'], 'capacity'));
        $this->assertSame([false, false, true, false, false, false, false], array_column($next['days'], 'paused'));
        $this->assertSame(16, $next['capacity']);
        $this->assertGreaterThan(0, $next['load']);
        $this->assertTrue($next['hasBlocks']);
        $this->assertNull($next['tight']);
        $this->assertSame([
            ['title' => 'Bericht', 'date' => '2026-11-26', 'hard' => true],
            ['title' => 'Übungsblatt', 'date' => '2026-11-27', 'hard' => false],
        ], $next['due']);
    }

    public function test_an_overloaded_day_next_week_is_named_with_the_sessions_that_do_not_fit(): void
    {
        $this->task('Bericht', hard: '2026-11-24', attributes: ['size' => Size::Block, 'duration_minutes' => 400]);

        $next = $this->review('2026-11-22 20:00')['next'];

        $this->assertSame('2026-11-24', $next['tight']['date']);
        $this->assertGreaterThanOrEqual(1, $next['tight']['over']);
        $this->assertSame($next['tight']['over'], $next['days'][1]['over']);
    }

    public function test_without_any_work_blocks_there_is_no_room_to_show(): void
    {
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);

        $next = $this->review('2026-11-22 20:00')['next'];

        $this->assertFalse($next['hasBlocks']);
        $this->assertSame(0, $next['capacity']);
    }

    private function task(string $title, ?string $hard = null, ?string $soft = null, array $attributes = []): Task
    {
        return Task::factory()->for($this->user)->create([
            'list' => 'tasks', 'title' => $title, 'hard_deadline' => $hard, 'soft_deadline' => $soft, ...$attributes,
        ]);
    }
}
