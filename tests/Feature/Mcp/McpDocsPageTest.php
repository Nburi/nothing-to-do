<?php

namespace Tests\Feature\Mcp;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpDocsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_requires_login(): void
    {
        $this->get('/docs/mcp')->assertRedirect(route('login'));
    }

    public function test_it_renders_the_full_tool_catalog(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/docs/mcp');

        $response->assertOk()
            ->assertSee('list_tasks')
            ->assertSee('delete_task')
            ->assertSee('create_task')
            ->assertSee(url('/api/mcp'));
    }
}
