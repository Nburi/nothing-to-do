<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\Exceptions\McpUnknownToolException;
use App\Mcp\McpAbility;
use App\Mcp\McpServer;
use App\Models\Area;
use App\Models\Task;
use App\Models\User;
use App\Services\Projects\NoteEditor;
use App\Services\Projects\ProjectEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Steps 4.3 and 4.3b through MCP: projects, stages, steps, groups and note cards in the new model. */
class McpProjectsTest extends TestCase
{
    use RefreshDatabase;

    private McpServer $server;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = app(McpServer::class);
        $this->user = User::factory()->create();
    }

    /** @param  list<string>  $abilities */
    private function tool(string $tool, array $arguments = [], array $abilities = [McpAbility::READ, McpAbility::WRITE], ?User $as = null): array
    {
        return $this->server->call($as ?? $this->user, fn (string $a) => in_array($a, $abilities, true), $tool, $arguments);
    }

    private function editor(): ProjectEditor
    {
        return app(ProjectEditor::class);
    }

    // â”€â”€ Projects, stages, steps â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function test_a_project_is_built_up_and_read_back_in_full(): void
    {
        $project = $this->tool('create_project', ['name' => ' Website ']);
        $this->assertSame('Website', $project['name']);
        $this->assertSame([], $project['stages']);

        $first = $this->tool('add_project_stage', ['project_id' => $project['id'], 'name' => 'Entwurf', 'target_date' => '2026-12-01']);
        $second = $this->tool('add_project_stage', ['project_id' => $project['id'], 'name' => 'Bau']);
        $this->assertSame('2026-12-01', $first['target_date']);
        $this->assertNull($second['target_date']);

        $step = $this->tool('add_project_step', ['project_id' => $project['id'], 'title' => 'Wireframes', 'stage_id' => $first['id'], 'list' => 'todos']);
        $this->assertSame($project['id'], $step['project_id']);
        $this->assertSame($first['id'], $step['stage_id']);
        $this->assertSame('todos', $step['list']);

        // A step is a normal task: the task tools set its fields.
        $this->tool('update_task', ['id' => $step['id'], 'size' => 'long', 'first_step' => 'Block aufmachen', 'hard_deadline' => '2026-12-10']);

        $full = $this->tool('get_project', ['id' => $project['id']]);
        $this->assertSame('Entwurf', $full['current_stage']['name']);
        $this->assertSame(['done' => 0, 'total' => 1], $full['progress']);
        $this->assertSame(1, $full['stages'][0]['open']);
        $this->assertSame('long', $full['steps'][0]['size']);
        $this->assertSame('Block aufmachen', $full['steps'][0]['first_step']);

        $list = $this->tool('list_projects');
        $this->assertCount(1, $list['projects']);
        $this->assertSame('Wireframes', $list['projects'][0]['next_steps'][0]['title']);

        $renamed = $this->tool('update_project', ['id' => $project['id'], 'name' => 'Neue Website']);
        $this->assertSame('Neue Website', $renamed['name']);

        $stage = $this->tool('update_project_stage', ['id' => $second['id'], 'name' => 'Aufbau', 'target_date' => '2027-01-15']);
        $this->assertSame('Aufbau', $stage['name']);
        $this->assertSame('2027-01-15', $stage['target_date']);
        $this->assertNull($this->tool('update_project_stage', ['id' => $second['id'], 'target_date' => null])['target_date']);
    }

    public function test_removing_a_stage_needs_its_name_and_keeps_the_steps(): void
    {
        $project = $this->editor()->createProject($this->user, 'P');
        $stage = $this->editor()->addStage($project, 'Etappe', null);
        $step = $this->editor()->addProjectStep($this->user, $project, $stage, 'Schritt');

        try {
            $this->tool('remove_project_stage', ['id' => $stage->id, 'confirm_name' => 'falsch']);
            $this->fail('expected a refusal');
        } catch (McpToolExecutionException $e) {
            $this->assertStringContainsString('"Etappe"', $e->getMessage());
        }
        $this->assertDatabaseHas('project_stages', ['id' => $stage->id]);

        $this->assertSame(['removed' => true, 'id' => $stage->id], $this->tool('remove_project_stage', ['id' => $stage->id, 'confirm_name' => 'Etappe']));
        $this->assertDatabaseHas('tasks', ['id' => $step->id, 'stage_id' => null]);
    }

    public function test_place_step_and_waits_on_work_and_explain_refusals(): void
    {
        $project = $this->editor()->createProject($this->user, 'P');
        $one = $this->editor()->addStage($project, 'Eins', null);
        $two = $this->editor()->addStage($project, 'Zwei', null);
        $a = $this->editor()->addProjectStep($this->user, $project, $one, 'A');
        $b = $this->editor()->addProjectStep($this->user, $project, $two, 'B');
        $foreign = $this->editor()->addStage($this->editor()->createProject($this->user, 'Q'), 'Q1', null);

        $moved = $this->tool('place_step', ['task_id' => $a->id, 'stage_id' => $two->id, 'index' => 0]);
        $this->assertTrue($moved['move']['changed']);
        $this->assertSame($two->id, $moved['step']['stage_id']);
        $this->assertSame([$a->id, $b->id], Task::where('stage_id', $two->id)->orderBy('sort_order')->pluck('id')->all());
        $this->assertFalse($this->tool('place_step', ['task_id' => $a->id, 'stage_id' => $two->id, 'index' => 0])['move']['changed']);

        $this->assertSame($a->id, $this->tool('set_step_waits_on', ['task_id' => $b->id, 'waits_on_id' => $a->id])['waits_on_id']);
        $this->assertNull($this->tool('set_step_waits_on', ['task_id' => $b->id, 'waits_on_id' => null])['waits_on_id']);

        $this->tool('set_step_waits_on', ['task_id' => $b->id, 'waits_on_id' => $a->id]);
        $this->expectLoop(fn () => $this->tool('set_step_waits_on', ['task_id' => $a->id, 'waits_on_id' => $b->id]));
        $this->expectRefusal(fn () => $this->tool('place_step', ['task_id' => $a->id, 'stage_id' => $foreign->id]), 'another project');

        $a->update(['is_completed' => true]);
        $this->expectRefusal(fn () => $this->tool('place_step', ['task_id' => $a->id, 'stage_id' => null]), 'done');
    }

    // â”€â”€ Groups â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function test_a_group_is_created_with_steps_read_and_renamed(): void
    {
        $group = $this->tool('create_task_group', ['name' => 'Umzug', 'list' => 'todos', 'steps' => ['Kisten', 'Strom']]);
        $this->assertSame('group', $group['kind']);
        $this->assertSame(2, $group['open']);
        $this->assertSame('todos', $group['steps'][0]['list']);
        $this->assertSame($group['id'], $group['steps'][0]['group_id']);

        $step = $this->tool('add_group_step', ['group_id' => $group['id'], 'title' => 'Post']);
        $this->assertSame('todos', $step['list']);

        $this->assertSame('Umzug Mai', $this->tool('update_task_group', ['id' => $group['id'], 'name' => 'Umzug Mai'])['name']);
        $this->assertCount(3, $this->tool('get_task_group', ['id' => $group['id']])['steps']);
        $this->assertSame(1, $this->tool('list_task_groups')['task_groups'][0]['more']);
    }

    public function test_the_old_task_ids_argument_is_refused_with_an_explanation(): void
    {
        $task = Task::factory()->for($this->user)->tasks()->create();

        $this->expectRefusal(fn () => $this->tool('create_task_group', ['name' => 'X', 'task_ids' => [$task->id]]), 'task_ids');
        $this->assertDatabaseCount('task_groups', 0);
    }

    // â”€â”€ Notes â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function test_note_cards_are_added_changed_moved_and_deleted_with_confirmation(): void
    {
        $project = $this->editor()->createProject($this->user, 'Mein Projekt');
        $stage = $this->editor()->addStage($project, 'Eins', null);

        $note = $this->tool('add_note', ['project_id' => $project->id, 'body' => 'Idee #Logo']);
        $this->assertNull($note['stage_id']);
        $this->assertSame(['logo'], $note['tags']);

        $this->assertSame($stage->id, $this->tool('update_note', ['id' => $note['id'], 'stage_id' => $stage->id])['stage_id']);
        $this->assertSame(['neu'], $this->tool('update_note', ['id' => $note['id'], 'body' => 'Jetzt #neu'])['tags']);
        $this->assertNull($this->tool('update_note', ['id' => $note['id'], 'stage_id' => null])['stage_id']);
        $this->assertCount(1, $this->tool('get_project', ['id' => $project->id])['notes']);

        $this->expectRefusal(fn () => $this->tool('delete_note', ['id' => $note['id'], 'confirm_name' => 'x'], [McpAbility::READ, McpAbility::DELETE]), 'Mein Projekt');
        $this->assertDatabaseCount('notes', 1);

        $this->tool('delete_note', ['id' => $note['id'], 'confirm_name' => 'Mein Projekt'], [McpAbility::READ, McpAbility::DELETE]);
        $this->assertDatabaseCount('notes', 0);
    }

    public function test_exactly_one_owner_is_needed_and_a_group_note_has_no_stage(): void
    {
        $project = $this->editor()->createProject($this->user, 'P');
        $group = $this->editor()->createGroup($this->user, 'G', 'tasks');
        $stage = $this->editor()->addStage($project, 'S', null);

        foreach ([
            ['body' => 'x'],
            ['project_id' => $project->id, 'group_id' => $group->id, 'body' => 'x'],
        ] as $arguments) {
            try {
                $this->tool('add_note', $arguments);
                $this->fail('expected a validation error');
            } catch (ValidationException) {
                $this->assertDatabaseCount('notes', 0);
            }
        }

        $this->expectRefusal(fn () => $this->tool('add_note', ['group_id' => $group->id, 'body' => 'x', 'stage_id' => $stage->id]), 'stage');
        $this->assertDatabaseCount('notes', 0);
    }

    public function test_the_old_group_note_tool_names_still_work(): void
    {
        $group = $this->editor()->createGroup($this->user, 'Umzug', 'tasks');
        $other = $this->editor()->createGroup($this->user, 'Anderes', 'tasks');

        $note = $this->tool('add_group_note', ['group_id' => $group->id, 'content' => 'Checkliste']);
        $this->assertSame('Checkliste', $note['content']);
        $this->assertSame($group->id, $note['group_id']);

        $this->assertSame('Neu', $this->tool('update_group_note', ['group_id' => $group->id, 'note_id' => $note['id'], 'content' => 'Neu'])['content']);
        $this->expectRefusal(fn () => $this->tool('update_group_note', ['group_id' => $other->id, 'note_id' => $note['id'], 'content' => 'x']), 'No note');

        $this->expectRefusal(fn () => $this->tool('delete_group_note', ['group_id' => $group->id, 'note_id' => $note['id'], 'confirm_group_name' => 'nein'], [McpAbility::READ, McpAbility::DELETE]), 'Umzug');
        $deleted = $this->tool('delete_group_note', ['group_id' => $group->id, 'note_id' => $note['id'], 'confirm_group_name' => 'Umzug'], [McpAbility::READ, McpAbility::DELETE]);
        $this->assertSame(['deleted' => true, 'id' => $note['id'], 'group_id' => $group->id], $deleted);
    }

    // â”€â”€ Task tools: membership fields, filters, new fields on bulk tools â”€

    public function test_list_tasks_filters_by_project_group_stage_size_and_area(): void
    {
        $project = $this->editor()->createProject($this->user, 'P');
        $stage = $this->editor()->addStage($project, 'S', null);
        $group = $this->editor()->createGroup($this->user, 'G', 'tasks');
        $inProject = $this->editor()->addProjectStep($this->user, $project, $stage, 'Im Projekt');
        $inGroup = $this->editor()->addGroupStep($this->user, $group, 'In Gruppe');
        $loose = Task::factory()->for($this->user)->tasks()->create(['size' => 'block']);

        $ids = fn (array $filter) => collect($this->tool('list_tasks', $filter)['tasks'])->pluck('id')->all();

        $this->assertSame([$inProject->id], $ids(['project_id' => $project->id]));
        $this->assertSame([$inProject->id], $ids(['stage_id' => $stage->id]));
        $this->assertSame([$inGroup->id], $ids(['group_id' => $group->id]));
        $this->assertSame([$loose->id], $ids(['size' => 'block']));
        $this->assertSame([], $ids(['project_id' => $project->id, 'group_id' => $group->id]));
    }

    public function test_the_bulk_task_tools_carry_the_new_task_fields(): void
    {
        $area = Area::factory()->for($this->user)->create();

        $created = $this->tool('create_tasks', ['tasks' => [
            ['title' => 'A', 'size' => 'quick', 'area_id' => $area->id, 'is_important' => true, 'soft_deadline' => '2026-12-05', 'first_step' => 'Los'],
        ]])['tasks'][0];

        $this->assertSame('quick', $created['size']);
        $this->assertSame($area->id, $created['area_id']);
        $this->assertTrue($created['is_important']);
        $this->assertSame('2026-12-05', $created['soft_deadline']);
        $this->assertSame('Los', $created['first_step']);

        $updated = $this->tool('update_tasks', ['updates' => [['id' => $created['id'], 'size' => null, 'hard_deadline' => '2026-12-20', 'area_id' => null]]])['tasks'][0];
        $this->assertNull($updated['size']);
        $this->assertNull($updated['area_id']);
        $this->assertSame('2026-12-20', $updated['hard_deadline']);
    }

    public function test_set_task_order_still_numbers_the_given_tasks(): void
    {
        $a = Task::factory()->for($this->user)->tasks()->create();
        $b = Task::factory()->for($this->user)->tasks()->create();

        $result = $this->tool('set_task_order', ['ids' => [$b->id, $a->id]]);

        $this->assertSame([$b->id, $a->id], $result['updated_ids']);
        $this->assertSame(0, $b->fresh()->sort_order);
        $this->assertSame(1, $a->fresh()->sort_order);
    }

    // â”€â”€ Gating and strangers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function test_write_tools_are_hidden_without_the_write_ability(): void
    {
        $this->expectException(McpUnknownToolException::class);
        $this->tool('create_project', ['name' => 'x'], [McpAbility::READ]);
    }

    public function test_read_tools_work_with_a_read_only_token(): void
    {
        $project = $this->editor()->createProject($this->user, 'P');

        $this->assertSame('P', $this->tool('get_project', ['id' => $project->id], [McpAbility::READ])['name']);
        $this->assertCount(1, $this->tool('list_projects', [], [McpAbility::READ])['projects']);
        $this->assertSame([], $this->tool('list_task_groups', [], [McpAbility::READ])['task_groups']);
    }

    public function test_note_deletion_needs_the_delete_ability(): void
    {
        $this->expectException(McpUnknownToolException::class);
        $this->tool('delete_note', ['id' => 1, 'confirm_name' => 'x']);
    }

    public function test_every_tool_refuses_the_strangers_ids(): void
    {
        $stranger = User::factory()->create();
        $project = $this->editor()->createProject($stranger, 'Geheim');
        $stage = $this->editor()->addStage($project, 'S', null);
        $step = $this->editor()->addProjectStep($stranger, $project, $stage, 'Schritt');
        $group = $this->editor()->createGroup($stranger, 'Geheim', 'tasks');
        $note = app(NoteEditor::class)->add($stranger, $project, 'Karte');
        $mine = $this->editor()->addProjectStep($this->user, $this->editor()->createProject($this->user, 'Mein'), null, 'Meins');
        $all = [McpAbility::READ, McpAbility::WRITE, McpAbility::DELETE];

        $calls = [
            ['get_project', ['id' => $project->id]],
            ['update_project', ['id' => $project->id, 'name' => 'x']],
            ['add_project_stage', ['project_id' => $project->id, 'name' => 'x']],
            ['add_project_step', ['project_id' => $project->id, 'title' => 'x']],
            ['update_project_stage', ['id' => $stage->id, 'name' => 'x']],
            ['remove_project_stage', ['id' => $stage->id, 'confirm_name' => 'S']],
            ['get_task_group', ['id' => $group->id]],
            ['update_task_group', ['id' => $group->id, 'name' => 'x']],
            ['add_group_step', ['group_id' => $group->id, 'title' => 'x']],
            ['place_step', ['task_id' => $step->id, 'stage_id' => null]],
            ['place_step', ['task_id' => $mine->id, 'stage_id' => $stage->id]],
            ['set_step_waits_on', ['task_id' => $step->id, 'waits_on_id' => null]],
            ['set_step_waits_on', ['task_id' => $mine->id, 'waits_on_id' => $step->id]],
            ['add_note', ['project_id' => $project->id, 'body' => 'x']],
            ['add_note', ['group_id' => $group->id, 'body' => 'x']],
            ['update_note', ['id' => $note->id, 'body' => 'x']],
            ['delete_note', ['id' => $note->id, 'confirm_name' => 'Geheim']],
            ['add_group_note', ['group_id' => $group->id, 'content' => 'x']],
        ];

        foreach ($calls as [$tool, $arguments]) {
            $this->expectRefusal(fn () => $this->tool($tool, $arguments, $all), 'No ', "{$tool} must refuse a stranger's id");
        }

        $this->assertSame('Geheim', $project->fresh()->name);
        $this->assertDatabaseCount('notes', 1);
        $this->assertDatabaseHas('project_stages', ['id' => $stage->id]);
        $this->assertCount(1, $this->tool('list_projects', [], $all)['projects']);
        $this->assertSame('Mein', $this->tool('list_projects', [], $all)['projects'][0]['name']);
    }

    // â”€â”€ helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    private function expectRefusal(callable $call, string $contains, string $message = ''): void
    {
        try {
            $call();
        } catch (McpToolExecutionException $e) {
            $this->assertStringContainsString($contains, $e->getMessage(), $message);

            return;
        }

        $this->fail($message !== '' ? $message : 'Expected a refusal containing "'.$contains.'".');
    }

    private function expectLoop(callable $call): void
    {
        $this->expectRefusal($call, 'wait on each other');
    }
}
