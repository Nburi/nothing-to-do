<?php

namespace Tests\Feature;

use App\Livewire\QuickCapture;
use App\Models\AgendaEntry;
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

    public function test_duration_is_captured_for_a_group_task(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->call('setTarget', 'group')
            ->set('newGroupName', 'Vortrag')
            ->set('title', 'Folien vorbereiten')
            ->set('duration', 60)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(60, Task::where('title', 'Folien vorbereiten')->first()->duration_minutes);
    }

    public function test_duration_is_captured_for_homework_but_not_exams(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->call('setTarget', 'agenda')
            ->set('agendaType', 'homework')
            ->set('subject', 'Bio')
            ->set('title', 'Zellatmung')
            ->set('date', now()->addDay()->toDateString())
            ->set('duration', 25)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(25, AgendaEntry::where('title', 'Zellatmung')->first()->duration_minutes);

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->call('setTarget', 'agenda')
            ->set('agendaType', 'exam')
            ->set('subject', 'Bio')
            ->set('title', 'Prüfung Zellatmung')
            ->set('date', now()->addWeek()->toDateString())
            ->set('duration', 25) // stale/ignored value, the field isn't even shown for an exam
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull(AgendaEntry::where('title', 'Prüfung Zellatmung')->first()->duration_minutes);
    }

    public function test_duration_is_cleared_when_switching_to_a_non_task_target(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->call('setTarget', 'tasks')
            ->set('duration', 45)
            ->call('setTarget', 'project')
            ->assertSet('duration', null);
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
