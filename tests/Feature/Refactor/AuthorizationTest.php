<?php

namespace Tests\Feature\Refactor;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpServer;
use App\Models\Area;
use App\Models\EventCategory;
use App\Models\EventTemplate;
use App\Models\Task;
use App\Models\User;
use App\Services\Planning\Capacity;
use App\Services\Planning\Suggestions;
use App\Services\Planning\WeekPlanReader;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * A14 (all requirements): no endpoint returns or changes another user's items.
 * Covers every endpoint and MCP tool the refactor added (steps 1.3–1.5).
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    private User $stranger;

    private Task $foreignTask;

    private Area $foreignArea;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->me = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
        $this->stranger = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
        $this->token = $this->me->createToken('full')->plainTextToken;

        // The stranger has a full plan: block all day every day, an area, a deadline task.
        $this->foreignArea = Area::factory()->for($this->stranger)->create(['name' => 'Geheim']);
        $category = EventCategory::factory()->for($this->stranger)->pomodoro()->create();
        EventTemplate::factory()->for($this->stranger)->create([
            'category_id' => $category->id, 'is_recurring' => true, 'recurrence' => '1,2,3,4,5,6,7',
            'default_start' => '00:00', 'duration' => 1439,
        ]);
        $this->foreignTask = Task::factory()->for($this->stranger)->create([
            'list' => 'tasks', 'title' => 'Fremder Task', 'hard_deadline' => now()->addDay()->toDateString(),
        ]);
    }

    public function test_the_engines_never_see_another_users_rows(): void
    {
        $this->assertSame([], (new WeekPlanReader)->categories($this->me));
        $this->assertNull((new Suggestions)->current($this->me)->task);
        $this->assertFalse((new Capacity)->forecast($this->me)->hasCrunch());
        $this->assertSame(0, array_sum(array_map(fn ($d) => $d->capacitySessions, (new Capacity)->forecast($this->me)->days)));
    }

    public function test_rest_endpoints_do_not_leak_the_strangers_items(): void
    {
        $api = $this->withToken($this->token);

        $this->assertStringNotContainsString('Fremder Task', $api->getJson('/api/suggestion')->assertOk()->getContent());
        $this->assertStringNotContainsString('Geheim', $api->getJson('/api/areas')->assertOk()->getContent());
        $api->getJson('/api/forecast')->assertOk()->assertJsonPath('late_task_ids', []);
        $api->getJson('/api/session')->assertOk()->assertJsonPath('phase', 'idle');
    }

    public function test_rest_endpoints_refuse_the_strangers_ids(): void
    {
        $api = $this->withToken($this->token);
        $id = $this->foreignTask->id;

        $api->getJson("/api/tasks/{$id}")->assertNotFound();
        $api->postJson("/api/tasks/{$id}/drop")->assertNotFound();
        $api->postJson("/api/tasks/{$id}/shrink")->assertNotFound();
        $api->postJson('/api/session/start', ['task_id' => $id])->assertNotFound();
        $api->patchJson("/api/areas/{$this->foreignArea->id}", ['name' => 'Meins'])->assertNotFound();
        $api->deleteJson("/api/areas/{$this->foreignArea->id}")->assertNotFound();
        $api->postJson('/api/tasks', ['title' => 'X', 'area_id' => $this->foreignArea->id])->assertUnprocessable();

        $this->assertSame('tasks', $this->foreignTask->fresh()->list);
        $this->assertSame('Geheim', $this->foreignArea->fresh()->name);
        $this->assertDatabaseCount('pomodoro_sessions', 0);
    }

    public function test_a_category_cannot_be_linked_to_someone_elses_area(): void
    {
        $mine = EventCategory::factory()->for($this->me)->pomodoro()->create();

        $this->withToken($this->token)->patchJson("/api/event-categories/{$mine->id}", ['area_ids' => [$this->foreignArea->id]])
            ->assertOk()
            ->assertJsonPath('data.area_ids', []);
    }

    public function test_mcp_tools_refuse_the_strangers_ids(): void
    {
        $server = app(McpServer::class);
        $all = fn () => true;
        $mine = Task::factory()->for($this->me)->create();

        $refused = function (callable $call): bool {
            try {
                $call();
            } catch (ModelNotFoundException|ValidationException|McpToolExecutionException) {
                return true;
            }

            return false;
        };

        $this->assertTrue($refused(fn () => $server->call($this->me, $all, 'control_session', ['action' => 'start', 'task_id' => $this->foreignTask->id])));
        $this->assertTrue($refused(fn () => $server->call($this->me, $all, 'update_task', ['id' => $mine->id, 'area_id' => $this->foreignArea->id])));
        $this->assertTrue($refused(fn () => $server->call($this->me, $all, 'update_task', ['id' => $this->foreignTask->id, 'title' => 'Meins'])));
        $this->assertNull($server->call($this->me, $all, 'get_suggestion', [])['task']);
        $this->assertSame([], $server->call($this->me, $all, 'get_forecast', [])['late_task_ids']);
        $this->assertDatabaseCount('pomodoro_sessions', 0);
        $this->assertNull($mine->fresh()->area_id);
        $this->assertSame('Fremder Task', $this->foreignTask->fresh()->title);
    }
}
