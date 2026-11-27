<?php

namespace Tests\Feature\Api;

use App\Models\EventCategory;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\User;
use App\Services\Planning\BoardOrder;
use App\Services\Projects\ProjectEditor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Api parity (2026-11-27): what the new app can do and REST could not yet — the crunch screen's
 * "Verschieben" and "Mehr Zeit", deleting a project or group, moving a task into or out of one,
 * the stage order, and the shared board order of cards.
 * Monday 9 November 2026, 12:00 in Zurich.
 */
class ApiParityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-09 12:00', 'Europe/Zurich'));
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
        Sanctum::actingAs($this->user);
    }

    private function task(string $title, array $extra = [], ?User $owner = null): Task
    {
        return Task::forceCreate(array_merge(['user_id' => ($owner ?? $this->user)->id, 'title' => $title, 'list' => 'tasks'], $extra));
    }

    private function project(string $name = 'Umzug'): Project
    {
        return app(ProjectEditor::class)->createProject($this->user, $name);
    }

    private function group(string $name = 'Steuern', string $list = 'tasks'): TaskGroup
    {
        return app(ProjectEditor::class)->createGroup($this->user, $name, $list);
    }

    // ── Verschieben ────────────────────────────────────────────────────────

    public function test_move_with_a_date_moves_the_hard_deadline(): void
    {
        $task = $this->task('Steuererklärung', ['hard_deadline' => '2026-11-12']);

        $this->postJson("/api/tasks/{$task->id}/move", ['date' => '2026-11-20'])
            ->assertOk()->assertJsonPath('data.hard_deadline', '2026-11-20');
        $this->assertSame('2026-11-20', $task->fresh()->hardDeadline()->toDateString());
    }

    public function test_move_without_a_date_moves_the_soft_deadline_a_week_on(): void
    {
        $task = $this->task('Blog', ['soft_deadline' => '2026-11-10']);

        $this->postJson("/api/tasks/{$task->id}/move")->assertOk()->assertJsonPath('data.soft_deadline', '2026-11-17');
    }

    public function test_move_refuses_what_the_app_refuses(): void
    {
        $soft = $this->task('Nur weich', ['soft_deadline' => '2026-11-10']);
        $none = $this->task('Ohne Datum');
        $hard = $this->task('Hart', ['hard_deadline' => '2026-11-12']);

        // A hard move needs a hard deadline; the date has to lie after the current one.
        $this->postJson("/api/tasks/{$soft->id}/move", ['date' => '2026-11-20'])->assertStatus(422)->assertJsonValidationErrors('date');
        $this->postJson("/api/tasks/{$none->id}/move")->assertStatus(422);
        $this->postJson("/api/tasks/{$hard->id}/move", ['date' => '2026-11-12'])->assertStatus(422)->assertJsonValidationErrors('date');
        $this->postJson("/api/tasks/{$hard->id}/move", ['date' => 'tomorrow'])->assertStatus(422);
        $this->assertSame('2026-11-12', $hard->fresh()->hardDeadline()->toDateString());
    }

    public function test_move_is_scoped_to_the_owner(): void
    {
        $foreign = $this->task('Fremd', ['hard_deadline' => '2026-11-12'], User::factory()->create());

        $this->postJson("/api/tasks/{$foreign->id}/move", ['date' => '2026-11-20'])->assertNotFound();
        $this->assertSame('2026-11-12', $foreign->fresh()->hardDeadline()->toDateString());
    }

    // ── Mehr Zeit ──────────────────────────────────────────────────────────

    public function test_more_time_adds_a_one_off_block_of_a_pomodoro_category(): void
    {
        $deep = EventCategory::factory()->for($this->user)->pomodoro()->create(['name' => 'Deep work']);

        $id = $this->postJson('/api/forecast/more-time', ['date' => '2026-11-11', 'start_time' => '19:00', 'end_time' => '20:30', 'category_id' => $deep->id])
            ->assertCreated()->assertJsonPath('data.category_id', $deep->id)->assertJsonPath('data.date', '2026-11-11')->json('data.id');

        $this->assertDatabaseHas('schedule_events', ['id' => $id, 'user_id' => $this->user->id, 'template_id' => null]);
        // Undo is the ordinary delete.
        $this->deleteJson("/api/schedule-events/{$id}")->assertNoContent();
    }

    public function test_more_time_validates_like_the_screen(): void
    {
        $deep = EventCategory::factory()->for($this->user)->pomodoro()->create();
        $plain = EventCategory::factory()->for($this->user)->create(['pomodoro_enabled' => false]);
        $foreign = EventCategory::factory()->for(User::factory()->create())->pomodoro()->create();
        $ok = ['date' => '2026-11-11', 'start_time' => '19:00', 'end_time' => '20:00', 'category_id' => $deep->id];

        $this->postJson('/api/forecast/more-time', ['date' => '2026-11-08'] + $ok)->assertStatus(422)->assertJsonValidationErrors('date');
        $this->postJson('/api/forecast/more-time', ['date' => '2026-12-30'] + $ok)->assertStatus(422)->assertJsonValidationErrors('date');
        $this->postJson('/api/forecast/more-time', ['category_id' => $plain->id] + $ok)->assertStatus(422)->assertJsonValidationErrors('category_id');
        $this->postJson('/api/forecast/more-time', ['category_id' => $foreign->id] + $ok)->assertStatus(422)->assertJsonValidationErrors('category_id');
        $this->postJson('/api/forecast/more-time', ['end_time' => '18:00'] + $ok)->assertStatus(422)->assertJsonValidationErrors('end_time');
        $this->postJson('/api/forecast/more-time', [])->assertStatus(422);
        $this->assertDatabaseCount('schedule_events', 0);
    }

    // ── Delete a project or group ──────────────────────────────────────────

    public function test_deleting_a_project_keeps_every_step_on_the_board(): void
    {
        $project = $this->project();
        $editor = app(ProjectEditor::class);
        $stage = $editor->addStage($project, 'Planung', null);
        $open = $editor->addProjectStep($this->user, $project, $stage, 'Kisten holen');
        $done = $editor->addProjectStep($this->user, $project, null, 'Kündigen');
        $done->update(['is_completed' => true, 'completed_at' => now()]);
        $other = $this->project('Anderes');

        $this->deleteJson("/api/projects/{$project->id}")->assertNoContent();

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        $this->assertDatabaseMissing('project_stages', ['id' => $stage->id]);
        $this->assertNull($open->fresh()->project_id);
        $this->assertNull($open->fresh()->stage_id);
        $this->assertTrue($done->fresh()->is_completed, 'finished steps stay too');
        $this->assertNotNull(Project::find($other->id));
        $this->getJson("/api/projects/{$project->id}")->assertNotFound();
    }

    public function test_deleting_a_group_keeps_its_tasks_and_a_stranger_cannot_delete_it(): void
    {
        $group = $this->group();
        $step = app(ProjectEditor::class)->addGroupStep($this->user, $group, 'Belege sammeln');

        $stranger = User::factory()->create();
        Sanctum::actingAs($stranger);
        $this->deleteJson("/api/task-groups/{$group->id}")->assertNotFound();
        $this->deleteJson("/api/projects/{$group->id}")->assertNotFound();

        Sanctum::actingAs($this->user);
        $this->deleteJson("/api/task-groups/{$group->id}")->assertNoContent();
        $this->assertDatabaseMissing('task_groups', ['id' => $group->id]);
        $this->assertNull($step->fresh()->group_id);
        $this->assertNotNull(Task::find($step->id));
    }

    // ── Into a project or group, and out again ─────────────────────────────

    public function test_a_board_task_moves_into_a_project_stage_and_out_again(): void
    {
        $project = $this->project();
        $stage = app(ProjectEditor::class)->addStage($project, 'Planung', null);
        $task = $this->task('Kisten');

        $this->putJson("/api/tasks/{$task->id}/bundle", ['project_id' => $project->id, 'stage_id' => $stage->id])
            ->assertOk()->assertJsonPath('data.project_id', $project->id)->assertJsonPath('data.stage_id', $stage->id);

        $this->putJson("/api/tasks/{$task->id}/bundle", ['project_id' => null])
            ->assertOk()->assertJsonPath('data.project_id', null)->assertJsonPath('data.stage_id', null);
        $this->assertSame('tasks', $task->fresh()->list, 'a released step keeps its list');
    }

    public function test_a_task_moves_into_a_group_of_its_own_list_only(): void
    {
        $group = $this->group('Steuern', 'tasks');
        $fits = $this->task('Belege');
        $wrongList = $this->task('Telefon', ['list' => 'todos']);

        $this->putJson("/api/tasks/{$fits->id}/bundle", ['group_id' => $group->id])->assertOk()->assertJsonPath('data.group_id', $group->id);
        $this->putJson("/api/tasks/{$wrongList->id}/bundle", ['group_id' => $group->id])->assertStatus(422)->assertJsonValidationErrors('group_id');
        $this->assertNull($wrongList->fresh()->group_id);
    }

    public function test_the_bundle_call_refuses_unclear_or_foreign_requests(): void
    {
        $project = $this->project();
        $group = $this->group();
        $task = $this->task('Kisten');
        $foreignProject = app(ProjectEditor::class)->createProject(User::factory()->create(), 'Fremd');
        $foreignStage = ProjectStage::forceCreate(['project_id' => $foreignProject->id, 'name' => 'x', 'sort_order' => 0]);
        $recurring = $this->task('Täglich', ['repeat_rule' => 'daily']);

        $this->putJson("/api/tasks/{$task->id}/bundle", [])->assertStatus(422);
        $this->putJson("/api/tasks/{$task->id}/bundle", ['project_id' => $project->id, 'group_id' => $group->id])->assertStatus(422);
        $this->putJson("/api/tasks/{$task->id}/bundle", ['project_id' => $foreignProject->id])->assertNotFound();
        $this->putJson("/api/tasks/{$task->id}/bundle", ['project_id' => $project->id, 'stage_id' => $foreignStage->id])->assertNotFound();
        $this->putJson("/api/tasks/{$recurring->id}/bundle", ['project_id' => $project->id])->assertStatus(422);
        $this->assertNull($task->fresh()->project_id);

        // A step of one project cannot jump to another without being released first.
        $this->putJson("/api/tasks/{$task->id}/bundle", ['project_id' => $project->id])->assertOk();
        $other = $this->project('Zweites');
        $this->putJson("/api/tasks/{$task->id}/bundle", ['project_id' => $other->id])->assertStatus(422);
        $this->assertSame($project->id, $task->fresh()->project_id);
    }

    // ── Stage order ────────────────────────────────────────────────────────

    public function test_a_stage_moves_in_the_roadmap_with_position(): void
    {
        $project = $this->project();
        $editor = app(ProjectEditor::class);
        $a = $editor->addStage($project, 'A', null);
        $b = $editor->addStage($project, 'B', null);
        $c = $editor->addStage($project, 'C', null);

        $this->patchJson("/api/stages/{$c->id}", ['position' => 0])
            ->assertOk()->assertJsonPath('move.changed', true)->assertJsonPath('move.from_index', 2)->assertJsonPath('move.to_index', 0);
        $this->assertSame([$c->id, $a->id, $b->id], $project->stages()->pluck('id')->all());

        // The same place again, and a rename on its own, change nothing in the order.
        $this->patchJson("/api/stages/{$c->id}", ['position' => 0])->assertOk()->assertJsonPath('move.changed', false);
        $this->patchJson("/api/stages/{$a->id}", ['name' => 'A2'])->assertOk()->assertJsonMissingPath('move');
        $this->patchJson("/api/stages/{$a->id}", ['position' => -1])->assertStatus(422);
    }

    // ── Shared board order ─────────────────────────────────────────────────

    public function test_projects_groups_and_tasks_carry_their_place_in_the_board_order(): void
    {
        $project = $this->project();
        $group = $this->group();
        $task = $this->task('Lose');

        $this->getJson("/api/projects/{$project->id}")->assertJsonPath('data.ref', 'project:'.$project->id)->assertJsonPath('data.board_position', $project->fresh()->board_position);
        $this->getJson("/api/task-groups/{$group->id}")->assertJsonPath('data.ref', 'group:'.$group->id);
        $this->getJson("/api/tasks/{$task->id}")->assertJsonPath('data.board_position', $task->fresh()->board_position);
    }

    public function test_the_board_order_swaps_cards_and_tasks_and_leaves_the_rest(): void
    {
        $project = $this->project();
        $group = $this->group();
        $task = $this->task('Lose');
        $rest = $this->task('Andere');
        [$pp, $pg, $pt, $pr] = [$project->fresh()->board_position, $group->fresh()->board_position, $task->fresh()->board_position, $rest->fresh()->board_position];

        $this->postJson('/api/board/order', ['refs' => ['task:'.$task->id, 'group:'.$group->id, 'project:'.$project->id, 'task:99999']])
            ->assertOk()->assertJsonPath('data.changed', 2);

        $this->assertSame($pp, $task->fresh()->board_position);
        $this->assertSame($pt, $project->fresh()->board_position);
        $this->assertSame($pg, $group->fresh()->board_position);
        $this->assertSame($pr, $rest->fresh()->board_position);
    }

    public function test_the_board_order_ignores_foreign_entries_and_rejects_bad_refs(): void
    {
        $mine = $this->task('Meins');
        $foreign = $this->task('Fremd', [], User::factory()->create());
        $before = $foreign->fresh()->board_position;

        $this->postJson('/api/board/order', ['refs' => ['task:'.$foreign->id, 'task:'.$mine->id]])->assertOk()->assertJsonPath('data.changed', 0);
        $this->assertSame($before, $foreign->fresh()->board_position);
        $this->postJson('/api/board/order', ['refs' => ['nonsense']])->assertStatus(422);
        $this->postJson('/api/board/order', ['refs' => []])->assertStatus(422);
        $this->postJson('/api/board/order', [])->assertStatus(422);
    }

    public function test_bring_to_top_puts_an_entry_first_and_404s_for_strangers(): void
    {
        $first = $this->project();
        $second = $this->project('Zweites');
        $foreign = $this->task('Fremd', [], User::factory()->create());

        $this->postJson('/api/board/top', ['ref' => 'project:'.$second->id])->assertOk()->assertJsonPath('data.ref', 'project:'.$second->id);
        $this->assertLessThan($first->fresh()->board_position, $second->fresh()->board_position);

        $this->postJson('/api/board/top', ['ref' => 'task:'.$foreign->id])->assertNotFound();
        $this->postJson('/api/board/top', ['ref' => 'oops'])->assertStatus(422);
        $this->assertSame(BoardOrder::ref($first), 'project:'.$first->id);
    }

    public function test_the_new_routes_need_a_token(): void
    {
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/board/order', ['refs' => ['task:1']])->assertUnauthorized();
        $this->deleteJson('/api/projects/1')->assertUnauthorized();
    }
}
