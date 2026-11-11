<?php

namespace Tests\Feature\Refactor;

use App\Models\EventCategory;
use App\Models\EventTemplate;
use App\Models\ScheduleEvent;
use App\Models\SchedulePause;
use App\Models\User;
use App\Planning\Block;
use App\Services\Planning\WeekPlanReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The read side of the week plan (steps 1.2 / 1.3, R4, risk C3): the old
 * tables read into the pure engine with the old app's per-date rules, and
 * without materialising a single row.
 */
class WeekPlanReaderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private EventCategory $deepWork;

    private EventTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
        $this->deepWork = EventCategory::factory()->for($this->user)->pomodoro()->create();
        $this->template = EventTemplate::factory()->for($this->user)->create([
            'category_id' => $this->deepWork->id, 'is_recurring' => true, 'recurrence' => '1,2,3,4,5',
            'default_start' => '19:30', 'duration' => 120,
        ]);
    }

    /** @return list<string> "HH:MM–HH:MM" per block on that date */
    private function times(string $date): array
    {
        $blocks = (new WeekPlanReader)->blocks($this->user, $date, $date)[$date];

        return array_map(fn (Block $b) => $b->start->format('H:i').'–'.$b->end->format('H:i'), $blocks);
    }

    public function test_a_template_appears_on_its_weekdays_without_writing_rows(): void
    {
        $this->assertSame(['19:30–21:30'], $this->times('2026-11-09'));
        $this->assertSame([], $this->times('2026-11-14'), 'Saturday');
        $this->assertDatabaseCount('schedule_events', 0);
    }

    public function test_an_edited_occurrence_changes_that_date_only(): void
    {
        ScheduleEvent::factory()->for($this->user)->create([
            'template_id' => $this->template->id, 'category_id' => $this->deepWork->id,
            'date' => '2026-11-10', 'start_time' => '20:00', 'end_time' => '21:00',
        ]);

        $this->assertSame(['20:00–21:00'], $this->times('2026-11-10'));
        $this->assertSame(['19:30–21:30'], $this->times('2026-11-11'));
    }

    public function test_a_tombstone_hides_the_occurrence(): void
    {
        ScheduleEvent::factory()->for($this->user)->cancelled()->create([
            'template_id' => $this->template->id, 'category_id' => $this->deepWork->id, 'date' => '2026-11-10',
        ]);

        $this->assertSame([], $this->times('2026-11-10'));
    }

    public function test_a_pause_hides_template_blocks_but_not_a_termin(): void
    {
        SchedulePause::factory()->for($this->user)->create(['date' => '2026-11-10']);
        ScheduleEvent::factory()->for($this->user)->create([
            'date' => '2026-11-10', 'start_time' => '10:00', 'end_time' => '11:00', 'title' => 'Zahnarzt',
        ]);

        $this->assertSame(['10:00–11:00'], $this->times('2026-11-10'));
    }

    public function test_an_occurrence_given_another_category_this_week_uses_that_category(): void
    {
        $other = EventCategory::factory()->for($this->user)->create();
        ScheduleEvent::factory()->for($this->user)->create([
            'template_id' => $this->template->id, 'category_id' => $other->id,
            'date' => '2026-11-10', 'start_time' => '19:30', 'end_time' => '21:30',
        ]);

        $blocks = (new WeekPlanReader)->blocks($this->user, '2026-11-10', '2026-11-10')['2026-11-10'];

        $this->assertCount(1, $blocks);
        $this->assertSame($other->id, $blocks[0]->categoryId);
    }

    public function test_capacity_is_counted_in_sessions_of_pomodoro_blocks(): void
    {
        $slots = (new WeekPlanReader)->slots($this->user, '2026-11-09', '2026-11-15');

        $this->assertSame([4, 4, 4, 4, 4, 0, 0], array_values(array_map('count', $slots)));
    }

    public function test_blocks_keep_their_wall_clock_time_across_the_dst_change(): void
    {
        $blocks = (new WeekPlanReader)->blocks($this->user, '2026-10-23', '2026-10-26');

        $this->assertSame('19:30', $blocks['2026-10-23'][0]->start->format('H:i'));
        $this->assertSame('+02:00', $blocks['2026-10-23'][0]->start->format('P'), 'summer time');
        $this->assertSame('19:30', $blocks['2026-10-26'][0]->start->format('H:i'));
        $this->assertSame('+01:00', $blocks['2026-10-26'][0]->start->format('P'), 'winter time');
    }

    public function test_another_users_plan_is_not_read(): void
    {
        $stranger = User::factory()->create();

        $this->assertSame([], (new WeekPlanReader)->blocks($stranger, '2026-11-09', '2026-11-09')['2026-11-09']);
        $this->assertSame([], (new WeekPlanReader)->categories($stranger));
    }
}
