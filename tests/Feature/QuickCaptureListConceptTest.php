<?php

namespace Tests\Feature;

use App\Livewire\QuickCapture;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The "Eisenhower" list concept's one QuickCapture hook: quadrant tap-to-
 * create pre-fills $important/$dueDate via resetPanel()'s extra params (see
 * board-eisenhower.blade.php's per-quadrant "+" and PLAN_LIST_CONCEPTS.md
 * §4's "small, optional-params extension" scoped to this session).
 */
class QuickCaptureListConceptTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_panel_leaves_important_false_by_default(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(QuickCapture::class)
            ->call('resetPanel')
            ->assertSet('important', false);
    }

    public function test_reset_panel_applies_the_important_prefill(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(QuickCapture::class)
            ->call('resetPanel', null, null, true)
            ->assertSet('important', true);
    }

    public function test_reset_panel_applies_the_due_date_prefill(): void
    {
        $user = User::factory()->create();
        $date = now()->addDays(4)->toDateString();

        Livewire::actingAs($user)->test(QuickCapture::class)
            ->call('resetPanel', null, null, true, $date)
            ->assertSet('dueDate', $date);
    }

    public function test_reset_panel_without_a_due_date_prefill_leaves_it_null(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(QuickCapture::class)
            ->call('resetPanel', null, null, true, null)
            ->assertSet('dueDate', null);
    }

    public function test_saving_with_the_important_prefill_creates_an_important_task(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(QuickCapture::class)
            ->call('resetPanel', null, null, true)
            ->set('title', 'Crisis-quadrant capture')
            ->call('save');

        $task = Task::query()->where('title', 'Crisis-quadrant capture')->firstOrFail();
        $this->assertTrue($task->is_important);
    }

    public function test_saving_without_the_prefill_creates_a_task_that_is_not_important(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(QuickCapture::class)
            ->set('target', 'tasks')
            ->set('title', 'Ordinary capture')
            ->call('save');

        $task = Task::query()->where('title', 'Ordinary capture')->firstOrFail();
        $this->assertFalse($task->is_important);
    }

    public function test_important_resets_to_false_after_a_save(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(QuickCapture::class)
            ->call('resetPanel', null, null, true)
            ->set('title', 'First')
            ->call('save')
            ->assertSet('important', false);
    }

    // ── Eisenhower's chip-collapse: To-Dos/Tasks fold into the Inbox chip ──

    public function test_three_things_still_offers_all_three_task_chips(): void
    {
        $user = User::factory()->create();

        $targets = Livewire::actingAs($user)->test(QuickCapture::class)->get('availableTargets');

        $this->assertContains('inbox', $targets);
        $this->assertContains('todos', $targets);
        $this->assertContains('tasks', $targets);
    }

    public function test_eisenhower_collapses_todos_and_tasks_out_of_the_chip_row(): void
    {
        $user = User::factory()->create(['list_concept' => 'eisenhower']);

        $targets = Livewire::actingAs($user)->test(QuickCapture::class)->get('availableTargets');

        $this->assertContains('inbox', $targets);
        $this->assertNotContains('todos', $targets);
        $this->assertNotContains('tasks', $targets);
    }

    public function test_eisenhower_keeps_every_other_chip(): void
    {
        $user = User::factory()->create(['list_concept' => 'eisenhower']);

        $targets = Livewire::actingAs($user)->test(QuickCapture::class)->get('availableTargets');

        $this->assertContains('group', $targets);
        $this->assertContains('project', $targets);
        $this->assertContains('craft', $targets);
        $this->assertContains('agenda', $targets);
    }

    public function test_eisenhower_still_opens_on_the_inbox_target_by_default(): void
    {
        $user = User::factory()->create(['list_concept' => 'eisenhower']);

        Livewire::actingAs($user)->test(QuickCapture::class)->assertSet('target', 'inbox');
    }

    public function test_eisenhower_labels_the_inbox_chip_aufgabe(): void
    {
        $user = User::factory()->create(['list_concept' => 'eisenhower']);
        $this->actingAs($user);

        $this->assertSame('Aufgabe', QuickCapture::labelFor('inbox'));
    }

    public function test_three_things_still_labels_the_inbox_chip_inbox(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->assertSame('Inbox', QuickCapture::labelFor('inbox'));
    }

    public function test_setting_a_collapsed_target_under_eisenhower_is_ignored(): void
    {
        $user = User::factory()->create(['list_concept' => 'eisenhower']);

        Livewire::actingAs($user)->test(QuickCapture::class)
            ->call('setTarget', 'todos')
            ->assertSet('target', 'inbox');
    }

    public function test_capturing_under_eisenhower_writes_a_task_in_the_inbox_list(): void
    {
        $user = User::factory()->create(['list_concept' => 'eisenhower']);

        Livewire::actingAs($user)->test(QuickCapture::class)
            ->set('title', 'Postenbeschreibung studieren')
            ->call('save');

        $task = Task::query()->forUser($user)->sole();
        $this->assertSame('inbox', $task->list);
        $this->assertSame('Postenbeschreibung studieren', $task->title);
    }
}
