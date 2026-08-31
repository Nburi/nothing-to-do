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
}
