<?php

namespace Tests\Feature;

use App\Livewire\Schedule;
use App\Models\EventCategory;
use App\Models\ScheduleEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class ScheduleFirstVisitHintTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(): User
    {
        Carbon::setTestNow('2026-09-23 12:00:00');

        return User::factory()->create(['timezone_offset' => 0, 'timezone_auto_dst' => false]);
    }

    public function test_a_brand_new_account_is_told_how_to_get_started(): void
    {
        Livewire::actingAs($this->user())->test(Schedule::class)
            ->assertSee('Noch nichts geplant.')
            ->assertSee('Kategorien in den Einstellungen')
            ->assertSee(route('settings').'#schedule', false);
    }

    public function test_the_hint_is_gone_once_there_is_a_category(): void
    {
        $user = $this->user();
        EventCategory::factory()->for($user)->create();

        Livewire::actingAs($user)->test(Schedule::class)
            ->assertDontSee('Noch nichts geplant.')
            ->assertSee('Ziehen verschiebt');
    }

    public function test_the_hint_is_gone_once_the_week_has_an_event(): void
    {
        $user = $this->user();
        ScheduleEvent::factory()->for($user)->on('2026-09-24')->at('09:00', '10:00')->create();

        Livewire::actingAs($user)->test(Schedule::class)
            ->assertDontSee('Noch nichts geplant.')
            ->assertSee('Ziehen verschiebt');
    }

    public function test_the_gesture_line_is_not_shown_next_to_the_hint(): void
    {
        Livewire::actingAs($this->user())->test(Schedule::class)
            ->assertDontSee('Ziehen verschiebt');
    }

    public function test_the_settings_anchor_exists(): void
    {
        $this->actingAs($this->user())->get(route('settings'))->assertSee('id="schedule"', false);
    }
}
