<?php

namespace Tests\Feature;

use App\Models\AgendaEntry;
use App\Models\AgendaSpace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AgendaEntryHomeworkPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function todayIsThursday(): void
    {
        // 2026-08-13 is a Thursday — chosen so the 3-weekday lookahead crosses a weekend
        // (Fri, Mon, Tue), the exact case the feature exists to handle.
        Carbon::setTestNow(Carbon::parse('2026-08-13')->setTime(9, 0));
    }

    public function test_homework_due_tomorrow_is_included(): void
    {
        $this->todayIsThursday();
        $user = User::factory()->create(['timezone_offset' => 0]);
        $entry = AgendaEntry::factory()->for($user)->homework()->create(['date' => '2026-08-14']);

        $this->assertTrue(AgendaEntry::homeworkPreviewFor($user)->contains($entry));
    }

    public function test_homework_due_next_monday_is_included_because_the_weekend_is_skipped(): void
    {
        $this->todayIsThursday();
        $user = User::factory()->create(['timezone_offset' => 0]);
        // Friday, Monday, Tuesday are the next 3 weekdays from Thursday — Monday is #2.
        $entry = AgendaEntry::factory()->for($user)->homework()->create(['date' => '2026-08-17']);

        $this->assertTrue(AgendaEntry::homeworkPreviewFor($user)->contains($entry));
    }

    public function test_homework_due_the_following_wednesday_is_excluded(): void
    {
        $this->todayIsThursday();
        $user = User::factory()->create(['timezone_offset' => 0]);
        // Fri, Mon, Tue, Wed — Wednesday is the 4th weekday out, past the 3-weekday cutoff.
        $entry = AgendaEntry::factory()->for($user)->homework()->create(['date' => '2026-08-19']);

        $this->assertFalse(AgendaEntry::homeworkPreviewFor($user)->contains($entry));
    }

    public function test_overdue_homework_is_included(): void
    {
        $this->todayIsThursday();
        $user = User::factory()->create(['timezone_offset' => 0]);
        $entry = AgendaEntry::factory()->for($user)->homework()->create(['date' => '2026-08-10']);

        $this->assertTrue(AgendaEntry::homeworkPreviewFor($user)->contains($entry));
    }

    public function test_done_homework_is_excluded(): void
    {
        $this->todayIsThursday();
        $user = User::factory()->create(['timezone_offset' => 0]);
        $entry = AgendaEntry::factory()->for($user)->homework()->done()->create(['date' => '2026-08-14']);

        $this->assertFalse(AgendaEntry::homeworkPreviewFor($user)->contains($entry));
    }

    public function test_exams_are_excluded_even_when_due_soon(): void
    {
        $this->todayIsThursday();
        $user = User::factory()->create(['timezone_offset' => 0]);
        $entry = AgendaEntry::factory()->for($user)->exam()->create(['date' => '2026-08-14']);

        $this->assertFalse(AgendaEntry::homeworkPreviewFor($user)->contains($entry));
    }

    public function test_shared_class_homework_due_soon_is_included(): void
    {
        $this->todayIsThursday();
        $owner = User::factory()->create(['timezone_offset' => 0]);
        $classmate = User::factory()->create(['timezone_offset' => 0]);
        $space = AgendaSpace::factory()->for($owner, 'owner')->withMembers($classmate)->create();

        $entry = AgendaEntry::factory()->for($classmate)->homework()->inSpace($space)->create(['date' => '2026-08-14']);

        $this->assertTrue(AgendaEntry::homeworkPreviewFor($owner)->contains($entry));
    }

    public function test_a_private_entry_from_another_user_is_never_included(): void
    {
        $this->todayIsThursday();
        $user = User::factory()->create(['timezone_offset' => 0]);
        $stranger = User::factory()->create(['timezone_offset' => 0]);
        $entry = AgendaEntry::factory()->for($stranger)->homework()->create(['date' => '2026-08-14']);

        $this->assertFalse(AgendaEntry::homeworkPreviewFor($user)->contains($entry));
    }
}
