<?php

namespace Tests\Feature;

use App\Livewire\GroupPage;
use App\Livewire\QuickCapture;
use App\Livewire\TaskBoard;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskGroupsTest extends TestCase
{
    use RefreshDatabase;

    // ── Board visibility ──────────────────────────────────────────────

    public function test_grouped_tasks_are_not_rendered_as_loose_board_cards(): void
    {
        $user = User::factory()->create();
        $group = TaskGroup::factory()->for($user)->create();

        $loose = Task::factory()->for($user)->todos()->create();
        $grouped = Task::factory()->for($user)->todos()->create(['group_id' => $group->id]);

        Livewire::actingAs($user)
            ->test(TaskBoard::class)
            ->assertSeeHtml('wire:key="task-'.$loose->id.'"')
            // It's inside the group box's preview, not a loose card in the column.
            ->assertSeeHtml('wire:key="group-box-'.$group->id.'-todos"');

        $this->assertFalse(
            Livewire::actingAs($user)->test(TaskBoard::class)
                ->instance()->todosAll->contains('id', $grouped->id)
        );
    }

    public function test_important_and_today_grouped_tasks_still_show_as_ordinary_cards(): void
    {
        $user = User::factory()->create();
        $group = TaskGroup::factory()->for($user)->create();

        $important = Task::factory()->for($user)->todos()->important()->create(['group_id' => $group->id]);
        $today = Task::factory()->for($user)->todos()->today()->create(['group_id' => $group->id]);

        $board = Livewire::actingAs($user)->test(TaskBoard::class)->instance();

        $this->assertTrue($board->todosAll->contains('id', $important->id));
        $this->assertTrue($board->todosAll->contains('id', $today->id));
    }

    public function test_a_group_box_previews_two_tasks_and_counts_the_rest(): void
    {
        $user = User::factory()->create();
        $group = TaskGroup::factory()->for($user)->create();

        Task::factory()->count(5)->for($user)->tasks()->create(['group_id' => $group->id]);
        Task::factory()->for($user)->tasks()->completed()->create(['group_id' => $group->id]);

        $box = Livewire::actingAs($user)->test(TaskBoard::class)
            ->instance()->groupBoxesFor('tasks')->first();

        $this->assertSame(2, $box['preview']->count());
        $this->assertSame(3, $box['more']);
        $this->assertSame(1, $box['done']);
        $this->assertSame(6, $box['total']);
        $this->assertFalse($box['compact']);
    }

    public function test_a_group_without_board_work_falls_back_to_a_compact_box_in_tasks(): void
    {
        $user = User::factory()->create();
        $group = TaskGroup::factory()->for($user)->create();

        Task::factory()->count(3)->for($user)->inbox()->create(['group_id' => $group->id]);

        $board = Livewire::actingAs($user)->test(TaskBoard::class)->instance();

        $this->assertTrue($board->groupBoxesFor('todos')->isEmpty());

        $box = $board->groupBoxesFor('tasks')->first();
        $this->assertNotNull($box);
        $this->assertTrue($box['compact']);
        $this->assertSame(3, $box['inbox']);
    }

    public function test_the_inbox_column_never_shows_a_group_box(): void
    {
        $user = User::factory()->create();
        $group = TaskGroup::factory()->for($user)->create();

        Task::factory()->for($user)->inbox()->create(['group_id' => $group->id]);
        Task::factory()->for($user)->todos()->create(['group_id' => $group->id]);

        $board = Livewire::actingAs($user)->test(TaskBoard::class)->instance();

        $this->assertTrue($board->groupBoxesFor('inbox')->isEmpty());
        $this->assertFalse($board->groupBoxesFor('todos')->isEmpty());
        // …and the group's inbox tasks don't inflate the board's inbox count.
        $this->assertSame(0, $board->counts['inbox']);
    }

    public function test_grouped_tasks_count_towards_their_column(): void
    {
        $user = User::factory()->create();
        $group = TaskGroup::factory()->for($user)->create();

        Task::factory()->for($user)->todos()->create();
        Task::factory()->count(2)->for($user)->todos()->create(['group_id' => $group->id]);

        $counts = Livewire::actingAs($user)->test(TaskBoard::class)->instance()->counts;

        $this->assertSame(3, $counts['todos']);
    }

    // ── Creating a group ──────────────────────────────────────────────

    public function test_dropping_one_task_onto_another_creates_a_group_with_both(): void
    {
        $user = User::factory()->create();
        $dragged = Task::factory()->for($user)->inbox()->create();
        $target = Task::factory()->for($user)->tasks()->create();

        Livewire::actingAs($user)
            ->test(TaskBoard::class)
            ->call('groupTasks', $dragged->id, $target->id)
            ->assertSet('namingGroupId', fn ($id) => $id !== null);

        $group = TaskGroup::query()->firstOrFail();

        $this->assertSame(TaskGroup::DEFAULT_NAME, $group->name);
        $this->assertSame($group->id, $dragged->fresh()->group_id);
        $this->assertSame($group->id, $target->fresh()->group_id);
        // The dragged task adopts the list it was dropped into.
        $this->assertSame('tasks', $dragged->fresh()->list);
    }

    public function test_dropping_onto_an_already_grouped_task_joins_that_group(): void
    {
        $user = User::factory()->create();
        $group = TaskGroup::factory()->for($user)->create(['name' => 'Vortrag']);
        $target = Task::factory()->for($user)->todos()->create(['group_id' => $group->id]);
        $dragged = Task::factory()->for($user)->inbox()->create();

        Livewire::actingAs($user)
            ->test(TaskBoard::class)
            ->call('groupTasks', $dragged->id, $target->id)
            // No naming prompt: the group already has a name.
            ->assertSet('namingGroupId', null);

        $this->assertSame(1, TaskGroup::query()->count());
        $this->assertSame($group->id, $dragged->fresh()->group_id);
    }

    public function test_project_tasks_are_never_grouped(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $projectTask = Task::factory()->for($user)->create(['list' => 'projects', 'project_id' => $project->id]);
        $boardTask = Task::factory()->for($user)->todos()->create();

        Livewire::actingAs($user)
            ->test(TaskBoard::class)
            ->call('groupTasks', $boardTask->id, $projectTask->id);

        $this->assertSame(0, TaskGroup::query()->count());
        $this->assertNull($boardTask->fresh()->group_id);
    }

    public function test_a_foreign_task_can_never_be_grouped(): void
    {
        $user = User::factory()->create();
        $stranger = Task::factory()->for(User::factory())->todos()->create();
        $own = Task::factory()->for($user)->todos()->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($user)->test(TaskBoard::class)->call('groupTasks', $own->id, $stranger->id);
    }

    public function test_the_inline_name_field_renames_the_new_group(): void
    {
        $user = User::factory()->create();
        $a = Task::factory()->for($user)->todos()->create();
        $b = Task::factory()->for($user)->todos()->create();

        Livewire::actingAs($user)
            ->test(TaskBoard::class)
            ->call('groupTasks', $a->id, $b->id)
            ->set('groupNameDraft', '  Vortrag Klimawandel  ')
            ->call('saveGroupName')
            ->assertSet('namingGroupId', null);

        $this->assertSame('Vortrag Klimawandel', TaskGroup::query()->firstOrFail()->name);
    }

    public function test_an_empty_inline_name_keeps_the_default(): void
    {
        $user = User::factory()->create();
        $a = Task::factory()->for($user)->todos()->create();
        $b = Task::factory()->for($user)->todos()->create();

        Livewire::actingAs($user)
            ->test(TaskBoard::class)
            ->call('groupTasks', $a->id, $b->id)
            ->set('groupNameDraft', '')
            ->call('saveGroupName');

        $this->assertSame(TaskGroup::DEFAULT_NAME, TaskGroup::query()->firstOrFail()->name);
    }

    // ── Adding tasks to a group ───────────────────────────────────────

    public function test_dropping_a_task_on_a_group_box_keeps_its_list(): void
    {
        $user = User::factory()->create();
        $group = TaskGroup::factory()->for($user)->create();
        $task = Task::factory()->for($user)->inbox()->create();

        Livewire::actingAs($user)
            ->test(TaskBoard::class)
            ->call('assignTaskToGroup', $task->id, $group->id);

        $task->refresh();
        $this->assertSame($group->id, $task->group_id);
        $this->assertSame('inbox', $task->list, 'An inbox task must land in the group inbox.');
    }

    public function test_a_foreign_group_can_never_receive_a_task(): void
    {
        $user = User::factory()->create();
        $foreign = TaskGroup::factory()->for(User::factory())->create();
        $task = Task::factory()->for($user)->inbox()->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($user)->test(TaskBoard::class)->call('assignTaskToGroup', $task->id, $foreign->id);
    }

    public function test_the_edit_sheet_assigns_and_releases_a_group(): void
    {
        $user = User::factory()->create();
        $group = TaskGroup::factory()->for($user)->create();
        $task = Task::factory()->for($user)->todos()->create();

        Livewire::actingAs($user)
            ->test(TaskBoard::class)
            ->call('startEdit', $task->id)
            ->set('editGroupId', $group->id)
            ->call('saveEdit');

        $this->assertSame($group->id, $task->fresh()->group_id);

        Livewire::actingAs($user)
            ->test(TaskBoard::class)
            ->call('startEdit', $task->id)
            ->set('editGroupId', null)
            ->call('saveEdit');

        $this->assertNull($task->fresh()->group_id);
    }

    public function test_moving_a_task_into_a_project_clears_its_group(): void
    {
        $user = User::factory()->create();
        $group = TaskGroup::factory()->for($user)->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($user)->todos()->create(['group_id' => $group->id]);

        Livewire::actingAs($user)
            ->test(TaskBoard::class)
            ->call('startEdit', $task->id)
            ->set('editProjectId', $project->id)
            ->call('saveEdit');

        $task->refresh();
        $this->assertSame($project->id, $task->project_id);
        $this->assertNull($task->group_id);
    }

    // ── The group dashboard ───────────────────────────────────────────

    public function test_the_group_page_is_scoped_to_its_owner(): void
    {
        $user = User::factory()->create();
        $foreign = TaskGroup::factory()->for(User::factory())->create();

        $this->actingAs($user)
            ->get(route('group.show', $foreign))
            ->assertNotFound();
    }

    public function test_the_group_page_shows_only_its_own_tasks_per_list(): void
    {
        $user = User::factory()->create();
        $group = TaskGroup::factory()->for($user)->create();

        $mine = Task::factory()->for($user)->todos()->create(['group_id' => $group->id]);
        $elsewhere = Task::factory()->for($user)->todos()->create();

        $page = Livewire::actingAs($user)->test(GroupPage::class, ['group' => $group])->instance();

        $this->assertTrue($page->activeIn('todos')->contains('id', $mine->id));
        $this->assertFalse($page->activeIn('todos')->contains('id', $elsewhere->id));
    }

    public function test_tasks_can_be_added_per_list_inside_a_group(): void
    {
        $user = User::factory()->create();
        $group = TaskGroup::factory()->for($user)->create();

        Livewire::actingAs($user)
            ->test(GroupPage::class, ['group' => $group])
            ->set('newTitle.tasks', 'Folien bauen')
            ->call('addTask', 'tasks')
            ->assertSet('newTitle.tasks', '');

        $this->assertDatabaseHas('tasks', [
            'title' => 'Folien bauen',
            'list' => 'tasks',
            'group_id' => $group->id,
        ]);
    }

    public function test_important_does_not_reorder_tasks_inside_a_group(): void
    {
        $user = User::factory()->create();
        $group = TaskGroup::factory()->for($user)->create();

        $first = Task::factory()->for($user)->todos()->create(['group_id' => $group->id, 'sort_order' => 0]);
        $second = Task::factory()->for($user)->todos()->important()->create(['group_id' => $group->id, 'sort_order' => 1]);

        $order = Livewire::actingAs($user)
            ->test(GroupPage::class, ['group' => $group])
            ->instance()->activeIn('todos')->pluck('id')->all();

        $this->assertSame([$first->id, $second->id], $order, 'The star must not pull a task to the top here.');
    }

    public function test_a_deadline_does_order_tasks_inside_a_group(): void
    {
        $user = User::factory()->create();
        $group = TaskGroup::factory()->for($user)->create();

        $later = Task::factory()->for($user)->todos()->create(['group_id' => $group->id, 'sort_order' => 0]);
        $soon = Task::factory()->for($user)->todos()->deadline(now()->addDay()->toDateString())
            ->create(['group_id' => $group->id, 'sort_order' => 1]);

        $order = Livewire::actingAs($user)
            ->test(GroupPage::class, ['group' => $group])
            ->instance()->activeIn('todos')->pluck('id')->all();

        $this->assertSame([$soon->id, $later->id], $order);
    }

    public function test_reorder_inside_a_group_only_touches_that_groups_tasks(): void
    {
        $user = User::factory()->create();
        $group = TaskGroup::factory()->for($user)->create();

        $a = Task::factory()->for($user)->todos()->create(['group_id' => $group->id]);
        $outsider = Task::factory()->for($user)->inbox()->create();

        Livewire::actingAs($user)
            ->test(GroupPage::class, ['group' => $group])
            ->call('reorder', 'tasks', false, [$a->id, $outsider->id]);

        $this->assertSame('tasks', $a->fresh()->list);
        $this->assertSame('inbox', $outsider->fresh()->list, 'A task outside the group must be ignored.');
    }

    public function test_dissolving_a_group_keeps_every_task(): void
    {
        $user = User::factory()->create();
        $group = TaskGroup::factory()->for($user)->create();

        $open = Task::factory()->for($user)->todos()->create(['group_id' => $group->id]);
        $done = Task::factory()->for($user)->tasks()->completed()->create(['group_id' => $group->id]);

        Livewire::actingAs($user)
            ->test(GroupPage::class, ['group' => $group])
            ->call('dissolveGroup')
            ->assertRedirect(route('app'));

        $this->assertDatabaseMissing('task_groups', ['id' => $group->id]);
        $this->assertSame('todos', $open->fresh()->list);
        $this->assertNull($open->fresh()->group_id);
        $this->assertNotNull($done->fresh());
    }

    public function test_a_task_can_be_released_from_the_group_page(): void
    {
        $user = User::factory()->create();
        $group = TaskGroup::factory()->for($user)->create();
        $task = Task::factory()->for($user)->todos()->create(['group_id' => $group->id]);

        Livewire::actingAs($user)
            ->test(GroupPage::class, ['group' => $group])
            ->call('removeFromGroup', $task->id);

        $this->assertNull($task->fresh()->group_id);
        $this->assertSame('todos', $task->fresh()->list);
    }

    public function test_group_notes_are_rendered_as_safe_markdown(): void
    {
        $user = User::factory()->create();
        $group = TaskGroup::factory()->for($user)->create();

        Livewire::actingAs($user)
            ->test(GroupPage::class, ['group' => $group])
            ->set('notes', "## Kernaussage\n\n<script>alert(1)</script>");

        $this->assertSame("## Kernaussage\n\n<script>alert(1)</script>", $group->fresh()->notes);
        $this->assertStringContainsString('<h2>Kernaussage</h2>', $group->fresh()->notesHtml());
        $this->assertStringNotContainsString('<script>', $group->fresh()->notesHtml());
    }

    // ── Quick capture ─────────────────────────────────────────────────

    public function test_quick_capture_creates_a_new_group_with_its_first_task(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->call('setTarget', 'group')
            ->set('title', 'Quellen sammeln')
            ->set('newGroupName', 'Vortrag Klimawandel')
            ->set('groupList', 'todos')
            ->call('save')
            ->assertHasNoErrors();

        $group = TaskGroup::query()->firstOrFail();

        $this->assertSame('Vortrag Klimawandel', $group->name);
        $this->assertDatabaseHas('tasks', [
            'title' => 'Quellen sammeln',
            'list' => 'todos',
            'group_id' => $group->id,
        ]);
    }

    public function test_quick_capture_keeps_the_group_selected_for_the_next_entry(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->call('setTarget', 'group')
            ->set('title', 'Erster Schritt')
            ->set('newGroupName', 'Zimmer umstellen')
            ->call('save');

        $group = TaskGroup::query()->firstOrFail();

        $component->assertSet('groupId', $group->id)
            ->assertSet('newGroupName', '')
            ->set('title', 'Zweiter Schritt')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, TaskGroup::query()->count());
        $this->assertSame(2, Task::query()->where('group_id', $group->id)->count());
    }

    public function test_quick_capture_files_into_an_existing_group(): void
    {
        $user = User::factory()->create();
        $group = TaskGroup::factory()->for($user)->create();

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->call('setTarget', 'group')
            ->set('groupId', $group->id)
            ->set('groupList', 'inbox')
            ->set('title', 'Noch unsortiert')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'title' => 'Noch unsortiert',
            'list' => 'inbox',
            'group_id' => $group->id,
        ]);
    }

    public function test_quick_capture_demands_a_group(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->call('setTarget', 'group')
            ->set('title', 'Ohne Gruppe')
            ->call('save')
            ->assertHasErrors('newGroupName');

        $this->assertSame(0, Task::query()->count());
    }

    public function test_quick_capture_rejects_a_foreign_group(): void
    {
        $user = User::factory()->create();
        $foreign = TaskGroup::factory()->for(User::factory())->create();

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->call('setTarget', 'group')
            ->set('groupId', $foreign->id)
            ->set('title', 'Fremde Gruppe')
            ->call('save')
            ->assertHasErrors('groupId');

        $this->assertSame(0, Task::query()->count());
    }
}
