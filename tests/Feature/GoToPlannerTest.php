<?php

namespace Tests\Feature;

use App\Livewire\TaskBoard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * ManagesTasks::goToPlanner() is the second tap of the Planer-provenance icon
 * (see Task::isTodayFromPlanner(), partials/task-card*.blade.php) — a plain
 * server-driven redirect rather than a native `<a href wire:navigate>`, since
 * Livewire's own wire:navigate click listener fires independently of a
 * Blade-side preventDefault() and can't be made to wait for the first "peek"
 * tap. Verified end-to-end in the browser (Alpine's reveal-then-call logic
 * isn't exercised by PHPUnit); this just covers the server half.
 */
class GoToPlannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_go_to_planner_redirects_to_the_planer(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(TaskBoard::class)
            ->call('goToPlanner')
            ->assertRedirect(route('planner'));
    }
}
