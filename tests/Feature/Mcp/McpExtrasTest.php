<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpServer;
use App\Models\AgendaEntry;
use App\Models\CraftIdea;
use App\Models\HelpArticle;
use App\Models\Project;
use App\Models\SupportRequest;
use App\Models\Task;
use App\Models\TaskDayPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Planer, Notfallmodus, Bastelideen, overviews, settings, help & support. */
class McpExtrasTest extends TestCase
{
    use RefreshDatabase;

    private McpServer $server;

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = app(McpServer::class);
    }

    private function can(array $abilities): callable
    {
        return fn (string $a) => in_array($a, $abilities, true);
    }

    private function full(): callable
    {
        return $this->can([McpAbility::READ, McpAbility::WRITE, McpAbility::DELETE]);
    }

    private function tool(User $user, string $tool, array $args = [], ?callable $can = null): array
    {
        return $this->server->call($user, $can ?? $this->full(), $tool, $args);
    }

    // ── Planer ────────────────────────────────────────────────────────

    public function test_planner_tools_exist_only_while_the_planner_is_switched_on(): void
    {
        $user = User::factory()->create(['planner_enabled' => false]);
        $planner = ['get_planner', 'plan_task', 'set_day_plan', 'unplan_task', 'auto_plan_backlog', 'add_standard_task'];

        $off = collect($this->server->availableTools($user, $this->full()))->map->name()->all();
        foreach ($planner as $name) {
            $this->assertNotContains($name, $off, $name);
        }

        $this->tool($user, 'update_settings', ['planner_enabled' => true]);

        $on = collect($this->server->availableTools($user->fresh(), $this->full()))->map->name()->all();
        foreach ($planner as $name) {
            $this->assertContains($name, $on, $name);
        }
    }

    public function test_plan_a_task_read_the_board_move_it_and_release_it(): void
    {
        $user = User::factory()->create(['planner_enabled' => true]);
        $today = $user->localToday();
        $task = Task::factory()->for($user)->todos()->create(['duration_minutes' => 30]);

        $backlog = $this->tool($user, 'get_planner')['backlog'];
        $this->assertSame([$task->id], collect($backlog)->where('type', 'task')->pluck('id')->all());

        $tomorrow = $today->copy()->addDay()->toDateString();
        $this->tool($user, 'plan_task', ['task_id' => $task->id, 'date' => $tomorrow]);

        $planner = $this->tool($user, 'get_planner');
        $this->assertSame([], collect($planner['backlog'])->where('type', 'task')->all());
        $day = collect($planner['days'])->firstWhere('date', $tomorrow);
        $this->assertSame(30, $day['planned_minutes']);
        $this->assertSame($task->id, $day['tasks'][0]['id']);

        $later = $today->copy()->addDays(3)->toDateString();
        $this->tool($user, 'plan_task', ['task_id' => $task->id, 'date' => $later]);
        $this->assertSame(1, TaskDayPlan::count(), 'a task lives on one day only');

        $this->tool($user, 'unplan_task', ['task_id' => $task->id]);
        $this->assertSame(0, TaskDayPlan::count());
    }

    public function test_planning_for_today_flags_the_task_heute(): void
    {
        $user = User::factory()->create(['planner_enabled' => true]);
        $task = Task::factory()->for($user)->todos()->create();

        $this->tool($user, 'plan_task', ['task_id' => $task->id, 'date' => $user->localToday()->toDateString()]);

        $this->assertTrue($task->fresh()->is_today);
    }

    public function test_plan_task_validates_the_date_and_the_reference(): void
    {
        $user = User::factory()->create(['planner_enabled' => true]);
        $task = Task::factory()->for($user)->todos()->create();
        $foreign = Task::factory()->for(User::factory()->create())->todos()->create();
        $today = $user->localToday();

        foreach ([$today->copy()->subDay()->toDateString(), $today->copy()->addDays(30)->toDateString(), '2026-02-31', 'morgen'] as $bad) {
            try {
                $this->tool($user, 'plan_task', ['task_id' => $task->id, 'date' => $bad]);
                $this->fail("{$bad} must be refused");
            } catch (ValidationException) {
            }
        }

        $this->assertSame(0, TaskDayPlan::count());

        $this->expectException(McpToolExecutionException::class);
        $this->tool($user, 'plan_task', ['task_id' => $foreign->id, 'date' => $today->toDateString()]);
    }

    public function test_homework_is_promoted_into_a_task_when_planned_and_set_day_plan_orders_a_day(): void
    {
        $user = User::factory()->create(['planner_enabled' => true]);
        $entry = AgendaEntry::factory()->for($user)->create(['type' => 'homework', 'date' => $user->localToday()->addDays(5), 'duration_minutes' => 40]);
        $a = Task::factory()->for($user)->todos()->create();
        $date = $user->localToday()->addDays(2)->toDateString();

        $result = $this->tool($user, 'set_day_plan', ['date' => $date, 'items' => [['agenda_entry_id' => $entry->id], ['task_id' => $a->id]]]);

        $this->assertSame(2, $result['planned']);
        $promoted = Task::where('agenda_entry_id', $entry->id)->first();
        $this->assertNotNull($promoted);
        $this->assertSame(40, $promoted->duration_minutes);
        $this->assertSame([$promoted->id, $a->id], TaskDayPlan::orderBy('sort_order')->pluck('task_id')->all());

        $this->expectException(McpToolExecutionException::class);
        $this->tool($user, 'plan_task', ['task_id' => $a->id, 'agenda_entry_id' => $entry->id, 'date' => $date]);
    }

    public function test_auto_plan_places_backlog_items_and_standard_tasks_are_ordinary_tasks(): void
    {
        $user = User::factory()->create(['planner_enabled' => true]);
        Task::factory()->for($user)->todos()->create(['due_date' => $user->localToday()->addDays(6)]);

        // auto-plan only fills days that have Pomodoro capacity — a day without such a block stays untouched
        $this->assertSame(0, $this->tool($user, 'auto_plan_backlog')['placed']);

        $category = $this->tool($user, 'create_category', ['name' => 'Lernen', 'color' => 'forest', 'pomodoro_enabled' => true]);
        $this->tool($user, 'create_schedule_event', [
            'kind' => 'category', 'category_id' => $category['id'], 'date' => $user->localToday()->addDay()->toDateString(),
            'start_time' => '16:00', 'end_time' => '18:00',
        ]);

        $auto = $this->tool($user, 'auto_plan_backlog');
        $this->assertSame(0, $auto['still_in_backlog']);
        $this->assertSame(1, $auto['placed']);

        $std = $this->tool($user, 'add_standard_task', [
            'template' => 'study', 'date' => $user->localToday()->addDay()->toDateString(),
            'duration_minutes' => 45, 'study_mode' => 'subject', 'subject' => 'Mathe',
        ]);
        $this->assertSame('Lernen: Mathe', $std['title']);
        $this->assertSame(45, Task::find($std['task_id'])->duration_minutes);
        $this->assertSame('tasks', Task::find($std['task_id'])->list);

        $this->expectException(McpToolExecutionException::class);
        $this->tool($user, 'add_standard_task', ['template' => 'study', 'date' => $user->localToday()->toDateString(), 'study_mode' => 'exam']);
    }

    // ── Notfallmodus ──────────────────────────────────────────────────

    public function test_emergency_mode_start_arrange_and_end(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Referat']);
        $a = Task::factory()->for($user)->create(['project_id' => $project->id, 'list' => 'projects']);
        $b = Task::factory()->for($user)->create(['project_id' => $project->id, 'list' => 'projects']);
        $other = Task::factory()->for($user)->create(['list' => 'projects', 'project_id' => Project::factory()->for($user)->create()->id]);

        $this->assertFalse($this->tool($user, 'get_emergency_mode')['active']);

        $this->tool($user, 'start_emergency_mode', ['project_id' => $project->id]);
        $state = $this->tool($user, 'get_emergency_mode');
        $this->assertTrue($state['active']);
        $this->assertSame('Referat', $state['project']['name']);
        $this->assertSame('tasks', $a->fresh()->emergency_list);

        $this->tool($user, 'arrange_emergency_tasks', ['project_id' => $project->id, 'tasks' => [['id' => $b->id, 'list' => 'todos'], ['id' => $a->id]]]);
        $ordered = $this->tool($user, 'get_emergency_mode')['tasks'];
        $this->assertSame([$b->id, $a->id], array_column($ordered, 'id'));
        $this->assertSame('todos', $ordered[0]['emergency_list']);

        try {
            $this->tool($user, 'arrange_emergency_tasks', ['project_id' => $project->id, 'tasks' => [['id' => $other->id]]]);
            $this->fail('a task of another project must be refused');
        } catch (McpToolExecutionException) {
        }

        $this->tool($user, 'end_emergency_mode');
        $this->assertFalse($this->tool($user, 'get_emergency_mode')['active']);
    }

    // ── Bastelideen ───────────────────────────────────────────────────

    public function test_craft_ideas_crud(): void
    {
        $user = User::factory()->create();

        $idea = $this->tool($user, 'create_craft_idea', ['title' => 'Lampe bauen', 'note' => 'Holz, Kabel']);
        $this->tool($user, 'create_craft_idea', ['title' => 'Regal']);

        $this->assertSame(2, $this->tool($user, 'list_craft_ideas')['count']);
        $this->assertSame(1, $this->tool($user, 'list_craft_ideas', ['search' => 'Holz'])['count']);

        $this->tool($user, 'update_craft_idea', ['id' => $idea['id'], 'is_done' => true, 'note' => null]);
        $this->assertSame(1, $this->tool($user, 'list_craft_ideas')['count']);
        $done = $this->tool($user, 'list_craft_ideas', ['done' => true])['ideas'];
        $this->assertSame('Lampe bauen', $done[0]['title']);
        $this->assertNull($done[0]['note']);

        try {
            $this->tool($user, 'delete_craft_idea', ['id' => $idea['id'], 'confirm_title' => 'x']);
            $this->fail('must refuse');
        } catch (McpToolExecutionException) {
        }
        $this->tool($user, 'delete_craft_idea', ['id' => $idea['id'], 'confirm_title' => 'Lampe bauen']);
        $this->assertSame(1, CraftIdea::count());
    }

    public function test_a_foreign_craft_idea_is_unreachable(): void
    {
        $user = User::factory()->create();
        $theirs = CraftIdea::factory()->for(User::factory()->create())->create();

        $this->expectException(McpToolExecutionException::class);
        $this->tool($user, 'update_craft_idea', ['id' => $theirs->id, 'title' => 'x']);
    }

    // ── Overviews ─────────────────────────────────────────────────────

    public function test_day_overview_collects_the_day_and_respects_hidden_modules(): void
    {
        $user = User::factory()->create();
        $today = $user->localToday();
        Task::factory()->for($user)->todos()->create(['is_today' => true, 'today_date' => $today]);
        Task::factory()->for($user)->todos()->create(['deadline' => $today->copy()->subDay(), 'title' => 'Überfällig']);
        AgendaEntry::factory()->for($user)->create(['type' => 'homework', 'date' => $today->copy()->addDay()]);
        $this->tool($user, 'create_schedule_event', ['kind' => 'appointment', 'title' => 'Arzt', 'color' => 'ink', 'date' => $today->toDateString(), 'start_time' => '09:00', 'end_time' => '10:00']);

        $o = $this->tool($user, 'get_day_overview');

        $this->assertSame($today->toDateString(), $o['date']);
        $this->assertCount(1, $o['today_tasks']);
        $this->assertSame('Arzt', $o['schedule'][0]['title']);
        $this->assertContains('overdue', array_column($o['due']['tasks'], 'bucket'));
        $this->assertCount(1, $o['due']['agenda']);
        $this->assertArrayHasKey('goal', $o);

        $user->update(['hidden_modules' => ['agenda', 'schedule', 'progress']]);
        $hidden = $this->tool($user->fresh(), 'get_day_overview');
        $this->assertArrayNotHasKey('schedule', $hidden);
        $this->assertArrayNotHasKey('agenda', $hidden['due']);
        $this->assertArrayNotHasKey('goal', $hidden);
    }

    public function test_week_review_returns_plain_dates(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->todos()->create();
        $this->tool($user, 'complete_task', ['id' => $task->id]);

        $review = $this->tool($user, 'get_week_review');

        $this->assertSame(1, $review['total']);
        $this->assertIsString($review['start']);
        $this->assertCount(7, $review['days']);
        $this->assertSame(0, $this->tool($user, 'get_week_review', ['weeks_ago' => 1])['total']);
    }

    public function test_reschedule_overdue_moves_only_soft_dates_and_reports_the_old_ones(): void
    {
        $user = User::factory()->create();
        $today = $user->localToday();
        $soft = Task::factory()->for($user)->todos()->create(['due_date' => $today->copy()->subDays(3)]);
        $hard = Task::factory()->for($user)->todos()->create(['deadline' => $today->copy()->subDays(3)]);
        $future = Task::factory()->for($user)->todos()->create(['due_date' => $today->copy()->addDays(3)]);

        $result = $this->tool($user, 'reschedule_overdue_tasks', ['when' => 'tomorrow']);

        $this->assertSame(1, $result['moved']);
        $this->assertSame($today->copy()->subDays(3)->toDateString(), $result['tasks'][0]['previous_due_date']);
        $this->assertSame($today->copy()->addDay()->toDateString(), $soft->fresh()->due_date->toDateString());
        $this->assertSame($today->copy()->subDays(3)->toDateString(), $hard->fresh()->deadline->toDateString());
        $this->assertSame($today->copy()->addDays(3)->toDateString(), $future->fresh()->due_date->toDateString());

        $this->tool($user, 'reschedule_overdue_tasks', ['when' => 'clear']);
        $this->assertNotNull($soft->fresh()->due_date, 'no longer overdue, so "clear" leaves it alone');

        try {
            $this->tool($user, 'reschedule_overdue_tasks', ['when' => '2020-01-01']);
            $this->fail('past dates must be refused');
        } catch (ValidationException) {
        }
    }

    // ── Settings ──────────────────────────────────────────────────────

    public function test_update_settings_changes_only_what_is_passed_and_get_settings_shows_it(): void
    {
        $user = User::factory()->create();

        $this->tool($user, 'update_settings', [
            'daily_task_goal' => 8, 'pomodoro_work' => 40, 'pomodoro_autostart' => true,
            'timezone_offset' => 1, 'timezone_auto_dst' => true,
            'notify_daily_reminder' => true, 'daily_reminder_time' => '18:30',
            'prepare_reminder_mode' => 'fixed', 'prepare_reminder_time' => '20:00',
        ]);

        $s = $this->tool($user, 'get_settings');
        $this->assertSame(8, $s['daily_task_goal']);
        $this->assertSame(40, $s['pomodoro_rhythm']['work']);
        $this->assertTrue($s['pomodoro_autostart']);
        $this->assertEquals(1, $s['timezone_offset']);
        $this->assertTrue($s['notifications']['daily_reminder']);
        $this->assertSame('18:30', $s['notifications']['daily_reminder_time']);
        $this->assertSame('fixed', $s['prepare']['reminder_mode']);
        $this->assertSame(5, $user->fresh()->pomodoro()['short_break'], 'untouched settings stay');
    }

    public function test_update_settings_validates_and_refuses_unknown_keys(): void
    {
        $user = User::factory()->create();

        foreach ([['daily_task_goal' => 99], ['daily_reminder_time' => '25:00'], ['prepare_time_of_day' => 'noon'], ['timezone_offset' => 20]] as $bad) {
            try {
                $this->tool($user, 'update_settings', $bad);
                $this->fail('expected a validation error for '.json_encode($bad));
            } catch (ValidationException) {
            }
        }

        foreach ([['is_admin' => true], ['password' => 'x'], []] as $bad) {
            try {
                $this->tool($user, 'update_settings', $bad);
                $this->fail('expected a refusal for '.json_encode($bad));
            } catch (McpToolExecutionException) {
            }
        }

        $this->assertFalse((bool) $user->fresh()->is_admin);
    }

    public function test_header_badges_can_be_reordered_and_toggled(): void
    {
        $user = User::factory()->create();

        $this->tool($user, 'update_settings', ['header_badges' => [['key' => 'goal', 'enabled' => true], ['key' => 'streak', 'enabled' => false]]]);

        $rows = $this->tool($user, 'get_settings')['header_badges'];
        $this->assertSame(['goal', 'streak'], array_slice(array_column($rows, 'key'), 0, 2));
        $this->assertTrue($rows[0]['enabled']);
        $this->assertFalse($rows[1]['enabled']);
        $this->assertCount(count(\App\Services\HeaderBadges::CATALOG), $rows, 'unlisted badges are kept');
    }

    public function test_turning_presence_off_clears_last_seen(): void
    {
        $user = User::factory()->create(['show_presence' => true, 'last_seen_at' => now()]);

        $this->tool($user, 'update_settings', ['show_presence' => false]);

        $this->assertNull($user->fresh()->last_seen_at);
    }

    // ── Help & support ────────────────────────────────────────────────

    public function test_help_search_and_read_only_expose_published_articles(): void
    {
        $user = User::factory()->create();
        HelpArticle::create(['title' => 'Aufgaben planen', 'slug' => 'aufgaben-planen', 'content' => 'So planst du im Planer.', 'is_published' => true, 'published_at' => now()]);
        $draft = HelpArticle::create(['title' => 'Planer Geheim', 'slug' => 'planer-geheim', 'content' => 'Planer Entwurf', 'is_published' => false]);

        $found = $this->tool($user, 'search_help', ['query' => 'Planer']);
        $this->assertSame(1, $found['count']);
        $this->assertSame('Aufgaben planen', $found['articles'][0]['title']);

        $this->assertStringContainsString('Planer', $this->tool($user, 'read_help_article', ['id' => $found['articles'][0]['id']])['content']);
        $this->assertArrayHasKey('categories', $this->tool($user, 'search_help'));

        $this->expectException(McpToolExecutionException::class);
        $this->tool($user, 'read_help_article', ['id' => $draft->id]);
    }

    public function test_support_requests_are_sent_and_listed_only_for_the_sender(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $sent = $this->tool($user, 'submit_support_request', ['subject' => 'Idee', 'message' => 'Dunkelmodus pro Seite']);
        $this->assertSame('open', $sent['status']);
        $this->tool($other, 'submit_support_request', ['type' => 'support', 'subject' => 'Hilfe', 'message' => 'Geht nicht']);

        $mine = $this->tool($user, 'list_my_support_requests')['requests'];
        $this->assertCount(1, $mine);
        $this->assertSame('Idee', $mine[0]['subject']);
        $this->assertSame(2, SupportRequest::count());

        $this->expectException(ValidationException::class);
        $this->tool($user, 'submit_support_request', ['subject' => '', 'message' => 'x']);
    }

    public function test_read_only_tokens_cannot_send_support_or_change_settings(): void
    {
        $user = User::factory()->create();
        $readOnly = $this->can([McpAbility::READ]);

        $names = collect($this->server->availableTools($user, $readOnly))->map->name()->all();

        foreach (['submit_support_request', 'update_settings', 'reschedule_overdue_tasks', 'create_craft_idea'] as $tool) {
            $this->assertNotContains($tool, $names, $tool);
        }
        $this->assertContains('search_help', $names);
        $this->assertContains('get_day_overview', $names);
    }
}
