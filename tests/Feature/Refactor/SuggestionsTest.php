<?php

namespace Tests\Feature\Refactor;

use App\Models\Area;
use App\Models\Evening;
use App\Models\EventCategory;
use App\Models\EventTemplate;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Models\User;
use App\Planning\Battery;
use App\Planning\Pick\Reason;
use App\Planning\Size;
use App\Services\Planning\Suggestions;
use App\Services\Planning\SuggestionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Refactor step 1.3: the pick engine on real rows (A1 wiring, R1, R5, R10, F3).
 * Monday 9 November 2026, Zurich. Deep work (pomodoro, area School) Mon–Fri
 * 19:30–21:30; Training (no pomodoro) Tue + Thu 18:00–19:30.
 */
class SuggestionsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Suggestions $suggestions;

    private EventCategory $deepWork;

    private Area $school;

    private Area $sport;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');
        $this->suggestions = new Suggestions;
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);

        $this->school = Area::factory()->for($this->user)->create(['name' => 'Schule']);
        $this->sport = Area::factory()->for($this->user)->create(['name' => 'Sport']);
        $this->deepWork = EventCategory::factory()->for($this->user)->pomodoro()->create(['name' => 'Deep work']);
        $this->deepWork->syncAreas([$this->school->id]);
        $training = EventCategory::factory()->for($this->user)->create(['name' => 'Training']);

        EventTemplate::factory()->for($this->user)->create([
            'category_id' => $this->deepWork->id, 'is_recurring' => true, 'recurrence' => '1,2,3,4,5',
            'default_start' => '19:30', 'duration' => 120,
        ]);
        EventTemplate::factory()->for($this->user)->create([
            'category_id' => $training->id, 'is_recurring' => true, 'recurrence' => '2,4',
            'default_start' => '18:00', 'duration' => 90,
        ]);
    }

    private function at(string $dateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, 'Europe/Zurich');
    }

    private function task(array $attributes = []): Task
    {
        return Task::factory()->for($this->user)->create(['list' => 'tasks', ...$attributes]);
    }

    public function test_inside_a_block_the_deadline_task_is_suggested_with_where_to_start_and_why(): void
    {
        $this->task(['title' => 'Antwort an Marco', 'list' => 'todos', 'size' => Size::Quick]);
        $math = $this->task([
            'title' => 'Mathe Blatt 3', 'size' => Size::Long, 'area_id' => $this->school->id,
            'hard_deadline' => '2026-11-09', 'notes' => "- [x] Blatt holen\n- [ ] Aufgabe 1, die einfache",
        ]);

        $suggestion = $this->suggestions->current($this->user, $this->at('2026-11-09 19:45'));

        $this->assertSame(SuggestionStatus::Pick, $suggestion->status);
        $this->assertSame($math->id, $suggestion->task->id);
        $this->assertSame(Reason::StartsToday, $suggestion->pick->reason);
        $this->assertSame('Aufgabe 1, die einfache', $suggestion->startWith);
        $this->assertSame('Muss heute anfangen, damit es bis heute fertig ist.', $suggestion->why);
        $this->assertSame(Battery::Okay, $suggestion->battery, 'G4: no check-in counts as Okay');
        $this->assertSame(1, $suggestion->alternatives);
    }

    public function test_a_deadline_that_still_fits_tomorrow_is_next_in_line(): void
    {
        $this->task(['list' => 'todos', 'size' => Size::Quick]);
        $math = $this->task(['size' => Size::Long, 'hard_deadline' => '2026-11-10']);

        $suggestion = $this->suggestions->current($this->user, $this->at('2026-11-09 19:45'));

        $this->assertSame($math->id, $suggestion->task->id, 'deadline work before the undated quick one');
        $this->assertSame(Reason::NextDeadline, $suggestion->pick->reason);
        $this->assertSame('Fällig bis morgen. Als Nächstes an der Reihe.', $suggestion->why);
    }

    public function test_the_first_step_wins_over_the_checklist(): void
    {
        $this->task(['first_step' => 'Ordner öffnen', 'notes' => '- [ ] etwas anderes']);

        $this->assertSame('Ordner öffnen', $this->suggestions->current($this->user, $this->at('2026-11-09 20:00'))->startWith);
    }

    public function test_a_task_without_first_step_or_checklist_has_no_start_with(): void
    {
        $this->task(['notes' => 'Nur ein Gedanke.']);

        $this->assertNull($this->suggestions->current($this->user, $this->at('2026-11-09 20:00'))->startWith);
    }

    public function test_low_battery_starts_with_the_warm_up(): void
    {
        $this->task(['title' => 'Mathe', 'size' => Size::Long, 'hard_deadline' => '2026-11-10']);
        $quick = $this->task(['title' => 'Formular', 'list' => 'todos', 'size' => Size::Quick]);

        $suggestion = $this->suggestions->setBattery($this->user, Battery::Low, $this->at('2026-11-09 19:40'));

        $this->assertSame($quick->id, $suggestion->task->id);
        $this->assertSame(Reason::WarmUp, $suggestion->pick->reason);
        $this->assertSame('Klein und schnell erledigt. Bringt dich in Gang.', $suggestion->why);
        $this->assertSame('low', Evening::sole()->battery);
    }

    public function test_the_battery_can_be_changed_and_skipped_later_the_same_evening(): void
    {
        $this->suggestions->setBattery($this->user, Battery::Good, $this->at('2026-11-09 19:40'));
        $this->suggestions->setBattery($this->user, null, $this->at('2026-11-09 20:10'));

        $this->assertDatabaseCount('evenings', 1);
        $this->assertSame(Battery::Okay, $this->suggestions->current($this->user, $this->at('2026-11-09 20:15'))->battery);
    }

    public function test_the_battery_is_asked_again_the_next_evening(): void
    {
        $this->suggestions->setBattery($this->user, Battery::Low, $this->at('2026-11-09 19:40'));

        $this->assertSame(Battery::Okay, $this->suggestions->current($this->user, $this->at('2026-11-10 19:40'))->battery);
    }

    public function test_another_skips_to_the_next_pick_and_logs_the_swap(): void
    {
        $first = $this->task(['title' => 'Eins', 'hard_deadline' => '2026-11-10']);
        $second = $this->task(['title' => 'Zwei']);
        $third = $this->task(['title' => 'Drei']);
        $now = $this->at('2026-11-09 19:45');

        $this->assertSame($first->id, $this->suggestions->current($this->user, $now)->task->id);
        $this->assertSame($second->id, $this->suggestions->another($this->user, $now)->task->id);
        $this->assertSame($third->id, $this->suggestions->another($this->user, $now)->task->id);
        $this->assertSame([$first->id, $second->id], Evening::sole()->skippedTaskIds());

        // Everything skipped once: Another starts over at the top instead of running dry.
        $this->assertSame($first->id, $this->suggestions->another($this->user, $now)->task->id);
        $this->assertSame([], Evening::sole()->skippedTaskIds());
    }

    public function test_another_with_a_single_task_keeps_showing_it(): void
    {
        $only = $this->task();

        $suggestion = $this->suggestions->another($this->user, $this->at('2026-11-09 19:45'));

        $this->assertSame($only->id, $suggestion->task->id);
        $this->assertSame(0, $suggestion->alternatives);
    }

    public function test_skips_belong_to_one_evening(): void
    {
        $first = $this->task(['hard_deadline' => '2026-11-12']);
        $this->task();
        $this->suggestions->another($this->user, $this->at('2026-11-09 19:45'));

        $this->assertSame($first->id, $this->suggestions->current($this->user, $this->at('2026-11-10 19:45'))->task->id);
    }

    public function test_the_block_category_filters_areas(): void
    {
        $this->task(['title' => 'Lauf planen', 'area_id' => $this->sport->id, 'hard_deadline' => '2026-11-09']);
        $school = $this->task(['title' => 'Vortrag', 'area_id' => $this->school->id]);

        $suggestion = $this->suggestions->current($this->user, $this->at('2026-11-09 20:00'));

        $this->assertSame($school->id, $suggestion->task->id, 'Sport is not linked to Deep work');
        $this->assertSame($this->deepWork->id, $suggestion->categoryId);
    }

    public function test_inbox_later_and_completed_tasks_are_never_suggested(): void
    {
        $this->task(['list' => 'inbox']);
        $this->task(['list' => 'later']);
        $this->task(['is_completed' => true, 'completed_at' => now()]);
        Task::factory()->create(['list' => 'tasks']); // someone else's

        $suggestion = $this->suggestions->current($this->user, $this->at('2026-11-09 20:00'));

        $this->assertSame(SuggestionStatus::Empty, $suggestion->status);
        $this->assertNull($suggestion->task);
    }

    public function test_a_fixed_appointment_inside_the_block_cuts_it(): void
    {
        $this->task();
        ScheduleEvent::factory()->for($this->user)->create([
            'date' => '2026-11-09', 'start_time' => '20:00', 'end_time' => '20:30', 'title' => 'Zahnarzt', 'category_id' => null,
        ]);

        $this->assertSame(SuggestionStatus::OutsideBlocks, $this->suggestions->current($this->user, $this->at('2026-11-09 20:10'))->status);
        $this->assertSame(SuggestionStatus::Pick, $this->suggestions->current($this->user, $this->at('2026-11-09 20:40'))->status);
    }

    public function test_a_late_hard_deadline_says_so(): void
    {
        $this->task(['title' => 'Bericht', 'size' => Size::Block, 'duration_minutes' => 300, 'hard_deadline' => '2026-11-09']);

        $suggestion = $this->suggestions->current($this->user, $this->at('2026-11-09 19:35'));

        $this->assertSame(Reason::Late, $suggestion->pick->reason);
        $this->assertSame('Bis heute wird es knapp. Am besten jetzt anfangen.', $suggestion->why);
    }

    public function test_the_why_line_is_english_for_english_users(): void
    {
        app()->setLocale('en');
        $this->task(['hard_deadline' => '2026-11-12', 'size' => Size::Long]);
        $this->task(['hard_deadline' => '2026-11-12', 'size' => Size::Block, 'duration_minutes' => 300]);

        $suggestion = $this->suggestions->current($this->user, $this->at('2026-11-09 19:35'));

        $this->assertSame('Has to start today to be done by Thursday.', $suggestion->why);
    }

    public function test_reading_a_suggestion_writes_nothing(): void
    {
        $this->task();

        $this->suggestions->current($this->user, $this->at('2026-11-09 20:00'));

        $this->assertDatabaseCount('evenings', 0);
        $this->assertDatabaseCount('schedule_events', 0);
    }

    public function test_umlauts_and_400_character_titles_survive(): void
    {
        $title = str_repeat('Ärger über Öl ', 28).'Ü';
        $this->task(['title' => $title, 'first_step' => 'Grösse prüfen']);

        $suggestion = $this->suggestions->current($this->user, $this->at('2026-11-09 20:00'));

        $this->assertSame($title, $suggestion->task->title);
        $this->assertSame('Grösse prüfen', $suggestion->startWith);
    }
}
