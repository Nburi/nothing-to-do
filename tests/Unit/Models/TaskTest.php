<?php

namespace Tests\Unit\Models;

use App\Models\Task;
use Illuminate\Support\Carbon;
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

    public function test_today_date_for_stamps_the_target_date_when_entering_today(): void
    {
        $task = new Task(['is_today' => false, 'today_date' => null]);

        $this->assertSame('2026-08-18', $task->todayDateFor(true, Carbon::parse('2026-08-18')));
    }

    public function test_today_date_for_preserves_the_existing_date_while_already_today(): void
    {
        // Reordering within the Today zone re-sends is_today=true for a task that
        // was already there — this must not silently re-date an old leftover task.
        $task = new Task(['is_today' => true, 'today_date' => '2026-08-14']);

        $this->assertSame('2026-08-14', $task->todayDateFor(true, Carbon::parse('2026-08-18')));
    }

    public function test_today_date_for_clears_when_leaving_today(): void
    {
        $task = new Task(['is_today' => true, 'today_date' => '2026-08-14']);

        $this->assertNull($task->todayDateFor(false));
    }

    public function test_today_date_for_stays_null_for_a_pre_migration_task_still_flagged_today(): void
    {
        // is_today was already true before this feature shipped, so there was
        // never a today_date to stamp — it stays null until the task is next
        // explicitly un-flagged and re-flagged.
        $task = new Task(['is_today' => true, 'today_date' => null]);

        $this->assertNull($task->todayDateFor(true, Carbon::parse('2026-08-18')));
    }
}
