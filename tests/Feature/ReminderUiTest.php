<?php

namespace Tests\Feature;

use App\Livewire\DayPreview;
use App\Livewire\PrepareTomorrow;
use App\Livewire\QuickCapture;
use App\Livewire\ReminderBadges;
use App\Livewire\ReminderSheet;
use App\Livewire\Settings;
use App\Livewire\TaskBoard;
use App\Models\Reminder;
use App\Models\User;
use App\Services\DayPreviewData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/** "Nicht vergessen": the chips above the Zeitstrahl, the sheet, QuickCapture, Settings, Vorbereitung and the Tagesüberblick. */
class ReminderUiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Tuesday 2026-10-27 noon, user on UTC. */
    private function user(): User
    {
        Carbon::setTestNow('2026-10-27 12:00:00');

        return User::factory()->create(['timezone_offset' => 0, 'timezone_auto_dst' => false]);
    }

    // ── The chip row ──────────────────────────────────────────────────

    public function test_the_board_shows_the_chips_above_the_zeitstrahl(): void
    {
        $user = $this->user();
        Reminder::factory()->for($user)->create(['title' => 'Turnschuhe mitnehmen', 'remind_on' => '2026-10-28']);

        Livewire::actingAs($user)->test(TaskBoard::class)->assertSeeLivewire(ReminderBadges::class);
        Livewire::actingAs($user)->test(ReminderBadges::class)->assertSee('Turnschuhe mitnehmen')->assertSee('morgen')->assertSee('Hab ich');
    }

    public function test_with_nothing_at_all_the_row_renders_nothing(): void
    {
        Livewire::actingAs($this->user())->test(ReminderBadges::class)->assertDontSee('Nicht vergessen')->assertDontSee('geplant')->assertDontSee('Hab ich');
    }

    public function test_reminders_beyond_the_window_get_one_quiet_chip_that_is_the_way_into_the_list(): void
    {
        $user = $this->user();
        Reminder::factory()->for($user)->create(['title' => 'Viel später', 'remind_on' => '2026-12-01']);

        Livewire::actingAs($user)->test(ReminderBadges::class)->assertSee('1 Erinnerung geplant')->assertDontSee('Viel später')->assertDontSee('Hab ich');

        Reminder::factory()->for($user)->create(['title' => 'Nah', 'remind_on' => '2026-10-28']);
        Livewire::actingAs($user)->test(ReminderBadges::class)->assertSee('Nah')->assertSee('+1 geplant');
    }

    public function test_ticked_off_far_reminders_do_not_count_as_planned(): void
    {
        $user = $this->user();
        Reminder::factory()->for($user)->acknowledged()->create(['remind_on' => '2026-12-01']);

        Livewire::actingAs($user)->test(ReminderBadges::class)->assertDontSee('geplant');
    }

    public function test_hab_ich_acknowledges_the_reminder_and_says_what_changed(): void
    {
        $user = $this->user();
        $r = Reminder::factory()->for($user)->create(['title' => 'Turnschuhe', 'remind_on' => '2026-10-28']);

        Livewire::actingAs($user)->test(ReminderBadges::class)
            ->call('acknowledge', $r->id)
            ->assertDispatched('reminders-changed')
            ->assertSee('Morgen früh kommt nichts mehr.');

        $this->assertNotNull($r->fresh()->acknowledged_at);
    }

    public function test_the_message_for_today_and_for_a_day_that_never_pushes(): void
    {
        $user = $this->user();
        $today = Reminder::factory()->for($user)->create(['remind_on' => '2026-10-27']);
        $later = Reminder::factory()->for($user)->create(['remind_on' => '2026-10-29']);

        $component = Livewire::actingAs($user)->test(ReminderBadges::class);
        $component->call('acknowledge', $today->id)->assertSee('Eingepackt — nichts mehr offen.');
        $component->call('acknowledge', $later->id)->assertSee('Dann ist das erledigt.');
    }

    public function test_acknowledging_a_foreign_reminder_does_nothing(): void
    {
        $user = $this->user();
        $other = User::factory()->create();
        $r = Reminder::factory()->for($other)->create(['remind_on' => '2026-10-28']);

        Livewire::actingAs($user)->test(ReminderBadges::class)->call('acknowledge', $r->id);

        $this->assertNull($r->fresh()->acknowledged_at);
    }

    // ── The sheet ─────────────────────────────────────────────────────

    public function test_the_sheet_lists_adds_edits_ticks_and_deletes(): void
    {
        $user = $this->user();
        $existing = Reminder::factory()->for($user)->create(['title' => 'Alt', 'remind_on' => '2026-10-30']);

        $sheet = Livewire::actingAs($user)->test(ReminderSheet::class)->call('openSheet')->assertSet('open', true)->assertSee('Alt');

        $sheet->set('formTitle', 'Wasserflasche')->set('formDate', '2026-10-29')->set('formOnDay', false)->call('save')->assertDispatched('reminders-changed');
        $new = Reminder::where('title', 'Wasserflasche')->first();
        $this->assertNotNull($new);
        $this->assertFalse($new->notify_on_day);

        $sheet->call('startEdit', $existing->id)->assertSet('formTitle', 'Alt')->set('formTitle', 'Neu benannt')->call('save');
        $this->assertSame('Neu benannt', $existing->fresh()->title);

        $sheet->call('toggleAcknowledged', $existing->id);
        $this->assertNotNull($existing->fresh()->acknowledged_at);

        $sheet->call('delete', $new->id);
        $this->assertNull(Reminder::find($new->id));
    }

    public function test_the_sheet_shows_validation_errors_in_german_and_saves_nothing(): void
    {
        $user = $this->user();

        Livewire::actingAs($user)->test(ReminderSheet::class)->call('openSheet')
            ->set('formTitle', '')->call('save')->assertHasErrors('title')
            ->set('formTitle', 'X')->set('formDate', '2026-10-01')->call('save')->assertHasErrors('remind_on');

        $this->assertSame(0, Reminder::count());
    }

    public function test_opening_the_sheet_with_an_id_jumps_into_editing_and_a_foreign_id_is_ignored(): void
    {
        $user = $this->user();
        $mine = Reminder::factory()->for($user)->create(['title' => 'Meine']);
        $theirs = Reminder::factory()->for(User::factory()->create())->create(['title' => 'Fremde']);

        Livewire::actingAs($user)->test(ReminderSheet::class)->call('openSheet', $mine->id)->assertSet('editingId', $mine->id)->assertSet('formTitle', 'Meine');
        Livewire::actingAs($user)->test(ReminderSheet::class)->call('openSheet', $theirs->id)->assertSet('editingId', null)->assertDontSee('Fremde');
    }

    public function test_the_sheet_cannot_touch_someone_elses_reminder(): void
    {
        $user = $this->user();
        $theirs = Reminder::factory()->for(User::factory()->create())->create(['title' => 'Fremde']);

        $sheet = Livewire::actingAs($user)->test(ReminderSheet::class)->call('openSheet');
        $sheet->call('toggleAcknowledged', $theirs->id)->call('delete', $theirs->id);

        $this->assertNull($theirs->fresh()->acknowledged_at);
        $this->assertNotNull(Reminder::find($theirs->id));
    }

    public function test_the_empty_sheet_says_so(): void
    {
        Livewire::actingAs($this->user())->test(ReminderSheet::class)->call('openSheet')->assertSee('Nichts zu vergessen');
    }

    // ── QuickCapture ──────────────────────────────────────────────────

    public function test_quick_capture_offers_an_erinnerung_chip_and_defaults_to_tomorrow(): void
    {
        $user = $this->user();

        Livewire::actingAs($user)->test(QuickCapture::class)
            ->assertSee('Erinnerung')
            ->call('setTarget', 'reminder')
            ->assertSet('date', '2026-10-28');
    }

    public function test_quick_capture_writes_a_reminder_and_keeps_day_and_switches_for_the_next_one(): void
    {
        $user = $this->user();

        $component = Livewire::actingAs($user)->test(QuickCapture::class)
            ->call('setTarget', 'reminder')
            ->set('title', 'Turnschuhe')
            ->set('date', '2026-10-29')
            ->set('reminderOnDay', false)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('captured')
            ->assertDispatched('reminders-changed')
            ->assertSet('title', '')
            ->assertSet('date', '2026-10-29');

        $r = Reminder::sole();
        $this->assertSame('Turnschuhe', $r->title);
        $this->assertSame('2026-10-29', $r->remind_on->toDateString());
        $this->assertTrue($r->notify_evening_before);
        $this->assertFalse($r->notify_on_day);
        $this->assertSame(0, \App\Models\Task::count());
        $this->assertSame('Erinnerung · Do 29.10.', $component->get('captured')['label']);
    }

    public function test_quick_capture_refuses_the_past_a_missing_day_and_a_long_title(): void
    {
        $user = $this->user();

        Livewire::actingAs($user)->test(QuickCapture::class)
            ->call('setTarget', 'reminder')
            ->set('title', 'X')->set('date', '2026-10-01')->call('save')->assertHasErrors('date')
            ->set('date', '')->call('save')->assertHasErrors('date')
            ->set('date', '2026-10-29')->set('title', str_repeat('x', 121))->call('save')->assertHasErrors('title');

        $this->assertSame(0, Reminder::count());
    }

    public function test_switching_away_from_the_reminder_target_drops_its_date(): void
    {
        $user = $this->user();

        Livewire::actingAs($user)->test(QuickCapture::class)
            ->call('setTarget', 'reminder')
            ->call('setTarget', 'todos')
            ->assertSet('date', null);
    }

    public function test_reopening_the_panel_resets_the_reminder_switches(): void
    {
        $user = $this->user();

        Livewire::actingAs($user)->test(QuickCapture::class)
            ->set('reminderEvening', false)
            ->call('resetPanel', 'reminder')
            ->assertSet('reminderEvening', true)
            ->assertSet('target', 'reminder')
            ->assertSet('date', '2026-10-28');
    }

    // ── Settings ──────────────────────────────────────────────────────

    public function test_settings_saves_the_reminder_times_and_the_smart_timing_switch(): void
    {
        $user = $this->user();

        $component = Livewire::actingAs($user)->test(Settings::class)
            ->assertSet('reminderEveningTime', '19:00')
            ->assertSet('reminderMorningTime', '07:00')
            ->assertSet('reminderSmartTiming', true)
            ->set('reminderEveningTime', '20:30')
            ->set('reminderMorningTime', '06:45')
            ->call('saveReminderTimes')
            ->assertHasNoErrors();

        $fresh = $user->fresh();
        $this->assertSame('20:30', $fresh->reminder_evening_time);
        $this->assertSame('06:45', $fresh->reminder_morning_time);

        $component->call('toggleReminderSmartTiming')->assertSet('reminderSmartTiming', false);
        $this->assertFalse((bool) $user->fresh()->reminder_smart_timing);
    }

    public function test_settings_rejects_a_malformed_time(): void
    {
        Livewire::actingAs($this->user())->test(Settings::class)->set('reminderEveningTime', 'abends')->call('saveReminderTimes')->assertHasErrors('reminderEveningTime');
    }

    public function test_a_fresh_user_has_the_reminder_defaults_without_a_reload(): void
    {
        $user = User::factory()->create();

        $this->assertSame('19:00', $user->reminder_evening_time);
        $this->assertSame('07:00', $user->reminder_morning_time);
        $this->assertTrue($user->reminder_smart_timing);
    }

    // ── Vorbereitung ──────────────────────────────────────────────────

    public function test_the_vorbereitung_lists_the_target_days_reminders_and_ticks_them(): void
    {
        $user = $this->user();
        $tomorrow = Reminder::factory()->for($user)->create(['title' => 'Turnschuhe', 'remind_on' => '2026-10-28']);
        Reminder::factory()->for($user)->create(['title' => 'Übermorgen', 'remind_on' => '2026-10-29']);

        $page = Livewire::actingAs($user)->test(PrepareTomorrow::class)->assertSee('Nicht vergessen')->assertSee('Turnschuhe')->assertDontSee('Übermorgen');

        $page->call('toggleReminder', $tomorrow->id)->assertDispatched('reminders-changed');
        $this->assertNotNull($tomorrow->fresh()->acknowledged_at);

        $page->call('toggleReminder', $tomorrow->id);
        $this->assertNull($tomorrow->fresh()->acknowledged_at);
    }

    public function test_the_vorbereitung_follows_morning_mode_to_today(): void
    {
        $user = $this->user();
        $user->update(['prepare_time_of_day' => 'morning']);
        Reminder::factory()->for($user)->create(['title' => 'Heutiges', 'remind_on' => '2026-10-27']);
        Reminder::factory()->for($user)->create(['title' => 'Morgiges', 'remind_on' => '2026-10-28']);

        Livewire::actingAs($user->fresh())->test(PrepareTomorrow::class)->assertSee('Heutiges')->assertDontSee('Morgiges');
    }

    public function test_the_vorbereitung_cannot_tick_someone_elses_reminder(): void
    {
        $user = $this->user();
        $theirs = Reminder::factory()->for(User::factory()->create())->create(['remind_on' => '2026-10-28']);

        Livewire::actingAs($user)->test(PrepareTomorrow::class)->call('toggleReminder', $theirs->id);

        $this->assertNull($theirs->fresh()->acknowledged_at);
    }

    // ── Tagesüberblick ────────────────────────────────────────────────

    public function test_the_tagesueberblick_has_a_tile_only_when_something_is_open_for_today_or_tomorrow(): void
    {
        $user = $this->user();
        Livewire::actingAs($user)->test(DayPreview::class)->assertDontSee('Nicht vergessen');

        Reminder::factory()->for($user)->create(['title' => 'Turnschuhe', 'remind_on' => '2026-10-28']);
        Reminder::factory()->for($user)->acknowledged()->create(['title' => 'Schon dabei', 'remind_on' => '2026-10-27']);

        Livewire::actingAs($user)->test(DayPreview::class)->assertSee('Nicht vergessen')->assertSee('Turnschuhe')->assertDontSee('Schon dabei');
    }

    public function test_the_morning_push_summary_counts_todays_open_reminders(): void
    {
        $user = $this->user();
        Reminder::factory()->for($user)->create(['remind_on' => '2026-10-27']);
        Reminder::factory()->for($user)->create(['remind_on' => '2026-10-27']);
        Reminder::factory()->for($user)->acknowledged()->create(['remind_on' => '2026-10-27']);

        $this->assertStringContainsString('2 Sachen nicht vergessen', DayPreviewData::notificationSummary($user));
    }
}
