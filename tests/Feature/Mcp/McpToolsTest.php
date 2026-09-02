<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\Exceptions\McpUnknownToolException;
use App\Mcp\McpAbility;
use App\Mcp\McpServer;
use App\Models\AgendaEntry;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class McpToolsTest extends TestCase
{
    use RefreshDatabase;

    private McpServer $server;

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = app(McpServer::class);
    }

    /** @param  list<string>  $abilities */
    private function can(array $abilities): callable
    {
        return fn (string $ability) => in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }

    // ── Gating (the signature moment) ──────────────────────────────────

    public function test_tools_list_shrinks_when_a_module_is_hidden(): void
    {
        $user = User::factory()->create();
        $full = $this->can([McpAbility::READ, McpAbility::WRITE, McpAbility::DELETE]);

        $before = collect($this->server->availableTools($user, $full))->map->name();
        $this->assertContains('list_agenda_entries', $before);
        $this->assertContains('create_agenda_entry', $before);

        $user->update(['hidden_modules' => ['agenda']]);
        $user->refresh();

        $after = collect($this->server->availableTools($user, $full))->map->name();
        $this->assertNotContains('list_agenda_entries', $after);
        $this->assertNotContains('create_agenda_entry', $after);
        // Nothing else was affected by hiding just one module.
        $this->assertContains('list_tasks', $after);
    }

    public function test_tools_list_omits_write_and_delete_tools_for_a_read_only_token(): void
    {
        $user = User::factory()->create();
        $readOnly = $this->can([McpAbility::READ]);

        $names = collect($this->server->availableTools($user, $readOnly))->map->name();

        $this->assertContains('list_tasks', $names);
        $this->assertNotContains('create_task', $names);
        $this->assertNotContains('delete_task', $names);
    }

    public function test_tools_list_omits_delete_task_without_the_delete_ability(): void
    {
        $user = User::factory()->create();
        $readWrite = $this->can([McpAbility::READ, McpAbility::WRITE]);

        $names = collect($this->server->availableTools($user, $readWrite))->map->name();

        $this->assertContains('create_task', $names);
        $this->assertNotContains('delete_task', $names);
    }

    public function test_calling_a_hidden_tool_by_name_fails_exactly_like_a_nonexistent_one(): void
    {
        $user = User::factory()->create();
        $readOnly = $this->can([McpAbility::READ]);

        $realButHidden = null;
        $garbage = null;

        try {
            $this->server->call($user, $readOnly, 'delete_task', ['id' => 1, 'confirm_title' => 'x']);
        } catch (McpUnknownToolException $e) {
            $realButHidden = $e->getMessage();
        }

        try {
            $this->server->call($user, $readOnly, 'not_a_real_tool', []);
        } catch (McpUnknownToolException $e) {
            $garbage = $e->getMessage();
        }

        $this->assertNotNull($realButHidden);
        $this->assertNotNull($garbage);
        // Same error shape either way — no information leak about what
        // could be unlocked with a different token.
        $this->assertSame('Unknown tool: delete_task', $realButHidden);
        $this->assertSame('Unknown tool: not_a_real_tool', $garbage);
    }

    // ── Reads ───────────────────────────────────────────────────────────

    public function test_list_tasks_filters_and_excludes_completed_by_default(): void
    {
        $user = User::factory()->create();
        $read = $this->can([McpAbility::READ]);

        Task::factory()->for($user)->todos()->create(['title' => 'Open todo']);
        Task::factory()->for($user)->tasks()->create(['title' => 'Open task']);
        Task::factory()->for($user)->todos()->completed()->create(['title' => 'Done todo']);

        $result = $this->server->call($user, $read, 'list_tasks', ['list' => 'todos']);

        $this->assertSame(1, $result['count']);
        $this->assertSame('Open todo', $result['tasks'][0]['title']);
    }

    public function test_get_board_buckets_by_the_active_list_concept(): void
    {
        $user = User::factory()->create(['list_concept' => 'kanban']);
        $read = $this->can([McpAbility::READ]);

        Task::factory()->for($user)->todos()->create(['title' => 'Backlog item']);
        Task::factory()->for($user)->today()->create(['title' => 'Doing item']);

        $result = $this->server->call($user, $read, 'get_board', []);

        $this->assertSame('kanban', $result['list_concept']);
        $this->assertArrayHasKey('backlog', $result['columns']);
        $this->assertArrayHasKey('in_arbeit', $result['columns']);
        $this->assertArrayHasKey('erledigt', $result['columns']);
        $this->assertSame('Backlog item', $result['columns']['backlog'][0]['title']);
        $this->assertSame('Doing item', $result['columns']['in_arbeit'][0]['title']);
    }

    public function test_list_categories_and_agenda_are_hidden_when_the_module_is_hidden(): void
    {
        $user = User::factory()->create(['hidden_modules' => ['schedule', 'agenda', 'progress']]);
        $full = $this->can([McpAbility::READ, McpAbility::WRITE, McpAbility::DELETE]);

        foreach (['list_categories', 'list_agenda_entries', 'get_progress'] as $tool) {
            $this->expectExceptionOnCall($user, $full, $tool, []);
        }
    }

    private function expectExceptionOnCall(User $user, callable $can, string $tool, array $args): void
    {
        try {
            $this->server->call($user, $can, $tool, $args);
            $this->fail("Expected {$tool} to be unavailable.");
        } catch (McpUnknownToolException) {
            $this->assertTrue(true);
        }
    }

    public function test_get_settings_reflects_visible_modules(): void
    {
        $user = User::factory()->create(['hidden_modules' => ['progress']]);
        $read = $this->can([McpAbility::READ]);

        $result = $this->server->call($user, $read, 'get_settings', []);

        $this->assertNotContains('progress', $result['visible_modules']);
        $this->assertArrayNotHasKey('daily_task_goal', $result);
    }

    // ── Writes ──────────────────────────────────────────────────────────

    public function test_create_task_into_a_group(): void
    {
        $user = User::factory()->create();
        $write = $this->can([McpAbility::READ, McpAbility::WRITE]);
        $group = TaskGroup::factory()->for($user)->create();
        // A group needs 2+ tasks to survive — seed one so pruneIfTooSmall doesn't dissolve it later.
        Task::factory()->for($user)->create(['group_id' => $group->id]);

        $result = $this->server->call($user, $write, 'create_task', [
            'title' => 'Neue Aufgabe',
            'group_id' => $group->id,
        ]);

        $this->assertSame($group->id, $result['group_id']);
        $this->assertDatabaseHas('tasks', ['title' => 'Neue Aufgabe', 'group_id' => $group->id]);
    }

    public function test_create_task_rejects_a_group_belonging_to_another_user(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        $write = $this->can([McpAbility::READ, McpAbility::WRITE]);
        $foreignGroup = TaskGroup::factory()->for($stranger)->create();

        $this->expectException(ValidationException::class);

        $this->server->call($user, $write, 'create_task', [
            'title' => 'x',
            'group_id' => $foreignGroup->id,
        ]);
    }

    public function test_complete_task_syncs_a_linked_agenda_entry(): void
    {
        $user = User::factory()->create();
        $write = $this->can([McpAbility::READ, McpAbility::WRITE]);
        $entry = AgendaEntry::factory()->for($user)->create();
        $task = Task::factory()->for($user)->todos()->create(['agenda_entry_id' => $entry->id]);

        $this->server->call($user, $write, 'complete_task', ['id' => $task->id]);

        $this->assertTrue($entry->fresh()->isDoneFor($user));
    }

    public function test_update_task_moving_to_a_project_clears_group_and_prunes_it(): void
    {
        $user = User::factory()->create();
        $write = $this->can([McpAbility::READ, McpAbility::WRITE]);
        $group = TaskGroup::factory()->for($user)->create();
        $task = Task::factory()->for($user)->create(['group_id' => $group->id]);
        // Only one task in the group — moving it out should dissolve the group.
        $project = Project::factory()->for($user)->create();

        $result = $this->server->call($user, $write, 'update_task', [
            'id' => $task->id,
            'project_id' => $project->id,
        ]);

        $this->assertSame($project->id, $result['project_id']);
        $this->assertNull($result['group_id']);
        $this->assertDatabaseMissing('task_groups', ['id' => $group->id]);
    }

    public function test_set_task_order_writes_sort_order_and_skips_foreign_ids(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        $write = $this->can([McpAbility::READ, McpAbility::WRITE]);

        $a = Task::factory()->for($user)->todos()->create();
        $b = Task::factory()->for($user)->todos()->create();
        $foreign = Task::factory()->for($stranger)->todos()->create();

        $result = $this->server->call($user, $write, 'set_task_order', ['ids' => [$b->id, $a->id, $foreign->id]]);

        $this->assertSame(2, $result['updated_count']);
        $this->assertSame([$foreign->id], $result['skipped_ids']);
        $this->assertSame(0, $b->fresh()->sort_order);
        $this->assertSame(1, $a->fresh()->sort_order);
    }

    public function test_set_list_concept_rejects_an_invalid_value(): void
    {
        $user = User::factory()->create();
        $write = $this->can([McpAbility::READ, McpAbility::WRITE]);

        $this->expectException(McpToolExecutionException::class);

        $this->server->call($user, $write, 'set_list_concept', ['concept' => 'made_up']);
    }

    public function test_set_module_visibility_resets_default_page_when_hiding_it(): void
    {
        $user = User::factory()->create(['default_page' => 'agenda']);
        $write = $this->can([McpAbility::READ, McpAbility::WRITE]);

        $result = $this->server->call($user, $write, 'set_module_visibility', [
            'module' => 'agenda',
            'hidden' => true,
        ]);

        $this->assertSame('app', $result['default_page']);
        $this->assertSame('app', $user->fresh()->default_page);
    }

    public function test_set_default_landing_page_rejects_a_hidden_module(): void
    {
        $user = User::factory()->create(['hidden_modules' => ['agenda']]);
        $write = $this->can([McpAbility::READ, McpAbility::WRITE]);

        $this->expectException(McpToolExecutionException::class);

        $this->server->call($user, $write, 'set_default_landing_page', ['page' => 'agenda']);
    }

    public function test_create_agenda_entry_is_always_private(): void
    {
        $user = User::factory()->create();
        $write = $this->can([McpAbility::READ, McpAbility::WRITE]);

        $result = $this->server->call($user, $write, 'create_agenda_entry', [
            'type' => 'homework',
            'subject' => 'Mathematik',
            'title' => 'Aufgaben S. 12',
            'date' => now()->addDay()->toDateString(),
        ]);

        $this->assertFalse($result['is_shared']);
        $this->assertDatabaseHas('agenda_entries', ['id' => $result['id'], 'agenda_space_id' => null]);
    }

    // ── Delete ──────────────────────────────────────────────────────────

    public function test_delete_task_requires_the_confirm_title_to_match(): void
    {
        $user = User::factory()->create();
        $delete = $this->can([McpAbility::READ, McpAbility::WRITE, McpAbility::DELETE]);
        $task = Task::factory()->for($user)->create(['title' => 'Echter Titel']);

        try {
            $this->server->call($user, $delete, 'delete_task', ['id' => $task->id, 'confirm_title' => 'Falscher Titel']);
            $this->fail('Expected a mismatch to be rejected.');
        } catch (McpToolExecutionException $e) {
            $this->assertStringContainsString('Echter Titel', $e->getMessage());
        }

        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_delete_task_succeeds_with_the_exact_title_and_prunes_its_group(): void
    {
        $user = User::factory()->create();
        $delete = $this->can([McpAbility::READ, McpAbility::WRITE, McpAbility::DELETE]);
        $group = TaskGroup::factory()->for($user)->create();
        $task = Task::factory()->for($user)->create(['title' => 'Weg damit', 'group_id' => $group->id]);

        $result = $this->server->call($user, $delete, 'delete_task', [
            'id' => $task->id,
            'confirm_title' => 'Weg damit',
        ]);

        $this->assertTrue($result['deleted']);
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
        $this->assertDatabaseMissing('task_groups', ['id' => $group->id]);
    }

    public function test_delete_task_is_scoped_to_the_owner(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        $delete = $this->can([McpAbility::READ, McpAbility::WRITE, McpAbility::DELETE]);
        $foreignTask = Task::factory()->for($stranger)->create(['title' => 'Nicht meins']);

        $this->expectException(McpToolExecutionException::class);

        $this->server->call($user, $delete, 'delete_task', ['id' => $foreignTask->id, 'confirm_title' => 'Nicht meins']);
    }
}
