<?php

namespace Tests\Feature\Mcp;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The whole OAuth 2.1 authorization-code + PKCE flow exactly as claude.ai
 * walks it — dynamic client registration, consent, code exchange, an
 * authenticated tools/call, and a refresh — plus the permission model that
 * decides which tools an OAuth connection may see at all.
 */
class McpOAuthFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $verifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->verifier = Str::random(64);
    }

    private function challenge(): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $this->verifier, true)), '+/', '-_'), '=');
    }

    /**
     * Register a client the way Claude does, consent as the given user, and
     * exchange the resulting code for tokens.
     *
     * @return array<string, mixed>
     */
    private function connect(User $user): array
    {
        $clientId = $this->postJson('/oauth/register', [
            'client_name' => 'Claude',
            'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
        ])->assertCreated()->json('client_id');

        $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query([
                'client_id' => $clientId,
                'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
                'response_type' => 'code',
                'scope' => 'mcp:use',
                'state' => 'xyz',
                'code_challenge' => $this->challenge(),
                'code_challenge_method' => 'S256',
            ]))
            ->assertOk()
            // The consent screen is this app's own, not Passport's stock view.
            ->assertSee('Zugriff erlauben?')
            ->assertSee('Claude');

        $approval = $this->post('/oauth/authorize', [
            'auth_token' => session('authToken'),
        ]);

        $approval->assertRedirect();

        parse_str((string) parse_url((string) $approval->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->assertSame('xyz', $query['state'] ?? null, 'The state parameter must survive the round trip.');
        $this->assertArrayHasKey('code', $query);

        $tokens = $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
            'code_verifier' => $this->verifier,
            'code' => $query['code'],
        ])->assertOk()->json();

        $this->assertArrayHasKey('access_token', $tokens);
        $this->assertArrayHasKey('refresh_token', $tokens);

        return ['client_id' => $clientId, ...$tokens];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function rpc(string $accessToken, string $method, array $params = []): array
    {
        return $this->withHeaders([
            'Authorization' => 'Bearer '.$accessToken,
            'Accept' => 'application/json, text/event-stream',
        ])->postJson('/mcp', array_filter([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => $params ?: null,
        ]))->json();
    }

    /**
     * @param  array<string, mixed>  $tokens
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function toolNames(array $tokens): \Illuminate\Support\Collection
    {
        return collect($this->rpc($tokens['access_token'], 'tools/list')['result']['tools'])->pluck('name');
    }

    public function test_every_tool_arrives_in_a_single_tools_list_page(): void
    {
        // Laravel MCP's default page size is 15 and this server has more tools
        // than that, so without an explicit pagination length the last few
        // (delete_task included) silently land on a second page.
        $tokens = $this->connect(User::factory()->create([
            'mcp_oauth_write' => true,
            'mcp_oauth_delete' => true,
        ]));

        $result = $this->rpc($tokens['access_token'], 'tools/list')['result'];

        $this->assertArrayNotHasKey('nextCursor', $result);
        $this->assertCount(19, $result['tools']);
    }

    public function test_a_connected_client_can_list_and_call_tools(): void
    {
        $user = User::factory()->create();
        $tokens = $this->connect($user);

        $names = $this->toolNames($tokens);

        $this->assertContains('list_tasks', $names);
        $this->assertContains('create_task', $names);

        $result = $this->rpc($tokens['access_token'], 'tools/call', [
            'name' => 'create_task',
            'arguments' => ['title' => 'Aus Claude erstellt', 'list' => 'todos'],
        ]);

        $this->assertFalse($result['result']['isError'] ?? true);
        $this->assertDatabaseHas('tasks', ['user_id' => $user->id, 'title' => 'Aus Claude erstellt']);
    }

    public function test_the_connection_only_ever_reaches_its_own_account(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        Task::factory()->for($stranger)->create(['title' => 'Fremde Aufgabe']);
        Task::factory()->for($user)->create(['title' => 'Eigene Aufgabe']);

        $tokens = $this->connect($user);

        $payload = json_encode($this->rpc($tokens['access_token'], 'tools/call', [
            'name' => 'list_tasks',
            'arguments' => [],
        ]));

        $this->assertStringContainsString('Eigene Aufgabe', (string) $payload);
        $this->assertStringNotContainsString('Fremde Aufgabe', (string) $payload);
    }

    public function test_delete_is_not_granted_to_an_oauth_connection_by_default(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create(['title' => 'Bleibt']);
        $tokens = $this->connect($user);

        $this->assertNotContains('delete_task', $this->toolNames($tokens));

        // ...and calling it anyway fails exactly like a tool that never
        // existed, so a connection cannot discover what it would need to
        // unlock by probing.
        $response = $this->rpc($tokens['access_token'], 'tools/call', [
            'name' => 'delete_task',
            'arguments' => ['id' => $task->id, 'confirm_title' => 'Bleibt'],
        ]);

        $this->assertSame(-32602, $response['error']['code']);
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_delete_appears_once_the_user_allows_it(): void
    {
        $tokens = $this->connect(User::factory()->create(['mcp_oauth_delete' => true]));

        $this->assertContains('delete_task', $this->toolNames($tokens));
    }

    public function test_turning_off_writing_hides_every_write_tool(): void
    {
        $tokens = $this->connect(User::factory()->create(['mcp_oauth_write' => false]));
        $names = $this->toolNames($tokens);

        $this->assertContains('list_tasks', $names, 'Reading always stays available.');
        $this->assertNotContains('create_task', $names);
        $this->assertNotContains('update_task', $names);
        $this->assertNotContains('complete_task', $names);
    }

    public function test_a_hidden_module_removes_its_tools_from_an_oauth_connection_too(): void
    {
        $tokens = $this->connect(User::factory()->create(['hidden_modules' => ['agenda']]));
        $names = $this->toolNames($tokens);

        $this->assertNotContains('list_agenda_entries', $names);
        $this->assertNotContains('create_agenda_entry', $names);
    }

    public function test_the_refresh_token_mints_a_working_access_token(): void
    {
        // Claude refreshes reactively on a 401, and an access token only lives
        // an hour (AppServiceProvider), so this path runs constantly.
        $tokens = $this->connect(User::factory()->create());

        $refreshed = $this->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $tokens['client_id'],
            'refresh_token' => $tokens['refresh_token'],
            'scope' => 'mcp:use',
        ])->assertOk()->json();

        $this->assertArrayHasKey('access_token', $refreshed);
        $this->assertNotSame($tokens['access_token'], $refreshed['access_token']);

        $this->assertContains('list_tasks', $this->toolNames($refreshed));
    }

    public function test_a_bogus_bearer_token_is_refused(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer not-a-real-token'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
            ->assertUnauthorized();
    }
}
