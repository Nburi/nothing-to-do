<?php

namespace Tests\Feature\Api;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_task_in_the_inbox_by_default(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/tasks', ['title' => '  Vokabeln lernen  ']);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Vokabeln lernen')
            ->assertJsonPath('data.list', 'inbox');

        $this->assertDatabaseHas('tasks', ['user_id' => $user->id, 'title' => 'Vokabeln lernen', 'list' => 'inbox']);
    }

    public function test_a_blank_title_is_rejected(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/tasks', ['title' => '   '])->assertUnprocessable();
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_index_excludes_completed_tasks_by_default(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Task::factory()->for($user)->todos()->create(['title' => 'BOARD-VISIBLE']);
        Task::factory()->for($user)->completed()->todos()->create(['title' => 'DONE']);

        $response = $this->getJson('/api/tasks?list=todos');

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title');
        $this->assertTrue($titles->contains('BOARD-VISIBLE'));
        $this->assertFalse($titles->contains('DONE'));
    }

    public function test_it_toggles_completion_via_patch(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->todos()->create();
        Sanctum::actingAs($user);

        $this->patchJson("/api/tasks/{$task->id}", ['is_completed' => true])
            ->assertOk()
            ->assertJsonPath('data.is_completed', true);

        $task->refresh();
        $this->assertTrue($task->is_completed);
        $this->assertNotNull($task->completed_at);
    }

    public function test_it_sets_today_focus_only_for_todos_and_tasks(): void
    {
        $user = User::factory()->create();
        $inboxTask = Task::factory()->for($user)->inbox()->create();
        $todoTask = Task::factory()->for($user)->todos()->create();
        Sanctum::actingAs($user);

        $this->patchJson("/api/tasks/{$inboxTask->id}", ['is_today' => true]);
        $this->assertFalse($inboxTask->fresh()->is_today);

        $this->patchJson("/api/tasks/{$todoTask->id}", ['is_today' => true]);
        $this->assertTrue($todoTask->fresh()->is_today);
    }

    public function test_it_deletes_a_task(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $this->deleteJson("/api/tasks/{$task->id}")->assertNoContent();
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_it_creates_a_task_with_notes(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/tasks', ['title' => 'Vokabeln lernen', 'notes' => '  Kapitel 1-3  ']);

        $response->assertCreated()->assertJsonPath('data.notes', 'Kapitel 1-3');
        $this->assertDatabaseHas('tasks', ['title' => 'Vokabeln lernen', 'notes' => 'Kapitel 1-3']);
    }

    public function test_it_updates_and_clears_notes_via_patch(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->todos()->create(['notes' => 'Alte Notiz']);
        Sanctum::actingAs($user);

        $this->patchJson("/api/tasks/{$task->id}", ['notes' => 'Neue Notiz'])
            ->assertOk()
            ->assertJsonPath('data.notes', 'Neue Notiz');
        $this->assertSame('Neue Notiz', $task->fresh()->notes);

        $this->patchJson("/api/tasks/{$task->id}", ['notes' => null])->assertOk();
        $this->assertNull($task->fresh()->notes);
    }

    public function test_reorder_persists_list_today_and_order(): void
    {
        $user = User::factory()->create();
        $a = Task::factory()->for($user)->todos()->create();
        $b = Task::factory()->for($user)->todos()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/tasks/reorder', [
            'list' => 'todos',
            'today' => true,
            'ids' => [$b->id, $a->id],
        ])->assertOk();

        $this->assertTrue($b->fresh()->is_today);
        $this->assertSame(0, $b->fresh()->sort_order);
        $this->assertSame(1, $a->fresh()->sort_order);
    }
}
