<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GermanLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_locale_is_german_whatever_the_environment_says(): void
    {
        // config/app.php no longer reads APP_LOCALE: production once ran with the
        // .env.example default "en" and showed English weekday names in a German UI.
        $source = file_get_contents(config_path('app.php'));

        $this->assertStringNotContainsString("env('APP_LOCALE'", $source);
        $this->assertSame('de', config('app.locale'));
        $this->assertSame('de', app()->getLocale());
    }

    public function test_the_tagesueberblick_header_uses_german_weekday_and_month_names(): void
    {
        Carbon::setTestNow('2026-10-21 10:00:00'); // Wednesday
        $user = User::factory()->create(['timezone_offset' => 0, 'timezone_auto_dst' => false]);

        $this->actingAs($user)->get(route('today'))
            ->assertOk()
            ->assertSee('Mittwoch, 21. Oktober')
            ->assertDontSee('Wednesday')
            ->assertDontSee('October');
    }

    public function test_the_env_example_does_not_offer_an_english_locale(): void
    {
        $this->assertStringNotContainsString('APP_LOCALE=en', file_get_contents(base_path('.env.example')));
    }
}
