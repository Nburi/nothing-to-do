<?php

namespace Tests\Feature;

use App\Livewire\TaskBoard;
use App\Models\AgendaEntry;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Drag (desktop) / swipe-up (mobile) a homework preview card into today's
 * focus — see TaskBoard::promoteHomeworkToday() and Task::syncLinkedAgendaEntry().
 */
class HomeworkPromoteToTodayTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function actingUser(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['timezone_offset' => 0], $attributes));
        $this->actingAs($user);

        return $user;
    }

    public function test_promoting_a_homework_entry_creates_a_linked_task_flagged_for_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-19')->setTime(9, 0));
        $user = $this->actingUser();
        $entry = AgendaEntry::factory()->for($user)->homework()->create([
            'subject' => 'Mathematik',
            'title' => 'Seite 12 bis 15 rechnen',
            'date' => '2026-08-21',
            'notes' => 'Taschenrechner mitbringen',
        ]);

        Livewire::test(TaskBoard::class)->call('promoteHomeworkToday', $entry->id, 'tasks');

        $task = Task::forUser($user)->where('agenda_entry_id', $entry->id)->firstOrFail();
        $this->assertSame('Mathematik: Seite 12 bis 15 rechnen', $task->title);
        $this->assertSame('tasks', $task->list);
        $this->assertTrue($task->is_today);
        $this->assertSame('2026-08-19', $task->today_date->toDateString());
        $this->assertSame('2026-08-21', $task->deadline->toDateString());
        $this->assertSame('Taschenrechner mitbringen', $task->notes);
    }

    public function test_a_leading_list_marker_in_the_note_is_escaped_so_it_does_not_render_as_a_bullet(): void
    {
        $user = $this->actingUser();
        $entry = AgendaEntry::factory()->for($user)->homework()->create(['notes' => '- Seite 12 bis 15']);

        Livewire::test(TaskBoard::class)->call('promoteHomeworkToday', $entry->id, 'tasks');

        $task = Task::forUser($user)->where('agenda_entry_id', $entry->id)->firstOrFail();
        $this->assertSame('\\- Seite 12 bis 15', $task->notes);
        $this->assertStringNotContainsString('<li>', Task::renderNotesMarkdown($task->notes));
    }

    public function test_promoting_an_already_promoted_entry_does_not_duplicate_the_task(): void
    {
        $user = $this->actingUser();
        $entry = AgendaEntry::factory()->for($user)->homework()->create();

        $component = Livewire::test(TaskBoard::class);
        $component->call('promoteHomeworkToday', $entry->id, 'tasks');
        $component->call('promoteHomeworkToday', $entry->id, 'todos');

        $this->assertSame(1, Task::forUser($user)->where('agenda_entry_id', $entry->id)->count());
        $this->assertSame('todos', Task::forUser($user)->where('agenda_entry_id', $entry->id)->first()->list);
    }

    public function test_promoting_with_an_invalid_list_falls_back_to_tasks(): void
    {
        $user = $this->actingUser();
        $entry = AgendaEntry::factory()->for($user)->homework()->create();

        // 'today' is the mobile swipe's sentinel value, not a real board list.
        Livewire::test(TaskBoard::class)->call('promoteHomeworkToday', $entry->id, 'today');

        $task = Task::forUser($user)->where('agenda_entry_id', $entry->id)->firstOrFail();
        $this->assertSame('tasks', $task->list);
    }

    public function test_a_foreign_entry_cannot_be_promoted(): void
    {
        $this->actingUser();
        $stranger = AgendaEntry::factory()->for(User::factory())->homework()->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(TaskBoard::class)->call('promoteHomeworkToday', $stranger->id, 'tasks');
    }

    public function test_promoted_homework_entry_ids_reflects_an_active_linked_task(): void
    {
        $user = $this->actingUser();
        $entry = AgendaEntry::factory()->for($user)->homework()->create();

        $component = Livewire::test(TaskBoard::class);
        $this->assertSame([], $component->instance()->promotedHomeworkEntryIds());

        $component->call('promoteHomeworkToday', $entry->id, 'tasks');

        $this->assertSame([$entry->id], $component->instance()->promotedHomeworkEntryIds());
    }

    public function test_completing_a_homework_derived_task_completes_the_linked_agenda_entry(): void
    {
        $user = $this->actingUser();
        $entry = AgendaEntry::factory()->for($user)->homework()->create();
        $task = Task::factory()->for($user)->tasks()->today()->create(['agenda_entry_id' => $entry->id]);

        Livewire::test(TaskBoard::class)->call('toggleComplete', $task->id);

        $this->assertTrue($task->fresh()->is_completed);
        $this->assertTrue($entry->fresh()->isDoneFor($user));
    }

    public function test_uncompleting_a_homework_derived_task_reopens_the_linked_agenda_entry(): void
    {
        $user = $this->actingUser();
        $entry = AgendaEntry::factory()->for($user)->homework()->done()->create();
        $task = Task::factory()->for($user)->tasks()->completed()->create(['agenda_entry_id' => $entry->id]);

        Livewire::test(TaskBoard::class)->call('toggleComplete', $task->id);

        $this->assertFalse($task->fresh()->is_completed);
        $this->assertFalse($entry->fresh()->isDoneFor($user));
    }

    public function test_completing_an_ordinary_task_does_not_touch_agenda(): void
    {
        $user = $this->actingUser();
        $task = Task::factory()->for($user)->tasks()->create();

        // No agenda_entry_id — syncLinkedAgendaEntry() must be a pure no-op.
        Livewire::test(TaskBoard::class)->call('toggleComplete', $task->id);

        $this->assertTrue($task->fresh()->is_completed);
    }

    public function test_marking_the_preview_checkbox_done_also_completes_an_already_promoted_task(): void
    {
        $user = $this->actingUser();
        $entry = AgendaEntry::factory()->for($user)->homework()->create();
        $task = Task::factory()->for($user)->tasks()->today()->create(['agenda_entry_id' => $entry->id]);

        Livewire::test(TaskBoard::class)->call('toggleHomeworkPreviewDone', $entry->id);

        $this->assertTrue($entry->fresh()->isDoneFor($user));
        $this->assertTrue($task->fresh()->is_completed);
    }

    public function test_marking_the_preview_checkbox_undone_reopens_the_linked_completed_task(): void
    {
        $user = $this->actingUser();
        $entry = AgendaEntry::factory()->for($user)->homework()->done()->create();
        $task = Task::factory()->for($user)->tasks()->completed()->create(['agenda_entry_id' => $entry->id]);

        Livewire::test(TaskBoard::class)->call('toggleHomeworkPreviewDone', $entry->id);

        $this->assertFalse($entry->fresh()->isDoneFor($user));
        $this->assertFalse($task->fresh()->is_completed);
    }

    public function test_marking_the_preview_checkbox_done_without_a_linked_task_just_toggles_the_entry(): void
    {
        $user = $this->actingUser();
        $entry = AgendaEntry::factory()->for($user)->homework()->create();

        Livewire::test(TaskBoard::class)->call('toggleHomeworkPreviewDone', $entry->id);

        $this->assertTrue($entry->fresh()->isDoneFor($user));
    }

    public function test_deleting_the_agenda_entry_unlinks_the_task_without_deleting_it(): void
    {
        $user = $this->actingUser();
        $entry = AgendaEntry::factory()->for($user)->homework()->create();
        $task = Task::factory()->for($user)->tasks()->today()->create(['agenda_entry_id' => $entry->id]);

        $entry->delete();

        $this->assertNotNull($task->fresh());
        $this->assertNull($task->fresh()->agenda_entry_id);
    }
}
