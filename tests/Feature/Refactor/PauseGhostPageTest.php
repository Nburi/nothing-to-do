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
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * B6 (step 4.1, R4): the blocks of a paused day show as pale ghosts in the week editor and one ghost can be
 * brought back for that day only. Ghosts are not work time. "Today" is Tuesday 17 November 2026, Zurich.
 */
class PauseGhostPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private EventTemplate $work;

    private EventTemplate $gym;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');
        $this->travelTo(CarbonImmutable::parse('2026-11-17 12:00', 'Europe/Zurich'));
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
        $deep = EventCategory::factory()->for($this->user)->pomodoro()->create(['name' => 'Deep work', 'color' => 'forest']);
        $training = EventCategory::factory()->for($this->user)->create(['name' => 'Training', 'color' => 'signal']);
        $editor = new WeekPlanEditor;
        $this->work = $editor->addRepeating($this->user, [1, 2, 3, 4, 5], '19:30', '21:30', $deep->id);
        $this->gym = $editor->addRepeating($this->user, [2, 4], '18:00', '19:00', $training->id);
        $this->actingAs($this->user);
        SchedulePause::pauseRange($this->user, Carbon::parse('2026-11-18'), Carbon::parse('2026-11-19'), 'Ferien');
    }

    /** @return list<string> "HH:MM–HH:MM title" per real block */
    private function day(string $date): array
    {
        return array_map(
            fn (Block $b) => $b->start->format('H:i').'–'.$b->end->format('H:i').' '.($b->title ?? ''),
            (new WeekPlanReader)->blocks($this->user, $date, $date)[$date],
        );
    }

    /** Sessions of the day: the number capacity and the pick engine work with. */
    private function sessions(string $date): int
    {
        return count((new WeekPlanReader)->slots($this->user, $date, $date)[$date]);
    }

    public function test_a_paused_day_shows_its_blocks_as_ghosts_and_counts_no_work_time(): void
    {
        $html = Livewire::test(Week::class)->html();

        // Wednesday 18.11. (Deep work) and Thursday 19.11. (Training, Deep work) are paused: three ghosts.
        $this->assertSame(3, substr_count($html, 'data-pause-ghost'));
        $this->assertStringContainsString('19:30–21:30 Deep work, pausiert. Für diesen Tag zurückholen', $html);
        $this->assertSame([], $this->day('2026-11-18'));
        $this->assertSame(0, $this->sessions('2026-11-18'));
        $this->assertCount(2, (new WeekPlanReader)->ghosts($this->user, '2026-11-16', '2026-11-22')['2026-11-19']);
    }

    public function test_a_ghost_is_pale_and_quiet(): void
    {
        $html = Livewire::test(Week::class)->html();

        preg_match('/<button[^>]*data-pause-ghost[^>]*>/', $html, $m);
        $this->assertNotEmpty($m);
        $tag = $m[0];
        $this->assertStringContainsString('color-mix(in srgb, var(--t-ink) 45%, var(--t-surface))', $tag);
        $this->assertStringNotContainsString('repeating-linear-gradient', $tag, 'no hatching on a ghost');
        $this->assertStringNotContainsString('animate-', $tag, 'no animation on a ghost');
        $this->assertStringNotContainsString('dashed', $tag);
    }

    public function test_bringing_a_ghost_back_makes_it_a_real_block_for_that_day_only(): void
    {
        Livewire::test(Week::class)
            ->call('restoreGhost', $this->work->id, '2026-11-18')
            ->assertSee('Deep work ist am Mittwoch 18.11. zurück.');

        $this->assertSame(['19:30–21:30 Deep work'], $this->day('2026-11-18'));
        $this->assertSame(4, $this->sessions('2026-11-18'));
        // The pause stays: the other paused day is untouched, the series is unchanged.
        $this->assertSame([], $this->day('2026-11-19'));
        $this->assertSame(2, SchedulePause::forUser($this->user)->count());
        $this->assertSame('19:30', $this->work->fresh()->default_start);
        $this->assertTrue(ScheduleEvent::forUser($this->user)->where('template_id', $this->work->id)->whereDate('date', '2026-11-18')->value('pause_exception'));
        // It is gone from the ghosts, so it cannot be brought back twice.
        $this->assertArrayNotHasKey('2026-11-18', (new WeekPlanReader)->ghosts($this->user, '2026-11-16', '2026-11-22'));
    }

    public function test_a_brought_back_ghost_keeps_its_one_week_change(): void
    {
        (new WeekPlanEditor)->changeOccurrence($this->user, $this->work->id, '2026-11-18', '20:00', '21:00', $this->work->category_id, \App\Planning\ChangeScope::ThisWeekOnly);

        Livewire::test(Week::class)->call('restoreGhost', $this->work->id, '2026-11-18');

        $this->assertSame(['20:00–21:00 Deep work'], $this->day('2026-11-18'));
        $this->assertSame(1, ScheduleEvent::forUser($this->user)->where('template_id', $this->work->id)->whereDate('date', '2026-11-18')->count());
    }

    public function test_undo_puts_the_ghost_back(): void
    {
        Livewire::test(Week::class)
            ->call('restoreGhost', $this->work->id, '2026-11-18')
            ->call('undoLast');

        $this->assertSame([], $this->day('2026-11-18'));
        $this->assertSame(1, count((new WeekPlanReader)->ghosts($this->user, '2026-11-18', '2026-11-18')['2026-11-18']));
        $this->assertSame(0, ScheduleEvent::forUser($this->user)->where('template_id', $this->work->id)->whereDate('date', '2026-11-18')->count());
    }

    public function test_the_form_of_a_brought_back_block_offers_the_way_back_to_a_ghost(): void
    {
        Livewire::test(Week::class)
            ->call('restoreGhost', $this->work->id, '2026-11-18')
            ->call('openBlock', 'occ', $this->work->id, '2026-11-18')
            ->assertSee('data-restore-note', false)
            ->call('restoreOccurrence');

        $this->assertSame([], $this->day('2026-11-18'));
        $this->assertArrayHasKey('2026-11-18', (new WeekPlanReader)->ghosts($this->user, '2026-11-18', '2026-11-18'));
    }

    public function test_switching_the_day_back_on_shows_a_brought_back_block_once(): void
    {
        Livewire::test(Week::class)
            ->call('restoreGhost', $this->work->id, '2026-11-18')
            ->call('togglePause', '2026-11-18');

        $this->assertSame(['19:30–21:30 Deep work'], $this->day('2026-11-18'));
    }

    public function test_only_a_paused_day_has_a_ghost_to_bring_back(): void
    {
        $page = Livewire::test(Week::class)->call('restoreGhost', $this->work->id, '2026-11-20');

        $this->assertSame(0, ScheduleEvent::forUser($this->user)->whereDate('date', '2026-11-20')->count());
        $page->assertSee('Diesen Block gibt es nicht mehr');
    }

    public function test_a_date_outside_the_shown_week_is_ignored(): void
    {
        Livewire::test(Week::class)->call('restoreGhost', $this->work->id, '2026-11-25');

        $this->assertSame(0, ScheduleEvent::forUser($this->user)->count());
    }

    public function test_a_foreign_template_cannot_be_brought_back(): void
    {
        $other = User::factory()->create();
        $theirs = (new WeekPlanEditor)->addRepeating($other, [3], '10:00', '11:00', null, 'Termin');

        $this->expectException(ModelNotFoundException::class);
        (new WeekPlanEditor)->restoreGhost($this->user, $theirs->id, '2026-11-18');
    }

    public function test_the_wochenplan_has_no_ghosts(): void
    {
        $this->assertStringNotContainsString('data-pause-ghost', Livewire::test(Week::class, ['view' => 'plan'])->html());
    }

    public function test_old_screens_keep_a_brought_back_block_visible_on_the_paused_day(): void
    {
        (new WeekPlanEditor)->restoreGhost($this->user, $this->work->id, '2026-11-18');

        $this->assertSame(1, ScheduleEvent::forUser($this->user)->visible()->whereDate('date', '2026-11-18')->count());
    }
}
