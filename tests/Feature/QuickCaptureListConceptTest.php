<?php

namespace Tests\Feature;

use App\Livewire\QuickCapture;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The "Kanban" list concept's one QuickCapture change: Inbox/To-Dos/Tasks
 * collapse into a single "Aufgabe" chip (see ListConcepts, TaskBoard's
 * "Kanban" concept, PLAN_LIST_CONCEPTS.md §4) — mirrors the same fix the
 * "Simple" concept already has, applied here since Kanban's own board
 * ignores `list` for display just as completely (see TaskBoard::
 * kanbanColumns()).
 */
class QuickCaptureListConceptTest extends TestCase
{
    use RefreshDatabase;

    public function test_three_things_still_offers_all_three_task_chips(): void
    {
        $user = User::factory()->create();

        $targets = Livewire::actingAs($user)->test(QuickCapture::class)->get('availableTargets');

        $this->assertContains('inbox', $targets);
        $this->assertContains('todos', $targets);
        $this->assertContains('tasks', $targets);
    }

    public function test_kanban_collapses_inbox_and_todos_out_of_the_chip_row(): void
    {
        $user = User::factory()->create(['list_concept' => 'kanban']);

        $targets = Livewire::actingAs($user)->test(QuickCapture::class)->get('availableTargets');

        $this->assertNotContains('inbox', $targets);
        $this->assertNotContains('todos', $targets);
        $this->assertContains('tasks', $targets);
    }

    public function test_kanban_keeps_every_other_chip(): void
    {
        $user = User::factory()->create(['list_concept' => 'kanban']);

        $targets = Livewire::actingAs($user)->test(QuickCapture::class)->get('availableTargets');

        $this->assertContains('group', $targets);
        $this->assertContains('project', $targets);
        $this->assertContains('craft', $targets);
        $this->assertContains('agenda', $targets);
    }

    public function test_kanban_opens_on_the_tasks_target_by_default(): void
    {
        $user = User::factory()->create(['list_concept' => 'kanban']);

        Livewire::actingAs($user)->test(QuickCapture::class)->assertSet('target', 'tasks');
    }

    public function test_kanban_labels_the_tasks_chip_aufgabe(): void
    {
        $user = User::factory()->create(['list_concept' => 'kanban']);
        $this->actingAs($user);

        $this->assertSame('Aufgabe', QuickCapture::labelFor('tasks'));
    }

    public function test_three_things_still_labels_the_tasks_chip_task(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->assertSame('Task', QuickCapture::labelFor('tasks'));
    }

    public function test_setting_the_collapsed_inbox_target_under_kanban_is_ignored(): void
    {
        $user = User::factory()->create(['list_concept' => 'kanban']);

        Livewire::actingAs($user)->test(QuickCapture::class)
            ->call('setTarget', 'inbox')
            ->assertSet('target', 'tasks');
    }

    public function test_capturing_under_kanban_writes_a_task_in_the_tasks_list(): void
    {
        $user = User::factory()->create(['list_concept' => 'kanban']);

        Livewire::actingAs($user)->test(QuickCapture::class)
            ->set('title', 'Postenbeschreibung studieren')
            ->call('save');

        $task = Task::query()->forUser($user)->sole();
        $this->assertSame('tasks', $task->list);
        $this->assertSame('Postenbeschreibung studieren', $task->title);
    }

    public function test_a_kanban_captured_task_lands_in_backlog(): void
    {
        $user = User::factory()->create(['list_concept' => 'kanban']);

        Livewire::actingAs($user)->test(QuickCapture::class)
            ->set('title', 'Fresh capture')
            ->call('save');

        $task = Task::query()->forUser($user)->sole();
        $this->assertFalse($task->is_today);
        $this->assertFalse($task->is_completed);
    }
}
