<?php

namespace Tests\Feature;

use App\Livewire\FamilyList;
use App\Models\FamilySpace;
use App\Models\FamilyTask;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FamilyListTest extends TestCase
{
    use RefreshDatabase;

    private function familyOf(User ...$members): FamilySpace
    {
        $space = FamilySpace::factory()->for($members[0], 'owner')->create();

        foreach (array_slice($members, 1) as $member) {
            $space->members()->attach($member->id, ['color' => $space->nextAvailableColor()]);
        }

        return $space;
    }

    public function test_adding_a_task_credits_the_creator(): void
    {
        $user = User::factory()->create();
        $space = $this->familyOf($user);

        Livewire::actingAs($user)
            ->test(FamilyList::class)
            ->set('newTaskTitle', '  Müll rausbringen  ')
            ->call('addTask');

        $task = FamilyTask::firstOrFail();
        $this->assertSame('Müll rausbringen', $task->title);
        $this->assertSame($space->id, $task->family_space_id);
        $this->assertSame($user->id, $task->created_by);
        $this->assertNull($task->assigned_to);
    }

    public function test_an_empty_title_is_not_added(): void
    {
        $user = User::factory()->create();
        $this->familyOf($user);

        Livewire::actingAs($user)
            ->test(FamilyList::class)
            ->set('newTaskTitle', '   ')
            ->call('addTask');

        $this->assertDatabaseCount('family_tasks', 0);
    }

    // ── The tap cycle: claim → complete → reopen ───────────────────────

    public function test_tapping_an_unclaimed_card_claims_it_for_the_tapper(): void
    {
        $user = User::factory()->create();
        $space = $this->familyOf($user);
        $task = FamilyTask::factory()->create(['family_space_id' => $space->id]);

        Livewire::actingAs($user)->test(FamilyList::class)->call('claimTask', $task->id);

        $task->refresh();
        $this->assertSame($user->id, $task->assigned_to);
        $this->assertFalse($task->is_completed);
    }

    public function test_claiming_an_already_claimed_card_is_a_no_op_never_a_steal(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $space = $this->familyOf($first, $second);
        $task = FamilyTask::factory()->create(['family_space_id' => $space->id, 'assigned_to' => $first->id]);

        Livewire::actingAs($second)->test(FamilyList::class)->call('claimTask', $task->id);

        $task->refresh();
        $this->assertSame($first->id, $task->assigned_to, 'A second claim attempt must never overwrite the first.');
        $this->assertFalse($task->is_completed, 'A blocked claim must not silently complete the card instead.');
    }

    public function test_tapping_a_claimed_card_completes_it_crediting_whoever_tapped(): void
    {
        $assignee = User::factory()->create();
        $finisher = User::factory()->create();
        $space = $this->familyOf($assignee, $finisher);
        $task = FamilyTask::factory()->create(['family_space_id' => $space->id, 'assigned_to' => $assignee->id]);

        // Anyone may complete it, not just the assignee — a family task has
        // one shared completion (see FamilyTask::completeBy()'s own doc).
        Livewire::actingAs($finisher)->test(FamilyList::class)->call('completeTask', $task->id);

        $task->refresh();
        $this->assertTrue($task->is_completed);
        $this->assertSame($finisher->id, $task->completed_by);
        $this->assertNotNull($task->completed_at);
    }

    public function test_completing_an_unclaimed_card_is_a_no_op(): void
    {
        $user = User::factory()->create();
        $space = $this->familyOf($user);
        $task = FamilyTask::factory()->create(['family_space_id' => $space->id]);

        Livewire::actingAs($user)->test(FamilyList::class)->call('completeTask', $task->id);

        $this->assertFalse($task->fresh()->is_completed);
    }

    public function test_tapping_a_done_card_reopens_it_keeping_the_assignee(): void
    {
        $user = User::factory()->create();
        $space = $this->familyOf($user);
        $task = FamilyTask::factory()->create([
            'family_space_id' => $space->id,
            'assigned_to' => $user->id,
            'is_completed' => true,
            'completed_by' => $user->id,
            'completed_at' => now(),
        ]);

        Livewire::actingAs($user)->test(FamilyList::class)->call('reopenTask', $task->id);

        $task->refresh();
        $this->assertFalse($task->is_completed);
        $this->assertNull($task->completed_by);
        $this->assertNull($task->completed_at);
        $this->assertSame($user->id, $task->assigned_to, 'Reopening must not un-claim the card.');
    }

    // ── The deliberate assign path ──────────────────────────────────────

    public function test_assign_task_lets_anyone_assign_to_anyone_in_the_family(): void
    {
        $parent = User::factory()->create();
        $kid = User::factory()->create();
        $space = $this->familyOf($parent, $kid);
        $task = FamilyTask::factory()->create(['family_space_id' => $space->id]);

        Livewire::actingAs($parent)->test(FamilyList::class)->call('assignTask', $task->id, $kid->id);

        $this->assertSame($kid->id, $task->fresh()->assigned_to);
    }

    public function test_assign_task_to_null_unclaims_it(): void
    {
        $user = User::factory()->create();
        $space = $this->familyOf($user);
        $task = FamilyTask::factory()->create(['family_space_id' => $space->id, 'assigned_to' => $user->id]);

        Livewire::actingAs($user)->test(FamilyList::class)->call('assignTask', $task->id, null);

        $this->assertNull($task->fresh()->assigned_to);
    }

    public function test_a_task_cannot_be_assigned_to_someone_outside_the_family(): void
    {
        $user = User::factory()->create();
        $outsider = User::factory()->create();
        $space = $this->familyOf($user);
        $task = FamilyTask::factory()->create(['family_space_id' => $space->id]);

        Livewire::actingAs($user)->test(FamilyList::class)->call('assignTask', $task->id, $outsider->id);

        $this->assertNull($task->fresh()->assigned_to);
    }

    // ── Editing, deleting ────────────────────────────────────────────

    public function test_editing_updates_title_and_notes(): void
    {
        $user = User::factory()->create();
        $space = $this->familyOf($user);
        $task = FamilyTask::factory()->create(['family_space_id' => $space->id, 'title' => 'Alt']);

        Livewire::actingAs($user)
            ->test(FamilyList::class)
            ->call('startEditTask', $task->id)
            ->set('editTitle', 'Neu')
            ->set('editNotes', 'Vollmilch, 2 Liter')
            ->call('saveTaskEdit');

        $task->refresh();
        $this->assertSame('Neu', $task->title);
        $this->assertSame('Vollmilch, 2 Liter', $task->notes);
    }

    public function test_an_empty_title_cannot_be_saved(): void
    {
        $user = User::factory()->create();
        $space = $this->familyOf($user);
        $task = FamilyTask::factory()->create(['family_space_id' => $space->id, 'title' => 'Behalten']);

        Livewire::actingAs($user)
            ->test(FamilyList::class)
            ->call('startEditTask', $task->id)
            ->set('editTitle', '   ')
            ->call('saveTaskEdit')
            ->assertHasErrors(['editTitle' => 'required']);

        $this->assertSame('Behalten', $task->fresh()->title);
    }

    public function test_deleting_removes_the_task(): void
    {
        $user = User::factory()->create();
        $space = $this->familyOf($user);
        $task = FamilyTask::factory()->create(['family_space_id' => $space->id]);

        Livewire::actingAs($user)->test(FamilyList::class)->call('deleteTask', $task->id);

        $this->assertDatabaseMissing('family_tasks', ['id' => $task->id]);
    }

    // ── Scoping: never trust a bare id ──────────────────────────────────

    public function test_a_stranger_cannot_act_on_a_task_outside_their_family(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $space = $this->familyOf($owner);
        $task = FamilyTask::factory()->create(['family_space_id' => $space->id]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($stranger)->test(FamilyList::class)->call('claimTask', $task->id);
    }

    public function test_a_task_from_another_family_is_invisible_even_while_that_family_is_active(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $spaceA = $this->familyOf($userA);
        $spaceB = $this->familyOf($userB);
        $taskInB = FamilyTask::factory()->create(['family_space_id' => $spaceB->id]);

        // userA belongs only to spaceA, but is also a member of spaceB below —
        // membership in SOME family must not be enough; it must be THIS task's family.
        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($userA)->test(FamilyList::class)->call('claimTask', $taskInB->id);
    }

    public function test_switching_to_a_family_the_user_does_not_belong_to_is_rejected(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $this->familyOf($userA);
        $spaceB = $this->familyOf($userB);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($userA)->test(FamilyList::class)->call('switchSpace', $spaceB->id);
    }
}
