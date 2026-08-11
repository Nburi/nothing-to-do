<?php

namespace Tests\Feature;

use App\Livewire\Agenda;
use App\Models\AgendaEntry;
use App\Models\AgendaSpace;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class AgendaSpaceMembersTest extends TestCase
{
    use RefreshDatabase;

    // ── The member view ───────────────────────────────────────────────

    public function test_a_member_can_open_the_member_list(): void
    {
        $owner = User::factory()->create(['name' => 'Lena']);
        $member = User::factory()->create(['name' => 'Jonas']);
        $space = AgendaSpace::factory()->for($owner, 'owner')->withMembers($member)->create();

        Livewire::actingAs($member)
            ->test(Agenda::class)
            ->call('openMembers', $space->id)
            ->assertSet('managingSpaceId', $space->id)
            ->assertSee('Lena')
            ->assertSee('Jonas')
            ->assertSee('Besitzer');
    }

    public function test_a_stranger_cannot_open_the_member_list(): void
    {
        $stranger = User::factory()->create();
        $space = AgendaSpace::factory()->create();

        try {
            Livewire::actingAs($stranger)->test(Agenda::class)->call('openMembers', $space->id);
            $this->fail('A non-member must not be able to see who is in a class.');
        } catch (ModelNotFoundException) {
            // Resolved through the user's own memberships.
            $this->assertFalse($space->hasMember($stranger));
        }
    }

    public function test_online_members_are_listed_first(): void
    {
        $owner = User::factory()->create(['name' => 'Zoe', 'last_seen_at' => Carbon::now()]);
        $offline = User::factory()->create(['name' => 'Anna', 'last_seen_at' => null]);
        $space = AgendaSpace::factory()->for($owner, 'owner')->withMembers($offline)->create();

        $members = Livewire::actingAs($owner)
            ->test(Agenda::class)
            ->call('openMembers', $space->id)
            ->get('managedMembers');

        // Alphabetically Anna wins; "who is here right now" is the question the
        // list exists to answer, so online sorts above it.
        $this->assertSame('Zoe', $members->first()->name);
    }

    public function test_the_list_shows_online_state_and_hides_it_when_opted_out(): void
    {
        $owner = User::factory()->create(['name' => 'Lena', 'last_seen_at' => Carbon::now()]);
        $shy = User::factory()->create([
            'name' => 'Jonas',
            'show_presence' => false,
            'last_seen_at' => Carbon::now(),
        ]);
        $space = AgendaSpace::factory()->for($owner, 'owner')->withMembers($shy)->create();

        $component = Livewire::actingAs($owner)->test(Agenda::class)->call('openMembers', $space->id);

        $component->assertSee('1 gerade online');
        $this->assertFalse($shy->fresh()->isOnline(), 'An opted-out member must never count as online.');
    }

    // ── Removing a member ─────────────────────────────────────────────

    public function test_the_owner_can_remove_a_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->withMembers($member)->create();

        Livewire::actingAs($owner)
            ->test(Agenda::class)
            ->call('openMembers', $space->id)
            ->call('removeMember', $space->id, $member->id);

        $this->assertFalse($space->fresh()->hasMember($member));
        $this->assertTrue($space->fresh()->hasMember($owner));
    }

    public function test_removing_a_member_keeps_the_entries_they_wrote(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->withMembers($member)->create();
        $entry = AgendaEntry::factory()->for($member)->inSpace($space)->create();

        Livewire::actingAs($owner)->test(Agenda::class)->call('removeMember', $space->id, $member->id);

        // Same rule as leaving: what you wrote for the class stays with it.
        $this->assertDatabaseHas('agenda_entries', [
            'id' => $entry->id,
            'agenda_space_id' => $space->id,
        ]);
        // …and the class entry is no longer visible to the person removed.
        Livewire::actingAs($member->fresh())->test(Agenda::class)->assertDontSee($entry->title);
    }

    public function test_a_plain_member_cannot_remove_anyone(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $victim = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->withMembers($member, $victim)->create();

        try {
            Livewire::actingAs($member)->test(Agenda::class)->call('removeMember', $space->id, $victim->id);
            $this->fail('Removing people is owner-only, unlike editing entries.');
        } catch (ModelNotFoundException) {
            // Not their space to administer.
        }

        $this->assertTrue($space->fresh()->hasMember($victim));
    }

    public function test_the_owner_cannot_remove_themselves(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->withMembers($member)->create();

        Livewire::actingAs($owner)->test(Agenda::class)->call('removeMember', $space->id, $owner->id);

        // Leaving goes through "Verlassen", which hands ownership over properly.
        $this->assertTrue($space->fresh()->hasMember($owner));
    }

    public function test_removing_someone_who_is_not_a_member_is_rejected(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($owner)->test(Agenda::class)->call('removeMember', $space->id, $outsider->id);
    }

    // ── Transferring ownership ────────────────────────────────────────

    public function test_the_owner_can_hand_the_class_over(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->withMembers($member)->create();

        Livewire::actingAs($owner)
            ->test(Agenda::class)
            ->call('transferOwnership', $space->id, $member->id);

        $space->refresh();
        $this->assertSame($member->id, $space->owner_id);
        // The old owner stays in the class, just without the admin role.
        $this->assertTrue($space->hasMember($owner));
    }

    public function test_a_plain_member_cannot_seize_ownership(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->withMembers($member)->create();

        try {
            Livewire::actingAs($member)->test(Agenda::class)->call('transferOwnership', $space->id, $member->id);
            $this->fail('Only the owner may hand the class over.');
        } catch (ModelNotFoundException) {
            // Not their space to administer.
        }

        $this->assertSame($owner->id, $space->fresh()->owner_id);
    }

    public function test_ownership_cannot_be_handed_to_a_non_member(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($owner)->test(Agenda::class)->call('transferOwnership', $space->id, $outsider->id);
    }

    // ── The sheet's own state ─────────────────────────────────────────

    public function test_deleting_the_managed_class_returns_to_the_class_list(): void
    {
        $owner = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->create();

        Livewire::actingAs($owner)
            ->test(Agenda::class)
            ->call('openMembers', $space->id)
            ->call('deleteSpace', $space->id)
            // Otherwise the sheet would sit on a member view for a space that
            // no longer exists.
            ->assertSet('managingSpaceId', null);
    }

    public function test_leaving_the_managed_class_returns_to_the_class_list(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->withMembers($member)->create();

        Livewire::actingAs($member)
            ->test(Agenda::class)
            ->call('openMembers', $space->id)
            ->call('leaveSpace', $space->id)
            ->assertSet('managingSpaceId', null);
    }

    public function test_reopening_the_sheet_starts_on_the_class_list(): void
    {
        $owner = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->create();

        Livewire::actingAs($owner)
            ->test(Agenda::class)
            ->call('openMembers', $space->id)
            ->call('closeSpaces')
            ->call('openSpaces')
            ->assertSet('managingSpaceId', null);
    }
}
