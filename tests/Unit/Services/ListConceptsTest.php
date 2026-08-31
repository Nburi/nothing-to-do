<?php

namespace Tests\Unit\Services;

use App\Models\Task;
use App\Models\User;
use App\Services\ListConcepts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListConceptsTest extends TestCase
{
    use RefreshDatabase;

    // ── for() / isValid() ───────────────────────────────────────────────

    public function test_a_never_customised_user_defaults_to_three_things(): void
    {
        $user = User::factory()->create();

        $this->assertSame('three_things', ListConcepts::for($user));
    }

    public function test_for_self_heals_a_stored_value_that_is_not_currently_available(): void
    {
        // 'eisenhower' is a real catalog key, but not available yet — a
        // stray stored value must never render nothing.
        $user = User::factory()->create(['list_concept' => 'eisenhower']);

        $this->assertSame('three_things', ListConcepts::for($user));
    }

    public function test_for_returns_simple_once_it_is_available(): void
    {
        $user = User::factory()->create(['list_concept' => 'simple']);

        $this->assertSame('simple', ListConcepts::for($user));
    }

    public function test_for_self_heals_a_stored_value_that_is_not_a_real_key_at_all(): void
    {
        $user = User::factory()->create(['list_concept' => 'not-a-real-concept']);

        $this->assertSame('three_things', ListConcepts::for($user));
    }

    public function test_three_things_is_valid(): void
    {
        $this->assertTrue(ListConcepts::isValid('three_things'));
    }

    public function test_simple_is_valid(): void
    {
        $this->assertTrue(ListConcepts::isValid('simple'));
    }

    public function test_an_unavailable_catalog_key_is_not_valid(): void
    {
        $this->assertFalse(ListConcepts::isValid('eisenhower'));
        $this->assertFalse(ListConcepts::isValid('kanban'));
    }

    public function test_an_unknown_key_is_not_valid(): void
    {
        $this->assertFalse(ListConcepts::isValid('not-a-real-concept'));
    }

    // ── rowsFor() ────────────────────────────────────────────────────────

    public function test_rows_for_lists_every_catalog_entry_with_availability_and_current_flags(): void
    {
        $user = User::factory()->create();

        $rows = collect(ListConcepts::rowsFor($user))->keyBy('key');

        $this->assertSame(count(ListConcepts::CATALOG), $rows->count());
        $this->assertTrue($rows['three_things']['available']);
        $this->assertTrue($rows['three_things']['current']);
        $this->assertTrue($rows['simple']['available']);
        $this->assertFalse($rows['simple']['current']);
        $this->assertFalse($rows['eisenhower']['available']);
        $this->assertFalse($rows['kanban']['available']);
    }

    public function test_rows_for_reflects_a_self_healed_current_choice_not_the_raw_stored_value(): void
    {
        $user = User::factory()->create(['list_concept' => 'kanban']);

        $rows = collect(ListConcepts::rowsFor($user))->keyBy('key');

        $this->assertTrue($rows['three_things']['current']);
        $this->assertFalse($rows['kanban']['current']);
    }

    // ── defaultCaptureList() ─────────────────────────────────────────────

    public function test_default_capture_list_is_inbox_for_three_things(): void
    {
        $user = User::factory()->create();

        $this->assertSame('inbox', ListConcepts::defaultCaptureList($user));
    }

    public function test_default_capture_list_falls_back_to_inbox_for_an_unreachable_concept_value(): void
    {
        // 'eisenhower' isn't selectable yet, so for() self-heals it to
        // 'three_things' before defaultCaptureList() ever sees it.
        $user = User::factory()->create(['list_concept' => 'eisenhower']);

        $this->assertSame('inbox', ListConcepts::defaultCaptureList($user));
    }

    public function test_default_capture_list_is_tasks_for_simple(): void
    {
        $user = User::factory()->create(['list_concept' => 'simple']);

        $this->assertSame('tasks', ListConcepts::defaultCaptureList($user));
    }

    // ── previewTasksFor() ────────────────────────────────────────────────

    public function test_preview_tasks_for_returns_the_users_own_active_board_tasks(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Task::factory()->for($user)->create(['title' => 'Mine', 'list' => 'todos']);
        Task::factory()->for($other)->create(['title' => 'Not mine', 'list' => 'todos']);

        $preview = ListConcepts::previewTasksFor($user);

        $this->assertCount(1, $preview);
        $this->assertSame('Mine', $preview->first()->title);
    }

    public function test_preview_tasks_for_excludes_completed_tasks(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user)->create(['list' => 'todos', 'is_completed' => true, 'completed_at' => now()]);

        $this->assertCount(0, ListConcepts::previewTasksFor($user));
    }

    public function test_preview_tasks_for_respects_the_limit(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user)->count(10)->create(['list' => 'todos']);

        $this->assertCount(3, ListConcepts::previewTasksFor($user, 3));
    }
}
