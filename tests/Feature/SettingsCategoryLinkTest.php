<?php

namespace Tests\Feature;

use App\Livewire\Settings;
use App\Models\AgendaEntry;
use App\Models\EventCategory;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsCategoryLinkTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regression guard for a Runde-4 finding: the link entry point on a category row must have
     * an explicit accessible name and a real call-to-action label — the original plain-text
     * version rendered with no accessible name in an accessibility-tree read and was missed
     * entirely by a simulated user working toward exactly this feature.
     */
    public function test_the_link_entry_point_has_an_explicit_accessible_name_and_a_call_to_action_label(): void
    {
        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->pomodoro()->create(['name' => 'Training']);

        $html = Livewire::actingAs($user)->test(Settings::class)->html();

        $this->assertStringContainsString('aria-label="Aufgaben-Verknüpfung für Training verwalten', $html);
        $this->assertStringContainsString('+ Aufgaben verknüpfen', $html);
    }

    public function test_the_link_entry_point_shows_the_current_link_once_one_is_set(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Wettkampfvorbereitung']);
        $category = EventCategory::factory()->for($user)->pomodoro()->create([
            'name' => 'Training', 'task_source' => 'project', 'linked_project_id' => $project->id,
        ]);

        $html = Livewire::actingAs($user)->test(Settings::class)->html();

        $this->assertStringContainsString('Wettkampfvorbereitung', $html);
        $this->assertStringNotContainsString('+ Aufgaben verknüpfen', $html);
    }

    public function test_link_category_to_project_sets_the_source(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $category = EventCategory::factory()->for($user)->pomodoro()->create();

        Livewire::actingAs($user)->test(Settings::class)->call('linkCategoryToProject', $category->id, $project->id);

        $category->refresh();
        $this->assertSame('project', $category->task_source);
        $this->assertSame($project->id, $category->linked_project_id);
    }

    public function test_linking_to_a_foreign_project_is_rejected(): void
    {
        $user = User::factory()->create();
        $foreignProject = Project::factory()->for(User::factory())->create();
        $category = EventCategory::factory()->for($user)->pomodoro()->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($user)->test(Settings::class)->call('linkCategoryToProject', $category->id, $foreignProject->id);
    }

    public function test_a_foreign_category_can_never_be_linked(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $foreignCategory = EventCategory::factory()->for(User::factory())->pomodoro()->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($user)->test(Settings::class)->call('linkCategoryToProject', $foreignCategory->id, $project->id);
    }

    public function test_link_category_to_group_sets_the_source(): void
    {
        $user = User::factory()->create();
        $group = TaskGroup::factory()->for($user)->create();
        $category = EventCategory::factory()->for($user)->pomodoro()->create();

        Livewire::actingAs($user)->test(Settings::class)->call('linkCategoryToGroup', $category->id, $group->id);

        $category->refresh();
        $this->assertSame('group', $category->task_source);
        $this->assertSame($group->id, $category->linked_group_id);
    }

    public function test_link_category_to_agenda_entry_sets_the_source(): void
    {
        $user = User::factory()->create();
        $entry = AgendaEntry::factory()->for($user)->homework()->create();
        $category = EventCategory::factory()->for($user)->pomodoro()->create();

        Livewire::actingAs($user)->test(Settings::class)->call('linkCategoryToAgendaEntry', $category->id, $entry->id);

        $category->refresh();
        $this->assertSame('agenda_entry', $category->task_source);
        $this->assertSame($entry->id, $category->linked_agenda_entry_id);
    }

    public function test_link_category_to_agenda_generic_sets_the_source(): void
    {
        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->pomodoro()->create();

        Livewire::actingAs($user)->test(Settings::class)->call('linkCategoryToAgendaGeneric', $category->id);

        $this->assertSame('agenda_generic', $category->refresh()->task_source);
    }

    public function test_save_category_link_text_requires_non_empty_text(): void
    {
        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->pomodoro()->create();

        Livewire::actingAs($user)->test(Settings::class)
            ->set('linkTextDraft', '')
            ->call('saveCategoryLinkText', $category->id)
            ->assertHasErrors(['linkTextDraft']);

        $this->assertNull($category->refresh()->task_source);
    }

    public function test_save_category_link_text_saves_trimmed_text(): void
    {
        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->pomodoro()->create();

        Livewire::actingAs($user)->test(Settings::class)
            ->set('linkTextDraft', '  Zimmer aufräumen  ')
            ->call('saveCategoryLinkText', $category->id);

        $category->refresh();
        $this->assertSame('text', $category->task_source);
        $this->assertSame('Zimmer aufräumen', $category->linked_text);
    }

    public function test_set_category_tasks_mode_then_toggle_pinned_task_adds_and_removes(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->todos()->create();
        $category = EventCategory::factory()->for($user)->pomodoro()->create();

        $component = Livewire::actingAs($user)->test(Settings::class)
            ->call('setCategoryTasksMode', $category->id)
            ->call('togglePinnedTask', $category->id, $task->id);

        $this->assertTrue($category->pinnedTasks()->whereKey($task->id)->exists());

        $component->call('togglePinnedTask', $category->id, $task->id);

        $this->assertFalse($category->pinnedTasks()->whereKey($task->id)->exists());
    }

    public function test_toggling_a_foreign_task_is_rejected(): void
    {
        $user = User::factory()->create();
        $foreignTask = Task::factory()->for(User::factory())->todos()->create();
        $category = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'tasks']);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($user)->test(Settings::class)->call('togglePinnedTask', $category->id, $foreignTask->id);
    }

    public function test_toggle_pinned_task_is_a_no_op_when_not_in_tasks_mode(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->todos()->create();
        $project = Project::factory()->for($user)->create();
        $category = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'project', 'linked_project_id' => $project->id]);

        Livewire::actingAs($user)->test(Settings::class)->call('togglePinnedTask', $category->id, $task->id);

        $this->assertSame(0, $category->pinnedTasks()->count());
        $this->assertSame('project', $category->refresh()->task_source);
    }

    public function test_switching_source_clears_the_previous_one(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($user)->todos()->create();
        $category = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'tasks']);
        $category->pinnedTasks()->attach($task->id, ['sort_order' => 0]);

        Livewire::actingAs($user)->test(Settings::class)->call('linkCategoryToProject', $category->id, $project->id);

        $category->refresh();
        $this->assertSame('project', $category->task_source);
        $this->assertSame(0, $category->pinnedTasks()->count());
    }

    public function test_clear_category_link_resets_everything(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $category = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'project', 'linked_project_id' => $project->id]);

        Livewire::actingAs($user)->test(Settings::class)->call('clearCategoryLink', $category->id);

        $category->refresh();
        $this->assertNull($category->task_source);
        $this->assertNull($category->linked_project_id);
    }

    public function test_link_task_candidates_suggests_tasks_due_soon_or_with_a_wunschtermin_today(): void
    {
        $user = User::factory()->create(['timezone_offset' => 0]);
        $today = $user->localToday();

        $dueSoon = Task::factory()->for($user)->todos()->create(['title' => 'Bald fällig', 'deadline' => $today->copy()->addDay()->toDateString()]);
        $wunschHeute = Task::factory()->for($user)->todos()->create(['title' => 'Wunsch heute', 'due_date' => $today->toDateString()]);
        Task::factory()->for($user)->todos()->create(['title' => 'Weit weg', 'deadline' => $today->copy()->addDays(10)->toDateString()]);
        Task::factory()->for($user)->todos()->create(['title' => 'Ohne Datum']);

        $component = Livewire::actingAs($user)->test(Settings::class)->call('manageCategoryLink', EventCategory::factory()->for($user)->pomodoro()->create()->id);

        $ids = $component->instance()->linkTaskCandidates->pluck('id')->all();

        $this->assertContains($dueSoon->id, $ids);
        $this->assertContains($wunschHeute->id, $ids);
        $this->assertCount(2, $ids);
    }

    public function test_link_task_candidates_search_overrides_the_default_window(): void
    {
        $user = User::factory()->create();
        $farAway = Task::factory()->for($user)->todos()->create(['title' => 'Ganz weit weg', 'deadline' => now()->addDays(30)->toDateString()]);
        Task::factory()->for($user)->todos()->create(['title' => 'Anderer Titel']);

        $component = Livewire::actingAs($user)->test(Settings::class)
            ->call('manageCategoryLink', EventCategory::factory()->for($user)->pomodoro()->create()->id)
            ->set('linkTaskSearch', 'Ganz weit');

        $ids = $component->instance()->linkTaskCandidates->pluck('id')->all();

        $this->assertSame([$farAway->id], $ids);
    }
}
