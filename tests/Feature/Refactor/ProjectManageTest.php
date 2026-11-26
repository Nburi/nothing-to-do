<?php

namespace Tests\Feature\Refactor;

use App\Livewire\Shell\GroupPage;
use App\Livewire\Shell\ProjectPage;
use App\Livewire\Shell\Projects;
use App\Models\EventCategory;
use App\Models\Note;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\User;
use App\Services\Planning\EveningReset;
use App\Services\Projects\NextSteps;
use App\Services\Projects\NoteEditor;
use App\Services\Projects\ProjectEditor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Step 4.3d: the gaps of the project pages. Delete a project or group (no step is lost: every task goes
 * back to the board in its own list), change the order of stages (a "wartet auf" that now contradicts is
 * let go, undoable) and move an existing board task into a project or group and back out (undoable).
 */
class ProjectManageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ProjectEditor $editor;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');
        $this->travelTo(CarbonImmutable::parse('2026-11-24 20:00', 'Europe/Zurich'));
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
        $this->editor = new ProjectEditor;
        app(EveningReset::class)->close($this->user);
        app(EveningReset::class)->answerBattery($this->user, null);
    }

    private function project(?User $user = null, string $name = 'Enderdragon'): Project
    {
        return $this->editor->createProject($user ?? $this->user, $name);
    }

    private function task(string $title, string $list = 'tasks', ?User $user = null, array $extra = []): Task
    {
        return Task::factory()->create(['user_id' => ($user ?? $this->user)->id, 'title' => $title, 'list' => $list, ...$extra]);
    }

    private function page(Project $p)
    {
        return Livewire::actingAs($this->user)->test(ProjectPage::class, ['project' => $p->id]);
    }

    private function groupPage(TaskGroup $g)
    {
        return Livewire::actingAs($this->user)->test(GroupPage::class, ['group' => $g->id]);
    }

    /** @return array<int, int> */
    private function stageOrder(Project $p): array
    {
        return $p->stages()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    // ---- delete a project --------------------------------------------------------------------

    public function test_deleting_a_project_sets_every_task_free_and_removes_stages_and_notes(): void
    {
        $p = $this->project();
        $bugs = $this->editor->addStage($p, 'Bugs fixen', null);
        $open = $this->editor->addStage($p, 'Server öffnen', null);
        $a = $this->editor->addProjectStep($this->user, $p, $bugs, 'Spear Swap', 'todos');
        $b = $this->editor->addProjectStep($this->user, $p, $open, 'Backup testen', 'tasks');
        $b->forceFill(['waits_on_id' => $a->id])->save();
        $inbox = $this->task('Idee', 'inbox');
        $inbox->forceFill(['project_id' => $p->id])->save();
        $done = $this->editor->addProjectStep($this->user, $p, $bugs, 'Alt', 'tasks');
        $done->forceFill(['is_completed' => true, 'completed_at' => now()])->save();
        app(NoteEditor::class)->add($this->user, $p, 'Ein Gedanke #idee', $bugs);

        $other = $this->project($this->user, 'Bleibt');
        $otherStep = $this->editor->addProjectStep($this->user, $other, null, 'Bleibt stehen');

        $this->page($p)
            ->call('askDelete')
            ->assertSet('confirmingDelete', true)
            ->assertSee('Projekt „Enderdragon“ löschen?')
            ->assertSee('2 Etappen')
            ->assertSee('1 Notiz')
            ->assertSee('Das lässt sich nicht rückgängig machen.')
            ->call('deleteOwner')
            ->assertRedirect(route('shell.projects'));

        $this->assertNull(Project::find($p->id));
        $this->assertSame(0, ProjectStage::where('project_id', $p->id)->count());
        $this->assertSame(0, Note::count());

        foreach ([[$a, 'todos'], [$b, 'tasks'], [$inbox, 'inbox'], [$done, 'tasks']] as [$task, $list]) {
            $fresh = $task->fresh();
            $this->assertNotNull($fresh, 'no task is deleted');
            $this->assertNull($fresh->project_id);
            $this->assertNull($fresh->stage_id);
            $this->assertNull($fresh->waits_on_id);
            $this->assertSame($list, $fresh->list, 'the task keeps its list');
        }
        $this->assertTrue($done->fresh()->is_completed);

        $this->assertSame($other->id, $otherStep->fresh()->project_id, 'another project is untouched');
    }

    public function test_the_confirm_names_open_and_done_steps_and_what_goes(): void
    {
        $p = $this->project();
        foreach (['Eins', 'Zwei', 'Drei'] as $t) {
            $this->editor->addProjectStep($this->user, $p, null, $t);
        }
        $this->editor->addProjectStep($this->user, $p, null, 'Fertig')->forceFill(['is_completed' => true])->save();

        $this->page($p)->call('askDelete')
            ->assertSee('Die 3 offenen Schritte bleiben erhalten')
            ->assertSee('Der erledigte Schritt bleibt als erledigte Aufgabe.')
            ->assertSee('Sonst geht nichts verloren.');
    }

    public function test_cancel_keeps_everything(): void
    {
        $p = $this->project();
        $this->page($p)->call('askDelete')->call('cancelDelete')->assertSet('confirmingDelete', false)->assertNoRedirect();
        $this->assertNotNull(Project::find($p->id));
    }

    public function test_deleting_shows_one_plain_line_on_the_projects_page(): void
    {
        $p = $this->project();
        $this->editor->addProjectStep($this->user, $p, null, 'Eins');
        $this->editor->addProjectStep($this->user, $p, null, 'Zwei');

        $this->page($p)->call('deleteOwner');
        $line = session('projects.notice');
        $this->assertSame('Projekt „Enderdragon“ ist gelöscht. Die 2 offenen Schritte liegen wieder auf dem Board.', $line);

        session()->flash('projects.notice', $line);
        Livewire::actingAs($this->user)->test(Projects::class)->assertSet('notice', $line)->assertSee('ist gelöscht');
    }

    public function test_a_failed_delete_deletes_nothing_and_says_so_plainly(): void
    {
        $p = $this->project();
        $step = $this->editor->addProjectStep($this->user, $p, null, 'Eins');
        $page = $this->page($p)->call('askDelete');

        Project::deleting(fn () => throw new RuntimeException('database is gone: secret details'));

        $page->call('deleteOwner')
            ->assertNoRedirect()
            ->assertSet('confirmingDelete', true)
            ->assertSee('Das Löschen hat nicht geklappt. Es wurde nichts gelöscht.')
            ->assertDontSee('secret details');

        $this->assertNotNull(Project::find($p->id));
        $this->assertSame($p->id, $step->fresh()->project_id);
    }

    public function test_a_project_made_from_a_group_takes_that_group_with_it_and_frees_the_tasks(): void
    {
        $p = $this->project();
        $group = $this->editor->createGroup($this->user, 'Alte Gruppe', 'tasks');
        $group->forceFill(['project_id' => $p->id])->save();
        $task = $this->task('Aus der Gruppe');
        $task->forceFill(['project_id' => $p->id, 'group_id' => $group->id])->save();

        $this->editor->deleteProject($p);

        $this->assertNull(TaskGroup::find($group->id), 'else it would turn into a group again');
        $fresh = $task->fresh();
        $this->assertNull($fresh->project_id);
        $this->assertNull($fresh->group_id);
        $this->assertSame(0, TaskGroup::forUser($this->user)->count());
    }

    public function test_a_category_that_pointed_at_the_project_loses_the_link(): void
    {
        $p = $this->project();
        $category = EventCategory::factory()->create(['user_id' => $this->user->id]);
        $category->forceFill(['task_source' => 'project', 'linked_project_id' => $p->id])->save();

        $this->editor->deleteProject($p);

        $category = $category->fresh();
        $this->assertNull($category->task_source);
        $this->assertNull($category->linked_project_id);
    }

    public function test_another_users_project_cannot_be_opened_or_deleted(): void
    {
        $stranger = User::factory()->create();
        $theirs = $this->project($stranger, 'Fremd');

        Livewire::actingAs($this->user)->test(ProjectPage::class, ['project' => $theirs->id])->assertNotFound();
        $this->assertNotNull(Project::find($theirs->id));
    }

    // ---- delete a group ----------------------------------------------------------------------

    public function test_deleting_a_group_keeps_its_tasks_in_their_list_and_removes_its_notes(): void
    {
        $g = $this->editor->createGroup($this->user, 'Einkauf', 'todos');
        $a = $this->editor->addGroupStep($this->user, $g, 'Milch');
        $b = $this->editor->addGroupStep($this->user, $g, 'Brot');
        $b->forceFill(['is_completed' => true])->save();
        app(NoteEditor::class)->add($this->user, $g, 'Notiz');
        $category = EventCategory::factory()->create(['user_id' => $this->user->id]);
        $category->forceFill(['task_source' => 'group', 'linked_group_id' => $g->id])->save();

        $this->groupPage($g)
            ->call('askDelete')
            ->assertSee('Gruppe „Einkauf“ löschen?')
            ->assertSee('Der offene Schritt bleibt erhalten')
            ->assertSee('1 Notiz')
            ->call('deleteOwner')
            ->assertRedirect(route('shell.projects'));

        $this->assertNull(TaskGroup::find($g->id));
        $this->assertSame(0, Note::count());
        foreach ([$a, $b] as $task) {
            $fresh = $task->fresh();
            $this->assertNotNull($fresh);
            $this->assertNull($fresh->group_id);
            $this->assertSame('todos', $fresh->list);
        }
        $this->assertNull($category->fresh()->task_source);
    }

    // ---- stage order -------------------------------------------------------------------------

    /** @return array{Project, ProjectStage, ProjectStage, ProjectStage} */
    private function threeStages(): array
    {
        $p = $this->project();

        return [$p, $this->editor->addStage($p, 'Eins', null), $this->editor->addStage($p, 'Zwei', null), $this->editor->addStage($p, 'Drei', null)];
    }

    public function test_a_stage_moves_one_place_and_the_stages_are_numbered_again(): void
    {
        [$p, $one, $two, $three] = $this->threeStages();

        $this->page($p)->call('shiftStage', $three->id, -1)
            ->assertSee('Etappe „Drei“ ist jetzt Etappe 2.');

        $this->assertSame([$one->id, $three->id, $two->id], $this->stageOrder($p));
        $this->assertSame([0, 1, 2], $p->stages()->pluck('sort_order')->map(fn ($n) => (int) $n)->all());
    }

    public function test_a_move_past_the_edge_changes_nothing_and_says_nothing(): void
    {
        [$p, $one, $two, $three] = $this->threeStages();

        $this->page($p)->call('shiftStage', $one->id, -1)->assertSet('notice', null)->assertSet('undo', null);
        $this->page($p)->call('shiftStage', $three->id, 1)->assertSet('notice', null);

        $this->assertSame([$one->id, $two->id, $three->id], $this->stageOrder($p));
    }

    public function test_moving_a_stage_undoes_with_its_old_order(): void
    {
        [$p, $one, $two, $three] = $this->threeStages();

        $this->page($p)->call('shiftStage', $one->id, 1)->call('undoLast')->assertSee('Rückgängig gemacht.');

        $this->assertSame([$one->id, $two->id, $three->id], $this->stageOrder($p));
    }

    public function test_the_undo_copes_with_a_stage_added_in_between(): void
    {
        [$p, $one, $two, $three] = $this->threeStages();
        $page = $this->page($p)->call('shiftStage', $one->id, 1);
        $four = $this->editor->addStage($p, 'Vier', null);

        $page->call('undoLast');

        $this->assertSame([$one->id, $two->id, $three->id, $four->id], $this->stageOrder($p));
    }

    public function test_a_step_that_would_now_wait_for_a_later_stage_is_let_go_and_comes_back_with_undo(): void
    {
        [$p, $one, $two] = $this->threeStages();
        $first = $this->editor->addProjectStep($this->user, $p, $one, 'Erst');
        $second = $this->editor->addProjectStep($this->user, $p, $two, 'Dann');
        $second->forceFill(['waits_on_id' => $first->id])->save();

        // Stage 2 goes in front of stage 1: "Dann" would wait for something that cannot start before it is done.
        $page = $this->page($p)->call('shiftStage', $two->id, -1);
        $page->assertSee('Das Warten ist gelöst.');
        $this->assertNull($second->fresh()->waits_on_id);

        $page->call('undoLast');
        $this->assertSame([$one->id, $two->id, $this->stageOrder($p)[2]], $this->stageOrder($p));
        $this->assertSame($first->id, $second->fresh()->waits_on_id);
    }

    public function test_a_link_that_still_makes_sense_stays_when_stages_change_place(): void
    {
        [$p, $one, $two, $three] = $this->threeStages();
        $first = $this->editor->addProjectStep($this->user, $p, $one, 'Erst');
        $second = $this->editor->addProjectStep($this->user, $p, $two, 'Dann');
        $second->forceFill(['waits_on_id' => $first->id])->save();

        $this->page($p)->call('shiftStage', $three->id, -1); // the third in front of the second: the pair is untouched

        $this->assertSame($first->id, $second->fresh()->waits_on_id);
    }

    public function test_the_current_stage_follows_the_new_order(): void
    {
        [$p, $one, $two] = $this->threeStages();
        $this->editor->addProjectStep($this->user, $p, $one, 'A');
        $this->editor->addProjectStep($this->user, $p, $two, 'B');
        $bundle = fn () => app(NextSteps::class)->bundles($this->user)->first(fn ($b) => $b->isProject() && $b->id === $p->id);
        $this->assertSame($one->id, $bundle()->stage->id);

        $this->page($p)->call('shiftStage', $two->id, -1);

        $this->assertSame($two->id, $bundle()->stage->id);
        $this->assertSame(['B'], $bundle()->visible->pluck('title')->all());
    }

    public function test_a_foreign_or_vanished_stage_is_calm(): void
    {
        [$p, $one] = $this->threeStages();
        $stranger = $this->project(User::factory()->create(), 'Fremd');
        $foreign = $this->editor->addStage($stranger, 'Fremde Etappe', null);

        $page = $this->page($p)->call('shiftStage', $foreign->id, -1);
        $page->assertSee('Das gibt es nicht mehr.');
        $this->assertSame([$foreign->id], $this->stageOrder($stranger));

        $one->delete();
        $this->page($p)->call('shiftStage', $one->id, 1)->assertSee('Das gibt es nicht mehr.');
    }

    public function test_the_page_has_order_buttons_only_when_there_is_something_to_order(): void
    {
        $p = $this->project();
        $only = $this->editor->addStage($p, 'Einzige', null);
        $this->page($p)->assertDontSeeHtml('data-stage-order');

        $this->editor->addStage($p, 'Zweite', null);
        $html = $this->page($p)->assertSeeHtml('data-stage-order')->html();
        $this->assertSame(1, preg_match_all('/<button[^>]*\sdisabled[^>]*data-stage-earlier/s', $html), 'the first stage cannot go further up');
        $this->assertSame(1, preg_match_all('/<button[^>]*\sdisabled[^>]*data-stage-later/s', $html), 'the last cannot go further down');
        $this->assertNotNull($only);
    }

    // ---- existing board task in and out ------------------------------------------------------

    public function test_an_existing_board_task_becomes_a_step_in_the_chosen_stage_and_keeps_its_list(): void
    {
        $p = $this->project();
        $stage = $this->editor->addStage($p, 'Bugs fixen', null);
        $this->editor->addProjectStep($this->user, $p, $stage, 'Schon da');
        $task = $this->task('Rüstung', 'todos', null, ['is_important' => true]);

        $this->page($p)
            ->call('openPicker')
            ->assertSee('Rüstung')
            ->set('pickStage', 'stage-'.$stage->id)
            ->call('adoptTask', $task->id)
            ->assertSee('„Rüstung“ ist jetzt ein Schritt dieses Projekts.');

        $fresh = $task->fresh();
        $this->assertSame($p->id, $fresh->project_id);
        $this->assertSame($stage->id, $fresh->stage_id);
        $this->assertSame('todos', $fresh->list);
        $this->assertTrue($fresh->is_important);
        $this->assertSame(2, $fresh->sort_order, 'at the end of the stage');
    }

    public function test_adopting_undoes_back_onto_the_board(): void
    {
        $p = $this->project();
        $task = $this->task('Rüstung');

        $this->page($p)->call('adoptTask', $task->id)->call('undoLast')->assertSee('Rückgängig gemacht.');

        $fresh = $task->fresh();
        $this->assertNull($fresh->project_id);
        $this->assertNull($fresh->stage_id);
    }

    public function test_the_picker_offers_only_what_may_move_in(): void
    {
        $p = $this->project();
        $other = $this->project($this->user, 'Anderes');
        $g = $this->editor->createGroup($this->user, 'Gruppe', 'tasks');
        $stranger = User::factory()->create();

        $this->task('Frei');
        $this->task('Fertig', 'tasks', null, ['is_completed' => true]);
        $this->task('Wiederkehrend', 'tasks', null, ['repeat_rule' => 'daily']);
        $this->task('Fremd', 'tasks', $stranger);
        $this->task('Im anderen Projekt')->forceFill(['project_id' => $other->id])->save();
        $this->task('In einer Gruppe')->forceFill(['group_id' => $g->id])->save();

        $page = $this->page($p)->call('openPicker');

        $page->assertSee('Frei');
        foreach (['Fertig', 'Wiederkehrend', 'Fremd', 'Im anderen Projekt', 'In einer Gruppe'] as $hidden) {
            $page->assertDontSee($hidden);
        }
    }

    public function test_a_task_that_cannot_move_in_is_refused_calmly(): void
    {
        $p = $this->project();
        $stranger = User::factory()->create();
        $theirs = $this->task('Fremd', 'tasks', $stranger);
        $repeating = $this->task('Täglich', 'tasks', null, ['repeat_rule' => 'daily']);
        $done = $this->task('Fertig', 'tasks', null, ['is_completed' => true]);

        foreach ([$theirs, $repeating, $done] as $task) {
            $this->page($p)->call('adoptTask', $task->id)->assertSee('Das gibt es nicht mehr.');
            $this->assertNull($task->fresh()->project_id);
        }
    }

    public function test_a_stage_of_another_project_cannot_be_the_target(): void
    {
        $p = $this->project();
        $other = $this->project($this->user, 'Anderes');
        $foreign = $this->editor->addStage($other, 'Fremde Etappe', null);
        $task = $this->task('Eins');

        $this->page($p)->set('pickStage', 'stage-'.$foreign->id)->call('adoptTask', $task->id)->assertSee('Das gibt es nicht mehr.');

        $this->assertNull($task->fresh()->project_id);
    }

    public function test_the_picker_searches_limits_and_says_when_there_is_nothing(): void
    {
        $p = $this->project();
        $page = $this->page($p)->call('openPicker');
        $page->assertSee('Auf dem Board liegt gerade nichts, was du hinzufügen könntest.');

        foreach (range(1, 33) as $n) {
            $this->task(sprintf('Aufgabe %02d', $n));
        }
        $this->task('Besonderes');

        $page = $this->page($p)->call('openPicker');
        $page->assertSee('4 weitere'); // 34 tasks, 30 shown
        $page->set('pickSearch', 'Besond')->assertSee('Besonderes')->assertDontSee('Aufgabe 01')->assertDontSee('weitere');
        $page->set('pickSearch', 'gibtesnicht')->assertSee('Nichts gefunden für „gibtesnicht“.');
    }

    public function test_a_group_takes_only_tasks_of_its_own_list(): void
    {
        $g = $this->editor->createGroup($this->user, 'Einkauf', 'todos');
        $fits = $this->task('Milch', 'todos');
        $wrong = $this->task('Steuern', 'tasks');
        $inbox = $this->task('Irgendwas', 'inbox');

        $page = $this->groupPage($g)->call('openPicker')->assertSee('Milch')->assertDontSee('Steuern')->assertDontSee('Irgendwas');
        $page->call('adoptTask', $wrong->id)->assertSee('Das gibt es nicht mehr.');
        $page->call('adoptTask', $inbox->id)->assertSee('Das gibt es nicht mehr.');
        $page->call('adoptTask', $fits->id)->assertSee('„Milch“ gehört jetzt zu dieser Gruppe.');

        $this->assertSame($g->id, $fits->fresh()->group_id);
        $this->assertNull($wrong->fresh()->group_id);
        $page->call('undoLast');
        $this->assertNull($fits->fresh()->group_id);
    }

    public function test_the_service_itself_refuses_a_wrong_list_and_a_group_stage(): void
    {
        $g = $this->editor->createGroup($this->user, 'Einkauf', 'todos');
        $p = $this->project();
        $stage = $this->editor->addStage($p, 'Eine', null);
        $wrong = $this->task('Steuern', 'tasks');
        $fits = $this->task('Milch', 'todos');

        $this->assertThrows(fn () => $this->editor->adopt($this->user, $g, $wrong), \InvalidArgumentException::class);
        $this->assertThrows(fn () => $this->editor->adopt($this->user, $g, $fits, $stage), \InvalidArgumentException::class);
        $this->assertThrows(fn () => $this->editor->adopt($this->user, $p, $fits, $this->editor->addStage($this->project($this->user, 'Anderes'), 'X', null)), \InvalidArgumentException::class);

        foreach ([$wrong, $fits] as $task) {
            $this->assertNull($task->fresh()->group_id);
            $this->assertNull($task->fresh()->project_id);
        }
    }

    public function test_a_step_goes_back_to_the_board_and_comes_back_with_undo(): void
    {
        $p = $this->project();
        $one = $this->editor->addStage($p, 'Eins', null);
        $two = $this->editor->addStage($p, 'Zwei', null);
        $a = $this->editor->addProjectStep($this->user, $p, $one, 'Erst', 'todos');
        $b = $this->editor->addProjectStep($this->user, $p, $two, 'Dann', 'tasks');
        $c = $this->editor->addProjectStep($this->user, $p, $two, 'Danach', 'tasks');
        $b->forceFill(['waits_on_id' => $a->id])->save();
        $a->forceFill(['waits_on_id' => null])->save();
        $c->forceFill(['waits_on_id' => $b->id])->save();

        $page = $this->page($p)->call('releaseStep', $b->id)->assertSee('„Dann“ liegt wieder auf dem Board (Tasks).');

        $fresh = $b->fresh();
        $this->assertNull($fresh->project_id);
        $this->assertNull($fresh->stage_id);
        $this->assertNull($fresh->waits_on_id);
        $this->assertSame('tasks', $fresh->list);
        $this->assertNull($c->fresh()->waits_on_id, 'nobody keeps waiting for a step that left');

        $page->call('undoLast');

        $fresh = $b->fresh();
        $this->assertSame($p->id, $fresh->project_id);
        $this->assertSame($two->id, $fresh->stage_id);
        $this->assertSame($a->id, $fresh->waits_on_id);
        $this->assertSame($b->id, $c->fresh()->waits_on_id);
    }

    public function test_the_undo_of_leaving_falls_back_calmly_when_the_stage_is_gone_or_the_task_moved_on(): void
    {
        $p = $this->project();
        $stage = $this->editor->addStage($p, 'Eins', null);
        $step = $this->editor->addProjectStep($this->user, $p, $stage, 'Erst');
        $page = $this->page($p)->call('releaseStep', $step->id);

        $stage->delete();
        $page->call('undoLast');
        $this->assertSame($p->id, $step->fresh()->project_id);
        $this->assertNull($step->fresh()->stage_id);

        $page = $this->page($p)->call('releaseStep', $step->id);
        $other = $this->project($this->user, 'Anderes');
        $step->fresh()->forceFill(['project_id' => $other->id])->save();
        $page->call('undoLast')->assertSee('Das gibt es nicht mehr.');
        $this->assertSame($other->id, $step->fresh()->project_id, 'a task that moved on is not pulled back');
    }

    public function test_a_group_step_leaves_the_group_and_stays_in_its_list(): void
    {
        $g = $this->editor->createGroup($this->user, 'Einkauf', 'todos');
        $a = $this->editor->addGroupStep($this->user, $g, 'Milch');

        $page = $this->groupPage($g)->call('releaseStep', $a->id)->assertSee('„Milch“ liegt wieder auf dem Board (To-dos).');
        $this->assertNull($a->fresh()->group_id);
        $this->assertSame('todos', $a->fresh()->list);

        $page->call('undoLast');
        $this->assertSame($g->id, $a->fresh()->group_id);
    }

    public function test_a_step_menu_offers_the_way_back_to_the_board(): void
    {
        $p = $this->project();
        $this->editor->addProjectStep($this->user, $p, null, 'Erst');

        $this->page($p)->assertSeeHtml('data-release')->assertSee('Zurück aufs Board');
    }

    public function test_the_projects_page_flash_does_not_leak_to_another_session(): void
    {
        $this->actingAs($this->user)->get(route('shell.projects'))->assertOk()->assertDontSee('ist gelöscht');
        Livewire::actingAs($this->user)->test(Projects::class)->assertSet('notice', null);
    }
}
