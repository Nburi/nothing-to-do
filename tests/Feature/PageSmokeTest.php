<?php

namespace Tests\Feature;

use App\Models\CraftIdea;
use App\Models\EventCategory;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Models\User;
use App\Services\ListConcepts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every authenticated page must render (no 500) for an account that actually
 * has data in every corner — under every list concept, in awkward timezones,
 * with every module visible. Individual feature tests build minimal data; this
 * is the "does anything blow up when it all exists at once" net.
 */
class PageSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function richUser(string $concept, int $offset): User
    {
        Carbon::setTestNow('2026-09-23 23:40:00');
        $user = User::factory()->create([
            'list_concept' => $concept,
            'timezone_offset' => $offset,
            'timezone_auto_dst' => $offset === 1,
            'planner_enabled' => true,
            'is_admin' => true,
        ]);

        // Gruppen are gone from the app, but production rows still carry a group_id —
        // the pages must render fine with that leftover data in place.
        $groupId = DB::table('task_groups')->insertGetId(['user_id' => $user->id, 'name' => 'Alte Gruppe', 'created_at' => now(), 'updated_at' => now()]);

        foreach (['inbox', 'todos', 'tasks'] as $list) {
            Task::factory()->for($user)->create(['list' => $list, 'title' => "Task {$list}", 'due_date' => '2026-09-22', 'notes' => "**fett**\n- x"]);
            Task::factory()->for($user)->create(['list' => $list, 'title' => "Heute {$list}", 'is_today' => $list !== 'inbox', 'today_date' => '2026-09-23', 'is_important' => true, 'deadline' => '2026-09-24']);
        }
        Task::factory()->for($user)->completed()->create(['title' => 'Erledigt']);
        Task::factory()->for($user)->create(['list' => 'todos', 'group_id' => $groupId, 'title' => 'Gruppen-Task 1']);
        Task::factory()->for($user)->create(['list' => 'todos', 'group_id' => $groupId, 'title' => 'Gruppen-Task 2']);
        CraftIdea::factory()->for($user)->create();
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        ScheduleEvent::factory()->for($user)->on('2026-09-23')->at('09:00', '10:30')->create(['category_id' => $category->id]);
        ScheduleEvent::factory()->for($user)->on('2026-09-24')->at('23:00', '23:59')->create();

        return $user;
    }

    public static function matrix(): array
    {
        $rows = [];
        foreach (array_keys(ListConcepts::CATALOG) as $concept) {
            foreach ([0, 1, -8, 14] as $offset) {
                $rows["{$concept} utc{$offset}"] = [$concept, $offset];
            }
        }

        return $rows;
    }

    #[DataProvider('matrix')]
    public function test_every_page_renders_with_rich_data(string $concept, int $offset): void
    {
        $user = $this->richUser($concept, $offset);

        $urls = [
            route('app'), route('app', ['tab' => 'today']), route('today'), route('schedule'), route('weekplan'),
            route('planner'), route('crafts'), route('progress'),
            route('settings'), route('help'), route('support'), route('onboarding'),
            route('admin.announcements'), route('admin.help'), route('admin.support'), route('admin.errors'),
            route('profile.edit'), route('docs.api'), route('docs.mcp'),
        ];

        foreach ($urls as $url) {
            $response = $this->actingAs($user)->get($url);
            $this->assertLessThan(400, $response->getStatusCode(), "{$concept} utc{$offset}: GET {$url} → {$response->getStatusCode()}");
        }
    }
}
