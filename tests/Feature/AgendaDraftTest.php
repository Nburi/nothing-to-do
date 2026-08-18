<?php

namespace Tests\Feature;

use App\Livewire\Agenda;
use App\Models\AgendaDraft;
use App\Models\AgendaEntry;
use App\Models\AgendaSpace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class AgendaDraftTest extends TestCase
{
    use RefreshDatabase;

    // ── Starting a draft ──────────────────────────────────────────────

    public function test_opening_the_create_form_for_a_shared_space_starts_a_draft(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->withMembers($member)->create();

        Livewire::actingAs($member)
            ->test(Agenda::class)
            ->call('openCreateForm')
            ->set('formSpaceId', $space->id);

        $this->assertDatabaseHas('agenda_drafts', [
            'user_id' => $member->id,
            'agenda_space_id' => $space->id,
            'agenda_entry_id' => null,
        ]);
    }

    public function test_opening_the_create_form_for_a_private_entry_starts_no_draft(): void
    {
        $user = User::factory()->create();

        // The default target ("Nur ich") has no class selected at all.
        Livewire::actingAs($user)->test(Agenda::class)->call('openCreateForm');

        $this->assertDatabaseMissing('agenda_drafts', ['user_id' => $user->id]);
    }

    public function test_typing_a_subject_updates_the_draft(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->withMembers($member)->create();

        Livewire::actingAs($member)
            ->test(Agenda::class)
            ->call('openCreateForm')
            ->set('formSpaceId', $space->id)
            ->set('formSubject', 'Mathematik');

        $this->assertDatabaseHas('agenda_drafts', [
            'user_id' => $member->id,
            'subject' => 'Mathematik',
        ]);
    }

    public function test_editing_an_existing_shared_entry_starts_a_draft_referencing_it(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->withMembers($member)->create();
        $entry = AgendaEntry::factory()->for($owner)->inSpace($space)->create();

        Livewire::actingAs($member)->test(Agenda::class)->call('startEdit', $entry->id);

        $this->assertDatabaseHas('agenda_drafts', [
            'user_id' => $member->id,
            'agenda_space_id' => $space->id,
            'agenda_entry_id' => $entry->id,
        ]);
    }

    // ── Clearing a draft ──────────────────────────────────────────────

    public function test_cancelling_the_form_clears_the_draft(): void
    {
        $owner = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->create();

        Livewire::actingAs($owner)
            ->test(Agenda::class)
            ->call('openCreateForm')
            ->set('formSpaceId', $space->id)
            ->call('cancelForm');

        $this->assertDatabaseMissing('agenda_drafts', ['user_id' => $owner->id]);
    }

    public function test_saving_the_entry_clears_the_draft(): void
    {
        $owner = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->create();

        Livewire::actingAs($owner)
            ->test(Agenda::class)
            ->call('openCreateForm')
            ->set('formSpaceId', $space->id)
            ->set('formSubject', 'Mathematik')
            ->set('formTitle', 'Kapitel 5')
            ->set('formDate', Carbon::tomorrow()->toDateString())
            ->call('saveEntry');

        $this->assertDatabaseMissing('agenda_drafts', ['user_id' => $owner->id]);
    }

    public function test_switching_to_a_different_space_moves_the_draft(): void
    {
        $owner = User::factory()->create();
        $spaceA = AgendaSpace::factory()->for($owner, 'owner')->create();
        $spaceB = AgendaSpace::factory()->for($owner, 'owner')->create();

        Livewire::actingAs($owner)
            ->test(Agenda::class)
            ->call('openCreateForm')
            ->set('formSpaceId', $spaceA->id)
            ->set('formSpaceId', $spaceB->id);

        $this->assertDatabaseHas('agenda_drafts', ['user_id' => $owner->id, 'agenda_space_id' => $spaceB->id]);
        $this->assertDatabaseMissing('agenda_drafts', ['user_id' => $owner->id, 'agenda_space_id' => $spaceA->id]);
    }

    public function test_switching_to_nur_ich_clears_the_draft(): void
    {
        $owner = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->create();

        Livewire::actingAs($owner)
            ->test(Agenda::class)
            ->call('openCreateForm')
            ->set('formSpaceId', $space->id)
            ->set('formSpaceId', null);

        $this->assertDatabaseMissing('agenda_drafts', ['user_id' => $owner->id]);
    }

    // ── The heartbeat ─────────────────────────────────────────────────

    public function test_the_heartbeat_refreshes_the_draft_without_changing_its_content(): void
    {
        $owner = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->create();

        $component = Livewire::actingAs($owner)
            ->test(Agenda::class)
            ->call('openCreateForm')
            ->set('formSpaceId', $space->id)
            ->set('formSubject', 'Mathematik');

        $before = AgendaDraft::where('user_id', $owner->id)->first()->updated_at;

        $this->travel(10)->seconds();
        $component->call('heartbeatDraft');

        $after = AgendaDraft::where('user_id', $owner->id)->first();
        $this->assertTrue($after->updated_at->greaterThan($before));
        $this->assertSame('Mathematik', $after->subject);
    }

    public function test_the_heartbeat_does_nothing_when_no_form_is_open(): void
    {
        $owner = User::factory()->create();
        AgendaSpace::factory()->for($owner, 'owner')->create();

        Livewire::actingAs($owner)->test(Agenda::class)->call('heartbeatDraft');

        $this->assertDatabaseMissing('agenda_drafts', ['user_id' => $owner->id]);
    }

    // ── Visibility ────────────────────────────────────────────────────

    public function test_a_classmate_sees_the_draft_line(): void
    {
        $owner = User::factory()->create(['name' => 'Lisa']);
        $member = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->withMembers($member)->create();

        Livewire::actingAs($owner)
            ->test(Agenda::class)
            ->call('openCreateForm')
            ->set('formSpaceId', $space->id)
            ->set('formSubject', 'Mathematik');

        Livewire::actingAs($member)
            ->test(Agenda::class)
            ->assertSee('Lisa erstellt gerade eine Hausaufgabe zu Mathematik');
    }

    public function test_the_draft_line_names_the_type_not_just_the_subject(): void
    {
        $owner = User::factory()->create(['name' => 'Lisa']);
        $member = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->withMembers($member)->create();

        Livewire::actingAs($owner)
            ->test(Agenda::class)
            ->call('openCreateForm')
            ->set('formSpaceId', $space->id)
            ->set('formType', 'exam')
            ->set('formSubject', 'Chemie');

        // A Hausaufgabe and a Prüfung for the same Fach are not the duplicate
        // this feature exists to catch — the type has to be in the line.
        Livewire::actingAs($member)
            ->test(Agenda::class)
            ->assertSee('Lisa erstellt gerade eine Prüfung zu Chemie');
    }

    public function test_editing_line_names_the_entry_being_edited(): void
    {
        $owner = User::factory()->create(['name' => 'Lisa']);
        $member = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->withMembers($member)->create();
        $entry = AgendaEntry::factory()->for($member)->inSpace($space)->create();

        Livewire::actingAs($owner)->test(Agenda::class)->call('startEdit', $entry->id);

        Livewire::actingAs($member)
            ->test(Agenda::class)
            ->assertSee('Lisa bearbeitet gerade');
    }

    public function test_two_simultaneous_drafters_are_named_together(): void
    {
        $owner = User::factory()->create(['name' => 'Lisa']);
        $memberA = User::factory()->create(['name' => 'Tom']);
        $memberB = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->withMembers($memberA, $memberB)->create();

        Livewire::actingAs($owner)->test(Agenda::class)->call('openCreateForm')->set('formSpaceId', $space->id);
        Livewire::actingAs($memberA)->test(Agenda::class)->call('openCreateForm')->set('formSpaceId', $space->id);

        Livewire::actingAs($memberB)
            ->test(Agenda::class)
            ->assertSee('Lisa und Tom sind gerade aktiv');
    }

    public function test_you_never_see_your_own_draft(): void
    {
        $owner = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->create();

        $component = Livewire::actingAs($owner)
            ->test(Agenda::class)
            ->call('openCreateForm')
            ->set('formSpaceId', $space->id)
            ->set('formSubject', 'Mathematik');

        $this->assertTrue($component->get('draftLines')->isEmpty());
    }

    public function test_a_stranger_never_sees_a_draft_from_a_space_they_are_not_in(): void
    {
        $owner = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->create();
        $stranger = User::factory()->create();

        Livewire::actingAs($owner)
            ->test(Agenda::class)
            ->call('openCreateForm')
            ->set('formSpaceId', $space->id)
            ->set('formSubject', 'Mathematik');

        // Not assertDontSee('Mathematik') — the form's own "z. B. Mathematik"
        // placeholder is always in the DOM and would make that a false negative.
        $component = Livewire::actingAs($stranger)->test(Agenda::class);
        $this->assertTrue($component->get('draftLines')->isEmpty());
    }

    public function test_a_draft_older_than_the_ttl_is_ignored(): void
    {
        $owner = User::factory()->create(['name' => 'Lisa']);
        $member = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->withMembers($member)->create();

        AgendaDraft::create([
            'user_id' => $owner->id,
            'agenda_space_id' => $space->id,
            'type' => 'homework',
            'subject' => 'Mathematik',
        ]);

        // Backdate it past the TTL — a plain update() bypasses Eloquent's
        // save-time "always stamp updated_at with now()" behaviour.
        AgendaDraft::where('user_id', $owner->id)->update([
            'updated_at' => Carbon::now()->subSeconds(AgendaDraft::TTL_SECONDS + 5),
        ]);

        Livewire::actingAs($member)->test(Agenda::class)->assertDontSee('Lisa');
    }

    // ── Privacy opt-out ───────────────────────────────────────────────

    public function test_opting_out_of_presence_suppresses_the_draft_too(): void
    {
        $owner = User::factory()->create(['show_presence' => false]);
        $space = AgendaSpace::factory()->for($owner, 'owner')->create();

        Livewire::actingAs($owner)
            ->test(Agenda::class)
            ->call('openCreateForm')
            ->set('formSpaceId', $space->id);

        $this->assertDatabaseMissing('agenda_drafts', ['user_id' => $owner->id]);
    }

    // ── Authorization boundary ────────────────────────────────────────

    public function test_a_space_id_for_a_foreign_class_is_rejected(): void
    {
        $user = User::factory()->create();
        $foreignSpace = AgendaSpace::factory()->create();

        Livewire::actingAs($user)
            ->test(Agenda::class)
            ->call('openCreateForm')
            ->set('formSpaceId', $foreignSpace->id);

        $this->assertDatabaseMissing('agenda_drafts', ['user_id' => $user->id]);
    }

    public function test_deleting_the_referenced_entry_removes_the_draft(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->withMembers($member)->create();
        $entry = AgendaEntry::factory()->for($owner)->inSpace($space)->create();

        Livewire::actingAs($member)->test(Agenda::class)->call('startEdit', $entry->id);
        $this->assertDatabaseHas('agenda_drafts', ['agenda_entry_id' => $entry->id]);

        $entry->delete();

        $this->assertDatabaseMissing('agenda_drafts', ['agenda_entry_id' => $entry->id]);
    }
}
