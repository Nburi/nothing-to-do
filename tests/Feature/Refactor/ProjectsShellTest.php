<?php

namespace Tests\Feature\Refactor;

use App\Livewire\Shell\GroupPage;
use App\Livewire\Shell\ProjectPage;
use App\Livewire\Shell\Projects;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\User;
use App\Services\FocusSession;
use App\Services\Planning\EveningReset;
use App\Services\Projects\ProjectEditor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A42 (board cards), A44 (Projekte page) and A45 part two (project and group pages), step 4.3.
 */
class ProjectsShellTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ProjectEditor $editor;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');
        $this->travelTo(CarbonImmutable::parse('2026-11-17 20:00', 'Europe/Zurich'));
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
        $this->editor = new ProjectEditor;
        app(EveningReset::class)->close($this->user);
        app(EveningReset::class)->answerBattery($this->user, null);
    }

    private function enderdragon(): Project
    {
        $p = $this->editor->createProject($this->user, 'Enderdragon');
        $bugs = $this->editor->addStage($p, 'Bugs fixen', '2026-11-30');
        $open = $this->editor->addStage($p, 'Server öffnen', null);
        foreach (['Spear Swap', 'Bubble Lift', 'Rüstung', 'Salvador'] as $t) {
            $this->editor->addProjectStep($this->user, $p, $bugs, $t);
        }
        $this->editor->addProjectStep($this->user, $p, $open, 'Backup testen');

        return $p;
    }

    // ---- A42: the board -----------------------------------------------------------------------

    public function test_the_board_shows_one_card_per_project_with_its_next_two_steps_only(): void
    {
        $this->enderdragon();
        Task::forceCreate(['user_id' => $this->user->id, 'title' => 'Mathe-Serie', 'list' => 'tasks']);

        $html = $this->actingAs($this->user)->get('/board')->assertOk()->getContent();

        $this->assertStringContainsString('data-bundle="project-', $html);
        $this->assertStringContainsString('Spear Swap', $html);
        $this->assertStringContainsString('Bubble Lift', $html);
        $this->assertStringContainsString('Bugs fixen · bis 30.11.', $html);
        $this->assertStringContainsString('+3 weitere in diesem Projekt', $html);
        $this->assertStringContainsString('Mathe-Serie', $html);
        $this->assertStringNotContainsString('Rüstung', $html, 'a hidden step is not on the board');
        $this->assertStringNotContainsString('Backup testen', $html, 'a later stage is not on the board');
    }

    public function test_a_group_card_shows_its_count_and_next_two(): void
    {
        $g = $this->editor->createGroup($this->user, 'Notion för Vocj', 'tasks');
        foreach (['Regel', 'Füllen', 'Niels'] as $t) {
            $this->editor->addGroupStep($this->user, $g, $t);
        }

        $html = $this->actingAs($this->user)->get('/board')->assertOk()->getContent();

        $this->assertStringContainsString('data-bundle="group-'.$g->id.'"', $html);
        $this->assertStringContainsString('0/3', $html);
        $this->assertStringContainsString('+1 weitere in dieser Gruppe', $html);
        $this->assertStringNotContainsString('>Niels<', $html);
    }

    public function test_column_counts_are_what_the_column_shows(): void
    {
        $this->enderdragon();

        $board = Livewire::actingAs($this->user)->test(\App\Livewire\Shell\Board::class);

        $this->assertSame(2, $board->instance()->counts['tasks']);
        $this->assertCount(0, $board->instance()->lists['tasks']);
    }

    // ---- A44: the Projekte page ---------------------------------------------------------------

    public function test_the_projects_page_lists_active_cards_and_resting_chips(): void
    {
        $this->enderdragon();
        $this->editor->createProject($this->user, 'Smart Room');

        $this->actingAs($this->user)->get('/board/projects')->assertOk()
            ->assertSee('Enderdragon')->assertSee('Spear Swap')
            ->assertSee('Ruht gerade · 1')->assertSee('Smart Room')
            ->assertDontSee('Rüstung');
    }

    public function test_an_empty_projects_page_explains_what_projects_are(): void
    {
        $this->actingAs($this->user)->get('/board/projects')->assertOk()->assertSee('Noch keine Projekte und Gruppen.');
    }

    public function test_creating_validates_the_name_inline_and_opens_the_new_page(): void
    {
        Livewire::actingAs($this->user)->test(Projects::class)
            ->call('open', 'project')->set('newName', '   ')->call('create')
            ->assertHasErrors(['newName' => 'required']);

        Livewire::actingAs($this->user)->test(Projects::class)
            ->call('open', 'group')->set('newName', 'Umzug')->set('newList', 'todos')->call('create')
            ->assertRedirect(route('shell.group', TaskGroup::first()->id));

        $this->assertSame('todos', TaskGroup::first()->list);
    }

    public function test_a_group_list_must_be_todos_or_tasks(): void
    {
        Livewire::actingAs($this->user)->test(Projects::class)
            ->call('open', 'group')->set('newName', 'X')->set('newList', 'later')->call('create')
            ->assertHasErrors(['newList']);
        $this->assertSame(0, TaskGroup::count());
    }

    public function test_another_users_project_or_group_is_not_found(): void
    {
        $other = User::factory()->create();
        $p = (new ProjectEditor)->createProject($other, 'Fremd');
        $g = (new ProjectEditor)->createGroup($other, 'Fremd', 'tasks');

        $this->actingAs($this->user)->get('/board/projects/'.$p->id)->assertNotFound();
        $this->actingAs($this->user)->get('/board/groups/'.$g->id)->assertNotFound();
        $this->actingAs($this->user)->get('/board/projects')->assertDontSee('Fremd');
    }

    public function test_the_pages_need_a_login(): void
    {
        $this->get('/board/projects')->assertRedirect('/login');
    }

    // ---- A45: project and group pages ---------------------------------------------------------

    public function test_the_project_page_shows_roadmap_stages_and_marks_the_next_two(): void
    {
        $p = $this->enderdragon();

        $this->actingAs($this->user)->get('/board/projects/'.$p->id)->assertOk()
            ->assertSeeInOrder(['Roadmap', 'Bugs fixen', '30.11.', 'Server öffnen', 'ohne Datum'])
            ->assertSee('Als Nächstes')
            ->assertSee('wartet auf Etappe 1')
            ->assertSee('Backup testen');
    }

    public function test_steps_and_stages_are_added_inline_with_validation(): void
    {
        $p = $this->editor->createProject($this->user, 'P');

        Livewire::actingAs($this->user)->test(ProjectPage::class, ['project' => $p->id])
            ->call('openStage')->set('stageName', '')->call('addStage')->assertHasErrors(['stageName' => 'required'])
            ->set('stageName', 'Eins')->set('stageDate', 'kein Datum')->call('addStage')->assertHasErrors(['stageDate'])
            ->set('stageDate', '2026-12-01')->call('addStage')->assertHasNoErrors()
            ->set('newStep.none', '')->call('addStep', 'none')->assertHasErrors(['newStep.none'])
            ->set('newStep.none', 'Lose Idee')->set('newStepList', 'todos')->call('addStep', 'none')->assertHasNoErrors();

        $this->assertSame('2026-12-01', ProjectStage::first()->target_date->toDateString());
        $this->assertSame(['Lose Idee', 'todos'], [Task::first()->title, Task::first()->list]);
    }

    public function test_done_is_undoable_and_a_removed_stage_comes_back(): void
    {
        $p = $this->enderdragon();
        $spear = Task::where('title', 'Spear Swap')->first();
        $bugs = ProjectStage::where('name', 'Bugs fixen')->first();

        $page = Livewire::actingAs($this->user)->test(ProjectPage::class, ['project' => $p->id])
            ->call('done', $spear->id)->assertSee('ist erledigt');
        $this->assertTrue($spear->fresh()->is_completed);
        $page->call('undoLast');
        $this->assertFalse($spear->fresh()->is_completed);

        $page->call('removeStage', $bugs->id)->assertSee('Ihre Schritte stehen jetzt ohne Etappe');
        $this->assertNull($spear->fresh()->stage_id);
        $page->call('undoLast');
        $restored = ProjectStage::where('project_id', $p->id)->where('name', 'Bugs fixen')->first();
        $this->assertNotNull($restored);
        $this->assertSame($restored->id, $spear->fresh()->stage_id);
        $this->assertSame(5, Task::where('project_id', $p->id)->count(), 'no step was lost');
    }

    public function test_a_waits_on_loop_gets_a_calm_notice(): void
    {
        $p = $this->editor->createProject($this->user, 'P');
        $a = $this->editor->addProjectStep($this->user, $p, null, 'A');
        $b = $this->editor->addProjectStep($this->user, $p, null, 'B');
        $this->editor->setWaitsOn($b, $a);

        Livewire::actingAs($this->user)->test(ProjectPage::class, ['project' => $p->id])
            ->call('setWaitsOn', $a->id, (string) $b->id)
            ->assertSee('die beiden würden aufeinander warten');
        $this->assertNull($a->fresh()->waits_on_id);
    }

    public function test_start_from_a_project_page_opens_a_session_on_the_board(): void
    {
        $p = $this->enderdragon();
        $spear = Task::where('title', 'Spear Swap')->first();

        Livewire::actingAs($this->user)->test(ProjectPage::class, ['project' => $p->id])
            ->call('start', $spear->id)->assertRedirect(route('shell.board'));

        $this->assertSame($spear->id, app(FocusSession::class)->current($this->user)->taskId);
    }

    public function test_a_foreign_step_id_on_a_page_is_refused_calmly(): void
    {
        $p = $this->editor->createProject($this->user, 'P');
        $other = User::factory()->create();
        $foreign = (new ProjectEditor)->addProjectStep($other, (new ProjectEditor)->createProject($other, 'Q'), null, 'Fremd');

        Livewire::actingAs($this->user)->test(ProjectPage::class, ['project' => $p->id])
            ->call('done', $foreign->id)->assertSee('Das gibt es nicht mehr');
        $this->assertFalse($foreign->fresh()->is_completed);
    }

    public function test_the_group_page_adds_steps_in_its_list_and_reorders(): void
    {
        $g = $this->editor->createGroup($this->user, 'Notion', 'todos');

        $page = Livewire::actingAs($this->user)->test(GroupPage::class, ['group' => $g->id])
            ->set('newStep', 'Eins')->call('addStep')
            ->set('newStep', 'Zwei')->call('addStep');

        $zwei = Task::where('title', 'Zwei')->first();
        $this->assertSame('todos', $zwei->list);
        $page->call('moveStep', $zwei->id, 'none', 0);

        $this->assertSame(['Zwei', 'Eins'], Task::where('group_id', $g->id)->orderBy('sort_order')->pluck('title')->all());
    }
}
