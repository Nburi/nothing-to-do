<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleNavVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_module_nav_entry_shows_by_default(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('app'));

        $response->assertSee('Zeitplan');
        $response->assertSee('Wochenplan &amp; Ferien', false);
        $response->assertSee('Bastelideen');
        $response->assertSee('Fortschritt');
    }

    public function test_hiding_a_module_removes_its_link_from_the_mehr_menu_only(): void
    {
        $user = User::factory()->create(['hidden_modules' => ['weekplan']]);

        $response = $this->actingAs($user)->get(route('app'));

        // What matters is that nothing on the page still links to the now-hidden page.
        $response->assertDontSee(route('weekplan'), false);
        $response->assertSee('Zeitplan');
    }

    public function test_hiding_progress_removes_it_from_the_profile_menu(): void
    {
        $user = User::factory()->create(['hidden_modules' => ['progress']]);

        $this->actingAs($user)->get(route('app'))->assertDontSee('Fortschritt');
    }

    public function test_hiding_every_module_removes_the_whole_mehr_button(): void
    {
        $user = User::factory()->create(['hidden_modules' => [
            'schedule', 'weekplan', 'crafts', 'emergency',
        ]]);

        $this->actingAs($user)->get(route('app'))->assertDontSee('Weitere Funktionen');
    }

}
