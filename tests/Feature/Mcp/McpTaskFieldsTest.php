<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpServer;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** duration_minutes, repeat_rule, list_tasks filters and the bulk task tools. */
class McpTaskFieldsTest extends TestCase
{
    use RefreshDatabase;

    private McpServer $server;

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = app(McpServer::class);
    }

    private function write(): callable
    {
        return fn (string $a) => in_array($a, [McpAbility::READ, McpAbility::WRITE], true);
    }

    public function test_create_and_update_set_duration_and_repeat_rule(): void
    {
        $user = User::factory()->create();

        $created = $this->server->call($user, $this->write(), 'create_task', [
            'title' => 'Zimmer aufräumen',
            'duration_minutes' => 45,
            'repeat_rule' => 'weekly',
        ]);

        $this->assertSame(45, $created['duration_minutes']);
        $this->assertSame('weekly', $created['repeat_rule']);

        $updated = $this->server->call($user, $this->write(), 'update_task', [
            'id' => $created['id'],
            'duration_minutes' => null,
            'repeat_rule' => null,
        ]);

        $this->assertNull($updated['duration_minutes']);
        $this->assertNull($updated['repeat_rule']);
    }

    public function test_duration_out_of_range_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->expectException(ValidationException::class);
        $this->server->call($user, $this->write(), 'create_task', ['title' => 'x', 'duration_minutes' => 601]);
    }

    public function test_unknown_repeat_rule_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->expectException(ValidationException::class);
        $this->server->call($user, $this->write(), 'create_task', ['title' => 'x', 'repeat_rule' => 'hourly']);
    }

    public function test_list_tasks_filters_by_date_overdue_duration_and_paginates(): void
    {
        $user = User::factory()->create();
        $today = $user->localToday();

        $overdue = Task::factory()->for($user)->todos()->create(['deadline' => $today->copy()->subDays(2)]);
        $soon = Task::factory()->for($user)->todos()->create(['due_date' => $today->copy()->addDays(3), 'duration_minutes' => 20]);
        $later = Task::factory()->for($user)->todos()->create(['deadline' => $today->copy()->addDays(20)]);
        Task::factory()->for($user)->todos()->create();

        $call = fn (array $args) => $this->server->call($user, $this->write(), 'list_tasks', $args);

        $this->assertSame([$overdue->id], collect($call(['overdue' => true])['tasks'])->pluck('id')->all());

        $this->assertEqualsCanonicalizing(
            [$overdue->id, $soon->id],
            collect($call(['due_on_or_before' => $today->copy()->addDays(3)->toDateString()])['tasks'])->pluck('id')->all(),
        );

        $this->assertSame([$later->id], collect($call(['due_on_or_after' => $today->copy()->addDays(10)->toDateString()])['tasks'])->pluck('id')->all());

        $this->assertNotContains($soon->id, collect($call(['without_duration' => true])['tasks'])->pluck('id')->all());

        $page = $call(['limit' => 2, 'offset' => 2]);
        $this->assertSame(4, $page['total']);
        $this->assertSame(2, $page['count']);
    }

    public function test_create_tasks_creates_all_or_nothing(): void
    {
        $user = User::factory()->create();

        $result = $this->server->call($user, $this->write(), 'create_tasks', [
            'tasks' => [['title' => 'A', 'duration_minutes' => 10], ['title' => 'B']],
        ]);

        $this->assertSame(2, $result['created']);
        $this->assertSame(2, $user->tasks()->count());

        try {
            $this->server->call($user, $this->write(), 'create_tasks', [
                'tasks' => [['title' => 'C'], ['title' => '']],
            ]);
            $this->fail('Expected a validation error');
        } catch (ValidationException) {
            $this->assertDatabaseMissing('tasks', ['title' => 'C']);
        }
    }

    public function test_update_tasks_rolls_back_when_one_task_is_foreign(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        $mine = Task::factory()->for($user)->todos()->create();
        $theirs = Task::factory()->for($stranger)->todos()->create();

        try {
            $this->server->call($user, $this->write(), 'update_tasks', [
                'updates' => [['id' => $mine->id, 'duration_minutes' => 30], ['id' => $theirs->id, 'duration_minutes' => 30]],
            ]);
            $this->fail('Expected a tool error');
        } catch (McpToolExecutionException) {
            $this->assertNull($mine->fresh()->duration_minutes);
            $this->assertNull($theirs->fresh()->duration_minutes);
        }

        $ok = $this->server->call($user, $this->write(), 'update_tasks', [
            'updates' => [['id' => $mine->id, 'duration_minutes' => 30]],
        ]);
        $this->assertSame(1, $ok['updated']);
        $this->assertSame(30, $mine->fresh()->duration_minutes);
    }
}
