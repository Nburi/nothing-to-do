<?php

namespace Tests\Feature\Api;

use App\Models\AgendaEntry;
use App\Models\AgendaSpace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgendaApiTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'homework',
            'subject' => '  Mathematik ',
            'title' => 'Aufgaben 4–6',
            'date' => now()->addDays(2)->toDateString(),
        ], $overrides);
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/agenda-entries')->assertUnauthorized();
        $this->getJson('/api/agenda-spaces')->assertUnauthorized();
    }

    public function test_it_creates_a_private_entry(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/agenda-entries', $this->payload(['notes' => ' Seite 12 ', 'duration_minutes' => 30]))
            ->assertCreated()
            ->assertJsonPath('data.subject', 'Mathematik')
            ->assertJsonPath('data.notes', 'Seite 12')
            ->assertJsonPath('data.duration_minutes', 30)
            ->assertJsonPath('data.agenda_space_id', null)
            ->assertJsonPath('data.is_shared', false)
            ->assertJsonPath('data.is_done', false)
            ->assertJsonPath('data.is_mine', true);

        $this->assertDatabaseHas('agenda_entries', ['user_id' => $user->id, 'title' => 'Aufgaben 4–6']);
    }

    public function test_validation_rejects_bad_input_and_an_exam_never_keeps_a_duration(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/agenda-entries', $this->payload(['type' => 'quiz']))->assertUnprocessable();
        $this->postJson('/api/agenda-entries', $this->payload(['subject' => '']))->assertUnprocessable();
        $this->postJson('/api/agenda-entries', $this->payload(['date' => 'bald']))->assertUnprocessable();

        $this->postJson('/api/agenda-entries', $this->payload(['type' => 'exam', 'duration_minutes' => 30]))
            ->assertCreated()
            ->assertJsonPath('data.duration_minutes', null);
    }

    public function test_an_entry_can_be_filed_into_my_class_but_never_into_a_foreign_one(): void
    {
        $user = User::factory()->create();
        $mine = AgendaSpace::factory()->withMembers($user)->create(['name' => 'Klasse 4b']);
        $foreign = AgendaSpace::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/agenda-entries', $this->payload(['agenda_space_id' => $mine->id]))
            ->assertCreated()
            ->assertJsonPath('data.agenda_space_id', $mine->id)
            ->assertJsonPath('data.agenda_space_name', 'Klasse 4b')
            ->assertJsonPath('data.is_shared', true);

        $this->postJson('/api/agenda-entries', $this->payload(['agenda_space_id' => $foreign->id]))->assertUnprocessable();
    }

    public function test_visibility_is_own_private_plus_my_classes_and_nothing_else(): void
    {
        $user = User::factory()->create();
        $classmate = User::factory()->create();
        $stranger = User::factory()->create();
        $space = AgendaSpace::factory()->withMembers($user, $classmate)->create();
        $otherSpace = AgendaSpace::factory()->for($stranger, 'owner')->create();

        $own = AgendaEntry::factory()->for($user)->create(['agenda_space_id' => null]);
        $classEntry = AgendaEntry::factory()->for($classmate)->create(['agenda_space_id' => $space->id]);
        $classmatePrivate = AgendaEntry::factory()->for($classmate)->create(['agenda_space_id' => null]);
        $foreignClass = AgendaEntry::factory()->for($stranger)->create(['agenda_space_id' => $otherSpace->id]);
        Sanctum::actingAs($user);

        $ids = collect($this->getJson('/api/agenda-entries')->assertOk()->json('data'))->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$own->id, $classEntry->id], $ids);
        $this->getJson("/api/agenda-entries/{$classmatePrivate->id}")->assertNotFound();
        $this->getJson("/api/agenda-entries/{$foreignClass->id}")->assertNotFound();
        $this->getJson("/api/agenda-entries/{$classEntry->id}")->assertOk()->assertJsonPath('data.is_mine', false);
    }

    public function test_filters_and_status(): void
    {
        $user = User::factory()->create();
        $space = AgendaSpace::factory()->withMembers($user)->create();
        $hw = AgendaEntry::factory()->for($user)->homework()->create();
        $exam = AgendaEntry::factory()->for($user)->create(['type' => 'exam']);
        $shared = AgendaEntry::factory()->for($user)->homework()->create(['agenda_space_id' => $space->id]);
        $done = AgendaEntry::factory()->for($user)->homework()->create();
        $done->toggleDoneFor($user);
        Sanctum::actingAs($user);

        $ids = fn (string $query) => collect($this->getJson('/api/agenda-entries'.$query)->assertOk()->json('data'))->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$hw->id, $exam->id, $shared->id], $ids(''));
        $this->assertSame([$done->id], $ids('?status=done'));
        $this->assertCount(4, $ids('?status=all'));
        $this->assertEqualsCanonicalizing([$hw->id, $shared->id], $ids('?type=homework'));
        $this->assertSame([$shared->id], $ids('?agenda_space_id='.$space->id));
        $this->assertEqualsCanonicalizing([$hw->id, $exam->id], $ids('?private=1'));

        $this->getJson('/api/agenda-entries?agenda_space_id='.AgendaSpace::factory()->create()->id)->assertUnprocessable();
    }

    public function test_done_is_per_person_and_toggles_through_patch(): void
    {
        $user = User::factory()->create();
        $classmate = User::factory()->create();
        $space = AgendaSpace::factory()->withMembers($user, $classmate)->create();
        $entry = AgendaEntry::factory()->for($classmate)->homework()->create(['agenda_space_id' => $space->id]);

        Sanctum::actingAs($user);
        $this->patchJson("/api/agenda-entries/{$entry->id}", ['is_done' => true])
            ->assertOk()
            ->assertJsonPath('data.is_done', true)
            ->assertJsonPath('data.completed_count', 1);

        // Idempotent: setting the same state again doesn't flip it back.
        $this->patchJson("/api/agenda-entries/{$entry->id}", ['is_done' => true])->assertJsonPath('data.is_done', true);

        // The classmate's own list is untouched.
        $this->assertFalse($entry->isDoneFor($classmate));

        $this->patchJson("/api/agenda-entries/{$entry->id}", ['is_done' => false])
            ->assertOk()
            ->assertJsonPath('data.is_done', false)
            ->assertJsonPath('data.completed_count', 0);
    }

    public function test_any_member_may_edit_and_delete_a_class_entry_but_a_stranger_may_not(): void
    {
        $author = User::factory()->create();
        $member = User::factory()->create();
        $stranger = User::factory()->create();
        $space = AgendaSpace::factory()->withMembers($author, $member)->create();
        $entry = AgendaEntry::factory()->for($author)->homework()->create(['agenda_space_id' => $space->id, 'title' => 'Alt']);

        Sanctum::actingAs($stranger);
        $this->patchJson("/api/agenda-entries/{$entry->id}", ['title' => 'Gehackt'])->assertNotFound();
        $this->deleteJson("/api/agenda-entries/{$entry->id}")->assertNotFound();

        Sanctum::actingAs($member);
        $this->patchJson("/api/agenda-entries/{$entry->id}", ['title' => ' Neu '])->assertOk()->assertJsonPath('data.title', 'Neu');
        $this->deleteJson("/api/agenda-entries/{$entry->id}")->assertNoContent();
        $this->assertDatabaseMissing('agenda_entries', ['id' => $entry->id]);
    }

    public function test_an_entry_cannot_be_moved_into_a_foreign_class(): void
    {
        $user = User::factory()->create();
        $entry = AgendaEntry::factory()->for($user)->create(['agenda_space_id' => null]);
        $foreign = AgendaSpace::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson("/api/agenda-entries/{$entry->id}", ['agenda_space_id' => $foreign->id])->assertUnprocessable();
        $this->assertNull($entry->fresh()->agenda_space_id);
    }

    public function test_switching_a_homework_to_an_exam_drops_its_duration(): void
    {
        $user = User::factory()->create();
        $entry = AgendaEntry::factory()->for($user)->homework()->create(['duration_minutes' => 45]);
        Sanctum::actingAs($user);

        $this->patchJson("/api/agenda-entries/{$entry->id}", ['type' => 'exam'])
            ->assertOk()
            ->assertJsonPath('data.duration_minutes', null);
    }

    public function test_spaces_lists_only_my_classes_and_hides_the_invite_code(): void
    {
        $user = User::factory()->create();
        $mine = AgendaSpace::factory()->for($user, 'owner')->withMembers(User::factory()->create())->create(['name' => 'Klasse 4b']);
        AgendaSpace::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/agenda-spaces')->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('data.0.members_count', 2)
            ->assertJsonPath('data.0.is_owner', true);
        $this->assertStringNotContainsString($mine->invite_code, $response->getContent());
    }
}
