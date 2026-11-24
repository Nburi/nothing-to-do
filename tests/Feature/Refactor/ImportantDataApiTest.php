<?php

namespace Tests\Feature\Refactor;

use App\Mcp\McpServer;
use App\Models\EventCategory;
use App\Models\EventTemplate;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A66 (R5, R7, R8, step 4.6): rows the old app marked important carry over untouched (the column
 * and its data stay; nothing is migrated), and REST and MCP set, clear and rank the marker.
 * Monday 9 November 2026, 19:40 in Zurich, inside a Deep work block.
 */
class ImportantDataApiTest extends TestCase
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

    // ── existing data (R8) ───────────────────────────────────────────────

    public function test_the_old_column_is_still_there_with_no_marker_by_default(): void
    {
        $this->assertTrue(Schema::hasColumn('tasks', 'is_important'), 'tasks.is_important carries the production data; it must not be dropped or renamed');
        $this->assertFalse(Task::factory()->for($this->user)->create()->fresh()->is_important);
    }

    public function test_a_row_the_old_app_marked_important_shows_as_marked_and_is_ranked(): void
    {
        // Written with the old columns only, the way production rows look: no size, no area, no new dates.
        $now = now();
        foreach ([['Erste', 0, 0], ['Vom alten Stand markiert', 1, 1], ['Dritte', 2, 0]] as [$title, $order, $important]) {
            DB::table('tasks')->insert([
                'user_id' => $this->user->id, 'title' => $title, 'list' => 'tasks', 'is_today' => 0,
                'is_important' => $important, 'is_completed' => 0, 'sort_order' => $order,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $old = Task::where('title', 'Vom alten Stand markiert')->first();
        $this->assertTrue($old->is_important);
        $this->assertTrue($old->toPlannedTask()->important);

        $this->actingAs($this->user)->get('/board')->assertOk()->assertSee('data-important-tag', false);
        $this->withToken($this->token)->getJson('/api/suggestion')->assertOk()
            ->assertJsonPath('task.id', $old->id)
            ->assertJsonPath('reason', 'important');
    }

    // ── REST and MCP (parity) ────────────────────────────────────────────

    public function test_rest_sets_and_clears_the_marker_and_the_suggestion_follows(): void
    {
        $plain = Task::factory()->for($this->user)->create(['list' => 'tasks', 'sort_order' => 0]);
        $other = Task::factory()->for($this->user)->create(['list' => 'tasks', 'sort_order' => 1]);
        $api = $this->withToken($this->token);

        $api->getJson('/api/suggestion')->assertJsonPath('task.id', $plain->id)->assertJsonPath('reason', 'no_deadline');

        $api->patchJson("/api/tasks/{$other->id}", ['is_important' => true])->assertOk()->assertJsonPath('data.is_important', true);
        $api->getJson('/api/suggestion')->assertJsonPath('task.id', $other->id)->assertJsonPath('reason', 'important')
            ->assertJsonPath('task.is_important', true);

        $api->patchJson("/api/tasks/{$other->id}", ['is_important' => false])->assertOk()->assertJsonPath('data.is_important', false);
        $api->getJson('/api/suggestion')->assertJsonPath('task.id', $plain->id);

        $api->patchJson("/api/tasks/{$other->id}", ['is_important' => 'maybe'])->assertUnprocessable();
    }

    public function test_mcp_sets_and_clears_the_marker_and_the_suggestion_follows(): void
    {
        $server = app(McpServer::class);
        $all = fn () => true;
        $plain = Task::factory()->for($this->user)->create(['list' => 'tasks', 'sort_order' => 0]);

        $created = $server->call($this->user, $all, 'create_task', ['title' => 'Nicht vergessen', 'list' => 'tasks', 'is_important' => true]);
        $this->assertTrue($created['is_important']);
        $this->assertSame($created['id'], $server->call($this->user, $all, 'get_suggestion', [])['task']['id']);

        $cleared = $server->call($this->user, $all, 'update_task', ['id' => $created['id'], 'is_important' => false]);
        $this->assertFalse($cleared['is_important']);
        $this->assertSame($plain->id, $server->call($this->user, $all, 'get_suggestion', [])['task']['id']);
    }
}
