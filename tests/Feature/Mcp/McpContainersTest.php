<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\Exceptions\McpUnknownToolException;
use App\Mcp\McpAbility;
use App\Mcp\McpServer;
use App\Models\AgendaEntry;
use App\Models\AgendaSpace;
use App\Models\GroupNote;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Projects, task groups (+ notes) and the Agenda: the write side the MCP used to lack. */
class McpContainersTest extends TestCase
{
    use RefreshDatabase;

    private McpServer $server;

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = app(McpServer::class);
    }

    private function can(array $abilities): callable
    {
        return fn (string $a) => in_array($a, $abilities, true);
    }

    private function write(): callable
    {
        return $this->can([McpAbility::READ, McpAbility::WRITE]);
    }

    private function full(): callable
    {
        return $this->can([McpAbility::READ, McpAbility::WRITE, McpAbility::DELETE]);
    }

    // ── Projects ──────────────────────────────────────────────────────

    public function test_project_create_update_get(): void
    {
        $user = User::factory()->create();

        $created = $this->server->call($user, $this->write(), 'create_project', [
            'name' => '  Maturaarbeit ',
            'deadline' => '2026-12-01',
            'brainstorm' => '# Ideen',
        ]);
        $this->assertSame('Maturaarbeit', $created['name']);

        $this->server->call($user, $this->write(), 'update_project', [
            'id' => $created['id'],
            'external_url' => 'https://github.com/x/y',
            'brainstorm' => null,
        ]);

        $project = $this->server->call($user, $this->write(), 'get_project', ['id' => $created['id']]);
        $this->assertSame('https://github.com/x/y', $project['external_url']);
        $this->assertNull($project['brainstorm']);
    }

    public function test_update_project_refuses_a_foreign_project_and_a_bad_url(): void
    {
        $user = User::factory()->create();
        $theirs = Project::factory()->for(User::factory()->create())->create();
        $mine = Project::factory()->for($user)->create();

        try {
            $this->server->call($user, $this->write(), 'update_project', ['id' => $theirs->id, 'name' => 'x']);
            $this->fail('foreign project must be refused');
        } catch (McpToolExecutionException) {
            $this->assertNotSame('x', $theirs->fresh()->name);
        }

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->server->call($user, $this->write(), 'update_project', ['id' => $mine->id, 'external_url' => 'nope']);
    }

    public function test_delete_project_needs_the_delete_ability_and_the_exact_name(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Alt']);
        $open = Task::factory()->for($user)->create(['project_id' => $project->id, 'list' => 'projects']);

        try {
            $this->server->call($user, $this->write(), 'delete_project', ['id' => $project->id, 'confirm_name' => 'Alt']);
            $this->fail('write-only token must not delete');
        } catch (McpUnknownToolException) {
        }

        try {
            $this->server->call($user, $this->full(), 'delete_project', ['id' => $project->id, 'confirm_name' => 'Falsch']);
            $this->fail('wrong confirm_name must be refused');
        } catch (McpToolExecutionException) {
            $this->assertNotNull($project->fresh());
        }

        $this->server->call($user, $this->full(), 'delete_project', ['id' => $project->id, 'confirm_name' => 'Alt']);

        $this->assertNull(Project::find($project->id));
        $this->assertSame('inbox', $open->fresh()->list);
        $this->assertNull($open->fresh()->project_id);
    }

    // ── Task groups ───────────────────────────────────────────────────

    public function test_group_create_rename_notes_and_dissolve(): void
    {
        $user = User::factory()->create();
        $a = Task::factory()->for($user)->todos()->create();
        $b = Task::factory()->for($user)->todos()->create();

        $group = $this->server->call($user, $this->write(), 'create_task_group', [
            'name' => 'Referat',
            'task_ids' => [$a->id, $b->id],
        ]);
        $this->assertCount(2, $group['tasks']);
        $this->assertSame($group['id'], $a->fresh()->group_id);

        $this->server->call($user, $this->write(), 'update_task_group', ['id' => $group['id'], 'name' => 'Referat Bio']);

        $note = $this->server->call($user, $this->write(), 'add_group_note', ['group_id' => $group['id'], 'content' => '- Quellen']);
        $this->server->call($user, $this->write(), 'update_group_note', ['group_id' => $group['id'], 'note_id' => $note['id'], 'content' => '- Quellen prüfen']);

        $got = $this->server->call($user, $this->write(), 'get_task_group', ['id' => $group['id']]);
        $this->assertSame('Referat Bio', $got['name']);
        $this->assertSame('- Quellen prüfen', $got['notes'][0]['content']);

        // wrong confirmation keeps the group, right one dissolves it and keeps the tasks
        try {
            $this->server->call($user, $this->full(), 'dissolve_task_group', ['id' => $group['id'], 'confirm_name' => 'x']);
            $this->fail('must refuse');
        } catch (McpToolExecutionException) {
        }
        $this->server->call($user, $this->full(), 'dissolve_task_group', ['id' => $group['id'], 'confirm_name' => 'Referat Bio']);

        $this->assertNull(TaskGroup::find($group['id']));
        $this->assertNull($a->fresh()->group_id);
        $this->assertSame(0, GroupNote::count());
    }

    public function test_create_task_group_rejects_project_and_foreign_tasks(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $inProject = Task::factory()->for($user)->create(['project_id' => $project->id, 'list' => 'projects']);
        $foreign = Task::factory()->for(User::factory()->create())->todos()->create();

        foreach ([$inProject, $foreign] as $task) {
            try {
                $this->server->call($user, $this->write(), 'create_task_group', ['name' => 'G', 'task_ids' => [$task->id]]);
                $this->fail('must be refused');
            } catch (\Illuminate\Validation\ValidationException) {
            }
        }

        $this->assertSame(0, TaskGroup::count());
    }

    public function test_group_notes_are_scoped_to_the_owner_and_delete_needs_confirmation(): void
    {
        $user = User::factory()->create();
        $group = TaskGroup::factory()->for($user)->create(['name' => 'G']);
        $other = TaskGroup::factory()->for($user)->create(['name' => 'H']);
        $note = $group->notes()->create(['content' => 'x', 'sort_order' => 0]);

        // a note id from another group of mine is not reachable through the wrong group
        $this->expectException(McpToolExecutionException::class);
        $this->server->call($user, $this->full(), 'delete_group_note', [
            'group_id' => $other->id, 'note_id' => $note->id, 'confirm_group_name' => 'H',
        ]);
    }

    public function test_group_note_delete_with_right_confirmation(): void
    {
        $user = User::factory()->create();
        $group = TaskGroup::factory()->for($user)->create(['name' => 'G']);
        $note = $group->notes()->create(['content' => 'x', 'sort_order' => 0]);

        $this->server->call($user, $this->full(), 'delete_group_note', [
            'group_id' => $group->id, 'note_id' => $note->id, 'confirm_group_name' => 'G',
        ]);

        $this->assertSame(0, $group->notes()->count());
    }

    // ── Agenda ────────────────────────────────────────────────────────

    public function test_agenda_create_with_duration_then_update_done_and_delete(): void
    {
        $user = User::factory()->create();

        $created = $this->server->call($user, $this->write(), 'create_agenda_entry', [
            'type' => 'homework', 'subject' => 'Mathe', 'title' => 'Seite 12', 'date' => '2026-11-02', 'duration_minutes' => 30,
        ]);
        $this->assertSame(30, $created['duration_minutes']);

        $updated = $this->server->call($user, $this->write(), 'update_agenda_entry', [
            'id' => $created['id'], 'is_done' => true, 'notes' => 'fertig',
        ]);
        $this->assertTrue($updated['is_done']);
        $this->assertSame('fertig', $updated['notes']);

        // switching to an exam drops the duration
        $exam = $this->server->call($user, $this->write(), 'update_agenda_entry', ['id' => $created['id'], 'type' => 'exam']);
        $this->assertNull($exam['duration_minutes']);

        try {
            $this->server->call($user, $this->full(), 'delete_agenda_entry', ['id' => $created['id'], 'confirm_title' => 'nope']);
            $this->fail('must refuse');
        } catch (McpToolExecutionException) {
        }
        $this->server->call($user, $this->full(), 'delete_agenda_entry', ['id' => $created['id'], 'confirm_title' => 'Seite 12']);
        $this->assertSame(0, AgendaEntry::count());
    }

    public function test_private_note_only_on_shared_entries_and_never_leaks_to_classmates(): void
    {
        $user = User::factory()->create();
        $mate = User::factory()->create();
        $space = AgendaSpace::factory()->withMembers($user, $mate)->create();
        $shared = AgendaEntry::factory()->for($mate)->create(['agenda_space_id' => $space->id, 'type' => 'homework']);
        $private = AgendaEntry::factory()->for($user)->create(['agenda_space_id' => null]);

        $this->server->call($user, $this->write(), 'update_agenda_entry', ['id' => $shared->id, 'private_note' => 'meins']);

        $this->assertSame('meins', $this->server->call($user, $this->write(), 'get_agenda_entry', ['id' => $shared->id])['private_note']);
        $this->assertNull($this->server->call($mate, $this->write(), 'get_agenda_entry', ['id' => $shared->id])['private_note']);

        $this->expectException(McpToolExecutionException::class);
        $this->server->call($user, $this->write(), 'update_agenda_entry', ['id' => $private->id, 'private_note' => 'x']);
    }

    public function test_a_stranger_cannot_reach_a_private_entry_and_spaces_hide_invite_codes(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        $entry = AgendaEntry::factory()->for($stranger)->create(['agenda_space_id' => null]);
        AgendaSpace::factory()->withMembers($user)->create();

        $spaces = $this->server->call($user, $this->write(), 'list_agenda_spaces', [])['spaces'];
        $this->assertCount(1, $spaces);
        $this->assertArrayNotHasKey('invite_code', $spaces[0]);

        $this->expectException(McpToolExecutionException::class);
        $this->server->call($user, $this->write(), 'get_agenda_entry', ['id' => $entry->id]);
    }

    public function test_hiding_the_agenda_module_hides_every_agenda_tool(): void
    {
        $user = User::factory()->create(['hidden_modules' => ['agenda']]);

        $names = collect($this->server->availableTools($user->fresh(), $this->full()))->map->name()->all();

        foreach (['get_agenda_entry', 'update_agenda_entry', 'delete_agenda_entry', 'list_agenda_spaces'] as $tool) {
            $this->assertNotContains($tool, $names);
        }
        $this->assertContains('get_project', $names);
    }
}
