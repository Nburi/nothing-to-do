<?php

namespace Tests\Unit\Models;

use App\Models\Task;
use Tests\TestCase;

class TaskTest extends TestCase
{
    public function test_notes_preview_is_null_when_notes_are_empty(): void
    {
        $task = new Task(['notes' => null]);
        $this->assertNull($task->notesPreview());

        $task = new Task(['notes' => "   \n  "]);
        $this->assertNull($task->notesPreview());
    }

    public function test_notes_preview_strips_formatting_markers(): void
    {
        $task = new Task(['notes' => "**Wichtig**: *bitte* ++unterstreichen++\n- ein Punkt\n- [ ] eine Aufgabe"]);

        $this->assertSame(
            'Wichtig: bitte unterstreichen ein Punkt eine Aufgabe',
            $task->notesPreview(20)
        );
    }

    public function test_notes_preview_truncates_to_the_given_word_count(): void
    {
        $task = new Task(['notes' => 'eins zwei drei vier fünf sechs']);

        $this->assertSame('eins zwei drei…', $task->notesPreview(3));
    }

    public function test_notes_preview_does_not_truncate_when_within_the_word_limit(): void
    {
        $task = new Task(['notes' => 'eins zwei drei']);

        $this->assertSame('eins zwei drei', $task->notesPreview(3));
    }
}
