<?php

namespace Tests\Feature\Mcp;

use App\Mcp\McpAbility;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Thin JSON-RPC framing tests for POST /api/mcp — tool logic itself is covered by McpToolsTest. */
class McpTransportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_requires_authentication(): void
    {
        $this->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])
            ->assertUnauthorized();
    }

    public function test_initialize(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18'],
        ]);

        $response->assertOk()
            ->assertJsonPath('jsonrpc', '2.0')
            ->assertJsonPath('id', 1)
            ->assertJsonPath('result.protocolVersion', '2025-06-18')
            ->assertJsonPath('result.capabilities.tools.listChanged', false)
            ->assertJsonPath('result.serverInfo.name', 'nothing-to-do');
    }

    public function test_tools_list_over_http_reflects_token_abilities(): void
    {
        Sanctum::actingAs(User::factory()->create(), [McpAbility::READ]);

        $response = $this->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list']);

        $response->assertOk();
        $names = collect($response->json('result.tools'))->pluck('name');

        $this->assertContains('list_tasks', $names);
        $this->assertNotContains('create_task', $names);
        $this->assertNotContains('delete_task', $names);
    }

    public function test_tools_call_creates_a_task_and_returns_structured_content(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, [McpAbility::READ, McpAbility::WRITE]);

        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call',
            'params' => ['name' => 'create_task', 'arguments' => ['title' => 'Via MCP angelegt']],
        ]);

        $response->assertOk()
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.title', 'Via MCP angelegt');

        $this->assertDatabaseHas('tasks', ['user_id' => $user->id, 'title' => 'Via MCP angelegt']);
    }

    public function test_tools_call_business_error_reports_is_error_true_not_a_protocol_error(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, [McpAbility::READ, McpAbility::WRITE, McpAbility::DELETE]);
        $task = Task::factory()->for($user)->create(['title' => 'Richtig']);

        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call',
            'params' => ['name' => 'delete_task', 'arguments' => ['id' => $task->id, 'confirm_title' => 'Falsch']],
        ]);

        $response->assertOk()
            ->assertJsonMissingPath('error')
            ->assertJsonPath('result.isError', true);

        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_calling_an_unknown_tool_is_a_protocol_level_error(): void
    {
        Sanctum::actingAs(User::factory()->create(), [McpAbility::READ]);

        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'id' => 5, 'method' => 'tools/call',
            'params' => ['name' => 'does_not_exist', 'arguments' => []],
        ]);

        $response->assertOk()
            ->assertJsonPath('error.code', -32602)
            ->assertJsonMissingPath('result');
    }

    public function test_a_notification_with_no_id_gets_202_and_no_body(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/mcp', ['jsonrpc' => '2.0', 'method' => 'notifications/initialized']);

        $response->assertStatus(202);
        $this->assertSame('', $response->getContent());
    }

    public function test_a_write_tool_is_refused_for_a_read_only_token(): void
    {
        Sanctum::actingAs(User::factory()->create(), [McpAbility::READ]);

        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'id' => 6, 'method' => 'tools/call',
            'params' => ['name' => 'create_task', 'arguments' => ['title' => 'x']],
        ]);

        $response->assertOk()->assertJsonPath('error.code', -32602);
    }
}
