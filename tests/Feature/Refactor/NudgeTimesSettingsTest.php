<?php

namespace Tests\Feature\Refactor;

use App\Livewire\Shell\NudgeTimes;
use App\Models\Evening;
use App\Models\PushSubscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Step 3.3: the "Abschalten am Abend" card in the new settings (R2, R7). Monday 9 November
 * 2026, 12:00 in Zurich.
 */
class NudgeTimesSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');
        $this->travelTo(CarbonImmutable::parse('2026-11-09 12:00', 'Europe/Zurich'));
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
    }

    private function card()
    {
        return Livewire::actingAs($this->user)->test(NudgeTimes::class);
    }

    public function test_the_card_is_on_the_new_settings_page_with_all_seven_days_and_a_calm_empty_state(): void
    {
        $this->actingAs($this->user)->get('/board/settings')->assertOk()
            ->assertSee('Abschalten am Abend')
            ->assertSee(['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'])
            ->assertSee('Noch keine Zeit gesetzt. Bis dahin bietet dir das Board die zehn Minuten ab 17:00 an, ohne Mitteilung.')
            ->assertSee('Zeiten gelten in deiner Zeitzone (Europe/Zurich).')
            ->assertSee('data-push-status', false);
    }

    public function test_setting_copying_and_saving_times(): void
    {
        $this->card()
            ->call('addTime', 1)->assertSet('times.1', '18:00')
            ->set('times.1', '18:30')
            ->call('copyWeekdays')
            ->call('addTime', 7)->assertSet('times.7', '18:30') // the last time above
            ->set('times.7', '17:45')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Gespeichert. Heute ab 18:30.');

        $this->assertSame(['1' => '18:30', '2' => '18:30', '3' => '18:30', '4' => '18:30', '5' => '18:30', '7' => '17:45'], $this->user->fresh()->nudge_times);
    }

    public function test_a_wrong_time_gets_a_german_message_on_its_own_row_and_nothing_is_saved(): void
    {
        $this->card()
            ->set('times.2', '25:00')
            ->set('times.3', '19:00')
            ->call('save')
            ->assertHasErrors(['times.2'])
            ->assertHasNoErrors(['times.3'])
            ->assertSee('Bitte eine Uhrzeit wie 18:30.')
            ->assertSee('aria-invalid="true"', false);

        $this->assertNull($this->user->fresh()->nudge_times);
    }

    public function test_removing_every_time_keeps_the_week_quiet_and_says_so(): void
    {
        $this->user->forceFill(['nudge_times' => ['1' => '18:00']])->save();

        $this->card()
            ->assertSet('times.1', '18:00')
            ->call('clearTime', 1)
            ->call('save')
            ->assertSee('Gespeichert. Heute bleibt es still.')
            ->assertSee('Kein Tag hat eine Zeit.');

        $this->assertSame([], $this->user->fresh()->nudge_times);
    }

    public function test_saving_a_time_that_has_already_passed_today_does_not_push_a_minute_later(): void
    {
        $this->card()->set('times.1', '11:00')->call('save');

        $this->assertNotNull(Evening::for($this->user, '2026-11-09')->nudge_sent_at);
    }

    public function test_saving_a_later_time_leaves_tonight_open(): void
    {
        $this->card()->set('times.1', '18:00')->call('save');

        $this->assertNull(Evening::for($this->user, '2026-11-09')?->nudge_sent_at);
    }

    public function test_unknown_weekdays_are_ignored(): void
    {
        $this->card()->call('addTime', 9)->call('clearTime', 0)->assertHasNoErrors();
        $this->assertNull($this->user->fresh()->nudge_times);
    }

    public function test_allowing_notifications_here_stores_the_subscription_like_the_old_settings(): void
    {
        $this->card()->call('subscribeToPush', 'https://push.example.com/new', 'p256dh-key', 'auth-token');
        $this->card()->call('subscribeToPush', 'https://127.0.0.1/evil', 'k', 't')->assertHasErrors(['endpoint']);

        $this->assertSame(1, PushSubscription::query()->where('user_id', $this->user->id)->count());
    }

    public function test_the_settings_are_reachable_from_both_layouts(): void
    {
        $html = $this->actingAs($this->user)->get('/board')->assertOk()->getContent();

        $this->assertGreaterThanOrEqual(2, substr_count($html, 'href="'.route('shell.settings')));
    }
}
