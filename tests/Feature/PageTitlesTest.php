<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageTitlesTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_app_page_names_itself_in_the_browser_tab(): void
    {
        $this->actingAs(User::factory()->create());

        foreach ([
            '/app' => 'Board',
            '/app/agenda' => 'Agenda',
            '/app/schedule' => 'Zeitplan',
            '/app/weekplan' => 'Wochenplan',
            '/app/settings' => 'Einstellungen',
            '/app/progress' => 'Fortschritt',
        ] as $path => $title) {
            $this->get($path)->assertSee("<title>{$title} · ".config('app.name').'</title>', false);
        }
    }

    public function test_project_and_group_pages_use_their_own_name(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $project = $user->projects()->create(['name' => 'Maturaarbeit', 'sort_order' => 0]);
        $group = $user->taskGroups()->create(['name' => 'Zimmer umstellen', 'sort_order' => 0]);

        $this->get(route('project.show', $project))->assertSee('<title>Maturaarbeit · '.config('app.name').'</title>', false);
        $this->get(route('group.show', $group))->assertSee('<title>Zimmer umstellen · '.config('app.name').'</title>', false);
    }

    public function test_a_page_without_a_title_falls_back_to_the_app_name(): void
    {
        $this->get('/login')->assertSee('<title>'.config('app.name').'</title>', false);
    }
}
