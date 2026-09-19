<?php

namespace Tests\Feature;

use App\Models\AgendaEntry;
use App\Models\EventCategory;
use App\Models\Project;
use App\Models\TaskGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryLinkTargetDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_linked_project_resets_the_category_source(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $category = EventCategory::factory()->for($user)->pomodoro()->create([
            'task_source' => 'project', 'linked_project_id' => $project->id,
        ]);

        $project->delete();

        $category->refresh();
        $this->assertNull($category->task_source);
        $this->assertNull($category->linked_project_id);
    }

    public function test_deleting_a_linked_group_resets_the_category_source(): void
    {
        $user = User::factory()->create();
        $group = TaskGroup::factory()->for($user)->create();
        $category = EventCategory::factory()->for($user)->pomodoro()->create([
            'task_source' => 'group', 'linked_group_id' => $group->id,
        ]);

        $group->delete();

        $this->assertNull($category->fresh()->task_source);
    }

    public function test_deleting_a_linked_agenda_entry_resets_the_category_source(): void
    {
        $user = User::factory()->create();
        $entry = AgendaEntry::factory()->for($user)->create();
        $category = EventCategory::factory()->for($user)->pomodoro()->create([
            'task_source' => 'agenda_entry', 'linked_agenda_entry_id' => $entry->id,
        ]);

        $entry->delete();

        $this->assertNull($category->fresh()->task_source);
    }

    public function test_deleting_an_unrelated_project_leaves_other_links_alone(): void
    {
        $user = User::factory()->create();
        $linked = Project::factory()->for($user)->create();
        $other = Project::factory()->for($user)->create();
        $category = EventCategory::factory()->for($user)->pomodoro()->create([
            'task_source' => 'project', 'linked_project_id' => $linked->id,
        ]);

        $other->delete();

        $this->assertSame('project', $category->fresh()->task_source);
    }
}
