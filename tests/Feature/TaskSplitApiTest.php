<?php

namespace Tests\Feature;

use App\Mcp\McpAbility;
use App\Mcp\McpServer;
use App\Mcp\Exceptions\McpToolExecutionException;
use App\Models\Task;
use App\Models\TaskSession;
use App\Models\User;
use App\Services\DataExport;
use App\Services\DayPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** "Aufteilen" over the REST API, MCP and the data export. */
class TaskSplitApiTest extends TestCase
{
    use RefreshDatabase;

    private function plannerUser(): User
    {
        return User::factory()->create(['planner_enabled' => true]);
    }

    private function day(int $offset): string
    {
        return now()->addDays($offset)->toDateString();
    }

    private function mcp(User $user, string $tool, array $args = []): array
    {
        return app(McpServer::class)->call($user, fn (string $a) => in_array($a, [McpAbility::READ, McpAbility::WRITE, McpAbility::DELETE], true), $tool, $args);
    }

    // ── REST API ──────────────────────────────────────────────────────

    public function test_put_split_with_explicit_dates_creates_parts_and_returns_them(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/tasks/{$task->id}/split", ['dates' => [$this->day(1), $this->day(3)], 'total_minutes' => 90]);

        $response->assertOk()->assertJsonCount(2, 'data.sessions')->assertJsonPath('data.sessions.0.minutes', 45)->assertJsonPath('data.sessions.0.done', false);
        $this->assertSame(2, TaskSession::where('task_id', $task->id)->count());
    }

    public function test_put_split_with_a_part_count_lets_the_app_pick_the_days(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        Sanctum::actingAs($user);

        $this->putJson("/api/tasks/{$task->id}/split", ['parts' => 3, 'total_minutes' => 90])->assertOk()->assertJsonCount(3, 'data.sessions');
    }

    public function test_put_split_needs_dates_or_parts_and_rejects_one_day(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        Sanctum::actingAs($user);

        $this->putJson("/api/tasks/{$task->id}/split", [])->assertStatus(422);
        $this->putJson("/api/tasks/{$task->id}/split", ['dates' => [$this->day(1)]])->assertStatus(422);
        $this->putJson("/api/tasks/{$task->id}/split", ['dates' => ['nonsense', 'x']])->assertStatus(422);
        $this->assertSame(0, TaskSession::count());
    }

    public function test_put_split_is_refused_without_the_planner_and_for_a_foreign_task(): void
    {
        $off = User::factory()->create(['planner_enabled' => false]);
        $offTask = Task::factory()->for($off)->tasks()->create();
        $other = $this->plannerUser();
        $theirs = Task::factory()->for($other)->tasks()->create();

        Sanctum::actingAs($off);
        $this->putJson("/api/tasks/{$offTask->id}/split", ['parts' => 2])->assertStatus(422);

        Sanctum::actingAs($this->plannerUser());
        $this->putJson("/api/tasks/{$theirs->id}/split", ['parts' => 2])->assertNotFound();
        $this->assertSame(0, TaskSession::count());
    }

    public function test_delete_split_removes_the_parts_and_a_foreign_task_is_a_404(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        DayPlanner::saveSplit($user, $task, [$this->day(1), $this->day(2)], 60);
        Sanctum::actingAs($this->plannerUser());
        $this->deleteJson("/api/tasks/{$task->id}/split")->assertNotFound();
        $this->assertSame(2, TaskSession::count());

        Sanctum::actingAs($user);
        $this->deleteJson("/api/tasks/{$task->id}/split")->assertNoContent();
        $this->assertSame(0, TaskSession::count());
    }

    public function test_patch_task_session_ticks_a_part_without_completing_the_task(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        DayPlanner::saveSplit($user, $task, [$this->day(1), $this->day(2)], 60);
        $part = TaskSession::where('task_id', $task->id)->orderBy('planned_date')->first();
        Sanctum::actingAs($user);

        $this->patchJson("/api/task-sessions/{$part->id}", ['done' => true])->assertOk()->assertJsonPath('data.sessions.0.done', true)->assertJsonPath('data.is_completed', false);
        $this->patchJson("/api/task-sessions/{$part->id}", ['done' => false])->assertOk()->assertJsonPath('data.sessions.0.done', false);
        $this->patchJson("/api/task-sessions/{$part->id}", [])->assertStatus(422);
    }

    public function test_patch_task_session_of_someone_else_is_a_404(): void
    {
        $other = $this->plannerUser();
        $task = Task::factory()->for($other)->tasks()->create();
        DayPlanner::saveSplit($other, $task, [$this->day(1), $this->day(2)], 60);
        $part = TaskSession::where('task_id', $task->id)->first();
        Sanctum::actingAs($this->plannerUser());

        $this->patchJson("/api/task-sessions/{$part->id}", ['done' => true])->assertNotFound();
        $this->assertNull($part->fresh()->done_at);
    }

    public function test_show_includes_the_parts_but_the_list_does_not(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        DayPlanner::saveSplit($user, $task, [$this->day(1), $this->day(2)], 60);
        Sanctum::actingAs($user);

        $this->getJson("/api/tasks/{$task->id}")->assertJsonCount(2, 'data.sessions');
        $this->assertArrayNotHasKey('sessions', $this->getJson('/api/tasks')->json('data.0'));
    }

