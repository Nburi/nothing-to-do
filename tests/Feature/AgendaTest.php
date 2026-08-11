<?php

namespace Tests\Feature;

use App\Livewire\Agenda;
use App\Models\AgendaEntry;
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
