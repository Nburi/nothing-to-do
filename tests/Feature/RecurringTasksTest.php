<?php

namespace Tests\Feature;

use App\Livewire\TaskBoard;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class RecurringTasksTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Wednesday 2026-09-23, noon UTC, user offset 0. */
    private function user(): User
    {
        Carbon::setTestNow('2026-09-23 12:00:00');

        return User::factory()->create(['timezone_offset' => 0, 'timezone_auto_dst' => false]);
    }

    private function repeating(User $user, string $rule, array $attributes = []): Task
    {
        return Task::factory()->for($user)->todos()->create($attributes + ['repeat_rule' => $rule]);
    }

    // ── nextOccurrenceDate ───────────────────────────────────────────

    public function test_a_dateless_daily_task_comes_back_tomorrow(): void
    {
        $user = $this->user();
        $task = $this->repeating($user, 'daily');

        $this->assertSame('2026-09-24', $task->nextOccurrenceDate($user)->toDateString());
    }

    public function test_a_weekly_task_counts_from_its_own_date_not_from_the_day_it_was_finished(): void
    {
        $user = $this->user();
        // Due next Friday (25th), finished early on Wednesday: still "next Friday" after that.
        $task = $this->repeating($user, 'weekly', ['due_date' => '2026-09-25']);

        $this->assertSame('2026-10-02', $task->nextOccurrenceDate($user)->toDateString());
    }

    public function test_a_neglected_task_skips_past_today_instead_of_coming_back_overdue(): void
    {
        $user = $this->user();
        $task = $this->repeating($user, 'weekly', ['due_date' => '2026-08-26']); // four weeks ago, a Wednesday

        // 26.8 + 7k: 2.9, 9.9, 16.9, 23.9 (== today, not strictly after), so 30.9.
        $this->assertSame('2026-09-30', $task->nextOccurrenceDate($user)->toDateString());
    }

    public function test_weekdays_skip_the_weekend(): void
    {
        $user = $this->user();
        $friday = $this->repeating($user, 'weekdays', ['due_date' => '2026-09-25']);
        $saturday = $this->repeating($user, 'weekdays', ['due_date' => '2026-09-26']);

        $this->assertSame('2026-09-28', $friday->nextOccurrenceDate($user)->toDateString()); // Monday
        $this->assertSame('2026-09-28', $saturday->nextOccurrenceDate($user)->toDateString());
    }

    public function test_monthly_does_not_overflow_short_months(): void
    {
        $user = $this->user();
        $task = $this->repeating($user, 'monthly', ['due_date' => '2026-10-31']);

        $this->assertSame('2026-11-30', $task->nextOccurrenceDate($user)->toDateString());
    }

    // ── completing / un-completing ───────────────────────────────────

    public function test_completing_a_repeating_task_creates_the_next_occurrence(): void
    {
        $user = $this->user();
        $task = $this->repeating($user, 'daily', [
            'title' => 'Wäsche',
            'is_important' => true,
            'notes' => 'Dunkle zuerst',
            'duration_minutes' => 30,
        ]);

        Livewire::actingAs($user)->test(TaskBoard::class)->call('toggleComplete', $task->id);

        $next = Task::query()->where('repeated_from_id', $task->id)->sole();

        $this->assertTrue($task->fresh()->is_completed);
        $this->assertFalse($next->is_completed);
        $this->assertSame('Wäsche', $next->title);
        $this->assertSame('todos', $next->list);
        $this->assertTrue($next->is_important);
        $this->assertSame('Dunkle zuerst', $next->notes);
        $this->assertSame(30, $next->duration_minutes);
        $this->assertSame('daily', $next->repeat_rule);
        $this->assertFalse($next->is_today);
        // Nothing carried a date, so the occurrence date becomes the soft due date.
        $this->assertSame('2026-09-24', $next->due_date->toDateString());
    }

    public function test_both_dates_keep_their_distance_when_shifted(): void
    {
        $user = $this->user();
        $task = $this->repeating($user, 'weekly', ['due_date' => '2026-09-25', 'deadline' => '2026-09-28']);

        Livewire::actingAs($user)->test(TaskBoard::class)->call('toggleComplete', $task->id);

        $next = Task::query()->where('repeated_from_id', $task->id)->sole();
        $this->assertSame('2026-10-02', $next->due_date->toDateString());
        $this->assertSame('2026-10-05', $next->deadline->toDateString());
    }

    public function test_a_task_without_a_rule_spawns_nothing(): void
    {
        $user = $this->user();
        $task = Task::factory()->for($user)->todos()->create();

        Livewire::actingAs($user)->test(TaskBoard::class)->call('toggleComplete', $task->id);

        $this->assertSame(1, Task::query()->count());
    }

    public function test_the_successor_carries_project_and_group(): void
    {
        $user = $this->user();
        $project = \App\Models\Project::factory()->for($user)->create();
        $task = Task::factory()->for($user)->create([
            'list' => 'projects', 'project_id' => $project->id, 'repeat_rule' => 'weekly',
        ]);

        Livewire::actingAs($user)->test(TaskBoard::class)->call('toggleComplete', $task->id);

        $next = Task::query()->where('repeated_from_id', $task->id)->sole();
        $this->assertSame($project->id, $next->project_id);
        $this->assertSame('projects', $next->list);
    }

    public function test_completing_twice_never_creates_two_successors(): void
    {
        $user = $this->user();
        $task = $this->repeating($user, 'daily');

        $task->syncRepeat($user, true);
        $task->syncRepeat($user, true);

        $this->assertSame(1, Task::query()->where('repeated_from_id', $task->id)->count());
    }

    public function test_undoing_the_completion_removes_the_untouched_successor(): void
    {
        $user = $this->user();
        $task = $this->repeating($user, 'daily');
        $component = Livewire::actingAs($user)->test(TaskBoard::class);

        $component->call('toggleComplete', $task->id);
        $this->assertSame(1, Task::query()->where('repeated_from_id', $task->id)->count());

        $component->call('toggleComplete', $task->id);

        $this->assertFalse($task->fresh()->is_completed);
        $this->assertSame(0, Task::query()->where('repeated_from_id', $task->id)->count());

        // ...and completing again gives exactly one successor again.
        $component->call('toggleComplete', $task->id);
        $this->assertSame(1, Task::query()->where('repeated_from_id', $task->id)->count());
    }

    public function test_undoing_keeps_a_successor_that_was_already_finished(): void
    {
        $user = $this->user();
        $task = $this->repeating($user, 'daily');
        $task->syncRepeat($user, true);
        $successor = Task::query()->where('repeated_from_id', $task->id)->sole();
        $successor->update(['is_completed' => true, 'completed_at' => now()]);

        $task->syncRepeat($user, false);

        $this->assertNotNull($successor->fresh());
    }

    public function test_deleting_the_original_keeps_its_successor(): void
    {
        $user = $this->user();
        $task = $this->repeating($user, 'daily');
        $task->syncRepeat($user, true);
        $successor = Task::query()->where('repeated_from_id', $task->id)->sole();

        $task->delete();

        $this->assertNull($successor->fresh()->repeated_from_id);
    }

    // ── edit sheet ───────────────────────────────────────────────────

    public function test_the_edit_sheet_saves_and_clears_the_rule(): void
    {
        $user = $this->user();
        $task = Task::factory()->for($user)->todos()->create(['title' => 'Pflanzen giessen']);

        $component = Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('startEdit', $task->id)
            ->assertSet('editRepeat', null)
            ->set('editRepeat', 'weekly')
            ->call('saveEdit')
            ->assertHasNoErrors();

        $this->assertSame('weekly', $task->fresh()->repeat_rule);

        $component->call('startEdit', $task->id)
            ->assertSet('editRepeat', 'weekly')
            ->set('editRepeat', null)
            ->call('saveEdit');

        $this->assertNull($task->fresh()->repeat_rule);
    }

    public function test_the_edit_sheet_rejects_an_unknown_rule(): void
    {
        $user = $this->user();
        $task = Task::factory()->for($user)->todos()->create();

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('startEdit', $task->id)
            ->set('editRepeat', 'yearly')
            ->call('saveEdit')
            ->assertHasErrors('editRepeat');

        $this->assertNull($task->fresh()->repeat_rule);
    }

    public function test_a_completed_repeating_card_shows_when_the_next_one_lands(): void
    {
        $user = $this->user();
        $task = $this->repeating($user, 'daily', ['title' => 'Zähne putzen']);

        Livewire::actingAs($user)->test(TaskBoard::class)
            ->call('toggleComplete', $task->id)
            ->assertSee('nächste: morgen');
    }

    // ── API ──────────────────────────────────────────────────────────

    public function test_the_api_sets_and_returns_the_rule_and_spawns_on_completion(): void
    {
        $user = $this->user();
        \Laravel\Sanctum\Sanctum::actingAs($user);

        $id = $this->postJson('/api/tasks', ['title' => 'Backup', 'list' => 'todos', 'repeat_rule' => 'weekly'])
            ->assertCreated()
            ->assertJsonPath('data.repeat_rule', 'weekly')
            ->json('data.id');

        $this->patchJson("/api/tasks/{$id}", ['is_completed' => true])->assertOk();

        $this->assertSame(1, Task::query()->where('repeated_from_id', $id)->count());
    }

    public function test_the_api_rejects_an_unknown_rule(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->user());

        $this->postJson('/api/tasks', ['title' => 'X', 'repeat_rule' => 'hourly'])
            ->assertUnprocessable();
    }
}
