<?php

namespace Tests\Feature\Refactor;

use App\Livewire\Shell\Board;
use App\Models\Evening;
use App\Models\EventCategory;
use App\Models\SchedulePause;
use App\Models\Task;
use App\Models\User;
use App\Planning\Battery;
use App\Planning\ResetStage;
use App\Planning\Size;
use App\Services\FocusSession;
use App\Services\Planning\EveningReset;
use App\Services\Planning\WeekPlanEditor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Step 3.2: the ten minute reset and the battery question at its end (R2, G4, CONVENTIONS 11).
 * Monday 9 November 2026 in Zurich; the reset is due from 17:00 local (EveningReset::DUE_FROM).
 */
class EveningResetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');
        $this->at('20:00');
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
        $deep = EventCategory::factory()->for($this->user)->pomodoro()->create(['name' => 'Deep work']);
        (new WeekPlanEditor)->addRepeating($this->user, [1, 2, 3, 4, 5], '19:30', '21:30', $deep->id);
        Task::forceCreate(['user_id' => $this->user->id, 'title' => 'Formular', 'list' => 'todos', 'size' => Size::Quick]);
    }

    private function at(string $time, string $date = '2026-11-09'): void
    {
        $this->travelTo(CarbonImmutable::parse("{$date} {$time}", 'Europe/Zurich'));
    }

    private function board(array $query = [])
    {
        return Livewire::withQueryParams($query)->actingAs($this->user)->test(Board::class);
    }

    private function stage(): ResetStage
    {
        return app(EveningReset::class)->stage($this->user);
    }

    private function evening(): ?Evening
    {
        return Evening::for($this->user, '2026-11-09');
    }

    public function test_the_first_visit_of_the_evening_shows_the_calm_reset_screen_over_the_board(): void
    {
        $html = $this->actingAs($this->user)->get('/board')->assertOk()
            ->assertSee('data-reset="offer"', false)
            ->assertSee(['Zehn Minuten nur für dich.', '10:00', 'Duschen', 'Etwas essen', 'Kurz raus', 'Los', 'Überspringen'])
            ->getContent();

        // The board is not visible during the reset: both layouts are inert behind it.
        $this->assertSame(2, preg_match_all('/data-layout-root="(werkbank|stapel)"[^>]*\binert\b/', $html));
    }

    public function test_before_the_evening_nothing_is_offered_unless_the_nudge_link_asks(): void
    {
        $this->at('15:00');

        $this->actingAs($this->user)->get('/board')->assertOk()->assertSee('data-reset="hidden"', false)->assertDontSee('Zehn Minuten nur für dich.');
        $this->actingAs($this->user)->get('/board?reset=1')->assertOk()->assertSee('data-reset="offer"', false);
    }

    public function test_los_starts_ten_minutes_that_a_reload_and_a_second_device_show_the_same(): void
    {
        $this->board()->call('beginReset')->assertSee('data-reset="running"', false)->assertSee('Schon fertig');

        $this->at('20:03');

        // A reload, and the phone: both read the server's state, with the time that is left.
        $this->actingAs($this->user)->get('/board')->assertSee('data-reset="running"', false)->assertSee('sessionTimer(420, 600, true)', false);
        $this->assertSame(420, app(EveningReset::class)->remainingSeconds($this->user));
        $this->board()->assertSee('data-reset="running"', false);
    }

    public function test_after_ten_minutes_the_battery_is_asked_once_and_then_the_board_comes(): void
    {
        $this->board()->call('beginReset');
        $this->at('20:10');

        $this->board()
            ->assertSee('data-reset="battery"', false)
            ->assertSee(['Wie viel Akku hast du heute?', 'Leer', '2 kleine Sachen', 'Okay', 'normal', 'Voll', 'das Grosse zuerst', 'Später'])
            ->call('answerBattery', 'low')
            ->assertSee('data-reset="hidden"', false)
            ->assertSee('Akku: Leer');

        $this->assertSame('low', $this->evening()->battery);

        // Not offered again tonight, not even from the nudge link.
        $this->at('21:00');
        $this->assertSame(ResetStage::Hidden, $this->stage());
        $this->board(['reset' => '1'])->assertSee('data-reset="hidden"', false);
    }

    public function test_skipping_is_silent_and_later_counts_as_okay(): void
    {
        $board = $this->board()->call('closeReset')
            ->assertSet('notice', null)
            ->assertSee('data-reset="battery"', false)
            ->call('answerBattery', 'later')
            ->assertSet('notice', null)
            ->assertSee('data-reset="hidden"', false)
            ->assertSee('Akku: Okay');

        $evening = $this->evening();
        $this->assertNull($evening->battery, 'Später leaves the level unset, which is Okay');
        $this->assertSame(Battery::Okay, $evening->batteryLevel());
        $this->assertNull($evening->reset_started_at, 'a skipped reset was never started');
        $this->assertNotNull($evening->reset_closed_at);

        // Nothing counted: the row only knows tonight's reset is closed, there is no tally anywhere.
        $this->assertSame(1, Evening::count());
        $this->assertSame(ResetStage::Hidden, $this->stage());

        // The chip still changes it later.
        $board->call('setBattery', 'good')->assertSee('Akku: Voll');
    }

    public function test_ending_early_closes_it_and_asks_the_battery(): void
    {
        $this->board()->call('beginReset');
        $this->at('20:04');

        $this->board()->call('closeReset')->assertSee('data-reset="battery"', false);
        $this->assertSame(ResetStage::Battery, $this->stage());
    }

    public function test_a_battery_set_on_the_chip_earlier_tonight_is_not_asked_again(): void
    {
        $this->board()->call('setBattery', 'good');
        $this->board()->call('closeReset')->assertSee('data-reset="hidden"', false);

        $this->assertSame('good', $this->evening()->battery);
    }

    public function test_never_offered_on_a_paused_day_but_can_be_started_by_hand(): void
    {
        SchedulePause::forceCreate(['user_id' => $this->user->id, 'date' => '2026-11-09']);

        $this->actingAs($this->user)->get('/board')->assertSee('data-reset="hidden"', false);
        $this->actingAs($this->user)->get('/board?reset=1')->assertSee('data-reset="hidden"', false);

        $this->board()->call('beginReset')->assertSee('data-reset="running"', false);
    }

    public function test_not_offered_during_a_running_session_but_abschalten_works_there(): void
    {
        $task = Task::where('title', 'Formular')->first();
        app(FocusSession::class)->start($this->user, $task->id);

        $this->board()->assertSee('data-shell-mode="session"', false)->assertSee('data-reset="hidden"', false)
            ->call('beginReset')->assertSee('data-reset="running"', false);
    }

    public function test_the_next_evening_offers_it_again(): void
    {
        $this->board()->call('closeReset')->call('answerBattery', 'okay');
        $this->assertSame(ResetStage::Hidden, $this->stage());

        $this->at('19:00', '2026-11-10');
        $this->assertSame(ResetStage::Offer, $this->stage());
    }

    public function test_two_devices_closing_at_once_keep_the_first_close(): void
    {
        $this->board()->call('closeReset');
        $first = $this->evening()->reset_closed_at;

        $this->at('20:02');
        $this->board()->call('closeReset');

        $this->assertTrue($first->equalTo($this->evening()->reset_closed_at));
    }

    public function test_an_unknown_battery_answer_is_ignored(): void
    {
        $this->board()->call('closeReset')->call('answerBattery', 'full-power')->assertSee('data-reset="battery"', false);
        $this->assertNull($this->evening()->battery_asked_at);
    }

    public function test_abschalten_is_offered_in_both_layouts_and_modes(): void
    {
        app(EveningReset::class)->close($this->user);
        app(EveningReset::class)->answerBattery($this->user, null);

        $html = $this->actingAs($this->user)->get('/board')->getContent();

        foreach (['werkbank', 'stapel'] as $layout) {
            $this->assertMatchesRegularExpression('/data-action="reset" data-layout="'.$layout.'" data-mode="board"/', $html);
        }
        $this->assertStringContainsString('wire:click="beginReset"', $html);
    }
}
