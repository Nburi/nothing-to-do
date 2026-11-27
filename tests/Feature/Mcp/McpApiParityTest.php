<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpServer;
use App\Models\EventCategory;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\User;
use App\Services\Projects\ProjectEditor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Api parity, the MCP side of ApiParityTest: move_task, add_more_time, delete_project,
 * dissolve_task_group, set_task_bundle, bring_to_top, set_task_order with refs and the stage
 * position of update_project_stage. Monday 9 November 2026, 12:00 in Zurich.
 */
class McpApiParityTest extends TestCase
{
    use RefreshDatabase;

    private McpServer $server;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-09 12:00', 'Europe/Zurich'));
        $this->server = app(McpServer::class);
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
    }

    /** @param  list<string>  $abilities */
    private function tool(string $tool, array $args, array $abilities = [McpAbility::READ, McpAbility::WRITE, McpAbility::DELETE], ?User $as = null): array
    {
        return $this->server->call($as ?? $this->user, fn (string $a) => in_array($a, $abilities, true), $tool, $args);
    }

    private function task(string $title, array $extra = [], ?User $owner = null): Task
    {
        return Task::forceCreate(array_merge(['user_id' => ($owner ?? $this->user)->id, 'title' => $title, 'list' => 'tasks'], $extra));
    }

    private function editor(): ProjectEditor
    {
        return app(ProjectEditor::class);
    }

    public function test_the_new_tools_are_listed_and_gated_by_ability(): void
    {
        $names = fn (array $abilities) => collect($this->server->availableTools($this->user, fn (string $a) => in_array($a, $abilities, true)))->map->name();

        $readOnly = $names([McpAbility::READ]);
        $readWrite = $names([McpAbility::READ, McpAbility::WRITE]);
        $all = $names([McpAbility::READ, McpAbility::WRITE, McpAbility::DELETE]);

        foreach (['move_task', 'add_more_time', 'set_task_bundle', 'bring_to_top'] as $tool) {
            $this->assertNotContains($tool, $readOnly);
            $this->assertContains($tool, $readWrite);
        }
        foreach (['delete_project', 'dissolve_task_group'] as $tool) {
            $this->assertNotContains($tool, $readWrite, "{$tool} needs the delete ability");
            $this->assertContains($tool, $all);
        }
    }

    public function test_move_task_moves_the_hard_or_the_soft_deadline(): void
    {
        $hard = $this->task('Hart', ['hard_deadline' => '2026-11-12']);
        $soft = $this->task('Weich', ['soft_deadline' => '2026-11-10']);

        $this->assertSame('2026-11-20', $this->tool('move_task', ['id' => $hard->id, 'date' => '2026-11-20'])['hard_deadline']);
        $this->assertSame('2026-11-17', $this->tool('move_task', ['id' => $soft->id])['soft_deadline']);
    }

    public function test_move_task_refuses_what_the_app_refuses_and_strangers(): void
    {
        $soft = $this->task('Weich', ['soft_deadline' => '2026-11-10']);
        $foreign = $this->task('Fremd', ['hard_deadline' => '2026-11-12'], User::factory()->create());

        try {
            $this->tool('move_task', ['id' => $soft->id, 'date' => '2026-11-20']);
            $this->fail('a hard move needs a hard deadline');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('moveDate', $e->errors());
        }
        $this->expectException(McpToolExecutionException::class);
        $this->tool('move_task', ['id' => $foreign->id, 'date' => '2026-11-20']);
    }

    public function test_add_more_time_adds_a_block_and_respects_the_rules(): void
    {
        $deep = EventCategory::factory()->for($this->user)->pomodoro()->create();

        $event = $this->tool('add_more_time', ['date' => '2026-11-11', 'start_time' => '19:00', 'end_time' => '20:00', 'category_id' => $deep->id]);
        $this->assertSame($deep->id, $event['category_id']);
        $this->assertSame('2026-11-11', $event['date']);

        $this->expectException(ValidationException::class);
        $this->tool('add_more_time', ['date' => '2026-12-30', 'start_time' => '19:00', 'end_time' => '20:00', 'category_id' => $deep->id]);
    }

    public function test_delete_project_needs_the_name_and_keeps_the_steps(): void
    {
        $project = $this->editor()->createProject($this->user, 'Umzug');
        $stage = $this->editor()->addStage($project, 'Planung', null);
        $step = $this->editor()->addProjectStep($this->user, $project, $stage, 'Kisten');

        try {
            $this->tool('delete_project', ['id' => $project->id, 'confirm_name' => 'umzug']);
            $this->fail('the name has to match exactly');
        } catch (McpToolExecutionException $e) {
            $this->assertStringContainsString('Umzug', $e->getMessage());
        }
        $this->assertNotNull(Project::find($project->id));

        $result = $this->tool('delete_project', ['id' => $project->id, 'confirm_name' => 'Umzug']);

        $this->assertTrue($result['deleted']);
        $this->assertSame(1, $result['steps_kept']);
        $this->assertSame(1, $result['stages_deleted']);
        $this->assertNull(Project::find($project->id));
        $this->assertNull($step->fresh()->project_id);
        $this->assertNull($step->fresh()->stage_id);
    }

    public function test_dissolve_task_group_keeps_the_tasks_and_ignores_strangers(): void
    {
        $group = $this->editor()->createGroup($this->user, 'Steuern', 'tasks');
        $step = $this->editor()->addGroupStep($this->user, $group, 'Belege');

        $stranger = User::factory()->create();
        try {
            $this->tool('dissolve_task_group', ['id' => $group->id, 'confirm_name' => 'Steuern'], [McpAbility::READ, McpAbility::WRITE, McpAbility::DELETE], $stranger);
            $this->fail('a stranger cannot dissolve it');
        } catch (McpToolExecutionException) {
            $this->assertNotNull(TaskGroup::find($group->id));
        }

        $result = $this->tool('dissolve_task_group', ['id' => $group->id, 'confirm_name' => 'Steuern']);
        $this->assertTrue($result['dissolved']);
        $this->assertSame(1, $result['tasks_kept']);
        $this->assertNull(TaskGroup::find($group->id));
        $this->assertNull($step->fresh()->group_id);
    }

    public function test_set_task_bundle_moves_a_task_in_and_out(): void
    {
        $project = $this->editor()->createProject($this->user, 'Umzug');
        $stage = $this->editor()->addStage($project, 'Planung', null);
        $task = $this->task('Kisten');

        $in = $this->tool('set_task_bundle', ['task_id' => $task->id, 'project_id' => $project->id, 'stage_id' => $stage->id]);
        $this->assertSame($project->id, $in['project_id']);
        $this->assertSame($stage->id, $in['stage_id']);

        $out = $this->tool('set_task_bundle', ['task_id' => $task->id, 'project_id' => null]);
        $this->assertNull($out['project_id']);
        $this->assertSame('tasks', $out['list']);
    }

    public function test_set_task_bundle_refuses_unclear_and_impossible_moves(): void
    {
        $project = $this->editor()->createProject($this->user, 'Umzug');
        $group = $this->editor()->createGroup($this->user, 'Steuern', 'tasks');
        $other = $this->editor()->createProject($this->user, 'Zweites');
        $task = $this->task('Kisten');
        $recurring = $this->task('Täglich', ['repeat_rule' => 'daily']);

        $refused = function (array $args) {
            try {
                $this->tool('set_task_bundle', $args);

                return false;
            } catch (McpToolExecutionException) {
                return true;
            }
        };

        $this->assertTrue($refused(['task_id' => $task->id]), 'needs project_id or group_id');
        $this->assertTrue($refused(['task_id' => $task->id, 'project_id' => $project->id, 'group_id' => $group->id]));
        $this->assertTrue($refused(['task_id' => $recurring->id, 'project_id' => $project->id]));
        $this->tool('set_task_bundle', ['task_id' => $task->id, 'project_id' => $project->id]);
        $this->assertTrue($refused(['task_id' => $task->id, 'project_id' => $other->id]), 'release it first');
        $this->assertSame($project->id, $task->fresh()->project_id);
    }

    public function test_bring_to_top_and_set_task_order_with_refs(): void
    {
        $project = $this->editor()->createProject($this->user, 'Umzug');
        $group = $this->editor()->createGroup($this->user, 'Steuern', 'tasks');
        $task = $this->task('Lose');
        [$pp, $pg, $pt] = [$project->fresh()->board_position, $group->fresh()->board_position, $task->fresh()->board_position];

        $result = $this->tool('set_task_order', ['refs' => ['task:'.$task->id, 'group:'.$group->id, 'project:'.$project->id]]);

        $this->assertSame(2, $result['board_changed_count']);
        $this->assertSame($pp, $task->fresh()->board_position);
        $this->assertSame($pt, $project->fresh()->board_position);
        $this->assertSame($pg, $group->fresh()->board_position);

        $this->tool('bring_to_top', ['ref' => 'group:'.$group->id]);
        $this->assertLessThan(min($pp, $pt, $pg), $group->fresh()->board_position);

        $this->expectException(McpToolExecutionException::class);
        $this->tool('bring_to_top', ['ref' => 'task:'.$this->task('Fremd', [], User::factory()->create())->id]);
    }

    public function test_set_task_order_needs_ids_or_refs_and_a_task_order_still_works(): void
    {
        $a = $this->task('A');
        $b = $this->task('B');
        [$pa, $pb] = [$a->fresh()->board_position, $b->fresh()->board_position];

        $result = $this->tool('set_task_order', ['ids' => [$b->id, $a->id]]);

        $this->assertSame(2, $result['updated_count']);
        $this->assertSame(0, $result['board_changed_count']);
        $this->assertSame($pa, $b->fresh()->board_position);
        $this->assertSame($pb, $a->fresh()->board_position);

        $this->expectException(ValidationException::class);
        $this->tool('set_task_order', []);
    }

    public function test_update_project_stage_moves_a_stage_with_position(): void
    {
        $project = $this->editor()->createProject($this->user, 'Umzug');
        $a = $this->editor()->addStage($project, 'A', null);
        $b = $this->editor()->addStage($project, 'B', null);

        $result = $this->tool('update_project_stage', ['id' => $b->id, 'position' => 0]);

        $this->assertTrue($result['move']['changed']);
        $this->assertSame([$b->id, $a->id], $project->stages()->pluck('id')->all());
        $this->assertArrayNotHasKey('move', $this->tool('update_project_stage', ['id' => $a->id, 'name' => 'A2']));
    }
}
