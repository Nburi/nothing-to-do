<?php

namespace Tests\Unit\Planning;

use App\Planning\Crunch\AlertState;
use App\Planning\Crunch\EarlyWarning;
use App\Planning\Crunch\Forecast;
use App\Planning\Crunch\Level;
use App\Planning\DayLoad;
use App\Planning\FeasibilityResult;
use PHPUnit\Framework\TestCase;

/**
 * Step 1.4 (R5, REQUIREMENTS § 4, risk F7): the early warning switches on at
 * the first missing minute, off only with a session of room, warns about hard
 * deadlines only, and at most once a day.
 */
class EarlyWarningTest extends TestCase
{
    private const MON = '2026-11-09';

    private const TUE = '2026-11-10';

    public function test_no_crunch_and_no_warning_stays_quiet(): void
    {
        $decision = $this->evaluate($this->calm(slack: 200), AlertState::none(), self::MON);

        $this->assertFalse($decision->state->active);
        $this->assertFalse($decision->notify);
        $this->assertFalse($decision->cleared);
    }

    public function test_a_hard_crunch_warns_once(): void
    {
        $first = $this->evaluate($this->hard(missing: 50, on: '2026-11-12'), AlertState::none(), self::MON);

        $this->assertTrue($first->state->active);
        $this->assertTrue($first->notify);
        $this->assertSame(Level::Hard, $first->state->level);
        $this->assertSame('2026-11-12', $first->state->crunchDate);
        $this->assertSame(50, $first->state->missingMinutes);
        $this->assertSame(self::MON, $first->state->raisedOn);

        $again = $this->evaluate($this->hard(missing: 60, on: '2026-11-12'), $first->state, self::MON);
        $this->assertTrue($again->state->active);
        $this->assertFalse($again->notify, 'still the same crunch');
        $this->assertSame(60, $again->state->missingMinutes);
    }

    public function test_it_stays_on_while_there_is_less_than_a_session_of_room(): void
    {
        $on = $this->evaluate($this->hard(missing: 10), AlertState::none(), self::MON)->state;

        $tight = $this->evaluate($this->calm(slack: 15), $on, self::MON);

        $this->assertTrue($tight->state->active, 'hysteresis: fits, but only just');
        $this->assertFalse($tight->cleared);
        $this->assertSame(0, $tight->state->missingMinutes);
    }

    public function test_it_switches_off_with_a_session_of_room(): void
    {
        $on = $this->evaluate($this->hard(missing: 10), AlertState::none(), self::MON)->state;

        $off = $this->evaluate($this->calm(slack: EarlyWarning::CLEAR_SLACK_MINUTES), $on, self::MON);

        $this->assertFalse($off->state->active);
        $this->assertTrue($off->cleared);
        $this->assertSame(self::MON, $off->state->raisedOn, 'remembered for the daily limit');
    }

    public function test_flicker_around_the_edge_gives_one_warning(): void
    {
        $state = AlertState::none();
        $notifications = 0;
        foreach ([[10, null], [null, 5], [5, null], [null, 20], [10, null], [null, 0]] as [$missing, $slack]) {
            $forecast = $missing === null ? $this->calm(slack: $slack) : $this->hard(missing: $missing);
            $decision = $this->evaluate($forecast, $state, self::MON);
            $notifications += $decision->notify ? 1 : 0;
            $state = $decision->state;
        }

        $this->assertSame(1, $notifications);
        $this->assertTrue($state->active);
    }

    public function test_at_most_one_warning_a_day_even_after_it_cleared(): void
    {
        $on = $this->evaluate($this->hard(missing: 10), AlertState::none(), self::MON)->state;
        $off = $this->evaluate($this->calm(slack: 100), $on, self::MON)->state;

        $sameDay = $this->evaluate($this->hard(missing: 10), $off, self::MON);
        $this->assertTrue($sameDay->state->active);
        $this->assertFalse($sameDay->notify);

        $nextDay = $this->evaluate($this->hard(missing: 10), $off, self::TUE);
        $this->assertTrue($nextDay->notify);
        $this->assertSame(self::TUE, $nextDay->state->raisedOn);
    }

    public function test_a_soft_crunch_is_shown_quietly_and_warns_when_it_turns_hard(): void
    {
        $soft = $this->evaluate($this->soft(missing: 30), AlertState::none(), self::MON);
        $this->assertTrue($soft->state->active);
        $this->assertSame(Level::Soft, $soft->state->level);
        $this->assertFalse($soft->notify, 'soft deadlines are never pushed');
        $this->assertNull($soft->state->raisedOn);

        $hard = $this->evaluate($this->hard(missing: 30), $soft->state, self::MON);
        $this->assertTrue($hard->notify);
        $this->assertSame(Level::Hard, $hard->state->level);
    }

    public function test_without_deadlines_an_active_warning_clears(): void
    {
        $on = $this->evaluate($this->hard(missing: 10), AlertState::none(), self::MON)->state;

        $this->assertTrue($this->evaluate($this->calm(slack: null), $on, self::MON)->cleared);
    }

    private function evaluate(Forecast $forecast, AlertState $previous, string $today)
    {
        return (new EarlyWarning)->evaluate($forecast, $previous, $today);
    }

    private function hard(int $missing, string $on = '2026-11-11'): Forecast
    {
        return $this->crunch($missing, $on, hard: true);
    }

    private function soft(int $missing, string $on = '2026-11-11'): Forecast
    {
        return $this->crunch($missing, $on, hard: false);
    }

    private function crunch(int $missing, string $on, bool $hard): Forecast
    {
        $plan = new FeasibilityResult([1 => null], [], [1], $hard ? [] : [1], [1 => $missing], $on, []);

        return new Forecast([new DayLoad($on, 4, 100, 100 + $missing, true)], $plan, $hard ? [1] : [], $hard ? [] : [1], -$missing);
    }

    private function calm(?int $slack): Forecast
    {
        return new Forecast([new DayLoad(self::MON, 4, 100, 50, false)], new FeasibilityResult([], [], [], [], [], null, []), [], [], $slack);
    }
}
