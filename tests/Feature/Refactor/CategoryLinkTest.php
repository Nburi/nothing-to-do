<?php

namespace Tests\Feature\Refactor;

use App\Livewire\Shell\Categories;
use App\Models\EventCategory;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\User;
use App\Planning\Size;
use App\Services\Planning\Suggestions;
use App\Services\Planning\WeekPlanEditor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Step 5.4, decision "catlinks" (Niels, 2026-11-27): a category linked to a project or group suggests that
 * project's or group's next steps in its blocks; the old Agenda links become a text link; nothing is deleted.
 */
class CategoryLinkTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private EventCategory $work;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-09 20:00', 'Europe/Zurich'));
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
        $this->work = EventCategory::factory()->for($this->user)->pomodoro()->create(['name' => 'Arbeiten']);
        (new WeekPlanEditor)->addRepeating($this->user, [1, 2, 3, 4, 5], '19:30', '21:30', $this->work->id);
    }

    private function task(string $title, array $attrs = []): Task
    {
        return Task::forceCreate(array_merge(['user_id' => $this->user->id, 'title' => $title, 'list' => 'tasks', 'size' => Size::Short, 'sort_order' => 0], $attrs));
    }

    private function project(string $name = 'Website'): Project
    {
        return Project::forceCreate(['user_id' => $this->user->id, 'name' => $name, 'sort_order' => 1]);
    }

    private function pick(): ?string
    {
        return app(Suggestions::class)->current($this->user)->task?->title;
    }

    // ---- the engine --------------------------------------------------------------------------

    public function test_a_linked_project_has_its_next_step_suggested_in_the_block(): void
    {
        $this->task('Lose Aufgabe', ['sort_order' => 0]);
        $project = $this->project();
        $this->task('Schritt 1', ['project_id' => $project->id, 'sort_order' => 1]);

        $this->assertSame('Lose Aufgabe', $this->pick(), 'without a link the board order decides');

        $this->work->linkSteps('project', $project->id);

        $this->assertSame('Schritt 1', $this->pick());
        $this->assertSame('Der nächste Schritt für das, wofür dieser Block gedacht ist.', app(Suggestions::class)->current($this->user)->why);
    }

    public function test_a_linked_group_works_too_and_a_deadline_still_comes_first(): void
    {
        $group = TaskGroup::forceCreate(['user_id' => $this->user->id, 'name' => 'Einkauf', 'list' => 'tasks']);
        $this->task('Gruppenschritt', ['group_id' => $group->id, 'sort_order' => 5]);
        $this->task('Mit Frist', ['sort_order' => 6, 'due_date' => '2026-11-10']);
        $this->work->linkSteps('group', $group->id);

        $this->assertSame('Mit Frist', $this->pick());

        Task::where('title', 'Mit Frist')->update(['is_completed' => true]);
        $this->assertSame('Gruppenschritt', $this->pick());
    }

    public function test_only_the_steps_the_board_shows_are_suggested(): void
    {
        $project = $this->project();
        foreach (['S1', 'S2', 'S3'] as $i => $title) {
            $this->task($title, ['project_id' => $project->id, 'sort_order' => $i]);
        }
        $this->work->linkSteps('project', $project->id);
        $this->task('Lose', ['sort_order' => 9]);

        // The board shows two steps of a project: S3 waits on the project page until one of them is done.
        $this->assertSame(['S1', 'S2', 'Lose'], app(Suggestions::class)->todoSession($this->user)->block->tasks()->pluck('title')->all());

        Task::where('title', 'S1')->update(['is_completed' => true]);
        $this->assertSame(['S2', 'S3', 'Lose'], app(Suggestions::class)->todoSession($this->user)->block->tasks()->pluck('title')->all());
    }

    public function test_a_link_of_another_category_does_not_count_in_this_block(): void
    {
        $other = EventCategory::factory()->for($this->user)->pomodoro()->create(['name' => 'Anderes']);
        $this->task('Lose', ['sort_order' => 0]); // created first: it has the earlier place on the board
        $project = $this->project();
        $this->task('Schritt', ['project_id' => $project->id, 'sort_order' => 1]);
        $other->linkSteps('project', $project->id); // linked, but the running block is "Arbeiten"

        $this->assertSame('Lose', $this->pick());
    }

    public function test_the_block_plan_follows_the_link_too(): void
    {
        $project = $this->project();
        $this->task('Lose', ['sort_order' => 0]);
        $this->task('Schritt', ['project_id' => $project->id, 'sort_order' => 1]);
        $this->work->linkSteps('project', $project->id);

        $plan = app(Suggestions::class)->todoSession($this->user)->block;

        $this->assertSame(['Schritt', 'Lose'], $plan->tasks()->pluck('title')->all());
    }

    public function test_deleting_the_project_drops_the_link_and_the_pick_is_the_normal_one(): void
    {
        $project = $this->project();
        $this->task('Lose', ['sort_order' => 0]);
        $this->work->linkSteps('project', $project->id);
        DB::table('projects')->where('id', $project->id)->delete();

        $this->assertNull($this->work->fresh()->toPlanningCategory()->linkedProjectId);
        $this->assertSame('Lose', $this->pick());
    }

    // ---- the category editor -----------------------------------------------------------------

    /** @return array{0: ?string, 1: ?int, 2: ?int} */
    private function link(): array
    {
        $c = $this->work->fresh();

        return [$c->task_source, $c->linked_project_id, $c->linked_group_id];
    }

    public function test_the_editor_sets_changes_and_clears_the_link(): void
    {
        $project = $this->project();
        $group = TaskGroup::forceCreate(['user_id' => $this->user->id, 'name' => 'Einkauf', 'list' => 'tasks']);
        $page = Livewire::actingAs($this->user)->test(Categories::class);

        $page->assertSee('Nächste Schritte vorschlagen aus')->assertSee('Website')->assertSee('Einkauf');

        $page->call('setLink', $this->work->id, 'project:'.$project->id);
        $this->assertSame(['project', $project->id, null], $this->link());

        $page->call('setLink', $this->work->id, 'group:'.$group->id);
        $this->assertSame(['group', null, $group->id], $this->link());

        $page->call('setLink', $this->work->id, '');
        $this->assertSame([null, null, null], $this->link());
    }

    public function test_the_editor_ignores_a_project_or_group_of_somebody_else(): void
    {
        $other = User::factory()->create();
        $foreign = Project::forceCreate(['user_id' => $other->id, 'name' => 'Fremdprojekt', 'sort_order' => 1]);
        $foreignGroup = TaskGroup::forceCreate(['user_id' => $other->id, 'name' => 'Fremdgruppe', 'list' => 'tasks']);

        $page = Livewire::actingAs($this->user)->test(Categories::class)->assertDontSee('Fremdprojekt');
        $page->call('setLink', $this->work->id, 'project:'.$foreign->id)->call('setLink', $this->work->id, 'group:'.$foreignGroup->id)
            ->call('setLink', $this->work->id, 'project:99999')->call('setLink', $this->work->id, 'nonsense');

        $this->assertSame([null, null, null], $this->link());
    }

    public function test_the_editor_cannot_touch_the_category_of_somebody_else(): void
    {
        $theirs = EventCategory::factory()->for(User::factory()->create())->pomodoro()->create();
        $project = $this->project();

        Livewire::actingAs($this->user)->test(Categories::class)->call('setLink', $theirs->id, 'project:'.$project->id);

        $this->assertNull($theirs->fresh()->task_source);
    }

    public function test_choosing_a_link_keeps_the_old_text_and_clearing_never_touches_a_text_link(): void
    {
        $project = $this->project();
        $this->work->forceFill(['task_source' => 'text', 'linked_text' => 'Lesen'])->save();

        $page = Livewire::actingAs($this->user)->test(Categories::class)->assertSee('Alter Hinweis: „Lesen“');
        $page->call('setLink', $this->work->id, '');
        $this->assertSame('text', $this->work->fresh()->task_source, 'clearing only ends a project or group link');

        $page->call('setLink', $this->work->id, 'project:'.$project->id);
        $this->assertSame('project', $this->work->fresh()->task_source);
        $this->assertSame('Lesen', $this->work->fresh()->linked_text, 'the text is kept, not deleted');
    }

    // ---- the migration -----------------------------------------------------------------------

    public function test_agenda_links_become_text_links_and_nothing_is_deleted_and_down_restores_them(): void
    {
        $entryId = DB::table('agenda_entries')->insertGetId(['user_id' => $this->user->id, 'type' => 'homework', 'subject' => 'Mathe', 'title' => 'Blatt 4', 'date' => '2026-11-12', 'created_at' => now(), 'updated_at' => now()]);
        $make = fn (array $attrs) => EventCategory::factory()->for($this->user)->create($attrs)->id;
        $agenda = $make(['task_source' => 'agenda_entry']);
        $generic = $make(['task_source' => 'agenda_generic']);
        $own = $make(['task_source' => 'agenda_entry', 'linked_text' => 'Mein Text']);
        $project = $make(['task_source' => 'project']);
        $group = $make(['task_source' => 'group']);
        $pinned = $make(['task_source' => 'tasks']);
        DB::table('event_categories')->where('id', $agenda)->update(['linked_agenda_entry_id' => $entryId]);
        $before = DB::table('event_categories')->count();

        $migration = require base_path('database/migrations/2026_11_27_000001_map_category_links_to_the_new_engine.php');
        $migration->down(); // the state before this step: no legacy column
        $migration->up();

        $row = fn (int $id) => DB::table('event_categories')->find($id);
        $this->assertSame(['text', 'Blatt 4', 'agenda_entry', $entryId], [$row($agenda)->task_source, $row($agenda)->linked_text, str_replace('+label', '', $row($agenda)->legacy_task_source), $row($agenda)->linked_agenda_entry_id]);
        $this->assertSame(['text', 'Agenda', 'agenda_generic'], [$row($generic)->task_source, $row($generic)->linked_text, str_replace('+label', '', $row($generic)->legacy_task_source)]);
        $this->assertSame(['text', 'Mein Text'], [$row($own)->task_source, $row($own)->linked_text], 'a label that is already there stays');
        foreach ([[$project, 'project'], [$group, 'group'], [$pinned, 'tasks']] as [$id, $source]) {
            $this->assertSame([$source, null], [$row($id)->task_source, $row($id)->legacy_task_source], 'project, group and pinned links are not touched');
        }
        $this->assertSame($before, DB::table('event_categories')->count());
        $this->assertSame(1, DB::table('agenda_entries')->count());

        $migration->down();
        $this->assertSame(['agenda_entry', 'agenda_generic', 'agenda_entry'], [$row($agenda)->task_source, $row($generic)->task_source, $row($own)->task_source]);
        $this->assertSame([null, null, 'Mein Text'], [$row($agenda)->linked_text, $row($generic)->linked_text, $row($own)->linked_text], 'the labels we wrote go, the one that was there stays');
    }
}
