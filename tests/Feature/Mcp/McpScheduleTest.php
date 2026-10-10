<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpServer;
use App\Models\CategoryAttribute;
use App\Models\EventCategory;
use App\Models\EventTemplate;
use App\Models\Project;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** The Zeitplan side of the MCP: events, focus timer, templates, weekly plan, categories, pauses, day frame. */
class McpScheduleTest extends TestCase
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

    private function write(): callable
    {
        return $this->can([McpAbility::READ, McpAbility::WRITE]);
    }

    private function full(): callable
    {
        return $this->can([McpAbility::READ, McpAbility::WRITE, McpAbility::DELETE]);
    }

    private function tool(User $user, string $tool, array $args = [], ?callable $can = null): array
    {
        return $this->server->call($user, $can ?? $this->full(), $tool, $args);
    }

    // ── Events ────────────────────────────────────────────────────────

    public function test_create_list_update_and_delete_an_appointment_with_buffers(): void
    {
        $user = User::factory()->create();

        $event = $this->tool($user, 'create_schedule_event', [
            'kind' => 'appointment', 'title' => 'Zahnarzt', 'color' => 'contour',
            'date' => '2026-11-03', 'start_time' => '10:00', 'end_time' => '11:00',
            'buffer_before' => 20, 'buffer_after' => 10,
        ]);

        $this->assertSame(20, $event['buffer_before']);
        $this->assertSame(10, $event['buffer_after']);

        $list = $this->tool($user, 'list_schedule_events', ['date' => '2026-11-03']);
        $this->assertSame(1, $list['count']);
        $this->assertSame('Zahnarzt', $list['events'][0]['title']);

        $moved = $this->tool($user, 'update_schedule_event', ['id' => $event['id'], 'start_time' => '14:00', 'end_time' => '15:00', 'buffer_before' => 0]);
        $this->assertSame('14:00', $moved['start_time']);
        $this->assertSame(0, $moved['buffer_before']);

        try {
            $this->tool($user, 'delete_schedule_event', ['id' => $event['id'], 'confirm_title' => 'falsch']);
            $this->fail('must refuse a wrong title');
        } catch (McpToolExecutionException) {
            $this->assertSame(1, ScheduleEvent::count());
        }

        $this->tool($user, 'delete_schedule_event', ['id' => $event['id'], 'confirm_title' => 'Zahnarzt']);
        $this->assertSame(0, ScheduleEvent::count());
    }

    public function test_update_refuses_an_end_before_the_stored_start(): void
    {
        $user = User::factory()->create();
        $event = ScheduleEvent::factory()->for($user)->on('2026-11-03')->at('10:00', '11:00')->create();

        try {
            $this->tool($user, 'update_schedule_event', ['id' => $event->id, 'end_time' => '09:00']);
            $this->fail('end before start must be refused');
        } catch (McpToolExecutionException $e) {
            $this->assertStringContainsString('after start_time', $e->getMessage());
        }
    }

    public function test_foreign_events_are_invisible(): void
    {
        $user = User::factory()->create();
        $foreign = ScheduleEvent::factory()->for(User::factory()->create())->create();

        $this->expectException(McpToolExecutionException::class);
        $this->tool($user, 'get_schedule_event', ['id' => $foreign->id]);
    }

    public function test_a_category_block_carries_attribute_values_and_linked_tasks(): void
    {
        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->create(['name' => 'Training']);
        $type = $this->tool($user, 'create_category_attribute', [
            'category_id' => $category->id, 'name' => 'Typ', 'type' => 'select',
            'options' => [['label' => 'Lauf', 'color' => 'forest'], ['label' => 'Kraft']],
        ]);
        $dur = $this->tool($user, 'create_category_attribute', ['category_id' => $category->id, 'name' => 'Dauer', 'type' => 'number', 'unit' => 'Min']);
        $task = Task::factory()->for($user)->todos()->create();

        $event = $this->tool($user, 'create_schedule_event', [
            'kind' => 'category', 'category_id' => $category->id,
            'date' => '2026-11-04', 'start_time' => '17:00', 'end_time' => '18:00',
            'attribute_values' => [(string) $type['id'] => 'Lauf', (string) $dur['id'] => '60', '99999' => 'ignored'],
            'linked_task_ids' => [$task->id],
        ]);

        $this->assertSame([$task->id], $event['linked_task_ids']);
        $values = collect($event['attribute_values'])->keyBy('name');
        $this->assertSame('Lauf', $values['Typ']['value']);
        $this->assertSame('60', $values['Dauer']['value']);
        $this->assertCount(2, $values);

        // an invalid select value leaves no row
        $updated = $this->tool($user, 'update_schedule_event', ['id' => $event['id'], 'attribute_values' => [(string) $type['id'] => 'Schwimmen']]);
        $this->assertSame([], $updated['attribute_values']);
    }

    public function test_attribute_values_cannot_go_on_a_series(): void
    {
        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->create();

        $this->expectException(ValidationException::class);
        $this->tool($user, 'create_schedule_event', [
            'kind' => 'category', 'category_id' => $category->id, 'date' => '2026-11-04',
            'start_time' => '08:00', 'end_time' => '09:00', 'recurring' => true, 'attribute_values' => ['1' => 'x'],
        ]);
    }

    // ── Focus timer ───────────────────────────────────────────────────

    public function test_focus_session_can_be_started_and_stopped_only_on_a_pomodoro_block(): void
    {
        $user = User::factory()->create();
        $plain = EventCategory::factory()->for($user)->create();
        $pomo = EventCategory::factory()->for($user)->pomodoro()->create();
        $today = $user->localToday()->toDateString();

        $plainBlock = ScheduleEvent::factory()->for($user)->on($today)->create(['category_id' => $plain->id]);
        $pomoBlock = ScheduleEvent::factory()->for($user)->on($today)->create(['category_id' => $pomo->id]);

        try {
            $this->tool($user, 'control_focus_session', ['event_id' => $plainBlock->id, 'action' => 'start']);
            $this->fail('no timer on this block');
        } catch (McpToolExecutionException) {
        }

        $started = $this->tool($user, 'control_focus_session', ['event_id' => $pomoBlock->id, 'action' => 'start']);
        $this->assertSame('work', $started['pomodoro_phase']);

        $stopped = $this->tool($user, 'control_focus_session', ['event_id' => $pomoBlock->id, 'action' => 'stop']);
        $this->assertNull($stopped['pomodoro_phase']);

        $this->expectException(McpToolExecutionException::class);
        $this->tool($user, 'control_focus_session', ['event_id' => $pomoBlock->id, 'action' => 'dance']);
    }

    public function test_get_focus_session_is_empty_without_a_running_timer(): void
    {
        $user = User::factory()->create();

        $result = $this->tool($user, 'get_focus_session');

        $this->assertNull($result['focus_session']);
    }

    // ── Templates & weekly plan ───────────────────────────────────────

    public function test_apply_a_template_carries_its_buffers(): void
    {
        $user = User::factory()->create();
        $template = EventTemplate::factory()->for($user)->create([
            'name' => 'Nachhilfe', 'duration' => 60, 'default_start' => '16:00', 'buffer_before' => 15, 'buffer_after' => 5,
        ]);

        $event = $this->tool($user, 'apply_event_template', ['template_id' => $template->id, 'date' => '2026-11-05']);

        $this->assertSame('16:00', $event['start_time']);
        $this->assertSame('17:00', $event['end_time']);
        $this->assertSame(15, $event['buffer_before']);
        $this->assertFalse($event['is_recurring']);
    }

    public function test_week_plan_block_create_update_propagates_to_future_occurrences_but_not_today(): void
    {
        $user = User::factory()->create();
        $today = $user->localToday();

        // every weekday, so there is always a row for today and for tomorrow regardless of when this runs
        $block = $this->tool($user, 'create_week_plan_block', [
            'kind' => 'appointment', 'title' => 'Schule', 'color' => 'forest',
            'start_time' => '08:00', 'end_time' => '12:00', 'days' => [1, 2, 3, 4, 5, 6, 7],
        ]);
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], $block['recurrence_days']);

        ScheduleEvent::materializeRange($user, $today->copy(), $today->copy()->addDays(14));
        $this->assertSame(15, $user->scheduleEvents()->where('template_id', $block['id'])->count());

        $updated = $this->tool($user, 'update_week_plan_block', ['id' => $block['id'], 'start_time' => '09:00', 'end_time' => '12:30', 'buffer_before' => 30, 'days' => [1, 2, 3]]);
        $this->assertSame('09:00', $updated['default_start']);
        $this->assertSame(210, $updated['duration']);
        $this->assertSame([1, 2, 3], $updated['recurrence_days']);

        // today's occurrence is never rewritten or removed, even if today is a weekday that was dropped
        $todayRow = $user->scheduleEvents()->where('template_id', $block['id'])->whereDate('date', $today->toDateString())->first();
        $this->assertNotNull($todayRow);
        $this->assertSame('08:00', $todayRow->start_time);

        $future = $user->scheduleEvents()->where('template_id', $block['id'])->whereDate('date', '>', $today->toDateString())->get();
        $this->assertNotEmpty($future);
        $this->assertTrue($future->every(fn ($e) => $e->date->dayOfWeekIso <= 3), 'dropped weekdays are removed from the future');
        $this->assertTrue($future->every(fn ($e) => $e->start_time === '09:00' && $e->end_time === '12:30' && (int) $e->buffer_before === 30));
    }

    public function test_week_plan_block_rejects_an_end_before_start_and_a_foreign_template(): void
    {
        $user = User::factory()->create();
        $theirs = EventTemplate::factory()->for(User::factory()->create())->recurring()->create();

        try {
            $this->tool($user, 'create_week_plan_block', ['kind' => 'appointment', 'title' => 'x', 'color' => 'ink', 'start_time' => '10:00', 'end_time' => '09:00', 'days' => [1]]);
            $this->fail('end before start');
        } catch (ValidationException) {
        }

        $this->expectException(McpToolExecutionException::class);
        $this->tool($user, 'update_week_plan_block', ['id' => $theirs->id, 'start_time' => '09:00']);
    }

    public function test_deleting_a_template_needs_the_name_and_removes_the_series(): void
    {
        $user = User::factory()->create();
        $block = $this->tool($user, 'create_week_plan_block', ['kind' => 'appointment', 'title' => 'Chor', 'color' => 'ink', 'start_time' => '19:00', 'end_time' => '20:00', 'days' => [2]]);

        try {
            $this->tool($user, 'delete_event_template', ['id' => $block['id'], 'confirm_name' => 'x']);
            $this->fail('must refuse');
        } catch (McpToolExecutionException) {
        }

        $this->tool($user, 'delete_event_template', ['id' => $block['id'], 'confirm_name' => 'Chor']);
        $this->assertSame(0, EventTemplate::count());
    }

    // ── Categories & attributes ───────────────────────────────────────

    public function test_category_crud_task_link_and_delete_confirmation(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $category = $this->tool($user, 'create_category', ['name' => 'Lernen', 'color' => 'forest', 'pomodoro_enabled' => true]);
        $this->tool($user, 'update_category', ['id' => $category['id'], 'name' => 'Lernen & Co']);

        $linked = $this->tool($user, 'set_category_task_link', ['category_id' => $category['id'], 'source' => 'project', 'project_id' => $project->id]);
        $this->assertSame('project', $linked['task_source']);
        $this->assertSame($project->id, $linked['linked_project_id']);

        $listed = collect($this->tool($user, 'list_categories')['categories'])->firstWhere('id', $category['id']);
        $this->assertSame($project->id, $listed['linked_project_id']);

        $cleared = $this->tool($user, 'clear_category_task_link', ['category_id' => $category['id']]);
        $this->assertNull($cleared['task_source']);

        try {
            $this->tool($user, 'delete_category', ['id' => $category['id'], 'confirm_name' => 'Lernen']);
            $this->fail('old name must not confirm');
        } catch (McpToolExecutionException) {
        }
        $this->tool($user, 'delete_category', ['id' => $category['id'], 'confirm_name' => 'Lernen & Co']);
        $this->assertSame(0, EventCategory::count());
    }

    public function test_task_link_is_refused_for_a_category_without_pomodoro(): void
    {
        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->create();

        $this->expectException(ValidationException::class);
        $this->tool($user, 'set_category_task_link', ['category_id' => $category->id, 'source' => 'text', 'text' => 'x']);
    }

    public function test_attribute_crud_select_needs_options_and_delete_needs_the_name(): void
    {
        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->create();

        try {
            $this->tool($user, 'create_category_attribute', ['category_id' => $category->id, 'name' => 'Typ', 'type' => 'select', 'options' => []]);
            $this->fail('select needs options');
        } catch (ValidationException) {
        }

        $attr = $this->tool($user, 'create_category_attribute', ['category_id' => $category->id, 'name' => 'Intensität', 'type' => 'select', 'options' => [['label' => 'leicht'], ['label' => 'hart', 'color' => 'signal']]]);
        $this->assertSame('hart', $attr['options'][1]['label']);

        $renamed = $this->tool($user, 'update_category_attribute', ['category_id' => $category->id, 'attribute_id' => $attr['id'], 'name' => 'Härte']);
        $this->assertSame('Härte', $renamed['name']);
        $this->assertCount(2, $renamed['options'], 'options survive a plain rename');

        $this->tool($user, 'update_category_attribute', ['category_id' => $category->id, 'attribute_id' => $attr['id'], 'type' => 'checkbox']);
        $this->assertSame([], CategoryAttribute::find($attr['id'])->optionsList());

        try {
            $this->tool($user, 'delete_category_attribute', ['category_id' => $category->id, 'attribute_id' => $attr['id'], 'confirm_name' => 'nope']);
            $this->fail('must refuse');
        } catch (McpToolExecutionException) {
        }
        $this->tool($user, 'delete_category_attribute', ['category_id' => $category->id, 'attribute_id' => $attr['id'], 'confirm_name' => 'Härte']);
        $this->assertSame(0, CategoryAttribute::count());
    }

    public function test_attributes_of_a_foreign_category_are_unreachable(): void
    {
        $user = User::factory()->create();
        $theirs = EventCategory::factory()->for(User::factory()->create())->create();

        $this->expectException(McpToolExecutionException::class);
        $this->tool($user, 'create_category_attribute', ['category_id' => $theirs->id, 'name' => 'x', 'type' => 'text']);
    }

    // ── Pauses & day frame ────────────────────────────────────────────

    public function test_pause_list_and_unpause_a_day_inside_a_range(): void
    {
        $user = User::factory()->create();
        $this->tool($user, 'create_week_plan_block', ['kind' => 'appointment', 'title' => 'Schule', 'color' => 'ink', 'start_time' => '08:00', 'end_time' => '09:00', 'days' => [1, 2, 3, 4, 5, 6, 7]]);

        $this->tool($user, 'pause_schedule', ['from' => '2027-01-04', 'to' => '2027-01-08', 'note' => 'Ferien']);
        $pauses = $this->tool($user, 'list_schedule_pauses')['pauses'];
        $this->assertSame([['from' => '2027-01-04', 'to' => '2027-01-08', 'note' => 'Ferien']], $pauses);
        $this->assertSame(0, $this->tool($user, 'list_schedule_events', ['date' => '2027-01-05'])['count']);

        $this->tool($user, 'unpause_schedule', ['from' => '2027-01-06', 'to' => '2027-01-06']);
        $this->assertSame(1, $this->tool($user, 'list_schedule_events', ['date' => '2027-01-06'])['count']);
        $this->assertCount(2, $this->tool($user, 'list_schedule_pauses')['pauses']);
    }

    public function test_pause_rejects_a_range_longer_than_a_year(): void
    {
        $user = User::factory()->create();

        $this->expectException(ValidationException::class);
        $this->tool($user, 'pause_schedule', ['from' => '2027-01-01', 'to' => '2028-06-01']);
    }

    public function test_day_frame_default_weekday_and_date_precedence(): void
    {
        $user = User::factory()->create();

        $this->tool($user, 'set_day_frame', ['scope' => 'default', 'start' => '07:00', 'end' => '22:00']);
        $this->tool($user, 'set_day_frame', ['scope' => 'weekday', 'weekday' => 6, 'start' => '09:00', 'end' => '23:00']);
        $this->tool($user, 'set_day_frame', ['scope' => 'date', 'date' => '2026-11-07', 'start' => '10:00', 'end' => '20:00']);

        $frame = $this->tool($user, 'get_day_frame', ['date' => '2026-11-07']); // a Saturday
        $this->assertSame('07:00', $frame['default']['start']);
        $this->assertSame('weekday', $frame['weekdays'][6]['source']);
        $this->assertSame('date', $frame['date']['source']);
        $this->assertSame('10:00', $frame['date']['start']);

        $this->tool($user, 'set_day_frame', ['scope' => 'date', 'date' => '2026-11-07', 'reset' => true]);
        $this->assertSame('weekday', $this->tool($user, 'get_day_frame', ['date' => '2026-11-07'])['date']['source']);
    }

    public function test_day_frame_validation(): void
    {
        $user = User::factory()->create();

        foreach ([
            ['scope' => 'default', 'start' => '10:00', 'end' => '11:00'],
            ['scope' => 'default', 'start' => '12:00', 'end' => '08:00'],
            ['scope' => 'weekday', 'weekday' => 9, 'start' => '08:00', 'end' => '20:00'],
            ['scope' => 'default', 'start' => '08:00'],
        ] as $args) {
            try {
                $this->tool($user, 'set_day_frame', $args);
                $this->fail('expected a validation error for '.json_encode($args));
            } catch (ValidationException) {
            }
        }

        $this->expectException(McpToolExecutionException::class);
        $this->tool($user, 'set_day_frame', ['scope' => 'default', 'reset' => true]);
    }

    // ── Gating ────────────────────────────────────────────────────────

    public function test_hiding_modules_hides_the_matching_tools(): void
    {
        $user = User::factory()->create(['hidden_modules' => ['schedule', 'weekplan']]);

        $names = collect($this->server->availableTools($user->fresh(), $this->full()))->map->name()->all();

        foreach (['list_schedule_events', 'create_category', 'set_day_frame', 'create_week_plan_block', 'pause_schedule', 'list_schedule_pauses'] as $tool) {
            $this->assertNotContains($tool, $names, $tool);
        }
        $this->assertContains('create_task', $names);
    }

    public function test_write_only_token_never_sees_the_destructive_schedule_tools(): void
    {
        $user = User::factory()->create();

        $names = collect($this->server->availableTools($user, $this->write()))->map->name()->all();

        foreach (['delete_schedule_event', 'delete_event_template', 'delete_category', 'delete_category_attribute'] as $tool) {
            $this->assertNotContains($tool, $names, $tool);
        }
        $this->assertContains('create_schedule_event', $names);
    }
}
