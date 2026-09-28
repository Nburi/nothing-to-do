<?php

namespace Tests\Feature;

use App\Livewire\CommandPalette;
use App\Livewire\QuickCapture;
use App\Models\AgendaEntry;
use App\Models\CraftIdea;
use App\Models\HelpArticle;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\User;
use App\Services\PaletteSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CommandPaletteTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> every result title across all groups */
    private function titles(User $user, string $query): array
    {
        return collect(PaletteSearch::search($user, $query))
            ->flatMap(fn (array $g) => collect($g['items'])->pluck('title'))
            ->all();
    }

    private function group(User $user, string $query, string $key): array
    {
        return collect(PaletteSearch::search($user, $query))->firstWhere('key', $key)['items'] ?? [];
    }

    public function test_an_empty_query_lists_only_pages(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user)->inbox()->create(['title' => 'Startnummer']);

        $groups = PaletteSearch::search($user, '   ');

        $this->assertSame(['pages'], collect($groups)->pluck('key')->all());
        $this->assertContains('Board', $this->titles($user, ''));
    }

    public function test_pages_match_on_keywords_not_just_the_label(): void
    {
        $user = User::factory()->create();

        $this->assertContains('Fortschritt', $this->titles($user, 'streak'));
        $this->assertContains('Einstellungen', $this->titles($user, 'zeitzone'));
    }

    public function test_a_hidden_module_disappears_from_pages_and_content(): void
    {
        $user = User::factory()->create(['hidden_modules' => ['agenda', 'crafts']]);
        AgendaEntry::factory()->for($user)->homework()->create(['title' => 'Vokabeln', 'subject' => 'Franz']);
        CraftIdea::factory()->for($user)->create(['title' => 'Vogelhaus']);

        $this->assertNotContains('Agenda', $this->titles($user, ''));
        $this->assertSame([], $this->group($user, 'Vokabeln', 'agenda'));
        $this->assertSame([], $this->group($user, 'Vogelhaus', 'crafts'));
    }

    public function test_the_planer_page_only_shows_when_the_planer_is_switched_on(): void
    {
        $off = User::factory()->create(['planner_enabled' => false]);
        $on = User::factory()->create(['planner_enabled' => true]);

        $this->assertNotContains('Planer', $this->titles($off, ''));
        $this->assertContains('Planer', $this->titles($on, ''));
    }

    public function test_admin_pages_are_only_offered_to_admins(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->assertNotContains('Support-Anfragen', $this->titles($user, 'admin'));
        $this->assertContains('Support-Anfragen', $this->titles($admin, 'admin'));
    }

    public function test_it_finds_only_the_users_own_active_tasks(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Task::factory()->for($user)->inbox()->create(['title' => 'Steuererklärung']);
        Task::factory()->for($user)->completed()->create(['title' => 'Steuer alt']);
        Task::factory()->for($other)->inbox()->create(['title' => 'Steuer fremd']);

        $this->assertSame(['Steuererklärung'], collect($this->group($user, 'steuer', 'tasks'))->pluck('title')->all());
    }

    public function test_like_wildcards_in_the_query_are_matched_literally(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user)->inbox()->create(['title' => 'Rabatt 50% prüfen']);
        Task::factory()->for($user)->inbox()->create(['title' => 'Etwas ganz anderes']);

        $this->assertSame(['Rabatt 50% prüfen'], collect($this->group($user, '50%', 'tasks'))->pluck('title')->all());
        $this->assertSame([], $this->group($user, '_', 'tasks'));
    }

    public function test_a_task_links_to_the_page_that_actually_shows_it(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $group = TaskGroup::factory()->for($user)->create();
        $loose = Task::factory()->for($user)->inbox()->create(['title' => 'Alpha lose']);
        Task::factory()->for($user)->tasks()->create(['title' => 'Alpha projekt', 'list' => 'projects', 'project_id' => $project->id]);
        Task::factory()->for($user)->todos()->create(['title' => 'Alpha gruppe', 'group_id' => $group->id]);

        $hrefs = collect($this->group($user, 'Alpha', 'tasks'))->pluck('href', 'title');

        $this->assertSame(route('app', ['task' => $loose->id]), $hrefs['Alpha lose']);
        $this->assertSame(route('project.show', $project->id), $hrefs['Alpha projekt']);
        $this->assertSame(route('group.show', $group->id), $hrefs['Alpha gruppe']);
    }

    public function test_each_group_is_capped(): void
    {
        $user = User::factory()->create();
        Task::factory()->count(9)->for($user)->inbox()->create(['title' => 'Massentest']);

        $this->assertCount(PaletteSearch::LIMIT, $this->group($user, 'Massentest', 'tasks'));
    }

    public function test_it_finds_projects_groups_and_help_articles_but_not_unpublished_ones(): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user)->create(['name' => 'Maturarbeit']);
        TaskGroup::factory()->for($user)->create(['name' => 'Maturarbeit Recherche']);
        HelpArticle::create(['title' => 'Maturarbeit planen', 'slug' => 'maturarbeit-planen', 'content' => 'x', 'is_published' => true, 'published_at' => now()]);
        HelpArticle::create(['title' => 'Maturarbeit Entwurf', 'slug' => 'maturarbeit-entwurf', 'content' => 'x', 'is_published' => false]);

        $this->assertSame(['Maturarbeit'], collect($this->group($user, 'Maturarbeit', 'projects'))->pluck('title')->all());
        $this->assertSame(['Maturarbeit Recherche'], collect($this->group($user, 'Maturarbeit', 'groups'))->pluck('title')->all());
        $this->assertSame(['Maturarbeit planen'], collect($this->group($user, 'Maturarbeit', 'help'))->pluck('title')->all());
    }

    public function test_agenda_search_matches_subject_and_skips_finished_entries(): void
    {
        $user = User::factory()->create();
        AgendaEntry::factory()->for($user)->homework()->create(['title' => 'Aufsatz', 'subject' => 'Deutsch']);
        AgendaEntry::factory()->for($user)->homework()->done()->create(['title' => 'Alt', 'subject' => 'Deutsch']);

        $this->assertSame(['Aufsatz'], collect($this->group($user, 'deutsch', 'agenda'))->pluck('title')->all());
    }

    public function test_the_component_renders_results_for_the_typed_query_and_resets_on_open(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user)->inbox()->create(['title' => 'Zahnarzt anrufen']);

        Livewire::actingAs($user)
            ->test(CommandPalette::class)
            ->assertSee('Board')
            ->assertDontSee('Zahnarzt anrufen')
            ->set('query', 'zahn')
            ->assertSee('Zahnarzt anrufen')
            ->assertDispatched('command-palette-results')
            ->dispatch('command-palette-opened')
            ->assertSet('query', '');
    }

    public function test_the_component_says_so_when_nothing_matches(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(CommandPalette::class)
            ->set('query', 'qqqxyz')
            ->assertSee('Nichts gefunden für „qqqxyz“');
    }

    public function test_the_layout_mounts_the_palette_for_authenticated_users_only(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('app'))->assertSee('command-palette-input', false);
        $this->get('/')->assertDontSee('command-palette-input', false);
    }

    public function test_quick_capture_opens_prefilled_from_the_palettes_capture_action(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->dispatch('quick-capture-opened', title: '  Zahnarzt anrufen  ')
            ->assertSet('title', 'Zahnarzt anrufen');
    }
}
