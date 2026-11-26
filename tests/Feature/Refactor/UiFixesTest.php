<?php

namespace Tests\Feature\Refactor;

use App\Livewire\Shell\GroupPage;
use App\Livewire\Shell\ProjectPage;
use App\Models\User;
use App\Services\Projects\ProjectEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * ui-fixes (Niels' feedback after the PR round): the ⋯ menus stay inside the screen, "löschen" moved into the
 * header menu of a project or group page, the confirm is a card of at most 28rem and the page scrollbar no
 * longer shifts the layout. The placing itself is measured in a real browser (TESTBERICHT B60+); these tests
 * hold the markup rules that make it work.
 */
class UiFixesTest extends TestCase
{
    use RefreshDatabase;

    private function source(string $name): string
    {
        return file_get_contents(resource_path('views/'.$name.'.blade.php'));
    }

    public function test_every_popover_menu_of_the_shell_is_placed_by_the_shared_helper(): void
    {
        // A100-style guard (A130): a menu that positions itself with absolute/top-full cannot flip upward.
        foreach (['shell/item-menu', 'shell/step-menu', 'shell/battery', 'shell/owner-menu'] as $name) {
            $source = $this->source($name);

            $this->assertStringContainsString('x-data="popover(', $source, "$name uses the shared popover helper");
            $this->assertStringContainsString('x-ref="panel"', $source, "$name marks its panel for the helper");
            $this->assertStringContainsString(':style="style"', $source, "$name takes its place from the helper");
            $this->assertStringContainsString('x-ref="trigger"', $source, "$name marks its trigger");
            $this->assertStringNotContainsString('wb:absolute', $source, "$name must not place itself");
            $this->assertStringNotContainsString('top-full', $source, "$name must not hang below its trigger");
        }
    }

    public function test_the_helper_is_loaded_and_keeps_the_phone_sheet_apart(): void
    {
        $this->assertStringContainsString("import './popover';", file_get_contents(resource_path('js/app.js')));

        $js = file_get_contents(resource_path('js/popover.js'));
        $this->assertStringContainsString("Alpine.data('popover'", $js);
        $this->assertStringContainsString('(min-width: 720px)', $js, 'below 720 px the bottom sheet stays untouched');
        $this->assertStringContainsString('placePopover', $js);
        $this->assertStringContainsString('maxHeight', $js, 'a menu taller than both sides scrolls inside');
    }

    public function test_the_project_page_menu_holds_delete_and_the_bottom_button_is_gone(): void
    {
        $user = User::factory()->create();
        $project = (new ProjectEditor)->createProject($user, 'Enderdragon');

        $html = Livewire::actingAs($user)->test(ProjectPage::class, ['project' => $project->id])
            ->assertSeeHtml('data-owner-menu')
            ->assertSee(__('projects.delete.project'))
            ->html();

        $this->assertSame(1, substr_count($html, 'data-delete-owner'), 'one entry only, in the header menu');
        $this->assertGreaterThan(strpos($html, 'data-owner-menu'), strpos($html, 'data-delete-owner'));
        $this->assertLessThan(strpos($html, 'id="roadmap"'), strpos($html, 'data-delete-owner'), 'it sits in the header, above the roadmap');
    }

    public function test_the_group_page_menu_holds_delete_too(): void
    {
        $user = User::factory()->create();
        $group = (new ProjectEditor)->createGroup($user, 'Einkauf', 'todos');

        $html = Livewire::actingAs($user)->test(GroupPage::class, ['group' => $group->id])
            ->assertSeeHtml('data-owner-menu')
            ->assertSee(__('projects.delete.group'))
            ->html();

        $this->assertSame(1, substr_count($html, 'data-delete-owner'));
    }

    public function test_the_menu_entry_still_opens_the_confirm_and_keeps_the_rules(): void
    {
        $user = User::factory()->create();
        $project = (new ProjectEditor)->createProject($user, 'Enderdragon');

        Livewire::actingAs($user)->test(ProjectPage::class, ['project' => $project->id])
            ->call('askDelete')
            ->assertSet('confirmingDelete', true)
            ->assertSeeHtml('data-confirm-delete')
            ->call('cancelDelete')
            ->assertSet('confirmingDelete', false);
    }

    public function test_the_confirm_is_a_centred_card_of_at_most_28rem(): void
    {
        $source = $this->source('shell/confirm-delete');

        $this->assertMatchesRegularExpression('/\bmax-w-md\b/', $source, 'no wb: prefix: the sheet is capped on a narrow desktop too');
        $this->assertStringNotContainsString('wb:max-w-md', $source);
        $this->assertStringContainsString('justify-center', $source);

        // "Behalten" is first in the DOM, so the focus trap starts on the safe choice.
        $this->assertLessThan(strpos($source, 'data-confirm-delete-button'), strpos($source, 'projects.delete.cancel'));
    }

    public function test_the_page_scrollbar_has_a_stable_gutter(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression('/html\s*\{[^}]*scrollbar-gutter:\s*stable/s', $css);
    }
}
