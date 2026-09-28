<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsCopyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_planer_description_matches_the_day_board_it_became(): void
    {
        // The Planer was reworked from "auto-fills Pomodoro blocks" to a manual day
        // board with an optional auto-fill; its Settings blurb kept the old promise
        // for weeks (seen on production).
        $this->actingAs(User::factory()->create())->get(route('settings'))
            ->assertOk()
            ->assertSee('Ein Tagesbrett für die nächsten zwei Wochen')
            ->assertSee('Rest automatisch')
            ->assertDontSee('automatisch auf deine nächsten')
            ->assertDontSee('Pomodoro-Arbeitsblöcke');
    }

    public function test_the_tagesziel_blurb_does_not_claim_a_fixed_number_of_celebrations(): void
    {
        $this->actingAs(User::factory()->create())->get(route('settings'))
            ->assertDontSee('beiden Feier-Animationen')
            ->assertSee('Tagesziel erreicht');
    }
}
