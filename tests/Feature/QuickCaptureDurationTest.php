<?php

namespace Tests\Feature;

use App\Livewire\QuickCapture;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuickCaptureDurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_duration_is_captured_for_todos_and_tasks(): void
    {
        $user = User::factory()->create();

        foreach (['todos', 'tasks'] as $list) {
            Livewire::actingAs($user)
                ->test(QuickCapture::class)
                ->call('setTarget', $list)
                ->set('title', 'Eintrag '.$list)
                ->set('duration', 45)
                ->call('save')
                ->assertHasNoErrors();

            $this->assertSame(45, Task::where('title', 'Eintrag '.$list)->first()->duration_minutes);
        }
    }

    public function test_switching_to_inbox_clears_a_duration_set_on_a_prior_target(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->call('setTarget', 'tasks')
            ->set('duration', 45)
            ->call('setTarget', 'inbox')
            ->assertSet('duration', null);
    }

    /**
     * Defense in depth: save() itself must never persist a duration for
     * Inbox, even if the property somehow still holds a value (a crafted
     * request setting target and duration in the same round trip, bypassing
     * setTarget()'s own clearing) — the UI hiding the field is not the only
     * thing standing between Inbox and a duration it shouldn't have.
     */
    public function test_duration_is_never_persisted_for_inbox_even_with_a_tampered_request(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->set('target', 'inbox')
            ->set('duration', 45)
            ->set('title', 'Irgendwas')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull(Task::where('title', 'Irgendwas')->first()->duration_minutes);
    }

    public function test_an_out_of_range_duration_is_rejected(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->call('setTarget', 'tasks')
            ->set('title', 'Zu lang')
            ->set('duration', 999)
            ->call('save')
            ->assertHasErrors('duration');

        $this->assertDatabaseMissing('tasks', ['title' => 'Zu lang']);
    }
}
