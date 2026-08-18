<?php

namespace Tests\Unit;

use App\Models\SchedulePause;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SchedulePauseTest extends TestCase
{
    use RefreshDatabase;

    public function test_pause_range_inserts_one_row_per_date(): void
    {
        $user = User::factory()->create();

        SchedulePause::pauseRange($user, Carbon::parse('2026-07-20'), Carbon::parse('2026-07-22'), 'Sportferien');

        $this->assertSame(3, SchedulePause::forUser($user)->count());
        $this->assertDatabaseHas('schedule_pauses', ['user_id' => $user->id, 'date' => '2026-07-20', 'note' => 'Sportferien']);
        $this->assertDatabaseHas('schedule_pauses', ['user_id' => $user->id, 'date' => '2026-07-21', 'note' => 'Sportferien']);
        $this->assertDatabaseHas('schedule_pauses', ['user_id' => $user->id, 'date' => '2026-07-22', 'note' => 'Sportferien']);
    }

    public function test_pause_range_is_idempotent_when_it_overlaps_an_existing_pause(): void
    {
        $user = User::factory()->create();

        SchedulePause::pauseRange($user, Carbon::parse('2026-07-20'), Carbon::parse('2026-07-21'), null);
        SchedulePause::pauseRange($user, Carbon::parse('2026-07-21'), Carbon::parse('2026-07-22'), null);

        // The overlapping day (07-21) is skipped, not duplicated.
        $this->assertSame(3, SchedulePause::forUser($user)->count());
    }

    public function test_collapse_to_ranges_groups_consecutive_dates(): void
    {
        $user = User::factory()->create();
        SchedulePause::pauseRange($user, Carbon::parse('2026-07-20'), Carbon::parse('2026-07-24'), 'Sportferien');

        $ranges = SchedulePause::collapseToRanges(SchedulePause::forUser($user)->ordered()->get());

        $this->assertCount(1, $ranges);
        $this->assertSame('2026-07-20', $ranges[0]['start']->toDateString());
        $this->assertSame('2026-07-24', $ranges[0]['end']->toDateString());
        $this->assertSame('Sportferien', $ranges[0]['note']);
    }

    public function test_collapse_to_ranges_splits_on_a_gap(): void
    {
        $user = User::factory()->create();
        SchedulePause::pauseRange($user, Carbon::parse('2026-07-20'), Carbon::parse('2026-07-21'), null);
        // A day switched back on inside a longer pause leaves a gap — must split into two ranges.
        SchedulePause::pauseRange($user, Carbon::parse('2026-07-23'), Carbon::parse('2026-07-24'), null);

        $ranges = SchedulePause::collapseToRanges(SchedulePause::forUser($user)->ordered()->get());

        $this->assertCount(2, $ranges);
        $this->assertSame('2026-07-20', $ranges[0]['start']->toDateString());
        $this->assertSame('2026-07-21', $ranges[0]['end']->toDateString());
        $this->assertSame('2026-07-23', $ranges[1]['start']->toDateString());
        $this->assertSame('2026-07-24', $ranges[1]['end']->toDateString());
    }

    public function test_pauses_are_scoped_per_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        SchedulePause::pauseRange($other, Carbon::parse('2026-07-20'), Carbon::parse('2026-07-20'), null);

        $this->assertSame(0, SchedulePause::forUser($user)->count());
        $this->assertSame(1, SchedulePause::forUser($other)->count());
    }
}
