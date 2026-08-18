<?php

namespace Tests\Unit\Models;

use App\Models\AgendaEntry;
use Tests\TestCase;

class AgendaEntryTest extends TestCase
{
    public function test_notes_preview_is_null_when_notes_are_empty(): void
    {
        $entry = new AgendaEntry(['notes' => null]);
        $this->assertNull($entry->notesPreview());

        $entry = new AgendaEntry(['notes' => "   \n  "]);
        $this->assertNull($entry->notesPreview());
    }

    public function test_notes_preview_truncates_to_the_given_word_count(): void
    {
        $entry = new AgendaEntry(['notes' => 'eins zwei drei vier fünf sechs']);

        $this->assertSame('eins zwei drei…', $entry->notesPreview(3));
    }

    public function test_notes_preview_does_not_truncate_when_within_the_word_limit(): void
    {
        $entry = new AgendaEntry(['notes' => 'Seite 12 bis 15 rechnen, Taschenrechner mitbringen.']);

        $this->assertSame('Seite 12 bis 15 rechnen, Taschenrechner mitbringen.', $entry->notesPreview());
    }

    public function test_notes_preview_collapses_whitespace(): void
    {
        $entry = new AgendaEntry(['notes' => "Seite 12\n\nbis   15"]);

        $this->assertSame('Seite 12 bis 15', $entry->notesPreview());
    }
}