    // ── MCP ───────────────────────────────────────────────────────────

    public function test_mcp_split_tools_exist_only_with_the_planner_on(): void
    {
        $on = $this->plannerUser();
        $off = User::factory()->create(['planner_enabled' => false]);
        $names = fn (User $u) => collect(app(McpServer::class)->availableTools($u, fn () => true))->map(fn ($t) => $t->name())->all();

        foreach (['split_task', 'unsplit_task', 'set_task_part_done'] as $tool) {
            $this->assertContains($tool, $names($on));
            $this->assertNotContains($tool, $names($off));
        }
    }

    public function test_mcp_split_task_with_dates_and_with_a_part_count(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();

        $result = $this->mcp($user, 'split_task', ['task_id' => $task->id, 'dates' => [$this->day(1), $this->day(2)], 'total_minutes' => 60]);
        $this->assertCount(2, $result['sessions']);

        $again = $this->mcp($user, 'split_task', ['task_id' => $task->id, 'parts' => 3, 'total_minutes' => 90]);
        $this->assertCount(3, $again['sessions']);
    }

    public function test_mcp_split_task_validates_its_arguments(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        $inbox = Task::factory()->for($user)->create(['list' => 'inbox']);

        foreach ([
            ['task_id' => $task->id],
            ['task_id' => $task->id, 'dates' => [$this->day(1), $this->day(2)], 'parts' => 2],
            ['task_id' => $task->id, 'dates' => [$this->day(1)]],
            ['task_id' => $inbox->id, 'parts' => 2],
            ['task_id' => 99999, 'parts' => 2],
        ] as $args) {
            try {
                $this->mcp($user, 'split_task', $args);
                $this->fail('expected a tool error for '.json_encode($args));
            } catch (McpToolExecutionException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, TaskSession::count());
    }

    public function test_mcp_set_task_part_done_and_unsplit_and_single_part_unplan(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        $this->mcp($user, 'split_task', ['task_id' => $task->id, 'dates' => [$this->day(1), $this->day(2), $this->day(3)], 'total_minutes' => 90]);
        $parts = TaskSession::where('task_id', $task->id)->orderBy('planned_date')->get();

        $ticked = $this->mcp($user, 'set_task_part_done', ['session_id' => $parts[0]->id]);
        $this->assertTrue($ticked['sessions'][0]['done']);
        $this->assertFalse($ticked['is_completed']);

        $this->mcp($user, 'unplan_task', ['session_id' => $parts[2]->id]);
        $this->assertSame(2, TaskSession::where('task_id', $task->id)->count());

        $this->mcp($user, 'plan_task', ['session_id' => $parts[1]->id, 'date' => $this->day(6)]);
        $this->assertSame($this->day(6), $parts[1]->fresh()->planned_date->toDateString());

        $this->mcp($user, 'unsplit_task', ['task_id' => $task->id]);
        $this->assertSame(0, TaskSession::count());
    }

    public function test_mcp_part_tools_refuse_someone_elses_part(): void
    {
        $user = $this->plannerUser();
        $other = $this->plannerUser();
        $task = Task::factory()->for($other)->tasks()->create();
        DayPlanner::saveSplit($other, $task, [$this->day(1), $this->day(2)], 60);
        $part = TaskSession::where('task_id', $task->id)->first();

        foreach (['set_task_part_done' => ['session_id' => $part->id], 'unplan_task' => ['session_id' => $part->id]] as $tool => $args) {
            try {
                $this->mcp($user, $tool, $args);
                $this->fail("{$tool} should refuse a foreign part");
            } catch (McpToolExecutionException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(2, TaskSession::count());
    }

    public function test_get_task_and_get_planner_expose_the_parts(): void
    {
        $user = $this->plannerUser();
        $task = Task::factory()->for($user)->tasks()->create();
        DayPlanner::saveSplit($user, $task, [$this->day(1), $this->day(2)], 60);

        $this->assertCount(2, $this->mcp($user, 'get_task', ['id' => $task->id])['sessions']);

        $planner = $this->mcp($user, 'get_planner');
        $chip = collect($planner['days'])->firstWhere('date', $this->day(1))['tasks'][0];
        $this->assertSame('session', $chip['type']);
        $this->assertSame($task->id, $chip['task_id']);
        $this->assertSame('Teil 1/2', $chip['part']);
    }

    // ── Export ────────────────────────────────────────────────────────

    public function test_the_data_export_lists_only_my_parts(): void
    {
        $user = $this->plannerUser();
        $other = $this->plannerUser();
        $mine = Task::factory()->for($user)->tasks()->create();
        $theirs = Task::factory()->for($other)->tasks()->create();
        DayPlanner::saveSplit($user, $mine, [$this->day(1), $this->day(2)], 60);
        DayPlanner::saveSplit($other, $theirs, [$this->day(1), $this->day(2)], 60);

        $parts = DataExport::for($user)['task_parts'];

        $this->assertCount(2, $parts);
        $this->assertSame([$mine->id], collect($parts)->pluck('task_id')->unique()->values()->all());
    }
}
