<?php

namespace Tests\Feature\Api;

use App\Models\Note;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\Task;
use App\Models\User;
use App\Services\Projects\NoteEditor;
use App\Services\Projects\ProjectEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Steps 4.3 and 4.3b through REST: projects, stages, steps, groups, note cards, place and "waits on". */
class ProjectApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    private function editor(): ProjectEditor
    {
        return app(ProjectEditor::class);
    }

    // ── Projects and stages ─────────────────────────────────────────────

    public function test_a_project_is_created_renamed_and_listed_with_its_roadmap(): void
    {
        $id = $this->postJson('/api/projects', ['name' => '  Website  '])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Website')
            ->assertJsonPath('data.kind', 'project')
            ->assertJsonPath('data.stage_count', 0)
            ->assertJsonPath('data.current_stage', null)
            ->json('data.id');

        $this->patchJson("/api/projects/{$id}", ['name' => 'Neue Website'])->assertOk()->assertJsonPath('data.name', 'Neue Website');

        $first = $this->postJson("/api/projects/{$id}/stages", ['name' => 'Entwurf', 'target_date' => '2026-12-01'])
            ->assertCreated()->assertJsonPath('data.target_date', '2026-12-01')->json('data.id');
        $this->postJson("/api/projects/{$id}/stages", ['name' => 'Bau'])->assertCreated()->assertJsonPath('data.target_date', null);
        $this->postJson("/api/projects/{$id}/steps", ['title' => 'Wireframes', 'stage_id' => $first, 'list' => 'todos'])->assertCreated();

        $this->getJson('/api/projects')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.stage_count', 2)
            ->assertJsonPath('data.0.current_stage.name', 'Entwurf')
            ->assertJsonPath('data.0.current_stage.number', 1)
            ->assertJsonPath('data.0.progress', ['done' => 0, 'total' => 1])
            ->assertJsonPath('data.0.next_steps.0.title', 'Wireframes');

        $this->getJson("/api/projects/{$id}")->assertOk()
            ->assertJsonPath('data.stages.0.name', 'Entwurf')
            ->assertJsonPath('data.stages.0.open', 1)
            ->assertJsonPath('data.stages.1.open', 0)
            ->assertJsonPath('data.steps.0.stage_id', $first)
            ->assertJsonPath('data.steps.0.project_id', $id);
    }

    public function test_a_stage_is_renamed_dated_and_removed_without_losing_its_steps(): void
    {
        $project = $this->editor()->createProject($this->user, 'P');
        $stage = $this->editor()->addStage($project, 'Alt', '2026-12-01');
        $step = $this->editor()->addProjectStep($this->user, $project, $stage, 'Schritt');

        $this->patchJson("/api/stages/{$stage->id}", ['name' => 'Neu'])->assertOk()
            ->assertJsonPath('data.name', 'Neu')->assertJsonPath('data.target_date', '2026-12-01');
        $this->patchJson("/api/stages/{$stage->id}", ['target_date' => null])->assertOk()->assertJsonPath('data.target_date', null);

        $this->deleteJson("/api/stages/{$stage->id}")->assertNoContent();

        $this->assertDatabaseMissing('project_stages', ['id' => $stage->id]);
        $this->assertDatabaseHas('tasks', ['id' => $step->id, 'stage_id' => null, 'project_id' => $project->id]);
    }

    public function test_bad_input_gets_a_422_with_the_field(): void
    {
        $project = $this->editor()->createProject($this->user, 'P');
        $other = $this->editor()->addStage($this->editor()->createProject($this->user, 'Q'), 'Q1', null);

        $this->postJson('/api/projects', ['name' => ''])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson('/api/projects', ['name' => str_repeat('x', Project::NAME_MAX + 1)])->assertUnprocessable();
        $this->postJson("/api/projects/{$project->id}/stages", ['name' => 'x', 'target_date' => 'morgen'])->assertUnprocessable()->assertJsonValidationErrors('target_date');
        $this->postJson("/api/projects/{$project->id}/steps", ['title' => 'x', 'list' => 'inbox'])->assertUnprocessable()->assertJsonValidationErrors('list');
        // A stage of another project of mine is refused, not silently ignored.
        $this->postJson("/api/projects/{$project->id}/steps", ['title' => 'x', 'stage_id' => $other->id])->assertUnprocessable()->assertJsonValidationErrors('stage_id');
        $this->assertSame(0, Task::where('project_id', $project->id)->count());
    }

    // ── Steps: place and waits on ───────────────────────────────────────

    public function test_place_moves_a_step_to_another_stage_and_position_and_is_idempotent(): void
    {
        $project = $this->editor()->createProject($this->user, 'P');
        $one = $this->editor()->addStage($project, 'Eins', null);
        $two = $this->editor()->addStage($project, 'Zwei', null);
        $a = $this->editor()->addProjectStep($this->user, $project, $one, 'A');
        $b = $this->editor()->addProjectStep($this->user, $project, $one, 'B');
        $c = $this->editor()->addProjectStep($this->user, $project, $two, 'C');

        $this->putJson("/api/tasks/{$a->id}/place", ['stage_id' => $two->id, 'index' => 0])
            ->assertOk()
            ->assertJsonPath('data.move.changed', true)
            ->assertJsonPath('data.move.from_stage_id', $one->id)
            ->assertJsonPath('data.move.to_stage_id', $two->id)
            ->assertJsonPath('data.step.stage_id', $two->id);

        $this->assertSame([$a->id, $c->id], Task::where('stage_id', $two->id)->orderBy('sort_order')->pluck('id')->all());

        $this->putJson("/api/tasks/{$a->id}/place", ['stage_id' => $two->id, 'index' => 0])->assertOk()->assertJsonPath('data.move.changed', false);

        // No stage = null; the end is the default.
        $this->putJson("/api/tasks/{$b->id}/place", ['stage_id' => null])->assertOk()->assertJsonPath('data.step.stage_id', null);
    }

    public function test_place_refuses_a_stage_of_another_project_and_a_finished_step(): void
    {
        $project = $this->editor()->createProject($this->user, 'P');
        $elsewhere = $this->editor()->addStage($this->editor()->createProject($this->user, 'Q'), 'Q1', null);
        $step = $this->editor()->addProjectStep($this->user, $project, null, 'A');

        $this->putJson("/api/tasks/{$step->id}/place", ['stage_id' => $elsewhere->id])->assertUnprocessable();

        $step->update(['is_completed' => true]);
        $this->putJson("/api/tasks/{$step->id}/place", ['stage_id' => null])->assertNotFound();
    }

    public function test_a_move_into_a_later_stage_lets_go_of_the_waits_on_link_and_says_so(): void
    {
        $project = $this->editor()->createProject($this->user, 'P');
        $one = $this->editor()->addStage($project, 'Eins', null);
        $two = $this->editor()->addStage($project, 'Zwei', null);
        $blocker = $this->editor()->addProjectStep($this->user, $project, $one, 'Blocker');
        $waiter = $this->editor()->addProjectStep($this->user, $project, $two, 'Waiter');
        $this->putJson("/api/tasks/{$waiter->id}/waits-on", ['waits_on_id' => $blocker->id])->assertOk()->assertJsonPath('data.waits_on_id', $blocker->id);

        $this->putJson("/api/tasks/{$blocker->id}/place", ['stage_id' => $two->id])
            ->assertOk()
            ->assertJsonPath('data.move.changed', true);

        // Same stage now, so the link is fine; moving the waiter back to stage one contradicts it.
        $this->putJson("/api/tasks/{$waiter->id}/place", ['stage_id' => $one->id])
            ->assertOk()
            ->assertJsonPath('data.move.cleared_waits_on', [(string) $waiter->id => $blocker->id]);
        $this->assertNull($waiter->fresh()->waits_on_id);
    }

    public function test_waits_on_refuses_loops_foreign_projects_and_clears_with_null(): void
    {
        $project = $this->editor()->createProject($this->user, 'P');
        $a = $this->editor()->addProjectStep($this->user, $project, null, 'A');
        $b = $this->editor()->addProjectStep($this->user, $project, null, 'B');
        $other = $this->editor()->addProjectStep($this->user, $this->editor()->createProject($this->user, 'Q'), null, 'X');

        $this->putJson("/api/tasks/{$a->id}/waits-on", ['waits_on_id' => $b->id])->assertOk();
        $this->putJson("/api/tasks/{$b->id}/waits-on", ['waits_on_id' => $a->id])->assertUnprocessable()->assertJsonValidationErrors('waits_on_id');
        $this->putJson("/api/tasks/{$a->id}/waits-on", ['waits_on_id' => $a->id])->assertUnprocessable();
        $this->putJson("/api/tasks/{$a->id}/waits-on", ['waits_on_id' => $other->id])->assertUnprocessable();
        $this->putJson("/api/tasks/{$a->id}/waits-on", [])->assertUnprocessable();

        $this->putJson("/api/tasks/{$a->id}/waits-on", ['waits_on_id' => null])->assertOk()->assertJsonPath('data.waits_on_id', null);
    }

    // ── Groups ──────────────────────────────────────────────────────────

    public function test_a_group_is_created_with_steps_and_renamed(): void
    {
        $id = $this->postJson('/api/task-groups', ['name' => 'Umzug', 'list' => 'todos', 'steps' => ['Kisten', 'Strom']])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'group')
            ->assertJsonPath('data.list', 'todos')
            ->assertJsonPath('data.open', 2)
            ->assertJsonPath('data.progress', ['done' => 0, 'total' => 2])
            ->assertJsonPath('data.steps.0.title', 'Kisten')
            ->json('data.id');

        $this->assertSame($id, $this->getJson("/api/task-groups/{$id}")->json('data.steps.0.group_id'));

        $this->postJson("/api/task-groups/{$id}/steps", ['title' => 'Post'])->assertCreated()->assertJsonPath('data.list', 'todos');
        $this->patchJson("/api/task-groups/{$id}", ['name' => 'Umzug Mai'])->assertOk()->assertJsonPath('data.name', 'Umzug Mai');

        $this->getJson('/api/task-groups')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.open', 3)->assertJsonPath('data.0.more', 1);
        $this->getJson("/api/task-groups/{$id}")->assertOk()->assertJsonCount(3, 'data.steps');

        $this->postJson('/api/task-groups', ['name' => 'X', 'list' => 'inbox'])->assertUnprocessable()->assertJsonValidationErrors('list');
    }

    // ── Notes ───────────────────────────────────────────────────────────

    public function test_note_cards_land_in_lose_get_tags_move_to_a_stage_and_can_be_deleted(): void
    {
        $project = $this->editor()->createProject($this->user, 'P');
        $stage = $this->editor()->addStage($project, 'Eins', null);

        $id = $this->postJson("/api/projects/{$project->id}/notes", ['body' => 'Idee #Logo und #farbe'])
            ->assertCreated()
            ->assertJsonPath('data.stage_id', null)
            ->assertJsonPath('data.tags', ['farbe', 'logo'])
            ->json('data.id');

        $this->patchJson("/api/notes/{$id}", ['stage_id' => $stage->id])->assertOk()->assertJsonPath('data.stage_id', $stage->id);
        $this->patchJson("/api/notes/{$id}", ['body' => 'Nur #logo'])->assertOk()->assertJsonPath('data.tags', ['logo']);
        $this->getJson("/api/projects/{$project->id}")->assertJsonCount(1, 'data.notes')->assertJsonPath('data.notes.0.body', 'Nur #logo');

        $this->patchJson("/api/notes/{$id}", ['stage_id' => null])->assertOk()->assertJsonPath('data.stage_id', null);
        $this->deleteJson("/api/notes/{$id}")->assertNoContent();
        $this->assertDatabaseCount('notes', 0);
        $this->assertDatabaseCount('tags', 0);
    }

    public function test_group_notes_have_no_stages_and_empty_bodies_are_refused(): void
    {
        $group = $this->editor()->createGroup($this->user, 'G', 'tasks');
        $stage = $this->editor()->addStage($this->editor()->createProject($this->user, 'P'), 'S', null);

        $this->postJson("/api/task-groups/{$group->id}/notes", ['body' => 'Karte'])->assertCreated()->assertJsonPath('data.group_id', $group->id);
        $this->postJson("/api/task-groups/{$group->id}/notes", ['body' => '   '])->assertUnprocessable()->assertJsonValidationErrors('body');

        $noteId = Note::first()->id;
        $this->patchJson("/api/notes/{$noteId}", ['stage_id' => $stage->id])->assertUnprocessable()->assertJsonValidationErrors('stage_id');
        $this->getJson("/api/task-groups/{$group->id}")->assertJsonCount(1, 'data.notes');
    }

    // ── Tasks: the new membership fields and filters ────────────────────

    public function test_tasks_show_their_project_stage_group_and_filter_by_them_and_size_and_area(): void
    {
        $project = $this->editor()->createProject($this->user, 'P');
        $stage = $this->editor()->addStage($project, 'S', null);
        $group = $this->editor()->createGroup($this->user, 'G', 'tasks');
        $inProject = $this->editor()->addProjectStep($this->user, $project, $stage, 'Im Projekt');
        $inGroup = $this->editor()->addGroupStep($this->user, $group, 'In Gruppe');
        $loose = Task::factory()->for($this->user)->tasks()->create(['title' => 'Lose', 'size' => 'long']);

        $this->getJson("/api/tasks/{$inProject->id}")->assertJsonPath('data.project_id', $project->id)->assertJsonPath('data.stage_id', $stage->id)->assertJsonPath('data.group_id', null);
        $this->getJson("/api/tasks/{$inGroup->id}")->assertJsonPath('data.group_id', $group->id)->assertJsonPath('data.project_id', null);

        $this->assertSame([$inProject->id], collect($this->getJson("/api/tasks?project_id={$project->id}")->json('data'))->pluck('id')->all());
        $this->assertSame([$inProject->id], collect($this->getJson("/api/tasks?stage_id={$stage->id}")->json('data'))->pluck('id')->all());
        $this->assertSame([$inGroup->id], collect($this->getJson("/api/tasks?group_id={$group->id}")->json('data'))->pluck('id')->all());
        $this->assertSame([$loose->id], collect($this->getJson('/api/tasks?size=long')->json('data'))->pluck('id')->all());
        $this->getJson('/api/tasks?size=huge')->assertUnprocessable();
    }

    // ── Other people's rows ─────────────────────────────────────────────

    public function test_another_users_rows_are_404_and_never_written(): void
    {
        $stranger = User::factory()->create();
        $project = $this->editor()->createProject($stranger, 'Geheim');
        $stage = $this->editor()->addStage($project, 'S', null);
        $step = $this->editor()->addProjectStep($stranger, $project, $stage, 'Schritt');
        $group = $this->editor()->createGroup($stranger, 'Geheim', 'tasks');
        $note = app(NoteEditor::class)->add($stranger, $project, 'Karte');
        $mine = $this->editor()->addProjectStep($this->user, $this->editor()->createProject($this->user, 'Mein'), null, 'Meins');

        $this->getJson("/api/projects/{$project->id}")->assertNotFound();
        $this->patchJson("/api/projects/{$project->id}", ['name' => 'x'])->assertNotFound();
        $this->postJson("/api/projects/{$project->id}/stages", ['name' => 'x'])->assertNotFound();
        $this->postJson("/api/projects/{$project->id}/steps", ['title' => 'x'])->assertNotFound();
        $this->postJson("/api/projects/{$project->id}/notes", ['body' => 'x'])->assertNotFound();
        $this->patchJson("/api/stages/{$stage->id}", ['name' => 'x'])->assertNotFound();
        $this->deleteJson("/api/stages/{$stage->id}")->assertNotFound();
        $this->getJson("/api/task-groups/{$group->id}")->assertNotFound();
        $this->patchJson("/api/task-groups/{$group->id}", ['name' => 'x'])->assertNotFound();
        $this->postJson("/api/task-groups/{$group->id}/steps", ['title' => 'x'])->assertNotFound();
        $this->patchJson("/api/notes/{$note->id}", ['body' => 'x'])->assertNotFound();
        $this->deleteJson("/api/notes/{$note->id}")->assertNotFound();
        $this->putJson("/api/tasks/{$step->id}/place", ['stage_id' => null])->assertNotFound();
        $this->putJson("/api/tasks/{$step->id}/waits-on", ['waits_on_id' => null])->assertNotFound();
        // My step cannot be put into the stranger's stage or made to wait on the stranger's step.
        $this->putJson("/api/tasks/{$mine->id}/place", ['stage_id' => $stage->id])->assertNotFound();
        $this->putJson("/api/tasks/{$mine->id}/waits-on", ['waits_on_id' => $step->id])->assertNotFound();

        $this->assertSame('Geheim', $project->fresh()->name);
        $this->assertDatabaseCount('notes', 1);
        $this->getJson('/api/projects')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Mein');
        $this->assertSame(0, ProjectStage::where('project_id', Project::where('name', 'Mein')->value('id'))->count());
    }

    public function test_non_numeric_ids_are_a_404_not_a_500(): void
    {
        config(['logging.default' => 'null']);

        foreach (['projects/abc', 'task-groups/abc', 'stages/abc', 'notes/abc'] as $path) {
            foreach (['getJson', 'patchJson', 'deleteJson'] as $verb) {
                $this->assertContains($this->$verb("/api/{$path}")->getStatusCode(), [404, 405], "{$verb} /api/{$path}");
            }
        }
    }

    public function test_a_narrow_capture_token_cannot_reach_projects(): void
    {
        $token = $this->user->createToken('capture', ['capture:inbox'])->plainTextToken;
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/projects')->assertForbidden();
    }
}
