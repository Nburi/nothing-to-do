<?php

namespace Tests\Feature\Refactor;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\Planning\EveningReset;
use App\Services\Projects\ProjectEditor;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\CssSelector\CssSelectorConverter;
use Tests\TestCase;

/**
 * C30 follow-up of step 4.3a (Niels' C-tests, 2026-11-17): the roadmap strip of a project page and the
 * ⋯ menu of a waiting step. The pixels were checked in a real browser at 1280 and 390 px
 * (docs/TESTBERICHT.md B27); these tests hold the markup rules that layout stands on.
 */
class ProjectRoadmapTest extends TestCase
{
    use RefreshDatabase;

    /** A class token that starts with "opacity-" (not a variant such as "disabled:opacity-30"). */
    private const FADED = "contains(concat(' ', normalize-space(@class), ' '), ' opacity-')";

    private User $user;

    private ProjectEditor $editor;

    /** The raw HTML of the page last fetched with page(). */
    private string $html = '';

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

    /** The rendered project page, ready for XPath queries. */
    private function page(Project $project): DOMXPath
    {
        $this->html = $this->actingAs($this->user)->get('/board/projects/'.$project->id)->assertOk()->getContent();

        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>'.$this->html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($doc);
    }

    private function css(DOMXPath $page, string $selector, ?DOMNode $within = null): DOMNodeList
    {
        return $page->query((new CssSelectorConverter)->toXPath($selector), $within);
    }

    // ---- the roadmap strip --------------------------------------------------------------------

    public function test_the_roadmap_lists_every_stage_in_order_with_its_state_and_date(): void
    {
        $p = $this->editor->createProject($this->user, 'Reihenfolge');
        $one = $this->editor->addStage($p, 'Erste Etappe', '2026-11-20');
        $two = $this->editor->addStage($p, 'Zweite Etappe', null);
        $three = $this->editor->addStage($p, 'Dritte Etappe', '2026-12-24');
        $this->editor->addProjectStep($this->user, $p, $one, 'Erledigt')->update(['is_completed' => true]);
        $this->editor->addProjectStep($this->user, $p, $two, 'Offen');
        $this->editor->addProjectStep($this->user, $p, $three, 'Später');

        $page = $this->page($p);
        $seen = [];
        foreach ($this->css($page, 'ol[data-roadmap] > li') as $li) {
            $seen[] = [
                $li->getAttribute('data-state'),
                trim($this->css($page, '[data-roadmap-name]', $li)->item(0)->textContent),
                trim($this->css($page, '[data-roadmap-date]', $li)->item(0)->textContent),
            ];
        }

        $this->assertSame([
            ['done', 'Erste Etappe', '20.11.'],
            ['now', 'Zweite Etappe', 'ohne Datum'],
            ['waits', 'Dritte Etappe', '24.12.'],
        ], $seen);
    }

    public function test_a_project_without_stages_one_stage_and_a_finished_stage_all_render(): void
    {
        $empty = $this->editor->createProject($this->user, 'Leer');
        $page = $this->page($empty);
        $this->assertCount(0, $this->css($page, '[data-roadmap]'));
        $this->assertStringContainsString('Noch keine Etappen', $this->html);

        $single = $this->editor->createProject($this->user, 'Eine');
        $only = $this->editor->addStage($single, 'Allein', null);
        $this->editor->addProjectStep($this->user, $single, $only, 'Offen');
        $items = $this->css($this->page($single), 'ol[data-roadmap] > li');
        $this->assertCount(1, $items);
        $this->assertSame('now', $items->item(0)->getAttribute('data-state'));

        // Every step done: no stage is current any more, the roadmap shows it as reached.
        Task::where('project_id', $single->id)->update(['is_completed' => true]);
        $items = $this->css($this->page($single), 'ol[data-roadmap] > li');
        $this->assertSame('done', $items->item(0)->getAttribute('data-state'));
    }

