<?php

namespace Tests\Feature\Refactor;

use App\Models\Task;
use App\Models\User;
use App\Services\Planning\SoftSlips;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A7 on real rows (G2): the slip rule, applied by the hourly planning checks. */
class SoftSlipTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
    }

    private function at(string $dateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, 'Europe/Zurich');
    }

    public function test_first_miss_rolls_over_second_miss_goes_to_later(): void
    {
        $task = Task::factory()->for($this->user)->create(['list' => 'tasks', 'soft_deadline' => '2026-11-08']);
        $slips = new SoftSlips;

        $this->assertSame(['rolled' => 1, 'later' => 0, 'dropped' => 0], $slips->apply($this->user, $this->at('2026-11-09 07:00')));
        $task->refresh();
        $this->assertSame('2026-11-15', $task->soft_deadline->toDateString());
        $this->assertSame('2026-11-15', $task->due_date->toDateString(), 'the old column stays mirrored');
        $this->assertSame(1, $task->soft_slips);

        $this->assertSame(['rolled' => 0, 'later' => 0, 'dropped' => 0], $slips->apply($this->user, $this->at('2026-11-09 08:00')), 'idempotent');

        $slips->apply($this->user, $this->at('2026-11-16 07:00'));
        $task->refresh();
        $this->assertSame('later', $task->list);
        $this->assertSame('2026-11-15', $task->soft_deadline->toDateString(), 'kept as a record, nothing lost');
        $this->assertFalse($task->is_completed);
    }

    public function test_a_task_with_a_hard_deadline_keeps_its_place_and_loses_only_the_soft_date(): void
    {
        $task = Task::factory()->for($this->user)->create([
            'list' => 'tasks', 'soft_deadline' => '2026-11-08', 'hard_deadline' => '2026-11-30', 'soft_slips' => 1,
        ]);

        (new SoftSlips)->apply($this->user, $this->at('2026-11-09 07:00'));

        $task->refresh();
        $this->assertSame('tasks', $task->list);
        $this->assertNull($task->soft_deadline);
        $this->assertNull($task->due_date);
        $this->assertSame('2026-11-30', $task->hard_deadline->toDateString());
    }

    public function test_setting_a_new_soft_date_starts_the_count_over(): void
    {
        $task = Task::factory()->for($this->user)->create(['list' => 'tasks', 'soft_deadline' => '2026-11-15', 'soft_slips' => 1]);

        $task->update(['soft_deadline' => '2026-11-20']);

        $this->assertSame(0, $task->fresh()->soft_slips);
    }

    public function test_taking_a_task_back_out_of_later_starts_the_count_over(): void
    {
        $task = Task::factory()->for($this->user)->create(['list' => 'later', 'soft_deadline' => '2026-11-01', 'soft_slips' => 2]);

        $task->update(['list' => 'tasks']);
        (new SoftSlips)->apply($this->user, $this->at('2026-11-09 07:00'));

        $task->refresh();
        $this->assertSame('tasks', $task->list, 'one more roll-over, not straight back to Later');
        $this->assertSame(1, $task->soft_slips);
        $this->assertSame('2026-11-10', $task->soft_deadline->toDateString());
    }

    public function test_inbox_later_completed_and_other_users_tasks_are_left_alone(): void
    {
        $inbox = Task::factory()->for($this->user)->create(['list' => 'inbox', 'soft_deadline' => '2026-11-01']);
        $later = Task::factory()->for($this->user)->create(['list' => 'later', 'soft_deadline' => '2026-11-01']);
        $done = Task::factory()->for($this->user)->create(['list' => 'tasks', 'soft_deadline' => '2026-11-01', 'is_completed' => true]);
        $foreign = Task::factory()->create(['list' => 'tasks', 'soft_deadline' => '2026-11-01']);

        (new SoftSlips)->apply($this->user, $this->at('2026-11-09 07:00'));

        foreach ([$inbox, $later, $done, $foreign] as $task) {
            $this->assertSame('2026-11-01', $task->fresh()->soft_deadline->toDateString());
            $this->assertSame(0, $task->fresh()->soft_slips);
        }
    }

    public function test_the_hourly_command_applies_the_rule_for_every_user_with_open_work(): void
    {
        $task = Task::factory()->for($this->user)->create(['list' => 'tasks', 'soft_deadline' => now()->subDays(3)->toDateString()]);
        User::factory()->create(); // no tasks: skipped

        $this->artisan('app:run-planning-checks')
            ->expectsOutputToContain('Checked 1 user(s): 1 soft date(s) rolled over')
            ->assertSuccessful();

        $this->assertSame(1, $task->fresh()->soft_slips);
    }
}
