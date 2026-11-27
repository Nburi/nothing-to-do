<?php

namespace Tests\Feature\Refactor;

use App\Livewire\TaskBoard;
use App\Mcp\McpAbility;
use App\Mcp\McpServer;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\Planning\BoardOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Api parity, part 1: the old entry points (REST `tasks/reorder`, MCP `set_task_order`, the old
 * board's drops) keep writing `sort_order` and now move `board_position` too, the shared order
 * of step 4.8. Like the new board they only swap places the tasks already hold.
 */
class SharedOrderEntryPointsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @return array<string, Task> */
    private function three(): array
    {
        return [
            'a' => Task::factory()->for($this->user)->create(['list' => 'tasks', 'title' => 'A']),
            'b' => Task::factory()->for($this->user)->create(['list' => 'tasks', 'title' => 'B']),
            'c' => Task::factory()->for($this->user)->create(['list' => 'tasks', 'title' => 'C']),
        ];
    }

    private function position(Task $t): ?int
    {
        return $t->fresh()->board_position;
    }

    public function test_place_tasks_swaps_the_held_positions_and_leaves_others_alone(): void
    {
        ['a' => $a, 'b' => $b, 'c' => $c] = $this->three();
        [$pa, $pb, $pc] = [$this->position($a), $this->position($b), $this->position($c)];

        $changed = app(BoardOrder::class)->placeTasks($this->user, [$c->id, $a->id]);

        $this->assertSame(2, $changed);
        $this->assertSame($pa, $this->position($c));
        $this->assertSame($pc, $this->position($a));
        $this->assertSame($pb, $this->position($b));
    }

    public function test_place_tasks_skips_foreign_finished_and_step_tasks_and_duplicates(): void
    {
        ['a' => $a, 'b' => $b] = $this->three();
        $foreign = Task::factory()->for(User::factory()->create())->create(['list' => 'tasks']);
        $done = Task::factory()->for($this->user)->create(['list' => 'tasks', 'is_completed' => true]);
        $project = new Project(['name' => 'P']);
        $project->user_id = $this->user->id;
        $project->save();
        $step = Task::forceCreate(['user_id' => $this->user->id, 'title' => 'Step', 'list' => 'tasks', 'project_id' => $project->id]);
        $stepBefore = $step->fresh()->board_position;
        $foreignBefore = $foreign->fresh()->board_position;
        [$pa, $pb] = [$this->position($a), $this->position($b)];

        app(BoardOrder::class)->placeTasks($this->user, [$foreign->id, $done->id, $step->id, $b->id, $b->id, $a->id, 999999]);

        $this->assertSame($pa, $this->position($b));
        $this->assertSame($pb, $this->position($a));
        $this->assertSame($foreignBefore, $this->position($foreign));
        $this->assertSame($stepBefore, $this->position($step));
    }

    public function test_the_rest_reorder_moves_the_shared_order_and_keeps_sort_order(): void
    {
        ['a' => $a, 'b' => $b, 'c' => $c] = $this->three();
        [$pa, $pc] = [$this->position($a), $this->position($c)];
        Sanctum::actingAs($this->user);

        $this->postJson('/api/tasks/reorder', ['list' => 'tasks', 'ids' => [$c->id, $b->id, $a->id]])->assertOk();

        $this->assertSame(0, $c->fresh()->sort_order);
        $this->assertSame(2, $a->fresh()->sort_order);
        $this->assertSame($pa, $this->position($c));
        $this->assertSame($pc, $this->position($a));
    }

    public function test_set_task_order_moves_the_shared_order_and_skips_foreign_ids(): void
    {
        ['a' => $a, 'b' => $b, 'c' => $c] = $this->three();
        $foreign = Task::factory()->for(User::factory()->create())->create(['list' => 'tasks']);
        [$pa, $pb, $pc] = [$this->position($a), $this->position($b), $this->position($c)];
        $foreignBefore = $this->position($foreign);

        $result = app(McpServer::class)->call(
            $this->user,
            fn (string $ability) => in_array($ability, [McpAbility::READ, McpAbility::WRITE], true),
            'set_task_order',
            ['ids' => [$c->id, $foreign->id, $b->id, $a->id]],
        );

        $this->assertSame(3, $result['updated_count']);
        $this->assertSame($pa, $this->position($c));
        $this->assertSame($pb, $this->position($b));
        $this->assertSame($pc, $this->position($a));
        $this->assertSame($foreignBefore, $this->position($foreign));
    }

    public function test_the_old_board_drop_moves_the_shared_order(): void
    {
        ['a' => $a, 'b' => $b] = $this->three();
        [$pa, $pb] = [$this->position($a), $this->position($b)];

        Livewire::actingAs($this->user)->test(TaskBoard::class)
            ->call('reorder', 'tasks', false, [$b->id, $a->id]);

        $this->assertSame($pa, $this->position($b));
        $this->assertSame($pb, $this->position($a));
        $this->assertSame(0, $b->fresh()->sort_order);
    }

    public function test_the_other_old_board_concepts_move_the_shared_order_too(): void
    {
        ['a' => $a, 'b' => $b, 'c' => $c] = $this->three();
        [$pa, $pb, $pc] = [$this->position($a), $this->position($b), $this->position($c)];

        Livewire::actingAs($this->user)->test(TaskBoard::class)
            ->call('reorderSimple', false, [$b->id, $a->id]);
        $this->assertSame($pa, $this->position($b));
        $this->assertSame($pb, $this->position($a));

        Livewire::actingAs($this->user)->test(TaskBoard::class)
            ->call('reorderEisenhower', false, false, [$a->id, $b->id]);
        $this->assertSame($pa, $this->position($a));
        $this->assertSame($pb, $this->position($b));

        Livewire::actingAs($this->user)->test(TaskBoard::class)
            ->call('reorderKanban', 'backlog', [$c->id, $a->id]);
        $this->assertSame($pa, $this->position($c));
        $this->assertSame($pc, $this->position($a));
    }
}
