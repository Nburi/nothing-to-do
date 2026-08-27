<?php

namespace Tests\Feature;

use App\Models\Project;
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

        $response->assertSee('Vorbereiten');
        $response->assertSee('Zeitplan');
        $response->assertSee('Wochenplan &amp; Ferien', false);
        $response->assertSee('Agenda');
        $response->assertSee('Bastelideen');
        $response->assertSee('Notfall');
        $response->assertSee('Fortschritt');
    }

    public function test_hiding_agenda_removes_its_link_from_the_mehr_menu_only(): void
    {
        $user = User::factory()->create(['hidden_modules' => ['agenda']]);

        $response = $this->actingAs($user)->get(route('app'));

        // Not a bare assertDontSee('Agenda') — the word legitimately still
        // appears in an unrelated JS comment inside QuickCapture's x-data
        // attribute. What actually matters is that nothing on the page still
        // links to the now-hidden page.
        $response->assertDontSee(route('agenda'), false);
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
            'prepare', 'schedule', 'weekplan', 'agenda', 'crafts', 'emergency', 'library',
        ]]);

        $this->actingAs($user)->get(route('app'))->assertDontSee('Weitere Funktionen');
    }

    public function test_notfall_stays_reachable_while_actually_active_even_if_hidden(): void
    {
        $user = User::factory()->create(['hidden_modules' => ['emergency']]);
        $project = Project::factory()->for($user)->create();
        $user->update(['emergency_project_id' => $project->id]);

        $this->actingAs($user)->get(route('app'))->assertSee('Notfall');
    }
}
