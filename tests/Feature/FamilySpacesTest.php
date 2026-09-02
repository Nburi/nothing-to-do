<?php

namespace Tests\Feature;

use App\Livewire\FamilyList;
use App\Livewire\JoinFamilySpace;
use App\Livewire\Support\FamilyColors;
use App\Models\FamilySpace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FamilySpacesTest extends TestCase
{
    use RefreshDatabase;

    // ── Creating, joining, leaving ────────────────────────────────────

    public function test_creating_a_space_makes_the_creator_owner_and_member_with_a_color(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(FamilyList::class)
            ->call('openFamilySpaces')
            ->set('newFamilySpaceName', '  Familie Meier  ')
            ->call('createFamilySpace')
            ->assertHasNoErrors();

        $space = FamilySpace::firstOrFail();

        $this->assertSame('Familie Meier', $space->name);
        $this->assertSame($user->id, $space->owner_id);
        $this->assertTrue($space->hasMember($user), 'The creator must be a member of their own space.');
        $this->assertContains($space->colorFor($user), FamilyColors::KEYS);
        $this->assertSame(6, strlen($space->invite_code));
    }

    public function test_creating_a_space_switches_the_board_to_it_immediately(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(FamilyList::class)
            ->set('newFamilySpaceName', 'Familie Meier')
            ->call('createFamilySpace');

        $space = FamilySpace::firstOrFail();
        $this->assertSame($space->id, $component->get('activeSpaceId'), 'Creating a family must not leave the board on the empty state.');
    }

    public function test_joining_a_space_switches_the_board_to_it_immediately(): void
    {
        $owner = User::factory()->create();
        $joiner = User::factory()->create();
        $space = FamilySpace::factory()->for($owner, 'owner')->create();

        $component = Livewire::actingAs($joiner)
            ->test(FamilyList::class)
            ->set('familyJoinCode', $space->invite_code)
            ->call('joinFamilySpace');

        $this->assertSame($space->id, $component->get('activeSpaceId'));
    }

    public function test_a_space_needs_a_name(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(FamilyList::class)
            ->set('newFamilySpaceName', '   ')
            ->call('createFamilySpace')
            ->assertHasErrors(['newFamilySpaceName' => 'required']);

        $this->assertDatabaseCount('family_spaces', 0);
    }

    public function test_joining_by_code_assigns_a_color_and_is_case_insensitive(): void
    {
        $owner = User::factory()->create();
        $joiner = User::factory()->create();
        $space = FamilySpace::factory()->for($owner, 'owner')->create(['invite_code' => 'K7M4XQ']);

        Livewire::actingAs($joiner)
            ->test(FamilyList::class)
            ->set('familyJoinCode', ' k7m 4xq ')
            ->call('joinFamilySpace')
            ->assertHasNoErrors();

        $space->refresh();
        $this->assertTrue($space->hasMember($joiner));
        $this->assertNotNull($space->colorFor($joiner));
        $this->assertNotSame($space->colorFor($owner), $space->colorFor($joiner), 'Two members should not default to the same color while unused ones remain.');
    }

    public function test_an_unknown_code_is_rejected(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(FamilyList::class)
            ->set('familyJoinCode', 'ZZZZZZ')
            ->call('joinFamilySpace')
            ->assertHasErrors('familyJoinCode');

        $this->assertDatabaseCount('family_space_user', 0);
    }

    public function test_joining_twice_does_not_duplicate_the_membership(): void
    {
        $owner = User::factory()->create();
        $joiner = User::factory()->create();
        $space = FamilySpace::factory()->for($owner, 'owner')->create();

        $component = Livewire::actingAs($joiner)->test(FamilyList::class);
        $component->set('familyJoinCode', $space->invite_code)->call('joinFamilySpace');
        $component->set('familyJoinCode', $space->invite_code)->call('joinFamilySpace')->assertHasNoErrors();

        $this->assertSame(1, $space->members()->where('user_id', $joiner->id)->count());
    }

    public function test_join_invite_link_page_joins_on_button_press_not_on_mount(): void
    {
        $owner = User::factory()->create();
        $joiner = User::factory()->create();
        $space = FamilySpace::factory()->for($owner, 'owner')->create();

        Livewire::actingAs($joiner)->test(JoinFamilySpace::class, ['code' => $space->invite_code]);
        $this->assertFalse($space->fresh()->hasMember($joiner), 'Visiting the link must not join by itself.');

        Livewire::actingAs($joiner)
            ->test(JoinFamilySpace::class, ['code' => $space->invite_code])
            ->call('join')
            ->assertRedirect(route('family'));

        $this->assertTrue($space->fresh()->hasMember($joiner));
    }

    public function test_leaving_hands_ownership_to_the_next_member_and_never_touches_tasks(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $space = FamilySpace::factory()->for($owner, 'owner')->create();
        $space->members()->attach($member->id, ['color' => 'amber']);

        $task = \App\Models\FamilyTask::factory()->create([
            'family_space_id' => $space->id,
            'created_by' => $owner->id,
        ]);

        Livewire::actingAs($owner)
            ->test(FamilyList::class)
            ->call('leaveFamilySpace', $space->id);

        $space->refresh();
        $this->assertSame($member->id, $space->owner_id);
        $this->assertFalse($space->hasMember($owner));
        $this->assertDatabaseHas('family_tasks', ['id' => $task->id, 'created_by' => $owner->id]);
    }

    public function test_the_last_member_leaving_deletes_the_space(): void
    {
        $owner = User::factory()->create();
        $space = FamilySpace::factory()->for($owner, 'owner')->create();

        Livewire::actingAs($owner)
            ->test(FamilyList::class)
            ->call('leaveFamilySpace', $space->id);

        $this->assertDatabaseMissing('family_spaces', ['id' => $space->id]);
    }

    public function test_only_the_owner_may_delete_the_space_for_everyone(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $space = FamilySpace::factory()->for($owner, 'owner')->create();
        $space->members()->attach($member->id, ['color' => 'amber']);

        // ownedFamilySpace() resolves through the user's OWN ownedFamilySpaces()
        // relation, so a non-owner's id simply matches no row — the same
        // findOrFail()-throws shape every id lookup in this app uses, not a
        // silent no-op.
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::actingAs($member)
            ->test(FamilyList::class)
            ->call('deleteFamilySpace', $space->id);
    }

    public function test_setting_an_invalid_color_key_is_ignored(): void
    {
        $user = User::factory()->create();
        $space = FamilySpace::factory()->for($user, 'owner')->create();
        $before = $space->colorFor($user);

        Livewire::actingAs($user)
            ->test(FamilyList::class)
            ->call('setFamilyColor', $space->id, 'not-a-real-color');

        $this->assertSame($before, $space->fresh()->colorFor($user));
    }

    public function test_a_stranger_cannot_manage_a_family_they_are_not_in(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $space = FamilySpace::factory()->for($owner, 'owner')->create();

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::actingAs($stranger)
            ->test(FamilyList::class)
            ->call('leaveFamilySpace', $space->id);
    }
}
