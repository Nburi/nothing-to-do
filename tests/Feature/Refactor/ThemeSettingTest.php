<?php

namespace Tests\Feature\Refactor;

use App\Livewire\ThemePicker;
use App\Models\User;
use App\Support\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Step 2.1: the theme is a per-user setting, Topografie by default, applied on every page.
 */
class ThemeSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_account_gets_topografie_and_follows_the_system(): void
    {
        $user = User::factory()->create()->fresh();

        $this->assertSame('topografie', $user->theme);
        $this->assertSame('system', $user->color_mode);
    }

    public function test_every_page_head_carries_the_users_theme(): void
    {
        $user = User::factory()->create(['theme' => 'nachtzug', 'color_mode' => 'dark']);
        $dark = config('themes.themes.nachtzug.dark');

        $this->actingAs($user)->get('/app/settings')
            ->assertOk()
            ->assertSee('<style id="theme-vars">', false)
            ->assertSee('--t-bg:'.$dark['bg'].';', false)
            ->assertSee('data-mode="dark"', false)
            ->assertDontSee('prefers-color-scheme: dark){:root', false);
    }

    public function test_guests_and_unknown_values_fall_back_to_topografie_system(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('--t-bg:'.config('themes.themes.topografie.light.bg').';', false)
            ->assertSee('@media (prefers-color-scheme: dark){:root{', false)
            ->assertSee('data-mode="system"', false);

        $user = User::factory()->create(['theme' => 'gone-theme', 'color_mode' => 'weird']);
        $this->assertSame('topografie', Theme::for($user));
        $this->assertSame('system', Theme::modeFor($user));
    }

    public function test_choosing_a_theme_saves_it_and_sends_the_new_stylesheet(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(ThemePicker::class)
            ->call('selectTheme', 'kiosk')
            ->assertHasNoErrors()
            ->assertDispatched('theme-applied', fn ($name, $params) => str_contains($params['css'], config('themes.themes.kiosk.light.bg'))
                && $params['mode'] === 'system'
                && $params['chrome']['light'] === config('themes.themes.kiosk.light.accent'));

        $this->assertSame('kiosk', $user->fresh()->theme);
    }

    public function test_choosing_a_mode_saves_it(): void
    {
        $user = User::factory()->create(['theme' => 'moos']);

        Livewire::actingAs($user)->test(ThemePicker::class)
            ->call('selectMode', 'light')
            ->assertDispatched('theme-applied', fn ($name, $params) => $params['mode'] === 'light'
                && ! str_contains($params['css'], 'prefers-color-scheme'));

        $this->assertSame('light', $user->fresh()->color_mode);
        $this->assertSame('moos', $user->fresh()->theme);
    }

    public function test_an_unknown_theme_or_mode_is_refused_with_a_german_message(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(ThemePicker::class)
            ->call('selectTheme', 'neon')
            ->assertHasErrors('theme')
            ->assertSee('Dieses Thema gibt es nicht.')
            ->call('selectMode', 'sepia')
            ->assertHasErrors('mode')
            ->assertNotDispatched('theme-applied');

        $this->assertSame('topografie', $user->fresh()->theme);
        $this->assertSame('system', $user->fresh()->color_mode);
    }

    public function test_the_settings_page_shows_all_seven_themes(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/app/settings')->assertOk();

        foreach (['Topografie', 'Nachtzug', 'Gletscher', 'Kiosk', 'Moos', 'Abendrot', 'Tinte'] as $name) {
            $response->assertSee($name);
        }
    }

    public function test_the_head_style_cannot_be_broken_out_of(): void
    {
        $this->assertStringNotContainsString('</style', Theme::css('tinte', 'system'));
        $this->assertStringNotContainsString('<', Theme::inlineStyle('kiosk', 'dark'));
    }
}