    public function test_a_long_roadmap_keeps_every_stage_in_order(): void
    {
        $p = $this->editor->createProject($this->user, 'Viele');
        $names = [];
        foreach (range(1, 12) as $n) {
            $names[] = 'Etappe '.$n;
            $this->editor->addStage($p, 'Etappe '.$n, $n % 2 === 0 ? sprintf('2027-%02d-01', $n) : null);
        }

        $page = $this->page($p);
        $seen = [];
        foreach ($this->css($page, 'ol[data-roadmap] > li [data-roadmap-name]') as $name) {
            $seen[] = trim($name->textContent);
        }

        $this->assertSame($names, $seen);
    }

    public function test_a_long_name_wraps_inside_its_column_and_is_escaped(): void
    {
        // The C30 screenshot: a long first label was cut off on the left and ran into the next one,
        // because every column was fixed at 7–9 rem and centred its unbreakable word on both sides.
        $long = 'Datenschutzerklärungsüberarbeitungsschleife';
        $p = $this->editor->createProject($this->user, 'Lang');
        $this->editor->addStage($p, '<b>'.$long.'</b>', null);
        $this->editor->addStage($p, 'Größenänderung für Übergabe', '2026-12-01');

        $page = $this->page($p);
        $this->assertStringContainsString('&lt;b&gt;'.$long.'&lt;/b&gt;', $this->html);
        $this->assertStringContainsString('Größenänderung für Übergabe', $this->html);
        $this->assertStringNotContainsString('<b>'.$long, $this->html);

        $strip = $this->css($page, 'ol[data-roadmap]')->item(0);
        $this->assertStringNotContainsString(' min-w-max ', ' '.$strip->getAttribute('class').' ', 'the strip must not be wider than its card');
        foreach ($this->css($page, 'ol[data-roadmap] > li') as $li) {
            $this->assertDoesNotMatchRegularExpression('/(^|\s)w-(28|36)(\s|$)/', $li->getAttribute('class'), 'no fixed-width column');
        }
        foreach ($this->css($page, '[data-roadmap-name]') as $name) {
            $this->assertStringContainsString('wrap-break-word', $name->getAttribute('class'), 'a long word breaks instead of overflowing');
        }
    }

    // ---- the ⋯ menu of a waiting step ---------------------------------------------------------

    public function test_the_menu_of_a_waiting_step_is_never_inside_something_dimmed(): void
    {
        // C30: a step that waits was faded as a whole row, so its open menu was faded with it.
        $p = $this->editor->createProject($this->user, 'Warten');
        $now = $this->editor->addStage($p, 'Jetzt', null);
        $later = $this->editor->addStage($p, 'Später', null);
        $first = $this->editor->addProjectStep($this->user, $p, $now, 'Erster');
        $this->editor->addProjectStep($this->user, $p, $now, 'Zweiter');
        $blocked = $this->editor->addProjectStep($this->user, $p, $now, 'Wartet auf Erster');
        $this->editor->setWaitsOn($blocked, $first);
        $inLaterStage = $this->editor->addProjectStep($this->user, $p, $later, 'In späterer Etappe');

        $page = $this->page($p);

        $waiting = $this->css($page, 'li[data-step][data-waiting]');
        $this->assertSame(
            [(string) $blocked->id, (string) $inLaterStage->id],
            array_map(fn ($li) => $li->getAttribute('data-step'), iterator_to_array($waiting)),
        );
        $this->assertCount(0, $this->css($page, 'li[data-step="'.$first->id.'"][data-waiting]'), 'a next step is not dimmed');

        // No row is faded as a whole ...
        $this->assertCount(0, $page->query('//li[@data-step][ancestor-or-self::*['.self::FADED.']]'));
        foreach ($waiting as $li) {
            // ... the step itself still is: its text block, its buttons and the ⋯ trigger carry the fade ...
            $this->assertGreaterThanOrEqual(3, $page->query('.//*['.self::FADED.']', $li)->length);
            // ... while the menu has no faded element around it or inside it.
            $menu = $page->query('.//*[@data-step-menu]', $li);
            $this->assertCount(1, $menu);
            $this->assertCount(0, $page->query('ancestor-or-self::*['.self::FADED.']', $menu->item(0)));
            $this->assertCount(0, $page->query('.//*['.self::FADED.']', $menu->item(0)));
        }
    }
}
