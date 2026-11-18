<?php

namespace Tests\Feature\Refactor;

use App\Models\Note;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use App\Services\Projects\NoteEditor;
use App\Services\Projects\NoteTags;
use App\Services\Projects\ProjectEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * A57 (tags and note writes) and A58 (safe Markdown with highlighted tags), step 4.3b.
 */
class NoteEditorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private NoteEditor $notes;

    private ProjectEditor $projects;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->notes = new NoteEditor;
        $this->projects = new ProjectEditor;
    }

    // ---- A57 ----------------------------------------------------------------------------------

    public function test_tags_are_words_after_a_hash_not_headings_anchors_or_numbers(): void
    {
        $this->assertSame(
            ['bug', 'idee', 'über-mich', 'link_2'],
            NoteTags::parse("#Bug am Anfang, mitten #idee und #über-mich.\n# Überschrift\nhttps://x.ch/seite#anker a#b #42 #link_2 #bug"),
        );
    }

    public function test_a_note_lands_loose_at_the_end_and_its_tags_are_shared_per_user(): void
    {
        $p = $this->projects->createProject($this->user, 'P');
        $q = $this->projects->createProject($this->user, 'Q');
        $a = $this->notes->add($this->user, $p, 'Erste #bug');
        $b = $this->notes->add($this->user, $p, 'Zweite');
        $c = $this->notes->add($this->user, $q, 'Woanders #Bug #idee');

        $this->assertSame([null, 1, 2], [$a->stage_id, $a->sort_order, $b->sort_order]);
        $this->assertSame(1, $c->sort_order, 'order is per project');
        $this->assertSame(['bug', 'idee'], Tag::where('user_id', $this->user->id)->orderBy('name')->pluck('name')->all());
        $this->assertSame($a->tags->first()->id, $c->tags->firstWhere('name', 'bug')->id, 'the same tag across projects');
    }

    public function test_editing_resyncs_tags_and_drops_unused_ones(): void
    {
        $p = $this->projects->createProject($this->user, 'P');
        $note = $this->notes->add($this->user, $p, 'Text #alt');

        $this->notes->update($note, 'Text #neu');

        $this->assertSame(['neu'], $note->fresh()->tags->pluck('name')->all());
        $this->assertSame(['neu'], Tag::where('user_id', $this->user->id)->pluck('name')->all());
    }

    public function test_a_note_goes_into_a_stage_of_its_own_project_only(): void
    {
        $p = $this->projects->createProject($this->user, 'P');
        $stage = $this->projects->addStage($p, 'Eins', null);
        $foreign = $this->projects->addStage($this->projects->createProject($this->user, 'Q'), 'Fremd', null);
        $this->notes->add($this->user, $p, 'Schon da', $stage);
        $note = $this->notes->add($this->user, $p, 'Wandert');

        $this->notes->setStage($note, $stage);
        $this->assertSame([$stage->id, 2], [$note->fresh()->stage_id, $note->fresh()->sort_order]);

        $this->expectException(InvalidArgumentException::class);
        $this->notes->setStage($note->fresh(), $foreign);
    }

    public function test_a_group_note_has_no_stage(): void
    {
        $g = $this->projects->createGroup($this->user, 'G', 'tasks');
        $note = $this->notes->add($this->user, $g, 'Gruppen-Notiz #x');

        $this->assertSame([$g->id, null], [$note->group_id, $note->project_id]);

        $this->expectException(InvalidArgumentException::class);
        $this->notes->setStage($note, $this->projects->addStage($this->projects->createProject($this->user, 'P'), 'S', null));
    }

    public function test_to_step_turns_the_note_into_a_step_in_its_stage_and_back(): void
    {
        $p = $this->projects->createProject($this->user, 'P');
        $stage = $this->projects->addStage($p, 'Bugs', null);
        $note = $this->notes->add($this->user, $p, "**Spear Swap** im Nether prüfen #bug\n\nPos x=700 z=-691", $stage);

        $task = $this->notes->toStep($this->user, $note);

        $this->assertSame('Spear Swap im Nether prüfen', $task->title, 'first line, Markdown and tags removed');
        $this->assertSame("**Spear Swap** im Nether prüfen #bug\n\nPos x=700 z=-691", $task->notes, 'the whole note stays on the step');
        $this->assertSame([$p->id, $stage->id, 'tasks'], [$task->project_id, $task->stage_id, $task->list]);
        $this->assertNull(Note::find($note->id));

        $restored = $this->notes->restore($this->user, $this->notes->snapshot($note), $task->id);
        $this->assertNull(Task::find($task->id), 'undo removes the step it made');
        $this->assertSame(['bug'], $restored->tags->pluck('name')->all());
        $this->assertSame($stage->id, $restored->stage_id);
    }

    public function test_a_group_note_becomes_a_step_in_the_groups_list(): void
    {
        $g = $this->projects->createGroup($this->user, 'G', 'todos');
        $task = $this->notes->toStep($this->user, $this->notes->add($this->user, $g, 'Anrufen'));

        $this->assertSame([$g->id, 'todos'], [$task->group_id, $task->list]);
    }

    public function test_deleting_a_stage_leaves_its_notes_loose(): void
    {
        $p = $this->projects->createProject($this->user, 'P');
        $stage = $this->projects->addStage($p, 'Eins', null);
        $note = $this->notes->add($this->user, $p, 'Bleibt', $stage);

        $this->projects->removeStage($stage);

        $this->assertNull($note->fresh()->stage_id);
    }

    // ---- A58 ----------------------------------------------------------------------------------

    public function test_markdown_is_rendered_safely_and_tags_are_marked_outside_links_and_code(): void
    {
        $p = $this->projects->createProject($this->user, 'P');
        $note = $this->notes->add($this->user, $p, "**fett** #bug <script>alert(1)</script>\n\n[x](javascript:alert(1)) [doku](https://x.ch/#anker) `#code`");

        $html = $note->html();

        $this->assertStringContainsString('<strong>fett</strong>', $html);
        $this->assertStringContainsString('data-tag="bug"', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('href="https://x.ch/#anker"', $html);
        $this->assertStringContainsString('<code>#code</code>', $html);
        $this->assertSame(1, substr_count($html, 'data-tag='), 'only the real tag is marked');
    }
}
