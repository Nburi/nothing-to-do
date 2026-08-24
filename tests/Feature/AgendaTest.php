<?php

namespace Tests\Feature;

use App\Livewire\Agenda;
use App\Models\AgendaEntry;
use App\Models\AgendaSpace;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AgendaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_homework_entry_can_be_created(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Agenda::class)
            ->call('openCreateForm')
            ->set('formType', 'homework')
            ->set('formSubject', 'Mathematik')
            ->set('formTitle', 'Kapitel 5, Aufgaben 1-10')
            ->set('formDate', now()->addDay()->toDateString())
            ->call('saveEntry')
            ->assertHasNoErrors()
            ->assertSet('showForm', false);

        $this->assertDatabaseHas('agenda_entries', [
            'user_id' => $user->id,
            'type' => 'homework',
            'subject' => 'Mathematik',
            'title' => 'Kapitel 5, Aufgaben 1-10',
            'agenda_space_id' => null,
        ]);
    }

    public function test_an_exam_entry_can_be_created(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Agenda::class)
            ->call('openCreateForm')
            ->set('formType', 'exam')
            ->set('formSubject', 'Physik')
            ->set('formTitle', 'Prüfung Kinematik')
            ->set('formDate', now()->addWeek()->toDateString())
            ->call('saveEntry')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('agenda_entries', [
            'user_id' => $user->id,
            'type' => 'exam',
            'subject' => 'Physik',
        ]);
    }

    public function test_required_fields_are_validated(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Agenda::class)
            ->call('openCreateForm')
            ->set('formSubject', '')
            ->set('formTitle', '')
            ->set('formDate', '')
            ->call('saveEntry')
            ->assertHasErrors(['formSubject' => 'required', 'formTitle' => 'required', 'formDate' => 'required']);

        $this->assertDatabaseCount('agenda_entries', 0);
    }

    public function test_toggling_done_records_and_clears_this_users_completion(): void
    {
        $user = User::factory()->create();
        $entry = AgendaEntry::factory()->for($user)->homework()->create();

        $component = Livewire::actingAs($user)->test(Agenda::class);

        $component->call('toggleDone', $entry->id);
        $this->assertDatabaseHas('agenda_entry_completions', [
            'agenda_entry_id' => $entry->id,
            'user_id' => $user->id,
        ]);

        $component->call('toggleDone', $entry->id);
        $this->assertDatabaseMissing('agenda_entry_completions', [
            'agenda_entry_id' => $entry->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_an_entry_can_be_edited(): void
    {
        $user = User::factory()->create();
        $entry = AgendaEntry::factory()->for($user)->exam()->create(['subject' => 'Chemie', 'title' => 'Alte Prüfung']);

        Livewire::actingAs($user)
            ->test(Agenda::class)
            ->call('startEdit', $entry->id)
            ->assertSet('formSubject', 'Chemie')
            ->set('formTitle', 'Neue Prüfung')
            ->call('saveEntry')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('agenda_entries', ['id' => $entry->id, 'title' => 'Neue Prüfung']);
    }

    public function test_an_entrys_note_is_visible_on_the_list_without_opening_the_edit_form(): void
    {
        $user = User::factory()->create();
        $entry = AgendaEntry::factory()->for($user)->create(['notes' => 'Seite 12 bis 15 rechnen, Taschenrechner mitbringen.']);

        Livewire::actingAs($user)
            ->test(Agenda::class)
            ->assertSee('Seite 12 bis 15 rechnen, Taschenrechner mitbringen.')
            ->assertSet('showForm', false);
    }

    public function test_an_entry_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $entry = AgendaEntry::factory()->for($user)->create();

        Livewire::actingAs($user)
            ->test(Agenda::class)
            ->call('deleteEntry', $entry->id);

        $this->assertDatabaseMissing('agenda_entries', ['id' => $entry->id]);
    }

    public function test_a_user_cannot_see_or_mutate_another_users_entries(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $foreignEntry = AgendaEntry::factory()->for($other)->create();

        $component = Livewire::actingAs($user)->test(Agenda::class);
        $component->assertDontSeeHtml('agenda-'.$foreignEntry->id);

        try {
            $component->call('toggleDone', $foreignEntry->id);
            $this->fail('Expected a ModelNotFoundException for the foreign entry.');
        } catch (ModelNotFoundException) {
            // The foreign entry is invisible through the visibility scope.
        }

        $this->assertDatabaseCount('agenda_entry_completions', 0);
    }

    public function test_filter_chips_only_show_the_matching_type(): void
    {
        $user = User::factory()->create();
        $homework = AgendaEntry::factory()->for($user)->homework()->create(['title' => 'HOMEWORK-ENTRY']);
        $exam = AgendaEntry::factory()->for($user)->exam()->create(['title' => 'EXAM-ENTRY']);

        Livewire::actingAs($user)
            ->test(Agenda::class)
            ->call('setFilter', 'homework')
            ->assertSee('HOMEWORK-ENTRY')
            ->assertDontSee('EXAM-ENTRY')
            ->call('setFilter', 'exam')
            ->assertSee('EXAM-ENTRY')
            ->assertDontSee('HOMEWORK-ENTRY');
    }

    public function test_subject_filter_only_shows_the_matching_subject(): void
    {
        $user = User::factory()->create();
        AgendaEntry::factory()->for($user)->create(['subject' => 'Mathematik', 'title' => 'MATH-ENTRY']);
        AgendaEntry::factory()->for($user)->create(['subject' => 'Physik', 'title' => 'PHYSICS-ENTRY']);

        Livewire::actingAs($user)
            ->test(Agenda::class)
            ->call('setSubjectFilter', 'Mathematik')
            ->assertSee('MATH-ENTRY')
            ->assertDontSee('PHYSICS-ENTRY')
            ->call('setSubjectFilter', 'all')
            ->assertSee('MATH-ENTRY')
            ->assertSee('PHYSICS-ENTRY');
    }

    public function test_subject_filter_combines_with_the_type_filter(): void
    {
        $user = User::factory()->create();
        AgendaEntry::factory()->for($user)->homework()->create(['subject' => 'Mathematik', 'title' => 'MATH-HOMEWORK']);
        AgendaEntry::factory()->for($user)->exam()->create(['subject' => 'Mathematik', 'title' => 'MATH-EXAM']);

        Livewire::actingAs($user)
            ->test(Agenda::class)
            ->call('setFilter', 'exam')
            ->call('setSubjectFilter', 'Mathematik')
            ->assertSee('MATH-EXAM')
            ->assertDontSee('MATH-HOMEWORK');
    }

    public function test_subject_filter_rejects_a_subject_the_user_has_never_used(): void
    {
        $user = User::factory()->create();
        AgendaEntry::factory()->for($user)->create(['subject' => 'Mathematik', 'title' => 'MATH-ENTRY']);

        $component = Livewire::actingAs($user)
            ->test(Agenda::class)
            ->call('setSubjectFilter', 'Nicht Existiert')
            ->assertSee('MATH-ENTRY');

        $this->assertSame('all', $component->instance()->filterSubject);
    }

    public function test_creating_from_a_filtered_subject_prefills_the_form(): void
    {
        $user = User::factory()->create();
        AgendaEntry::factory()->for($user)->create(['subject' => 'Mathematik']);

        $component = Livewire::actingAs($user)
            ->test(Agenda::class)
            ->call('setSubjectFilter', 'Mathematik')
            ->call('openCreateForm');

        $this->assertSame('Mathematik', $component->instance()->formSubject);
    }

    public function test_existing_subjects_are_distinct_sorted_and_scoped_to_the_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        AgendaEntry::factory()->for($user)->create(['subject' => 'Physik']);
        AgendaEntry::factory()->for($user)->create(['subject' => 'Mathematik']);
        AgendaEntry::factory()->for($user)->create(['subject' => 'Physik']); // duplicate, should collapse
        AgendaEntry::factory()->for($other)->create(['subject' => 'Chemie']); // another user, must not leak

        $subjects = Livewire::actingAs($user)
            ->test(Agenda::class)
            ->instance()
            ->existingSubjects;

        $this->assertSame(['Mathematik', 'Physik'], $subjects->values()->all());
    }

    // ── Private notes on a shared entry ──────────────────────────────

    public function test_a_private_note_can_be_saved_on_a_shared_entry(): void
    {
        $owner = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->create();

        Livewire::actingAs($owner)
            ->test(Agenda::class)
            ->call('openCreateForm')
            ->set('formSubject', 'Mathematik')
            ->set('formTitle', 'Kapitel 5')
            ->set('formDate', now()->addDay()->toDateString())
            ->set('formSpaceId', $space->id)
            ->set('formPrivateNotes', 'Nicht vergessen: Taschenrechner mitbringen.')
            ->call('saveEntry')
            ->assertHasNoErrors();

        $entry = AgendaEntry::firstOrFail();

        $this->assertDatabaseHas('agenda_entry_notes', [
            'agenda_entry_id' => $entry->id,
            'user_id' => $owner->id,
            'notes' => 'Nicht vergessen: Taschenrechner mitbringen.',
        ]);

        // The shared note stays untouched by the private one.
        $this->assertNull($entry->notes);
    }

    public function test_a_private_note_is_invisible_to_a_classmate(): void
    {
        $owner = User::factory()->create();
        $classmate = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->withMembers($classmate)->create();
        $entry = AgendaEntry::factory()->for($owner)->inSpace($space)->create();
        $entry->setPrivateNoteFor($owner, 'Meine ganz eigene Notiz.');

        Livewire::actingAs($classmate)
            ->test(Agenda::class)
            ->assertDontSee('Meine ganz eigene Notiz.');

        $this->assertNull($entry->privateNoteFor($classmate));
    }

    public function test_editing_reloads_only_your_own_private_note_not_a_classmates(): void
    {
        $owner = User::factory()->create();
        $classmate = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->withMembers($classmate)->create();
        $entry = AgendaEntry::factory()->for($owner)->inSpace($space)->create();
        $entry->setPrivateNoteFor($owner, 'Notiz von Besitzer:in.');
        $entry->setPrivateNoteFor($classmate, 'Notiz von Klassenkamerad:in.');

        Livewire::actingAs($classmate)
            ->test(Agenda::class)
            ->call('startEdit', $entry->id)
            ->assertSet('formPrivateNotes', 'Notiz von Klassenkamerad:in.');

        Livewire::actingAs($owner)
            ->test(Agenda::class)
            ->call('startEdit', $entry->id)
            ->assertSet('formPrivateNotes', 'Notiz von Besitzer:in.');
    }

    public function test_clearing_a_private_note_deletes_its_row(): void
    {
        $owner = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->create();
        $entry = AgendaEntry::factory()->for($owner)->inSpace($space)->create();
        $entry->setPrivateNoteFor($owner, 'Wird gleich wieder gelöscht.');

        Livewire::actingAs($owner)
            ->test(Agenda::class)
            ->call('startEdit', $entry->id)
            ->set('formPrivateNotes', '   ')
            ->call('saveEntry')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('agenda_entry_notes', [
            'agenda_entry_id' => $entry->id,
            'user_id' => $owner->id,
        ]);
    }

    public function test_a_private_note_is_not_stored_for_a_private_entry(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Agenda::class)
            ->call('openCreateForm')
            ->set('formSubject', 'Mathematik')
            ->set('formTitle', 'Kapitel 5')
            ->set('formDate', now()->addDay()->toDateString())
            ->set('formPrivateNotes', 'Sollte nirgends landen.')
            ->call('saveEntry')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('agenda_entry_notes', 0);
    }

    public function test_a_classmate_cannot_write_a_private_note_onto_a_foreign_entrys_id_via_another_users_component(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $entry = AgendaEntry::factory()->for($owner)->create(); // private, no space

        try {
            Livewire::actingAs($stranger)
                ->test(Agenda::class)
                ->call('startEdit', $entry->id);
            $this->fail('Expected a ModelNotFoundException for a private entry outside any shared space.');
        } catch (ModelNotFoundException) {
            // visibleEntry() hides it before any private note could ever be read or written.
        }

        $this->assertDatabaseCount('agenda_entry_notes', 0);
    }

    public function test_a_private_note_respects_the_same_length_limit_as_the_shared_note(): void
    {
        $owner = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->create();

        Livewire::actingAs($owner)
            ->test(Agenda::class)
            ->call('openCreateForm')
            ->set('formSubject', 'Mathematik')
            ->set('formTitle', 'Kapitel 5')
            ->set('formDate', now()->addDay()->toDateString())
            ->set('formSpaceId', $space->id)
            ->set('formPrivateNotes', str_repeat('a', 2001))
            ->call('saveEntry')
            ->assertHasErrors(['formPrivateNotes' => 'max']);

        $this->assertDatabaseCount('agenda_entries', 0);
        $this->assertDatabaseCount('agenda_entry_notes', 0);
    }

    public function test_deleting_a_shared_entry_deletes_every_members_private_note_with_it(): void
    {
        $owner = User::factory()->create();
        $classmate = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->withMembers($classmate)->create();
        $entry = AgendaEntry::factory()->for($owner)->inSpace($space)->create();
        $entry->setPrivateNoteFor($owner, 'Notiz A');
        $entry->setPrivateNoteFor($classmate, 'Notiz B');

        Livewire::actingAs($owner)
            ->test(Agenda::class)
            ->call('deleteEntry', $entry->id);

        $this->assertDatabaseCount('agenda_entry_notes', 0);
    }

    public function test_switching_a_shared_entry_back_to_private_leaves_its_note_untouched_for_later(): void
    {
        $owner = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->create();
        $entry = AgendaEntry::factory()->for($owner)->inSpace($space)->create();
        $entry->setPrivateNoteFor($owner, 'Bleibt erhalten.');

        // Switch "Für" to "Nur ich" without touching the (now hidden) private field.
        Livewire::actingAs($owner)
            ->test(Agenda::class)
            ->call('startEdit', $entry->id)
            ->set('formSpaceId', null)
            ->call('saveEntry')
            ->assertHasNoErrors();

        // Not deleted — a "Für" toggle is not the same action as clearing the field.
        $this->assertDatabaseHas('agenda_entry_notes', [
            'agenda_entry_id' => $entry->id,
            'user_id' => $owner->id,
            'notes' => 'Bleibt erhalten.',
        ]);
    }

    public function test_a_long_private_note_is_truncated_to_a_preview_like_the_shared_note(): void
    {
        $owner = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->create();
        $entry = AgendaEntry::factory()->for($owner)->inSpace($space)->create();
        $entry->setPrivateNoteFor($owner, 'eins zwei drei vier fünf sechs sieben acht neun zehn');

        $preview = $entry->privateNotePreview($owner);

        $this->assertSame('eins zwei drei vier fünf sechs sieben acht…', $preview);
    }

    public function test_a_private_note_works_identically_for_exam_entries(): void
    {
        $owner = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->create();
        $entry = AgendaEntry::factory()->for($owner)->inSpace($space)->exam()->create();
        $entry->setPrivateNoteFor($owner, 'Vor der Prüfung nochmal Karteikarten.');

        $this->assertSame('Vor der Prüfung nochmal Karteikarten.', $entry->privateNoteFor($owner));
    }

    public function test_picking_a_suggested_subject_fills_the_form_field(): void
    {
        $user = User::factory()->create();
        AgendaEntry::factory()->for($user)->create(['subject' => 'Mathematik']);

        Livewire::actingAs($user)
            ->test(Agenda::class)
            ->call('openCreateForm')
            ->set('formTitle', 'Kapitel 5')
            ->set('formDate', now()->addDay()->toDateString())
            ->set('formSubject', 'Mathematik')
            ->call('saveEntry')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('agenda_entries', [
            'user_id' => $user->id,
            'subject' => 'Mathematik',
            'title' => 'Kapitel 5',
        ]);
    }
}
