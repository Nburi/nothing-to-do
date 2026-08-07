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
            'is_done' => false,
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

    public function test_toggling_done_flips_the_flag(): void
    {
        $user = User::factory()->create();
        $entry = AgendaEntry::factory()->for($user)->homework()->create();

        Livewire::actingAs($user)
            ->test(Agenda::class)
            ->call('toggleDone', $entry->id);

        $this->assertDatabaseHas('agenda_entries', ['id' => $entry->id, 'is_done' => true]);
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
            // The foreign entry is invisible through the owner relationship.
        }

        $this->assertDatabaseHas('agenda_entries', ['id' => $foreignEntry->id, 'is_done' => false]);
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
}
