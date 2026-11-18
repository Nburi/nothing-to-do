<?php

namespace Tests\Feature\Refactor;

use App\Mcp\McpServer;
use App\Models\Area;
use App\Models\EventCategory;
use App\Models\EventTemplate;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Step 1.5, API parity: the planning engines and the refactor's task fields
 * over REST and MCP. Monday 9 November 2026, 19:40 in Zurich, inside a
 * Deep work block (pomodoro, Mon–Fri 19:30–21:30).
 */
class PlanningApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');
        $this->travelTo(CarbonImmutable::parse('2026-11-09 19:40', 'Europe/Zurich'));
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
        $this->token = $this->user->createToken('full')->plainTextToken;
        $category = EventCategory::factory()->for($this->user)->pomodoro()->create();
        EventTemplate::factory()->for($this->user)->create([
            'category_id' => $category->id, 'is_recurring' => true, 'recurrence' => '1,2,3,4,5',
            'default_start' => '19:30', 'duration' => 120,
        ]);
    }

    public function test_tasks_take_and_show_the_refactor_fields(): void
    {
        $area = Area::factory()->for($this->user)->create();

        $id = $this->withToken($this->token)->postJson('/api/tasks', [
            'title' => 'Vortrag', 'list' => 'tasks', 'size' => 'long', 'area_id' => $area->id,
            'hard_deadline' => '2026-11-12', 'soft_deadline' => '2026-11-11', 'first_step' => '  Folie 6  ',
        ])->assertCreated()
            ->assertJsonPath('data.size', 'long')
            ->assertJsonPath('data.area_id', $area->id)
            ->assertJsonPath('data.hard_deadline', '2026-11-12')
            ->assertJsonPath('data.deadline', '2026-11-12')
            ->assertJsonPath('data.soft_deadline', '2026-11-11')
            ->assertJsonPath('data.first_step', 'Folie 6')
            ->json('data.id');

        $this->withToken($this->token)->patchJson("/api/tasks/{$id}", ['size' => null, 'list' => 'later'])
            ->assertOk()->assertJsonPath('data.size', null)->assertJsonPath('data.list', 'later');

        $this->withToken($this->token)->patchJson("/api/tasks/{$id}", ['size' => 'huge'])->assertUnprocessable();
    }

    public function test_suggestion_another_and_battery(): void
    {
        $first = Task::factory()->for($this->user)->create(['list' => 'tasks', 'hard_deadline' => '2026-11-09', 'first_step' => 'Aufgabe 1']);
        $quick = Task::factory()->for($this->user)->create(['list' => 'todos', 'size' => 'quick']);
        $api = $this->withToken($this->token);

        $api->getJson('/api/suggestion')->assertOk()
            ->assertJsonPath('status', 'pick')
            ->assertJsonPath('task.id', $first->id)
            ->assertJsonPath('start_with', 'Aufgabe 1')
            ->assertJsonPath('why', 'Muss heute anfangen, damit es bis heute fertig ist.')
            ->assertJsonPath('battery', 'okay')
            ->assertJsonPath('alternatives', 1);

        $api->postJson('/api/suggestion/another')->assertOk()->assertJsonPath('task.id', $quick->id);

        $api->putJson('/api/evening/battery', ['battery' => 'low'])->assertOk()
            ->assertJsonPath('battery', 'low')
            ->assertJsonPath('reason', 'warm_up');
        $api->putJson('/api/evening/battery', ['battery' => 'tired'])->assertUnprocessable();
        $api->putJson('/api/evening/battery', ['battery' => null])->assertOk()->assertJsonPath('battery', 'okay');
    }

    public function test_the_session_over_rest_from_start_to_done(): void
    {
        $task = Task::factory()->for($this->user)->create(['list' => 'tasks']);
        $next = Task::factory()->for($this->user)->create(['list' => 'tasks']);
        $api = $this->withToken($this->token);

        $api->getJson('/api/session')->assertOk()->assertJsonPath('phase', 'idle');
        $api->postJson('/api/session/start', ['task_id' => $task->id])->assertOk()
            ->assertJsonPath('phase', 'work')
            ->assertJsonPath('remaining_seconds', 1500);
        $api->postJson('/api/session/pause')->assertOk()->assertJsonPath('paused', true)->assertJsonPath('ends_at', null);
        $api->postJson('/api/session/resume')->assertOk()->assertJsonPath('paused', false);
        $api->postJson('/api/session/done', ['task_id' => $task->id])->assertOk()
            ->assertJsonPath('phase', 'work')
            ->assertJsonPath('task_id', $next->id);
        $this->assertTrue($task->fresh()->is_completed);
        $api->postJson('/api/session/done', [])->assertUnprocessable();
        $api->postJson('/api/session/explode')->assertNotFound();
        $api->postJson('/api/session/stop')->assertOk()->assertJsonPath('phase', 'idle');
    }

    public function test_forecast_drop_and_shrink(): void
    {
        $big = Task::factory()->for($this->user)->create(['list' => 'tasks', 'size' => 'block', 'duration_minutes' => 300, 'hard_deadline' => '2026-11-10']);
        $api = $this->withToken($this->token);

        $api->getJson('/api/forecast')->assertOk()
            ->assertJsonPath('level', 'hard')
            ->assertJsonPath('late_task_ids', [$big->id])
            ->assertJsonPath('days.0.date', '2026-11-09')
            ->assertJsonPath('days.0.capacity_sessions', 3);

        $api->postJson("/api/tasks/{$big->id}/shrink")->assertOk()->assertJsonPath('data.size', 'long');
        $api->postJson("/api/tasks/{$big->id}/drop")->assertOk()->assertJsonPath('data.list', 'later');
        $api->getJson('/api/forecast')->assertOk()->assertJsonPath('level', null);
    }

    public function test_areas_and_category_links(): void
    {
        $api = $this->withToken($this->token);
        $id = $api->postJson('/api/areas', ['name' => 'Schule', 'color' => 'forest'])->assertCreated()->json('data.id');
        $api->postJson('/api/areas', ['name' => ''])->assertUnprocessable();
        $api->patchJson("/api/areas/{$id}", ['name' => 'Lehre'])->assertOk()->assertJsonPath('data.name', 'Lehre');
        $category = EventCategory::query()->where('user_id', $this->user->id)->sole();

        $api->patchJson("/api/event-categories/{$category->id}", ['area_ids' => [$id]])->assertOk()->assertJsonPath('data.area_ids', [$id]);
        $task = Task::factory()->for($this->user)->create(['area_id' => $id]);

        $api->deleteJson("/api/areas/{$id}")->assertNoContent();
        $this->assertNull($task->fresh()->area_id, 'the task stays, only the link goes');
        $api->getJson('/api/event-categories')->assertOk()->assertJsonPath('data.0.area_ids', []);
    }

    public function test_mcp_tools_for_suggestion_session_and_forecast(): void
    {
        $server = app(McpServer::class);
        $all = fn () => true;
        $task = Task::factory()->for($this->user)->create(['list' => 'tasks', 'hard_deadline' => '2026-11-09']);

        $this->assertSame($task->id, $server->call($this->user, $all, 'get_suggestion', [])['task']['id']);
        $this->assertSame('good', $server->call($this->user, $all, 'change_suggestion', ['action' => 'battery', 'battery' => 'good'])['battery']);
        $this->assertSame('work', $server->call($this->user, $all, 'control_session', ['action' => 'start', 'task_id' => $task->id])['phase']);
        $this->assertSame('work', $server->call($this->user, $all, 'get_session', [])['phase']);
        $this->assertSame('idle', $server->call($this->user, $all, 'control_session', ['action' => 'done', 'task_id' => $task->id])['phase']);
        $this->assertNull($server->call($this->user, $all, 'get_forecast', [])['level']);

        $created = $server->call($this->user, $all, 'create_task', ['title' => 'Neu', 'size' => 'quick', 'first_step' => 'Los']);
        $this->assertSame('quick', $created['size']);
        $this->assertSame('Los', $created['first_step']);

        $readOnly = fn (string $ability) => $ability === 'mcp:read';
        $names = collect($server->availableTools($this->user, $readOnly))->map->name();
        $this->assertContains('get_suggestion', $names);
        $this->assertNotContains('control_session', $names, 'writes need mcp:write');
        $this->assertNotContains('change_suggestion', $names);
    }
}
