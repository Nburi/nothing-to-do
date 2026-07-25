<?php

namespace Tests\Feature;

use App\Livewire\TaskBoard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class BoardPreparePromptTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_prompt_is_hidden_when_reminder_mode_is_off(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(20, 0));
        $user = User::factory()->create(['timezone_offset' => 0, 'prepare_reminder_mode' => 'off']);
        $this->actingAs($user);

        $this->assertFalse(Livewire::test(TaskBoard::class)->instance()->showPreparePrompt());
    }

    public function test_the_prompt_is_hidden_when_reminder_mode_is_fixed(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(20, 0));
        $user = User::factory()->create([
            'timezone_offset' => 0,
            'prepare_reminder_mode' => 'fixed',
            'prepare_time_of_day' => 'evening',
        ]);
        $this->actingAs($user);

        $this->assertFalse(Livewire::test(TaskBoard::class)->instance()->showPreparePrompt());
    }

    public function test_the_prompt_shows_in_automatic_mode_during_the_relevant_window(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(20, 0));
        $user = User::factory()->create([
            'timezone_offset' => 0,
            'prepare_reminder_mode' => 'automatic',
            'prepare_time_of_day' => 'evening',
        ]);
        $this->actingAs($user);

        $this->assertTrue(Livewire::test(TaskBoard::class)->instance()->showPreparePrompt());
    }

    public function test_the_prompt_is_hidden_outside_the_relevant_window(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(9, 0));
        $user = User::factory()->create([
            'timezone_offset' => 0,
            'prepare_reminder_mode' => 'automatic',
            'prepare_time_of_day' => 'evening',
        ]);
        $this->actingAs($user);

        $this->assertFalse(Livewire::test(TaskBoard::class)->instance()->showPreparePrompt());
    }

    public function test_the_prompt_is_hidden_once_prepared_today(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(20, 0));
        $user = User::factory()->create([
            'timezone_offset' => 0,
            'prepare_reminder_mode' => 'automatic',
            'prepare_time_of_day' => 'evening',
            'prepared_on' => Carbon::today()->toDateString(),
        ]);
        $this->actingAs($user);

        $this->assertFalse(Livewire::test(TaskBoard::class)->instance()->showPreparePrompt());
    }

    public function test_dismissing_the_prompt_hides_it_for_the_rest_of_the_day(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(20, 0));
        $user = User::factory()->create([
            'timezone_offset' => 0,
            'prepare_reminder_mode' => 'automatic',
            'prepare_time_of_day' => 'evening',
        ]);
        $this->actingAs($user);

        Livewire::test(TaskBoard::class)->call('dismissPreparePrompt');

        $this->assertSame(Carbon::today()->toDateString(), $user->fresh()->prepare_prompt_dismissed_on->toDateString());
        $this->assertFalse(Livewire::test(TaskBoard::class)->instance()->showPreparePrompt());
    }
}
