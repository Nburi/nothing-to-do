<?php

namespace Tests\Feature;

use App\Livewire\QuickCapture;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuickCaptureTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_captures_into_the_inbox_by_default(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->assertSet('target', 'inbox')
            ->set('title', '  Startnummer abholen  ')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => 'Startnummer abholen',
            'list' => 'inbox',
        ]);
    }

    public function test_it_captures_into_todos_and_tasks(): void
    {
        $user = User::factory()->create();

        foreach (['todos', 'tasks'] as $list) {
            Livewire::actingAs($user)
                ->test(QuickCapture::class)
                ->call('setTarget', $list)
                ->set('title', 'Eintrag '.$list)
                ->call('save')
                ->assertHasNoErrors();

            $this->assertDatabaseHas('tasks', [
                'user_id' => $user->id,
                'title' => 'Eintrag '.$list,
                'list' => $list,
            ]);
        }
    }

    public function test_it_captures_a_task_with_both_dates(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->call('setTarget', 'tasks')
            ->set('title', 'Aufsatz entwerfen')
            ->set('deadline', '2026-09-01')
            ->set('dueDate', '2026-08-28')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => 'Aufsatz entwerfen',
            'list' => 'tasks',
            'deadline' => '2026-09-01 00:00:00',
            'due_date' => '2026-08-28 00:00:00',
        ]);
    }

    public function test_it_captures_a_project(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->call('setTarget', 'project')
            ->set('title', 'Saisonplanung OL')
            ->set('deadline', '2026-10-15')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('projects', [
            'user_id' => $user->id,
            'name' => 'Saisonplanung OL',
            'deadline' => '2026-10-15 00:00:00',
        ]);
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_it_captures_a_craft_idea_with_where_to_begin(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->call('setTarget', 'craft')
            ->set('title', '  Kerzen giessen  ')
            ->set('whereToBegin', '  Sojawachs bestellen  ')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('craft_ideas', [
            'user_id' => $user->id,
            'title' => 'Kerzen giessen',
            'where_to_begin' => 'Sojawachs bestellen',
            'is_done' => false,
        ]);
    }

    public function test_where_to_begin_is_optional(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->call('setTarget', 'craft')
            ->set('title', 'Fotobuch gestalten')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('craft_ideas', [
            'title' => 'Fotobuch gestalten',
            'where_to_begin' => null,
        ]);
    }

    public function test_a_blank_title_is_rejected(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->set('title', '   ')
            ->call('save')
            ->assertHasErrors(['title' => 'required']);

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_an_unknown_target_is_ignored(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->call('setTarget', 'nonsense')
            ->assertSet('target', 'inbox');
    }

    /**
     * Switching away from a target has to drop the fields that target owned —
     * otherwise a Wunschtermin typed for a task would silently ride along into
     * the project created right after it.
     */
    public function test_switching_target_clears_fields_that_do_not_apply(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->set('dueDate', '2026-08-28')
            ->set('whereToBegin', 'Material besorgen')
            ->call('setTarget', 'project')
            ->assertSet('dueDate', null)
            ->assertSet('whereToBegin', '')
            ->call('setTarget', 'craft')
            ->assertSet('deadline', null);
    }

    /**
     * The panel stays open for the next entry: the title clears but the chosen
     * target survives, so three To-Dos in a row don't need three chip taps.
     */
    public function test_saving_clears_the_title_but_keeps_the_target(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->call('setTarget', 'todos')
            ->set('title', 'Dehnen & Mobility')
            ->set('deadline', '2026-09-01')
            ->call('save')
            ->assertSet('title', '')
            ->assertSet('deadline', null)
            ->assertSet('target', 'todos')
            ->assertSet('captured', ['title' => 'Dehnen & Mobility', 'label' => 'To-Do']);
    }

    public function test_opening_the_panel_resets_it(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->call('setTarget', 'craft')
            ->set('title', 'halb getippt')
            ->dispatch('quick-capture-opened')
            ->assertSet('title', '')
            ->assertSet('target', 'inbox')
            ->assertSet('captured', null);
    }

    public function test_opening_the_panel_can_preselect_a_target(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->dispatch('quick-capture-opened', target: 'craft')
            ->assertSet('target', 'craft');
    }

    public function test_an_unknown_preselected_target_falls_back_to_inbox(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->call('setTarget', 'tasks')
            ->dispatch('quick-capture-opened', target: 'nonsense')
            ->assertSet('target', 'inbox');
    }

    public function test_a_capture_dispatches_the_refresh_event(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->set('title', 'Buch zurückbringen')
            ->call('save')
            ->assertDispatched('captured');
    }

    public function test_entries_always_belong_to_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->set('title', 'Meine Aufgabe')
            ->call('save');

        $this->assertDatabaseHas('tasks', ['title' => 'Meine Aufgabe', 'user_id' => $user->id]);
        $this->assertDatabaseMissing('tasks', ['title' => 'Meine Aufgabe', 'user_id' => $other->id]);
    }
}
