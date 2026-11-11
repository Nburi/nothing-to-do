<?php

namespace Tests\Feature\Refactor;

use App\Models\Area;
use App\Models\EventCategory;
use App\Models\Task;
use App\Models\User;
use App\Planning\Battery;
use App\Planning\ChangeScope;
use App\Planning\Pick\Reason;
use App\Planning\Session\Phase;
use App\Planning\Size;
use App\Services\FocusSession;
use App\Services\Planning\Capacity;
use App\Services\Planning\Capture;
use App\Services\Planning\CrunchDecisions;
use App\Services\Planning\CrunchWatch;
use App\Services\Planning\SoftSlips;
use App\Services\Planning\Suggestions;
use App\Services\Planning\SuggestionStatus;
use App\Services\Planning\WeekPlanEditor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Step 1.8 (review): the engines of phase 1 together, the way the evening
 * loop will use them (REFACTOR_PLAN § 2), without any UI. One user, one week
 * from Monday 9 November 2026 in Zurich. Each block names the requirement it walks.
 */
class EveningFlowTest extends TestCase
{
    use RefreshDatabase;

    private function at(string $dateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, 'Europe/Zurich');
    }

    public function test_one_week_of_evenings_through_every_engine(): void
    {
        app()->setLocale('de');
        $this->travelTo($this->at('2026-11-09 07:00'));
        $user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
        $plan = new WeekPlanEditor;
        $suggestions = new Suggestions;
        $sessions = new FocusSession;

        // R10: categories with a pomodoro switch, areas linked to them.
        $school = Area::factory()->for($user)->create(['name' => 'Schule']);
        $sport = Area::factory()->for($user)->create(['name' => 'Sport']);
        $deepWork = EventCategory::factory()->for($user)->pomodoro()->create(['name' => 'Deep work']);
        $deepWork->syncAreas([$school->id]);
        $training = EventCategory::factory()->for($user)->create(['name' => 'Training']);

        // R4: the week plan, a one-week change and a holiday day.
        $evenings = $plan->addRepeating($user, [1, 2, 3, 4, 5], '19:30', '21:30', $deepWork->id);
        $plan->addRepeating($user, [2, 4], '18:00', '19:30', $training->id);
        $plan->changeOccurrence($user, $evenings->id, '2026-11-11', '20:00', '21:00', $deepWork->id, ChangeScope::ThisWeekOnly);
        $plan->pause($user, '2026-11-13', '2026-11-13', 'Brückentag');
        $this->assertSame([4, 4, 2, 4, 0], array_map(
            fn ($d) => $d->capacitySessions,
            array_slice((new Capacity)->forecast($user, $this->at('2026-11-09 07:00'))->days, 0, 5),
        ));

        // R3: capture is one gesture; the daily sort gives list, size, area and dates.
        $capture = new Capture;
        $math = $capture->inbox($user, 'Mathe Blatt 3');
        $form = $capture->inbox($user, 'Formular abgeben');
        $run = $capture->inbox($user, 'Laufroute für Samstag');
        $math->update(['list' => 'tasks', 'size' => Size::Long, 'area_id' => $school->id, 'hard_deadline' => '2026-11-10', 'first_step' => 'Aufgabe 1']);
        $form->update(['list' => 'todos', 'size' => Size::Quick, 'area_id' => $school->id]);
        $run->update(['list' => 'todos', 'size' => Size::Quick, 'area_id' => $sport->id]);

        // R1 / A19: before the block nothing is suggested, only when work time begins.
        $before = $suggestions->current($user, $this->at('2026-11-09 18:00'));
        $this->assertSame(SuggestionStatus::OutsideBlocks, $before->status);
        $this->assertTrue($before->nextBlockAt->equalTo($this->at('2026-11-09 19:30')));

        // R1 / A1: a tired evening starts with the warm-up; Sport is not linked to Deep work.
        $tired = $suggestions->setBattery($user, Battery::Low, $this->at('2026-11-09 19:31'));
        $this->assertSame($form->id, $tired->task->id);
        $this->assertSame(Reason::WarmUp, $tired->pick->reason);
        $this->assertSame(1, $tired->alternatives, 'only Mathe is left inside Deep work');

        // R9: the session, Done inside the session with time left, then the break.
        $sessions->start($user, $form->id, $this->at('2026-11-09 19:31'));
        $state = $sessions->done($user, $form->id, $this->at('2026-11-09 19:40'));
        $this->assertSame(Phase::Work, $state->phase);
        $this->assertSame($math->id, $state->taskId, 'the next suggestion, inside the same session');
        $state = $sessions->current($user, $this->at('2026-11-09 19:57'));
        $this->assertSame(Phase::ShortBreak, $state->phase);
        $this->assertSame($math->id, $state->taskId, 'unfinished: carries over');
        $this->assertSame(Phase::Ready, $sessions->current($user, $this->at('2026-11-09 20:02'))->phase);
        $sessions->stop($user, $this->at('2026-11-09 20:02'));

        // R5: tomorrow's hard deadline fits: no warning. A huge task due Wednesday does not.
        $this->assertFalse((new CrunchWatch)->check($user, $this->at('2026-11-09 20:05'))->state->active);
        $report = $capture->inbox($user, 'Bericht');
        $report->update(['list' => 'tasks', 'size' => Size::Block, 'duration_minutes' => 300, 'area_id' => $school->id, 'hard_deadline' => '2026-11-11']);
        $warning = (new CrunchWatch)->check($user, $this->at('2026-11-09 20:10'));
        $this->assertTrue($warning->notify);
        $this->assertSame('2026-11-11', $warning->state->crunchDate);

        // R5: the user decides: drop → Later, nothing deleted; the warning clears.
        (new CrunchDecisions)->drop($user, $report->id);
        $this->assertSame('later', $report->fresh()->list);
        $this->assertTrue((new CrunchWatch)->check($user, $this->at('2026-11-09 20:15'))->cleared);

        // G2: a soft deadline missed twice ends in Later.
        $run->update(['soft_deadline' => '2026-11-09']);
        (new SoftSlips)->apply($user, $this->at('2026-11-10 07:00'));
        $this->assertSame('2026-11-16', $run->fresh()->soft_deadline->toDateString());
        (new SoftSlips)->apply($user, $this->at('2026-11-17 07:00'));
        $this->assertSame('later', $run->fresh()->list);

        // R4: on the paused Friday there is no work time at all.
        $this->assertSame(SuggestionStatus::OutsideBlocks, $suggestions->current($user, $this->at('2026-11-13 20:00'))->status);

        // R8: nothing the user created was deleted along the way.
        $this->assertSame(4, Task::query()->where('user_id', $user->id)->count());
    }
}
