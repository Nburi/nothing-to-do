<?php

namespace Tests\Feature\Refactor;

use App\Livewire\Shell\GroupPage;
use App\Livewire\Shell\ProjectPage;
use App\Models\Note;
use App\Models\Task;
use App\Models\User;
use App\Services\DataExport;
use App\Services\Projects\NoteEditor;
use App\Services\Projects\ProjectEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A59 (notes on the project page) and A60 (notes on the group page, export), step 4.3b.
 */
class ProjectNotesShellTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ProjectEditor $projects;

    private NoteEditor $notes;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');
        $this->user = User::factory()->create();
        $this->projects = new ProjectEditor;
        $this->notes = new NoteEditor;
    }

    // ---- A59 ----------------------------------------------------------------------------------

    public function test_the_project_page_shows_notes_by_pile_with_rendered_markdown(): void
    {
        $p = $this->projects->createProject($this->user, 'Enderdragon');
        $bugs = $this->projects->addStage($p, 'Bugs fixen', null);
        $this->notes->add($this->user, $p, '**Lifesteal**-Mod');
        $this->notes->add($this->user, $p, 'Nur im Nether #bug', $bugs);

        $this->actingAs($this->user)->get('/board/projects/'.$p->id)->assertOk()
            ->assertSee('Notizen')
            ->assertSeeInOrder(['Lose', '<strong>Lifesteal</strong>-Mod', 'Etappe: 1 · Bugs fixen', 'Nur im Nether'], false)
            ->assertSee('data-tag="bug"', false);
    }

    public function test_a_note_is_added_with_inline_validation_and_lands_loose(): void
    {
        $p = $this->projects->createProject($this->user, 'P');

        Livewire::actingAs($this->user)->test(ProjectPage::class, ['project' => $p->id])
            ->set('noteDraft', '   ')->call('addNote')->assertHasErrors(['noteDraft' => 'required'])
            ->set('noteDraft', "Idee #neu\n\nzweite Zeile")->call('addNote')->assertHasNoErrors()
            ->assertSet('noteDraft', '')
            ->assertSee('zweite Zeile');

        $note = Note::first();
        $this->assertSame([$p->id, null], [$note->project_id, $note->stage_id]);
        $this->assertSame(['neu'], $note->tags->pluck('name')->all());
    }

    public function test_the_tag_filter_shows_only_matching_notes(): void
    {
        $p = $this->projects->createProject($this->user, 'P');
        $this->notes->add($this->user, $p, 'Fehler eins #bug');
        $this->notes->add($this->user, $p, 'Gute Idee #idee');

        Livewire::actingAs($this->user)->test(ProjectPage::class, ['project' => $p->id])
            ->call('filterTag', 'BUG')->assertSet('noteTag', 'bug')
            ->assertSee('Fehler eins')->assertDontSee('Gute Idee')
            ->call('filterTag', 'bug')->assertSet('noteTag', null)->assertSee('Gute Idee');
    }

    public function test_a_note_is_moved_to_a_stage_by_chip_or_drop(): void
    {
        $p = $this->projects->createProject($this->user, 'P');
        $stage = $this->projects->addStage($p, 'Eins', null);
        $a = $this->notes->add($this->user, $p, 'A', $stage);
        $b = $this->notes->add($this->user, $p, 'B');

        $page = Livewire::actingAs($this->user)->test(ProjectPage::class, ['project' => $p->id]);
        $page->call('setNoteStage', $b->id, 'stage-'.$stage->id, 0);
        $this->assertSame(['B', 'A'], Note::where('stage_id', $stage->id)->orderBy('sort_order')->pluck('body')->all(), 'a drop places it');

        $page->call('setNoteStage', $b->id, 'none');
        $this->assertNull($b->fresh()->stage_id);
    }

    public function test_edit_in_place_and_delete_with_undo(): void
    {
        $p = $this->projects->createProject($this->user, 'P');
        $note = $this->notes->add($this->user, $p, 'Alt #a');

        $page = Livewire::actingAs($this->user)->test(ProjectPage::class, ['project' => $p->id])
            ->call('editNote', $note->id)->assertSet('editNoteBody', 'Alt #a')
            ->set('editNoteBody', '')->call('saveNote')->assertHasErrors(['editNoteBody'])
            ->set('editNoteBody', 'Neu #b')->call('saveNote')->assertHasNoErrors()->assertSet('editingNote', null);
        $this->assertSame('Neu #b', $note->fresh()->body);

        $page->call('deleteNote', $note->id)->assertSee('Notiz gelöscht.');
        $this->assertSame(0, Note::count());
        $page->call('undoLast');
        $this->assertSame(['Neu #b'], Note::pluck('body')->all());
    }

    public function test_to_step_turns_the_note_into_a_step_and_undo_brings_it_back(): void
    {
        $p = $this->projects->createProject($this->user, 'P');
        $stage = $this->projects->addStage($p, 'Bugs', null);
        $note = $this->notes->add($this->user, $p, 'Spear Swap prüfen #bug', $stage);

        $page = Livewire::actingAs($this->user)->test(ProjectPage::class, ['project' => $p->id])
            ->call('noteToStep', $note->id)->assertSee('ist jetzt ein Schritt');

        $task = Task::where('project_id', $p->id)->first();
        $this->assertSame(['Spear Swap prüfen', $stage->id], [$task->title, $task->stage_id]);
        $this->assertSame(0, Note::count());

        $page->call('undoLast');
        $this->assertSame(0, Task::where('project_id', $p->id)->count());
        $this->assertSame($stage->id, Note::first()->stage_id);
    }

    public function test_a_foreign_note_is_refused_calmly(): void
    {
        $p = $this->projects->createProject($this->user, 'P');
        $other = User::factory()->create();
        $foreign = $this->notes->add($other, $this->projects->createProject($other, 'Q'), 'Fremd');

        Livewire::actingAs($this->user)->test(ProjectPage::class, ['project' => $p->id])
            ->call('deleteNote', $foreign->id)->assertSee('Das gibt es nicht mehr');
        $this->assertNotNull($foreign->fresh());
    }

    // ---- A60 ----------------------------------------------------------------------------------

    public function test_a_group_has_notes_without_stages(): void
    {
        $g = $this->projects->createGroup($this->user, 'Notion', 'tasks');

        Livewire::actingAs($this->user)->test(GroupPage::class, ['group' => $g->id])
            ->set('noteDraft', 'Vorlage fragen #idee')->call('addNote')
            ->assertSee('Vorlage fragen')->assertDontSee('Etappe wählen');

        $note = Note::first();
        $this->assertSame([$g->id, null, null], [$note->group_id, $note->project_id, $note->stage_id]);

        Livewire::actingAs($this->user)->test(GroupPage::class, ['group' => $g->id])
            ->call('noteToStep', $note->id)->call('undoLast');
        $this->assertSame(1, Note::count());
        $this->assertSame(0, Task::where('group_id', $g->id)->count());
    }

    public function test_the_export_holds_notes_with_their_tags(): void
    {
        $p = $this->projects->createProject($this->user, 'P');
        $stage = $this->projects->addStage($p, 'Eins', null);
        $this->notes->add($this->user, $p, 'Projekt-Notiz #a', $stage);
        $g = $this->projects->createGroup($this->user, 'G', 'tasks');
        $this->notes->add($this->user, $g, 'Gruppen-Notiz #b');

        $data = DataExport::for($this->user);

        $this->assertSame([['body' => 'Projekt-Notiz #a', 'stage_id' => $stage->id, 'tags' => ['a']]], $data['projects'][0]['notes']);
        $this->assertSame([['body' => 'Gruppen-Notiz #b', 'stage_id' => null, 'tags' => ['b']]], collect($data['task_groups'])->firstWhere('name', 'G')['note_cards']);
    }
}
