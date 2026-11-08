<?php

namespace Tests\Feature\Refactor;

use App\Models\Area;
use App\Models\EventCategory;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Refactor step 1.6 / test plan A18 (R10): categories and areas are
 * user-defined, a category only suggests its linked areas, deleting an area
 * unlinks it without losing tasks.
 */
class CategoryAreaLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_areas_are_an_open_per_user_list(): void
    {
        $niels = User::factory()->create();
        $other = User::factory()->create();
        Area::factory()->for($niels)->create(['name' => 'Schule', 'sort_order' => 1]);
        Area::factory()->for($niels)->create(['name' => 'Eigene Projekte', 'sort_order' => 0]);
        Area::factory()->for($other)->create(['name' => 'Fremd']);

        $this->assertSame(['Eigene Projekte', 'Schule'], $niels->areas()->ordered()->pluck('name')->all());
        $this->assertSame(['Fremd'], Area::forUser($other)->pluck('name')->all());
    }

    public function test_a_category_only_accepts_tasks_of_its_linked_areas(): void
    {
        $user = User::factory()->create();
        $school = Area::factory()->for($user)->create(['name' => 'Schule']);
        $own = Area::factory()->for($user)->create(['name' => 'Eigene Projekte']);
        $sport = Area::factory()->for($user)->create(['name' => 'Sport']);
        $deepWork = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $deepWork->syncAreas([$school->id, $own->id]);

        $category = $deepWork->fresh()->toPlanningCategory();

        $this->assertTrue($category->pomodoro);
        $this->assertTrue($category->accepts($school->id));
        $this->assertTrue($category->accepts($own->id));
        $this->assertFalse($category->accepts($sport->id));
        $this->assertTrue($category->accepts(null), 'a task without area fits any pomodoro category (E5)');
    }

    public function test_a_category_without_links_accepts_only_tasks_without_area(): void
    {
        $user = User::factory()->create();
        $sport = Area::factory()->for($user)->create();
        $training = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => false]);

        $category = $training->toPlanningCategory();

        $this->assertFalse($category->pomodoro);
        $this->assertSame([], $category->areaIds);
        $this->assertFalse($category->accepts($sport->id));
        $this->assertTrue($category->accepts(null));
    }

    public function test_linking_ignores_another_users_areas(): void
    {
        $user = User::factory()->create();
        $mine = Area::factory()->for($user)->create();
        $foreign = Area::factory()->for(User::factory())->create();
        $category = EventCategory::factory()->for($user)->create();

        $category->syncAreas([$mine->id, $foreign->id, 999999]);

        $this->assertSame([$mine->id], $category->areas()->pluck('areas.id')->all());
    }

    public function test_sync_replaces_the_previous_links(): void
    {
        $user = User::factory()->create();
        [$a, $b] = Area::factory()->for($user)->count(2)->create();
        $category = EventCategory::factory()->for($user)->create();

        $category->syncAreas([$a->id]);
        $category->syncAreas([$b->id]);

        $this->assertSame([$b->id], $category->toPlanningCategory()->areaIds);
    }

    public function test_deleting_an_area_unlinks_it_and_keeps_its_tasks(): void
    {
        $user = User::factory()->create();
        $school = Area::factory()->for($user)->create();
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $category->syncAreas([$school->id]);
        $task = Task::factory()->for($user)->create(['area_id' => $school->id]);

        $school->delete();

        $this->assertNotNull($task->fresh(), 'the task survives');
        $this->assertNull($task->fresh()->area_id);
        $this->assertSame([], $category->fresh()->toPlanningCategory()->areaIds);
        $this->assertDatabaseCount('category_area', 0);
    }

    public function test_deleting_a_category_keeps_the_area(): void
    {
        $user = User::factory()->create();
        $area = Area::factory()->for($user)->create();
        $category = EventCategory::factory()->for($user)->create();
        $category->syncAreas([$area->id]);

        $category->delete();

        $this->assertNotNull($area->fresh());
        $this->assertDatabaseCount('category_area', 0);
    }

    public function test_existing_categories_keep_their_pomodoro_switch(): void
    {
        $user = User::factory()->create();
        $existing = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);

        $this->assertTrue($existing->fresh()->toPlanningCategory()->pomodoro);
        $this->assertSame([], $existing->fresh()->toPlanningCategory()->areaIds);
    }
}
