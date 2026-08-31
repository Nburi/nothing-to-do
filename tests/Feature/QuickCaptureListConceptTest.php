<?php

namespace Tests\Feature;

use App\Livewire\QuickCapture;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The "Simple" list concept's one QuickCapture change: Inbox/To-Dos/Tasks
 * collapse into a single "Aufgabe" chip (see ListConcepts, TaskBoard's
 * "Simple" concept, PLAN_LIST_CONCEPTS.md §4) — deferred by the infra
 * session since 'simple' wasn't selectable yet at the time.
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

    public function test_simple_collapses_inbox_and_todos_out_of_the_chip_row(): void
    {
        $user = User::factory()->create(['list_concept' => 'simple']);

        $targets = Livewire::actingAs($user)->test(QuickCapture::class)->get('availableTargets');

        $this->assertNotContains('inbox', $targets);
        $this->assertNotContains('todos', $targets);
        $this->assertContains('tasks', $targets);
    }

    public function test_simple_keeps_every_other_chip(): void
    {
        $user = User::factory()->create(['list_concept' => 'simple']);

        $targets = Livewire::actingAs($user)->test(QuickCapture::class)->get('availableTargets');

        $this->assertContains('group', $targets);
        $this->assertContains('project', $targets);
        $this->assertContains('craft', $targets);
        $this->assertContains('agenda', $targets);
    }

    public function test_simple_opens_on_the_tasks_target_by_default(): void
    {
        $user = User::factory()->create(['list_concept' => 'simple']);

        Livewire::actingAs($user)->test(QuickCapture::class)->assertSet('target', 'tasks');
    }

    public function test_simple_labels_the_tasks_chip_aufgabe(): void
    {
        $user = User::factory()->create(['list_concept' => 'simple']);
        $this->actingAs($user);

        $this->assertSame('Aufgabe', QuickCapture::labelFor('tasks'));
    }

    public function test_three_things_still_labels_the_tasks_chip_task(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->assertSame('Task', QuickCapture::labelFor('tasks'));
    }

    public function test_setting_the_collapsed_inbox_target_under_simple_is_ignored(): void
    {
        $user = User::factory()->create(['list_concept' => 'simple']);

        Livewire::actingAs($user)->test(QuickCapture::class)
            ->call('setTarget', 'inbox')
            ->assertSet('target', 'tasks');
    }

    public function test_capturing_under_simple_writes_a_task_in_the_tasks_list(): void
    {
        $user = User::factory()->create(['list_concept' => 'simple']);

        Livewire::actingAs($user)->test(QuickCapture::class)
            ->set('title', 'Postenbeschreibung studieren')
            ->call('save');

        $task = Task::query()->forUser($user)->sole();
        $this->assertSame('tasks', $task->list);
        $this->assertSame('Postenbeschreibung studieren', $task->title);
    }
}
