<?php

namespace Tests\Feature\Api;

use App\Models\Evening;
use App\Models\SchedulePause;
use App\Models\Task;
use App\Planning\Size;
use App\Services\FocusSession;
use App\Services\Planning\EveningReset;

/**
 * A72 (R2): an arrival is held back where the time-based nudge would be: on paused days, on a
 * weekday without a nudge time, once tonight's reset was started or closed, during a focus
 * session. Long before the nudge time it stays quiet too, so a lunch visit or a trip past the
 * house cannot use up the evening. A held-back arrival writes nothing: the evening's nudge
 * is still there for whoever may send it later.
 */
class ArrivalQuietTest extends ArrivalNudgeTestCase
{
    private function assertHeldBack(string $reason): void
    {
        $this->arrive()->assertOk()->assertJson(['sent' => false, 'delivered' => 0, 'reason' => $reason]);

        $this->assertSame(0, $this->pushes());
        $this->assertNull(Evening::for($this->user, now('Europe/Zurich')->toDateString())?->nudge_sent_at, 'a held-back arrival must not use up the evening');
    }

    public function test_nothing_on_a_paused_day(): void
    {
        SchedulePause::forceCreate(['user_id' => $this->user->id, 'date' => '2026-11-09']);
        $this->at('18:10');

        $this->assertHeldBack('paused');
    }

    public function test_nothing_on_a_weekday_without_a_nudge_time(): void
    {
        $this->at('18:10', '2026-11-14'); // Saturday has no time

        $this->assertHeldBack('no_time');
    }

    public function test_nothing_for_a_user_who_never_saved_the_nudge_card(): void
    {
        $this->user->forceFill(['nudge_times' => null])->save();
        $this->at('18:10');

        $this->assertHeldBack('no_time');
    }

    public function test_nothing_once_tonights_reset_was_started(): void
    {
        $this->at('18:00');
        app(EveningReset::class)->begin($this->user);

        $this->at('18:10');
        $this->assertHeldBack('reset_done');
    }

    public function test_nothing_once_tonights_reset_was_closed(): void
    {
        $this->at('18:00');
        app(EveningReset::class)->close($this->user);

        $this->at('18:10');
        $this->assertHeldBack('reset_done');
    }

    public function test_nothing_during_a_running_session_but_the_evening_stays_open(): void
    {
        $task = Task::forceCreate(['user_id' => $this->user->id, 'title' => 'Formular', 'list' => 'todos', 'size' => Size::Quick]);
        $this->at('18:00');
        app(FocusSession::class)->start($this->user, $task->id);

        $this->at('18:10');
        $this->assertHeldBack('in_session');

        app(FocusSession::class)->stop($this->user);
        $this->at('18:20');
        $this->arrive()->assertJson(['sent' => true]);
        $this->assertSame(1, $this->pushes());
    }

    public function test_nothing_long_before_the_nudge_time_and_the_time_based_nudge_still_comes(): void
    {
        $this->at('12:30'); // lunch at home, or a bus passing the house
        $this->assertHeldBack('too_early');

        $this->at('18:30');
        $this->tick();

        $this->assertSame(1, $this->pushes());
        $this->assertSame('nudge', $this->sent[0]['via']);
    }

    public function test_the_arrival_window_opens_two_hours_before_the_nudge_time(): void
    {
        $this->at('16:29'); // nudge time 18:30
        $this->assertHeldBack('too_early');

        $this->at('16:30');
        $this->arrive()->assertJson(['sent' => true]);
    }

    public function test_a_late_arrival_still_sends_when_nothing_went_out_before(): void
    {
        // No end of the window: coming home at 23:10 is a real signal, not a guess.
        $this->at('23:10');
        $this->arrive()->assertJson(['sent' => true]);

        $this->assertSame(1, $this->pushes());
    }
}
