<?php

namespace Tests\Feature\Refactor;

use App\Livewire\Shell\GroupPage;
use App\Livewire\Shell\ProjectPage;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\Task;
use App\Models\User;
use App\Services\Planning\EveningReset;
use App\Services\Projects\ProjectEditor;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\CssSelector\CssSelectorConverter;
use Tests\TestCase;

/**
 * C30 (step 4.3c): moving a step by drag, keyboard or the ⋯ menu ends in one validated action,
 * moveStep(); its toast has "Rückgängig", which brings back the place and a "wartet auf" link that
 * the move let go. The up/down arrows are gone. The pixels and the gestures were checked in a real
 * browser (docs/TESTBERICHT.md); these tests hold the server side and the markup the script reads.
 */
class StepMoveTest extends TestCase
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

    /**
     * Enderdragon: "Bugs fixen" with four steps, "Server öffnen" with one.
     *
     * @return array{Project, ProjectStage, ProjectStage, array<string, Task>}
     */
    private function enderdragon(): array
    {
        $p = $this->editor->createProject($this->user, 'Enderdragon');
        $bugs = $this->editor->addStage($p, 'Bugs fixen', null);
        $open = $this->editor->addStage($p, 'Server öffnen', null);
        $steps = [];
        foreach (['Spear Swap', 'Bubble Lift', 'Rüstung', 'Salvador'] as $title) {
            $steps[$title] = $this->editor->addProjectStep($this->user, $p, $bugs, $title);
        }
        $steps['Backup testen'] = $this->editor->addProjectStep($this->user, $p, $open, 'Backup testen');

        return [$p, $bugs, $open, $steps];
    }

    /** @return list<string> */
    private function titles(Project $project, ?ProjectStage $stage): array
    {
        return Task::where('project_id', $project->id)->active()
            ->when($stage === null, fn ($q) => $q->whereNull('stage_id'), fn ($q) => $q->where('stage_id', $stage->id))
            ->orderBy('sort_order')->orderBy('id')->pluck('title')->all();
    }

    private function page(Project $p)
    {
        return Livewire::actingAs($this->user)->test(ProjectPage::class, ['project' => $p->id]);
    }

    // ---- the action ---------------------------------------------------------------------------

    public function test_a_drop_puts_the_step_at_its_place_and_the_toast_undoes_it(): void
    {
        [$p, $bugs, , $steps] = $this->enderdragon();

        $page = $this->page($p)->call('moveStep', $steps['Salvador']->id, 'stage-'.$bugs->id, 0)
            ->assertSee('„Salvador“ steht jetzt auf Platz 1.')->assertSee('Rückgängig');
        $this->assertSame(['Salvador', 'Spear Swap', 'Bubble Lift', 'Rüstung'], $this->titles($p, $bugs));

        $page->call('undoLast')->assertSee('Rückgängig gemacht.');
        $this->assertSame(['Spear Swap', 'Bubble Lift', 'Rüstung', 'Salvador'], $this->titles($p, $bugs));
    }

    public function test_a_step_dropped_into_another_stage_says_so_and_goes_back_on_undo(): void
    {
        [$p, $bugs, $open, $steps] = $this->enderdragon();

        $page = $this->page($p)->call('moveStep', $steps['Spear Swap']->id, 'stage-'.$open->id, 0)
            ->assertSee('„Spear Swap“ ist jetzt in Etappe 2.');
        $this->assertSame(['Spear Swap', 'Backup testen'], $this->titles($p, $open));

        $page->call('undoLast');
        $this->assertSame(['Spear Swap', 'Bubble Lift', 'Rüstung', 'Salvador'], $this->titles($p, $bugs));
        $this->assertSame(['Backup testen'], $this->titles($p, $open));
    }

    public function test_a_step_dropped_on_no_stage_and_a_drop_without_a_position_goes_to_the_end(): void
    {
        [$p, $bugs, , $steps] = $this->enderdragon();
        $this->editor->addProjectStep($this->user, $p, null, 'Lose Idee');

        $this->page($p)->call('moveStep', $steps['Rüstung']->id, 'none')->assertSee('„Rüstung“ steht jetzt ohne Etappe.');

        $this->assertSame(['Lose Idee', 'Rüstung'], $this->titles($p, null));
        $this->assertNull($steps['Rüstung']->fresh()->stage_id);
        $this->assertSame(['Spear Swap', 'Bubble Lift', 'Salvador'], $this->titles($p, $bugs));
    }

    public function test_a_waiting_step_stays_draggable_and_keeps_its_link_while_the_order_holds(): void
    {
        [$p, $bugs, , $steps] = $this->enderdragon();
        $this->editor->setWaitsOn($steps['Rüstung'], $steps['Spear Swap']);

        $this->page($p)->call('moveStep', $steps['Rüstung']->id, 'stage-'.$bugs->id, 0)
            ->assertSee('„Rüstung“ steht jetzt auf Platz 1.')->assertDontSee('Das Warten ist gelöst.');

        $this->assertSame($steps['Spear Swap']->id, $steps['Rüstung']->fresh()->waits_on_id);
    }

    public function test_a_place_that_contradicts_the_stage_order_lets_go_of_the_link_and_undo_restores_both(): void
    {
        [$p, $bugs, $open, $steps] = $this->enderdragon();
        $this->editor->setWaitsOn($steps['Rüstung'], $steps['Spear Swap']); // both in stage one

        $page = $this->page($p)->call('moveStep', $steps['Spear Swap']->id, 'stage-'.$open->id, 1)
            ->assertSee('„Spear Swap“ ist jetzt in Etappe 2. Das Warten ist gelöst.')->assertSee('Rückgängig');
        $this->assertNull($steps['Rüstung']->fresh()->waits_on_id, 'Rüstung (stage 1) cannot wait on a step of stage 2');
        $this->assertSame(['Backup testen', 'Spear Swap'], $this->titles($p, $open));

        $page->call('undoLast');

        $this->assertSame($steps['Spear Swap']->id, $steps['Rüstung']->fresh()->waits_on_id, 'the waiting is back');
        $this->assertSame(['Spear Swap', 'Bubble Lift', 'Rüstung', 'Salvador'], $this->titles($p, $bugs));
        $this->assertSame(['Backup testen'], $this->titles($p, $open));
    }

    public function test_a_move_that_changes_nothing_says_nothing_and_keeps_the_last_undo(): void
    {
        [$p, $bugs, , $steps] = $this->enderdragon();

        $page = $this->page($p)->call('done', $steps['Salvador']->id)->assertSee('ist erledigt');
        $page->call('moveStep', $steps['Spear Swap']->id, 'stage-'.$bugs->id, 0) // already first
            ->assertSee('Salvador')->assertSee('ist erledigt');

        $page->call('undoLast');
        $this->assertFalse($steps['Salvador']->fresh()->is_completed, 'the undo of "Erledigt" was not replaced by a move that did nothing');
    }

    public function test_a_stage_that_is_not_here_a_foreign_step_or_a_finished_one_is_refused_calmly(): void
    {
        [$p, $bugs, , $steps] = $this->enderdragon();
        $other = $this->editor->createProject($this->user, 'Q');
        $foreignStage = $this->editor->addStage($other, 'Fremd', null);
        $stranger = User::factory()->create();
        $theirs = $this->editor->addProjectStep($stranger, $this->editor->createProject($stranger, 'Ihr'), null, 'Fremd');
        $steps['Backup testen']->forceFill(['is_completed' => true])->save();

        $page = $this->page($p);
        foreach ([
            [$steps['Spear Swap']->id, 'stage-'.$foreignStage->id], // a stage of another project
            [$steps['Spear Swap']->id, 'stage-999999'],             // a stage nobody has
            [$steps['Spear Swap']->id, 'etappe'],                   // not a key at all
            [$theirs->id, 'none'],                                  // another user's step
            [$steps['Backup testen']->id, 'none'],                  // a finished step
        ] as [$id, $key]) {
            $page->call('dismiss')->call('moveStep', $id, $key, 0)->assertSee('Das gibt es nicht mehr');
        }

        $this->assertSame(['Spear Swap', 'Bubble Lift', 'Rüstung', 'Salvador'], $this->titles($p, $bugs));
        $this->assertNull($theirs->fresh()->stage_id);
        $this->assertSame($steps['Backup testen']->stage_id, $steps['Backup testen']->fresh()->stage_id, 'the finished step did not move');
    }

    public function test_the_group_page_moves_within_its_list_and_the_toast_undoes_it(): void
    {
        $g = $this->editor->createGroup($this->user, 'Notion', 'todos');
        [$a, $b, $c] = array_map(fn ($t) => $this->editor->addGroupStep($this->user, $g, $t), ['A', 'B', 'C']);

        $page = Livewire::actingAs($this->user)->test(GroupPage::class, ['group' => $g->id])
            ->call('moveStep', $c->id, 'none', 0)->assertSee('„C“ steht jetzt auf Platz 1.');
        $this->assertSame(['C', 'A', 'B'], Task::where('group_id', $g->id)->orderBy('sort_order')->pluck('title')->all());

        $page->call('undoLast')->assertSee('Rückgängig gemacht.');
        $this->assertSame(['A', 'B', 'C'], Task::where('group_id', $g->id)->orderBy('sort_order')->pluck('title')->all());

        $page->call('moveStep', $a->id, 'stage-1', 0)->assertSee('Das gibt es nicht mehr'); // a group has no stages
    }

    // ---- the page: arrows gone, menu and grip there ---------------------------------------------

    private function dom(string $html): DOMXPath
    {
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($doc);
    }

    private function css(DOMXPath $dom, string $selector)
    {
        return $dom->query((new CssSelectorConverter)->toXPath($selector));
    }

    public function test_the_arrows_are_gone_and_the_menu_and_the_grip_replace_them(): void
    {
        [$p, $bugs, $open, $steps] = $this->enderdragon();
        $html = $this->actingAs($this->user)->get('/board/projects/'.$p->id)->assertOk()->getContent();

        $this->assertStringNotContainsString('wire:click="move(', $html, 'no arrow calls move() any more');
        $this->assertStringNotContainsString('Nach oben:', $html);
        $this->assertStringNotContainsString('Nach unten:', $html);

        $dom = $this->dom($html);
        $this->assertSame(5, $this->css($dom, 'li[data-step] button[data-grip]')->length, 'every open step has a grip');
        $this->assertSame(5, $this->css($dom, 'li[data-step] [data-step-menu]')->length, 'and a menu');

        // The menu of a step in stage one: top, bottom, the other stage, none, and "wartet auf".
        $this->assertStringContainsString('moveStep('.$steps['Spear Swap']->id.", 'stage-".$bugs->id."', 0)", $html);
        $this->assertStringContainsString('moveStep('.$steps['Spear Swap']->id.", 'stage-".$bugs->id."')", $html);
        $this->assertStringContainsString('moveStep('.$steps['Spear Swap']->id.", 'stage-".$open->id."')", $html);
        $this->assertStringContainsString('moveStep('.$steps['Spear Swap']->id.", 'none')", $html);
        foreach (['Ganz nach oben', 'Ganz nach unten', 'In Etappe 2 verschieben', 'Ohne Etappe', 'Wartet auf'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        // The first step cannot go further up, the last cannot go further down.
        $this->assertCount(1, $dom->query('//li[@data-step="'.$steps['Spear Swap']->id.'"]//button[@role="menuitem"][@disabled][contains(., "Ganz nach oben")]'));
        $this->assertCount(1, $dom->query('//li[@data-step="'.$steps['Salvador']->id.'"]//button[@role="menuitem"][@disabled][contains(., "Ganz nach unten")]'));
    }

    public function test_every_lane_is_a_place_to_drop_on_even_when_it_is_empty(): void
    {
        $p = $this->editor->createProject($this->user, 'Leer');
        $one = $this->editor->addStage($p, 'Eins', null);
        $two = $this->editor->addStage($p, 'Zwei', null);
        $this->editor->addProjectStep($this->user, $p, $one, 'Nur einer');

        $dom = $this->dom($this->actingAs($this->user)->get('/board/projects/'.$p->id)->assertOk()->getContent());

        $lanes = [];
        foreach ($this->css($dom, 'ul[data-lane]') as $ul) {
            $lanes[$ul->getAttribute('data-lane')] = [$ul->getAttribute('data-index'), $ul->getAttribute('data-name')];
        }
        $this->assertSame(['stage-'.$one->id => ['0', 'Eins'], 'stage-'.$two->id => ['1', 'Zwei'], 'none' => ['-1', '']], $lanes);
        $this->assertCount(2, $this->css($dom, 'ul[data-lane] > li[data-lane-empty]'), 'the two empty lanes say where to drop');
        $this->assertCount(3, $this->css($dom, '[data-lane-box]'));
        $this->assertCount(1, $this->css($dom, '#step-drag-hint'), 'the keyboard hint the grips point to');
    }

    public function test_a_waiting_step_has_a_grip_and_tells_the_script_what_it_waits_on(): void
    {
        [$p, , , $steps] = $this->enderdragon();
        $this->editor->setWaitsOn($steps['Rüstung'], $steps['Spear Swap']);

        $dom = $this->dom($this->actingAs($this->user)->get('/board/projects/'.$p->id)->assertOk()->getContent());

        $row = $this->css($dom, 'li[data-step="'.$steps['Rüstung']->id.'"]')->item(0);
        $this->assertSame((string) $steps['Spear Swap']->id, $row->getAttribute('data-waits-on'));
        $this->assertSame('tasks', $row->getAttribute('data-list'));
        $this->assertSame('Rüstung', $row->getAttribute('data-title'));
        $this->assertCount(1, $dom->query('.//button[@data-grip]', $row), 'a waiting step stays draggable');
        $this->assertSame('', $this->css($dom, 'li[data-step="'.$steps['Spear Swap']->id.'"]')->item(0)->getAttribute('data-waits-on'));
    }

    public function test_a_group_page_has_one_lane_and_a_menu_without_stages(): void
    {
        $g = $this->editor->createGroup($this->user, 'Notion', 'tasks');
        $this->editor->addGroupStep($this->user, $g, 'Eins');
        $this->editor->addGroupStep($this->user, $g, 'Zwei');

        $html = $this->actingAs($this->user)->get('/board/groups/'.$g->id)->assertOk()->getContent();
        $dom = $this->dom($html);

        $this->assertCount(1, $this->css($dom, 'ul[data-lane="none"][data-index="-1"]'));
        $this->assertSame(2, $this->css($dom, 'li[data-step] button[data-grip]')->length);
        $this->assertStringContainsString('Ganz nach oben', $html);
        $this->assertStringNotContainsString('In Etappe', $html);
        $this->assertStringNotContainsString('setWaitsOn', $html, 'a group has no "wartet auf"');
        $this->assertStringNotContainsString('wire:click="move(', $html);
    }

    public function test_the_script_texts_exist_in_both_languages_with_the_same_keys(): void
    {
        $de = trans('projects.move.js', [], 'de');
        $en = trans('projects.move.js', [], 'en');

        $this->assertIsArray($de);
        $this->assertSame(array_keys($de), array_keys($en));
        foreach ($de as $key => $text) {
            preg_match_all('/:[a-z]+/', $text, $germanSlots);
            preg_match_all('/:[a-z]+/', $en[$key], $englishSlots);
            $this->assertEqualsCanonicalizing($germanSlots[0], $englishSlots[0], 'the same placeholders in "'.$key.'"');
        }
    }
}
