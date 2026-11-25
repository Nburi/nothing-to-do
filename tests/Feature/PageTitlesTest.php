<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LegacyScreens;
use Tests\TestCase;

class PageTitlesTest extends TestCase
{
    use RefreshDatabase;
    use LegacyScreens;

    public function test_each_app_page_names_itself_in_the_browser_tab(): void
    {
        $this->actingAs(User::factory()->create());

        foreach ([
            '/app' => 'Board',
            $this->legacy('schedule') => 'Zeitplan',
            $this->legacy('weekplan') => 'Wochenplan',
            '/app/settings' => 'Einstellungen',
            '/app/progress' => 'Fortschritt',
        ] as $path => $title) {
            $this->get($path)->assertSee("<title>{$title} · ".config('app.name').'</title>', false);
        }
    }

    public function test_a_page_without_a_title_falls_back_to_the_app_name(): void
    {
        $this->get('/login')->assertSee('<title>'.config('app.name').'</title>', false);
    }
}
