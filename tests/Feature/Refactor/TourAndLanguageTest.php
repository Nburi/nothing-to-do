<?php

namespace Tests\Feature\Refactor;

use App\Livewire\Shell\LanguagePicker;
use App\Livewire\Shell\Tour;
use App\Models\User;
use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Step 5.1: the language of a user, the first-run tour. Needs A-checks for R7 (a stranger
 * understands it cold) and the safety rule that existing users are never pushed through the tour.
 */
class TourAndLanguageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setLocale('de');
        parent::tearDown();
    }

    // ── language ───────────────────────────────────────────────────────

    public function test_a_new_account_speaks_german(): void
    {
        $user = User::factory()->create();

        $this->assertSame('de', $user->fresh()->locale);
        $this->actingAs($user)->get(route('shell.settings'))
            ->assertOk()
            ->assertSee('<html lang="de"', false)
            ->assertSee('Einstellungen');
    }

    public function test_an_english_user_gets_english_pages_dates_and_html_lang(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)->get(route('shell.settings'))
            ->assertOk()
            ->assertSee('<html lang="en"', false)
            ->assertSee('Language')
            ->assertSee('Show the tour')
            ->assertDontSee('Rundgang');

        $this->assertSame('Monday', Carbon::parse('2026-11-23')->isoFormat('dddd'));
        $this->assertSame('Montag', Locales::within(null, fn () => Carbon::parse('2026-11-23')->isoFormat('dddd')));
    }

    public function test_validation_messages_follow_the_users_language(): void
    {
        $german = User::factory()->create();
        $english = User::factory()->create(['locale' => 'en']);

        $this->actingAs($german)->postJson(route('shell.capture'), ['title' => ''])->assertUnprocessable();
        $de = $this->actingAs($german)->postJson(route('shell.capture'), ['title' => ''])->json('errors');
        $en = $this->actingAs($english)->postJson(route('shell.capture'), ['title' => ''])->json('errors');

        $this->assertNotEmpty($de);
        $this->assertNotEmpty($en);
        $this->assertNotSame(json_encode($de), json_encode($en));
        $this->assertStringNotContainsString('required', json_encode($de));
    }

    public function test_the_language_picker_saves_the_choice_and_reloads_the_page(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(LanguagePicker::class)
            ->assertSet('locale', 'de')
            ->call('select', 'en')
            ->assertRedirect(route('shell.settings'));

        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_the_language_picker_refuses_a_language_the_app_does_not_have(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(LanguagePicker::class)
            ->call('select', 'fr')
            ->assertHasErrors('locale')
            ->assertNoRedirect();

        $this->assertSame('de', $user->fresh()->locale);
    }

    public function test_a_damaged_locale_in_the_database_falls_back_to_german(): void
    {
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['locale' => 'xx']);

        $this->assertSame('de', Locales::for($user->fresh()));
        $this->actingAs($user->fresh())->get(route('shell.settings'))->assertOk()->assertSee('Einstellungen');
    }

    public function test_the_locale_helper_restores_the_previous_language_after_a_push_message(): void
    {
        $english = User::factory()->create(['locale' => 'en']);

        app()->setLocale('de');
        $text = Locales::within($english, fn () => __('onboarding.tour.next'));

        $this->assertSame('Next', $text);
        $this->assertSame('de', app()->getLocale());
        $this->assertSame('de', Carbon::getLocale());
    }

    // ── the tour ───────────────────────────────────────────────────────

    public function test_a_brand_new_account_sees_the_three_step_tour_on_the_board(): void
    {
        $user = User::factory()->unseenTour()->create();

        $this->actingAs($user)->get(route('shell.board'))
            ->assertOk()
            ->assertSee('data-tour', false)
            ->assertSee('Alles landet zuerst in der Inbox')
            ->assertSee('Einmal am Tag sortierst du')
            ->assertSee('Abends faltet sich das Board weg')
            ->assertSee('Überspringen')
            ->assertSee('role="dialog"', false);
    }

    public function test_the_tour_comes_in_english_for_an_english_account(): void
    {
        $user = User::factory()->unseenTour()->create(['locale' => 'en']);

        $this->actingAs($user)->get(route('shell.board'))
            ->assertOk()
            ->assertSee('Everything lands in the Inbox first')
            ->assertSee('Once a day you sort')
            ->assertDontSee('Überspringen');
    }

    public function test_the_tour_shows_on_any_page_of_the_new_app_not_only_the_board(): void
    {
        $user = User::factory()->unseenTour()->create();

        foreach ([route('shell.settings'), route('shell.projects'), route('shell.ideas')] as $url) {
            $this->actingAs($user)->get($url)->assertOk()->assertSee('data-tour', false);
        }
    }

    public function test_an_account_that_has_seen_the_tour_gets_no_overlay(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('shell.board'))->assertOk()->assertDontSee('data-tour', false);
    }

    public function test_the_tour_is_not_part_of_the_page_for_guests(): void
    {
        $this->get(route('shell.board'))->assertRedirect(route('login'));
    }

    public function test_the_tour_can_be_opened_again_with_a_link(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('shell.board', ['tour' => 1]))
            ->assertOk()
            ->assertSee('data-tour', false);
    }

    public function test_the_settings_offer_the_tour_again(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('shell.settings'))
            ->assertOk()
            ->assertSee('Rundgang ansehen')
            ->assertSee(route('shell.board', ['tour' => 1]), false);
    }

    public function test_finishing_or_skipping_stamps_the_account_once(): void
    {
        Carbon::setTestNow('2026-11-26 09:00:00');
        $user = User::factory()->unseenTour()->create();

        Livewire::actingAs($user)->test(Tour::class)
            ->assertSet('open', true)
            ->call('finish')
            ->assertSet('open', false);

        $this->assertEquals('2026-11-26 09:00:00', $user->fresh()->shell_tour_seen_at->toDateTimeString());
        $this->assertFalse($user->fresh()->needsShellTour());

        // A replay from the settings does not move the date of the first time.
        Carbon::setTestNow('2026-12-01 09:00:00');
        Livewire::actingAs($user->fresh())->test(Tour::class)->call('finish');

        $this->assertEquals('2026-11-26 09:00:00', $user->fresh()->shell_tour_seen_at->toDateTimeString());
        Carbon::setTestNow();
    }

    public function test_the_migration_marks_everyone_who_exists_as_having_seen_the_tour(): void
    {
        $old = User::factory()->unseenTour()->create();
        $migration = require database_path('migrations/2026_11_26_000001_add_locale_and_shell_tour_to_users_table.php');

        // Roll the columns back and forward again, the way a deploy meets an existing table.
        $migration->down();
        $migration->up();

        $this->assertNotNull($old->fresh()->shell_tour_seen_at);
        $this->assertSame('de', $old->fresh()->locale);

        $new = User::factory()->unseenTour()->create();
        $this->assertTrue($new->fresh()->needsShellTour());
    }
}
