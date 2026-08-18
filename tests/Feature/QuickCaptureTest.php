<?php

namespace Tests\Feature;

use App\Livewire\QuickCapture;
use App\Models\AgendaSpace;
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

    public function test_it_captures_a_task_with_notes(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->call('setTarget', 'tasks')
            ->set('title', 'Wettkampfanmeldung absenden')
            ->set('notes', '  **Wichtig**: vor Freitag  ')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'title' => 'Wettkampfanmeldung absenden',
            'notes' => '**Wichtig**: vor Freitag',
        ]);
    }

    public function test_notes_are_optional_and_blank_notes_stay_null(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->call('setTarget', 'todos')
            ->set('title', 'Ohne Notizen')
            ->set('notes', '   ')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', ['title' => 'Ohne Notizen', 'notes' => null]);
    }

    public function test_notes_over_the_length_limit_are_rejected(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->set('title', 'Zu lange Notiz')
            ->set('notes', str_repeat('a', 5001))
            ->call('save')
            ->assertHasErrors(['notes']);

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_notes_html_renders_bold_italic_and_underline(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->set('notes', '**fett** *kursiv* ++unterstrichen++');

        $html = $component->instance()->notesHtml();

        $this->assertStringContainsString('<strong>fett</strong>', $html);
        $this->assertStringContainsString('<em>kursiv</em>', $html);
        $this->assertStringContainsString('<u>unterstrichen</u>', $html);
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

    public function test_it_captures_an_agenda_entry(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->call('setTarget', 'agenda')
            ->set('agendaType', 'exam')
            ->set('subject', '  Mathematik  ')
            ->set('title', 'Prüfung Kapitel 4')
            ->set('date', '2026-09-12')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('agenda_entries', [
            'user_id' => $user->id,
            'type' => 'exam',
            'subject' => 'Mathematik',
            'title' => 'Prüfung Kapitel 4',
            'date' => '2026-09-12 00:00:00',
            'agenda_space_id' => null,
        ]);
    }

    public function test_agenda_requires_a_subject_and_a_date(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->call('setTarget', 'agenda')
            ->set('title', 'Ohne Fach und Datum')
            ->call('save')
            ->assertHasErrors(['subject' => 'required', 'date' => 'required']);

        $this->assertDatabaseCount('agenda_entries', 0);
    }

    /** Those same fields must not be demanded of any other target. */
    public function test_other_targets_do_not_require_the_agenda_fields(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->set('title', 'Ganz normale Aufgabe')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', ['title' => 'Ganz normale Aufgabe']);
    }

    /**
     * Several homework items for one subject on one day is the normal case, so
     * Fach, date and type survive a save the same way the target does.
     */
    public function test_agenda_keeps_subject_date_and_type_after_saving(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->call('setTarget', 'agenda')
            ->set('agendaType', 'exam')
            ->set('subject', 'Biologie')
            ->set('date', '2026-09-12')
            ->set('title', 'Erster Eintrag')
            ->call('save')
            ->assertSet('title', '')
            ->assertSet('subject', 'Biologie')
            ->assertSet('date', '2026-09-12')
            ->assertSet('agendaType', 'exam');
    }

    public function test_leaving_the_agenda_target_drops_its_fields(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->call('setTarget', 'agenda')
            ->set('subject', 'Physik')
            ->set('date', '2026-09-12')
            ->call('setTarget', 'todos')
            ->assertSet('subject', '')
            ->assertSet('date', null)
            ->assertSet('agendaType', 'homework');
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
            ->set('notes', 'Material besorgen')
            ->set('whereToBegin', 'Material besorgen')
            ->call('setTarget', 'project')
            ->assertSet('dueDate', null)
            ->assertSet('notes', '')
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
            ->set('notes', 'Nicht vergessen')
            ->call('save')
            ->assertSet('title', '')
            ->assertSet('deadline', null)
            ->assertSet('notes', '')
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

    public function test_opening_the_panel_straight_onto_agenda_defaults_the_users_only_class(): void
    {
        $user = User::factory()->create();
        $space = AgendaSpace::factory()->for($user, 'owner')->create();

        Livewire::actingAs($user)
            ->test(QuickCapture::class)
            ->dispatch('quick-capture-opened', target: 'agenda')
            ->assertSet('agendaSpaceId', $space->id);
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
