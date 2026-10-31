<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\Exceptions\McpUnknownToolException;
use App\Mcp\McpAbility;
use App\Mcp\McpServer;
use App\Models\Task;
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
        $this->assertContains('get_progress', $before);

        $user->update(['hidden_modules' => ['progress']]);
        $user->refresh();

        $after = collect($this->server->availableTools($user, $full))->map->name();
        $this->assertNotContains('get_progress', $after);
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
        $user = User::factory()->create(['default_page' => 'crafts']);
        $write = $this->can([McpAbility::READ, McpAbility::WRITE]);

        $result = $this->server->call($user, $write, 'set_module_visibility', [
            'module' => 'crafts',
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

    public function test_delete_task_succeeds_with_the_exact_title(): void
    {
        $user = User::factory()->create();
        $delete = $this->can([McpAbility::READ, McpAbility::WRITE, McpAbility::DELETE]);
        $task = Task::factory()->for($user)->create(['title' => 'Weg damit']);

        $result = $this->server->call($user, $delete, 'delete_task', [
            'id' => $task->id,
            'confirm_title' => 'Weg damit',
        ]);

        $this->assertTrue($result['deleted']);
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
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
