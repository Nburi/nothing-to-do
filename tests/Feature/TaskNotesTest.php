<?php

namespace Tests\Feature;

use App\Livewire\TaskBoard;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_edit_populates_the_notes_buffer(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $task = Task::factory()->for($user)->todos()->create(['notes' => 'Vorhandene Notiz']);

        Livewire::test(TaskBoard::class)
            ->call('startEdit', $task->id)
            ->assertSet('editNotes', 'Vorhandene Notiz');
    }

    public function test_saving_the_edit_sheet_persists_trimmed_notes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $task = Task::factory()->for($user)->todos()->create(['notes' => null]);

        Livewire::test(TaskBoard::class)
            ->call('startEdit', $task->id)
            ->set('editTitle', $task->title)
            ->set('editNotes', '  **Wichtig**: Rückruf nicht vergessen  ')
            ->call('saveEdit');

        $this->assertSame('**Wichtig**: Rückruf nicht vergessen', $task->fresh()->notes);
    }

    public function test_clearing_the_notes_field_stores_null(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $task = Task::factory()->for($user)->todos()->create(['notes' => 'Alte Notiz']);

        Livewire::test(TaskBoard::class)
            ->call('startEdit', $task->id)
            ->set('editTitle', $task->title)
            ->set('editNotes', '   ')
            ->call('saveEdit');

        $this->assertNull($task->fresh()->notes);
    }

    public function test_notes_over_the_length_limit_are_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $task = Task::factory()->for($user)->todos()->create();

        Livewire::test(TaskBoard::class)
            ->call('startEdit', $task->id)
            ->set('editTitle', $task->title)
            ->set('editNotes', str_repeat('a', 5001))
            ->call('saveEdit')
            ->assertHasErrors(['editNotes']);

        $this->assertNull($task->fresh()->notes);
    }

    public function test_edit_notes_html_renders_bold_italic_underline_and_task_list(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $task = Task::factory()->for($user)->todos()->create();

        $component = Livewire::test(TaskBoard::class)
            ->call('startEdit', $task->id)
            ->set('editNotes', "**fett** *kursiv* ++unterstrichen++\n- [ ] offene Aufgabe\n- [x] erledigte Aufgabe");

        $html = $component->instance()->editNotesHtml();

        $this->assertStringContainsString('<strong>fett</strong>', $html);
        $this->assertStringContainsString('<em>kursiv</em>', $html);
        $this->assertStringContainsString('<u>unterstrichen</u>', $html);
        $this->assertStringContainsString('type="checkbox"', $html);
    }

    public function test_edit_notes_html_strips_raw_html_for_safety(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $task = Task::factory()->for($user)->todos()->create();

        $component = Livewire::test(TaskBoard::class)
            ->call('startEdit', $task->id)
            ->set('editNotes', '<script>alert(1)</script>Text');

        $html = $component->instance()->editNotesHtml();

        $this->assertStringNotContainsString('<script>', $html);
    }
}
