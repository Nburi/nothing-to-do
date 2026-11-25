<?php

namespace Tests\Feature\Refactor;

use App\Livewire\Shell\Projects;
use App\Models\EventCategory;
use App\Models\Task;
use App\Models\User;
use App\Services\FocusSession;
use App\Services\Planning\WeekPlanEditor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Step 5.2 (R7, risk C7): what the audit found and fixed in the markup of the new shell, kept as guards.
 * Contrast has its own test (ThemeContrastTest); the browser checks are in docs/TESTBERICHT.md.
 */
class ShellAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');
        $this->travelTo(CarbonImmutable::parse('2026-11-09 20:00', 'Europe/Zurich'));
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
        $deep = EventCategory::factory()->for($this->user)->pomodoro()->create(['name' => 'Deep work']);
        (new WeekPlanEditor)->addRepeating($this->user, [1, 2, 3, 4, 5], '19:30', '21:30', $deep->id);
        Task::forceCreate(['user_id' => $this->user->id, 'title' => 'Formular', 'list' => 'todos']);
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8">'.$html);

        return new \DOMXPath($dom);
    }

    /** @return list<string> */
    private function shellViews(): array
    {
        $files = [];
        foreach ([resource_path('views/shell'), resource_path('views/livewire/shell')] as $dir) {
            foreach (glob($dir.'/*.blade.php') as $file) {
                $files[] = $file;
            }
        }

        return $files;
    }

    public function test_the_board_has_one_main_landmark_and_one_heading_in_both_modes(): void
    {
        $html = $this->actingAs($this->user)->get('/board')->assertOk()->getContent();
        $xpath = $this->xpath($html);

        $this->assertSame(1, $xpath->query('//main[@id="content"]')->length, 'the skip link target is the main landmark');
        $this->assertSame(1, $xpath->query('//main')->length, 'no second main');
        $this->assertSame(1, $xpath->query('//h1')->length);
        $this->assertSame('Board', trim($xpath->query('//h1')->item(0)->textContent));

        app(FocusSession::class)->start($this->user, Task::first()->id);
        $session = $this->xpath($this->actingAs($this->user)->get('/board')->getContent());

        $this->assertSame(1, $session->query('//h1')->length);
        $this->assertStringContainsString('Session läuft: Formular', $session->query('//h1')->item(0)->textContent);
        $this->assertSame(1, $session->query('//main')->length, 'the session view used to bring a second main');
    }

    public function test_the_page_title_takes_the_focus_when_the_view_changes(): void
    {
        $html = $this->actingAs($this->user)->get('/board')->getContent();

        $this->assertStringContainsString('<h1 tabindex="-1" class="sr-only" data-page-title>', $html);
        $this->assertStringContainsString('refocus()', file_get_contents(resource_path('js/app.js')));
    }

    public function test_notices_live_in_a_region_that_is_always_there(): void
    {
        $html = $this->actingAs($this->user)->get('/board')->assertOk()->getContent();

        $this->assertSame(1, $this->xpath($html)->query('//*[@role="status"][@data-notice]')->length);
    }

    public function test_overlays_are_labelled_and_hold_the_focus(): void
    {
        $html = $this->actingAs($this->user)->get('/board')->assertOk()->getContent();
        $xpath = $this->xpath($html);

        foreach ($xpath->query('//*[@role="dialog"]') as $dialog) {
            $this->assertTrue($dialog->hasAttribute('aria-label') || $dialog->hasAttribute('aria-labelledby'), 'a dialog has a name');
            $this->assertSame('true', $dialog->getAttribute('aria-modal'));
        }

        // The Stapel list sheet traps only below 720 px: it sits hidden in the Werkbank markup too.
        $this->assertStringContainsString("x-trap.noscroll=\"peek && ! window.matchMedia('(min-width: 45rem)').matches\"", $html);
        $this->assertStringContainsString('x-trap.noscroll.noautofocus.noreturn="$store.capture.sheetOpen"', $html);
        $this->assertStringContainsString('x-restore-focus="$store.capture.sheetOpen"', $html);
    }

    public function test_the_battery_is_a_disclosure_not_a_menu_without_arrow_keys(): void
    {
        $html = $this->actingAs($this->user)->get('/board')->getContent();

        $this->assertStringNotContainsString('role="menu"', $html);
        $this->assertStringNotContainsString('menuitemradio', $html);
        $this->assertStringContainsString('aria-pressed="true"', $html);
    }

    public function test_the_stapel_piles_read_list_count_then_the_top_card(): void
    {
        $html = $this->actingAs($this->user)->get('/board')->getContent();

        $this->assertStringContainsString('aria-label="To-dos (1): Formular"', $html);
        $this->assertStringContainsString('aria-label="Inbox (0)"', $html);
    }

    public function test_every_validation_message_in_the_shell_is_an_announced_field_error(): void
    {
        $missing = [];

        foreach ($this->shellViews() as $file) {
            $source = file_get_contents($file);
            preg_match_all('/@error\([^\n]*?\)\s*<(p|span)\b([^>]*)>/', $source, $found, PREG_SET_ORDER);

            foreach ($found as $match) {
                if (! str_contains($match[2], 'data-field-error') || ! str_contains($match[2], 'role="alert"')) {
                    $missing[] = basename($file).': '.trim($match[0]);
                }
            }
        }

        $this->assertSame([], $missing, 'every @error paragraph needs data-field-error role="alert"');
    }

    public function test_a_validation_error_is_rendered_as_an_alert_the_observer_can_link(): void
    {
        Livewire::actingAs($this->user)->test(Projects::class)
            ->call('open', 'project')
            ->set('newName', '')
            ->call('create')
            ->assertSeeHtml('data-field-error role="alert"');
    }

    public function test_no_close_button_is_named_just_ok_and_the_name_exists_in_both_languages(): void
    {
        foreach ($this->shellViews() as $file) {
            $this->assertStringNotContainsString('aria-label="OK"', file_get_contents($file), basename($file));
        }

        foreach (['de', 'en'] as $locale) {
            $this->assertNotSame('shell.close', trans('shell.close', [], $locale), $locale);
        }
    }

    public function test_round_buttons_on_the_phone_are_44_px(): void
    {
        foreach ($this->shellViews() as $file) {
            $source = file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression('/(?<![\w:\-])size-9(?![\w\-])/', $source, basename($file).' has a size-9 without the phone size');
            $this->assertDoesNotMatchRegularExpression('/(?<![\w:\-])min-h-(8|9|10)(?![\w\-])/', $source, basename($file).' has a small min-h without the phone size');
        }

        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('width: max(100%, 44px)', $css, 'small text links get an invisible 44 px hit area');
    }

    public function test_reduced_motion_is_honoured_by_the_fold_the_toasts_and_the_scroll(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $js = file_get_contents(resource_path('js/app.js'));

        // One global rule collapses every CSS transition and animation (toasts, sheets, fades) …
        $this->assertMatchesRegularExpression('/prefers-reduced-motion: reduce\)\s*\{\s*\*,\s*\*::before,\s*\*::after\s*\{[^}]*transition-duration: 0\.01ms !important/s', $css);
        // … the fold has its own instant path (CONVENTIONS 10) …
        $this->assertStringContainsString('.shell [data-region="board"] *', $css);
        $this->assertStringContainsString("if (!reducedMotion()) flag('shell-folding', true);", $js);
        $this->assertStringContainsString('if (!root || reducedMotion()) return null;', $js);
        // … and the one JS smooth scroll asks first.
        $board = file_get_contents(resource_path('views/livewire/shell/board.blade.php'));
        $this->assertStringContainsString("matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'", $board);
    }
}
