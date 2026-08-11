<?php

namespace Tests\Feature;

use App\Livewire\Agenda;
use App\Livewire\JoinAgendaSpace;
use App\Models\AgendaEntry;
use App\Models\AgendaSpace;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AgendaSpacesTest extends TestCase
{
    use RefreshDatabase;

    // ── Creating, joining, leaving ────────────────────────────────────

    public function test_creating_a_space_makes_the_creator_owner_and_member(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Agenda::class)
            ->call('openSpaces')
            ->set('newSpaceName', '  Klasse 4b  ')
            ->call('createSpace')
            ->assertHasNoErrors();

        $space = AgendaSpace::firstOrFail();

        $this->assertSame('Klasse 4b', $space->name);
        $this->assertSame($user->id, $space->owner_id);
        $this->assertTrue($space->hasMember($user), 'The creator must be a member of their own space.');
        $this->assertSame(6, strlen($space->invite_code));
    }

    public function test_a_space_needs_a_name(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Agenda::class)
            ->set('newSpaceName', '   ')
            ->call('createSpace')
            ->assertHasErrors(['newSpaceName' => 'required']);

        $this->assertDatabaseCount('agenda_spaces', 0);
    }

    public function test_joining_by_code_is_case_and_whitespace_insensitive(): void
    {
        $owner = User::factory()->create();
        $joiner = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->create(['invite_code' => 'K7M4XQ']);

        Livewire::actingAs($joiner)
            ->test(Agenda::class)
            ->set('joinCode', ' k7m 4xq ')
            ->call('joinSpace')
            ->assertHasNoErrors();

        $this->assertTrue($space->fresh()->hasMember($joiner));
    }

    public function test_an_unknown_code_is_rejected(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Agenda::class)
            ->set('joinCode', 'ZZZZZZ')
            ->call('joinSpace')
            ->assertHasErrors('joinCode');

        $this->assertDatabaseCount('agenda_space_user', 0);
    }

    public function test_joining_twice_does_not_duplicate_the_membership(): void
    {
        $owner = User::factory()->create();
        $joiner = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->create();

        $component = Livewire::actingAs($joiner)->test(Agenda::class);
        $component->set('joinCode', $space->invite_code)->call('joinSpace');
        $component->set('joinCode', $space->invite_code)->call('joinSpace')->assertHasNoErrors();

        $this->assertDatabaseCount('agenda_space_user', 2); // the owner plus one joiner
    }

    public function test_regenerating_the_code_invalidates_the_old_one(): void
    {
        $owner = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->create();
        $oldCode = $space->invite_code;

        Livewire::actingAs($owner)
            ->test(Agenda::class)
            ->call('regenerateInviteCode', $space->id);

        $this->assertNotSame($oldCode, $space->fresh()->invite_code);
        $this->assertNull(AgendaSpace::findByInviteCode($oldCode));
    }

    public function test_leaving_hands_ownership_to_the_longest_standing_member(): void
    {
        $owner = User::factory()->create();
        $classmate = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->withMembers($classmate)->create();

        Livewire::actingAs($owner)
            ->test(Agenda::class)
            ->call('leaveSpace', $space->id);

        $space->refresh();
        $this->assertSame($classmate->id, $space->owner_id);
        $this->assertFalse($space->hasMember($owner));
    }

    public function test_the_last_member_leaving_deletes_the_space_but_keeps_its_entries(): void
    {
        $owner = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->create();
        $entry = AgendaEntry::factory()->for($owner)->inSpace($space)->create();

        Livewire::actingAs($owner)
            ->test(Agenda::class)
            ->call('leaveSpace', $space->id);

        $this->assertDatabaseMissing('agenda_spaces', ['id' => $space->id]);
        // nullOnDelete: the homework survives, it just becomes private again.
        $this->assertDatabaseHas('agenda_entries', ['id' => $entry->id, 'agenda_space_id' => null]);
    }

    public function test_only_the_owner_can_delete_a_space_for_everyone(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->withMembers($member)->create();

        try {
            Livewire::actingAs($member)->test(Agenda::class)->call('deleteSpace', $space->id);
            $this->fail('A plain member must not be able to delete the space.');
        } catch (ModelNotFoundException) {
            // Not their space to delete.
        }

        $this->assertDatabaseHas('agenda_spaces', ['id' => $space->id]);

        Livewire::actingAs($owner)->test(Agenda::class)->call('deleteSpace', $space->id);
        $this->assertDatabaseMissing('agenda_spaces', ['id' => $space->id]);
    }

    public function test_a_stranger_cannot_act_on_a_space_they_are_not_in(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->create();

        foreach (['leaveSpace', 'regenerateInviteCode', 'deleteSpace'] as $action) {
            try {
                Livewire::actingAs($stranger)->test(Agenda::class)->call($action, $space->id);
                $this->fail("A non-member must not be able to call {$action}.");
            } catch (ModelNotFoundException) {
                // Invisible, exactly like a foreign entry.
            }
        }

        $this->assertDatabaseHas('agenda_spaces', ['id' => $space->id, 'owner_id' => $owner->id]);
    }

    // ── Invite link ───────────────────────────────────────────────────

    public function test_the_invite_link_page_joins_only_on_the_button_press(): void
    {
        $owner = User::factory()->create();
        $joiner = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->create();

        // Merely opening the link must not mutate anything — a GET is fetched by
        // link previewers long before a human clicks it.
        $this->actingAs($joiner)->get(route('agenda.join', ['code' => $space->invite_code]))
            ->assertOk()
            ->assertSee($space->name);
        $this->assertFalse($space->fresh()->hasMember($joiner));

        Livewire::actingAs($joiner)
            ->test(JoinAgendaSpace::class, ['code' => $space->invite_code])
            ->call('join')
            ->assertRedirect(route('agenda'));

        $this->assertTrue($space->fresh()->hasMember($joiner));
    }

    public function test_the_invite_link_page_handles_an_unknown_code(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(JoinAgendaSpace::class, ['code' => 'ZZZZZZ'])
            ->assertSet('space', null)
            ->assertSee('Diese Einladung gilt nicht mehr');
    }

    public function test_the_invite_link_recognises_an_existing_member(): void
    {
        $owner = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->create();

        Livewire::actingAs($owner)
            ->test(JoinAgendaSpace::class, ['code' => $space->invite_code])
            ->assertSet('alreadyMember', true)
            ->assertSee('Du bist schon dabei');
    }

    public function test_the_invite_link_requires_login_and_returns_after_it(): void
    {
        $owner = User::factory()->create();
        $space = AgendaSpace::factory()->for($owner, 'owner')->create();
        $url = route('agenda.join', ['code' => $space->invite_code]);

        $this->get($url)->assertRedirect(route('login'));

        // Registration — not just login — has to hand a brand-new classmate back
        // to the invite they followed.
        $this->post('/register', [
            'name' => 'Neue Mitschülerin',
            'email' => 'neu@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect($url);
    }
}
