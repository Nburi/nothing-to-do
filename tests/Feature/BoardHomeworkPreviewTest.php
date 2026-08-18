<?php

namespace Tests\Feature;

use App\Livewire\TaskBoard;
use App\Models\AgendaEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class BoardHomeworkPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_due_soon_homework_appears_in_the_dashboard_preview(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-13')->setTime(9, 0));
        $user = User::factory()->create(['timezone_offset' => 0, 'homework_preview_enabled' => true]);
        $entry = AgendaEntry::factory()->for($user)->homework()->create(['date' => '2026-08-14']);

        $preview = Livewire::actingAs($user)->test(TaskBoard::class)->instance()->homeworkPreview();

        $this->assertTrue($preview->contains($entry));
    }

    public function test_an_entrys_note_is_visible_on_the_dashboard_preview_card(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-13')->setTime(9, 0));
        $user = User::factory()->create(['timezone_offset' => 0, 'homework_preview_enabled' => true]);
        AgendaEntry::factory()->for($user)->homework()->create([
            'date' => '2026-08-14',
            'notes' => 'Seite 12 bis 15 rechnen, Taschenrechner mitbringen.',
        ]);

        Livewire::actingAs($user)
            ->test(TaskBoard::class)
            ->assertSee('Seite 12 bis 15 rechnen, Taschenrechner mitbringen.');
    }

    public function test_the_preview_is_empty_when_the_setting_is_off(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-13')->setTime(9, 0));
        $user = User::factory()->create(['timezone_offset' => 0, 'homework_preview_enabled' => false]);
        AgendaEntry::factory()->for($user)->homework()->create(['date' => '2026-08-14']);

        $preview = Livewire::actingAs($user)->test(TaskBoard::class)->instance()->homeworkPreview();

        $this->assertTrue($preview->isEmpty());
    }

    public function test_toggling_an_entry_done_removes_it_from_the_preview(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-13')->setTime(9, 0));
        $user = User::factory()->create(['timezone_offset' => 0, 'homework_preview_enabled' => true]);
        $entry = AgendaEntry::factory()->for($user)->homework()->create(['date' => '2026-08-14']);

        $component = Livewire::actingAs($user)->test(TaskBoard::class);
        $component->call('toggleHomeworkPreviewDone', $entry->id);

        $this->assertTrue($entry->fresh()->isDoneFor($user));
        $this->assertTrue($component->instance()->homeworkPreview()->isEmpty());
    }

    public function test_a_foreign_entry_cannot_be_toggled(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-13')->setTime(9, 0));
        $user = User::factory()->create(['timezone_offset' => 0]);
        $stranger = User::factory()->create(['timezone_offset' => 0]);
        $entry = AgendaEntry::factory()->for($stranger)->homework()->create(['date' => '2026-08-14']);

        $component = Livewire::actingAs($user)->test(TaskBoard::class);

        try {
            $component->call('toggleHomeworkPreviewDone', $entry->id);
            $this->fail('Expected a ModelNotFoundException for the foreign entry.');
        } catch (ModelNotFoundException) {
            $this->assertFalse($entry->fresh()->isDoneFor($stranger));
        }
    }
}
