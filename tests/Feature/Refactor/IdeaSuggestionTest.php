<?php

namespace Tests\Feature\Refactor;

use App\Livewire\Shell\Board;
use App\Models\Evening;
use App\Models\EventCategory;
use App\Models\Task;
use App\Models\User;
use App\Planning\Battery;
use App\Planning\Session\Phase;
use App\Planning\Size;
use App\Services\FocusSession;
use App\Services\Planning\EveningReset;
use App\Services\Planning\FreeTimeIdeas;
use App\Services\Planning\IdeaStatus;
use App\Services\Planning\Suggestions;
use App\Services\Planning\WeekPlanEditor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A10 (R6, CONVENTIONS 3, 4, 12; risk F9): ideas from Later come back only in real free time —
 * outside fixed and pomodoro blocks, never during a session — and only when deadline work is
 * covered. One idea per free slot, never the same idea twice a day, "Meins behalten" respected,
 * and a whole free day gets the bolder card.
 *
 * Week plan Mon–Fri: Training (fixed) 18:00–19:00, Deep work (pomodoro) 19:30–21:30, Zurich.
 * Monday 9 November 2026. Free: 06:00–18:00, 19:00–19:30, 21:30–24:00. Weekends are free.
 */
class IdeaSuggestionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);

        $training = EventCategory::factory()->for($this->user)->create(['name' => 'Training']);
        $deep = EventCategory::factory()->for($this->user)->pomodoro()->create(['name' => 'Deep work']);
        (new WeekPlanEditor)->addRepeating($this->user, [1, 2, 3, 4, 5], '18:00', '19:00', $training->id);
        (new WeekPlanEditor)->addRepeating($this->user, [1, 2, 3, 4, 5], '19:30', '21:30', $deep->id);
    }

    private function at(string $local): CarbonImmutable
    {
        $now = CarbonImmutable::parse($local, 'Europe/Zurich');
        $this->travelTo($now);

        return $now;
    }

    private function task(string $title, string $list = 'later', ?Size $size = null, array $extra = []): Task
    {
        static $order = 0;

        return Task::forceCreate(array_merge(['user_id' => $this->user->id, 'title' => $title, 'list' => $list, 'size' => $size, 'sort_order' => $order++], $extra));
    }

    private function ideas(): FreeTimeIdeas
    {
        return app(FreeTimeIdeas::class);
    }

    /** The board with tonight's reset behind us (step 3.2), so nothing covers it. */
    private function board(): string
    {
        app(EveningReset::class)->close($this->user);
        app(EveningReset::class)->answerBattery($this->user, null);

        return $this->actingAs($this->user)->get('/board')->assertOk()->getContent();
    }

    public function test_free_time_after_the_last_block_offers_one_idea_with_where_to_start(): void
    {
        $this->at('2026-11-09 21:45');
        $this->task('Kompass-App skizzieren', extra: ['first_step' => 'Drei Screens auf Papier']);
        $this->task('Fotoalbum Sommer');

        $offer = $this->ideas()->current($this->user);
        $this->assertSame(IdeaStatus::Offer, $offer->status);
        $this->assertSame('Kompass-App skizzieren', $offer->task->title);
        $this->assertTrue($offer->slot->untilMidnight);
        $this->assertSame(1, $offer->alternatives);

        $html = $this->board();
        $this->assertStringContainsString('Frei bis Mitternacht · Idee aus Später', $html);
        $this->assertStringContainsString('Drei Screens auf Papier', $html);
        $this->assertStringContainsString('data-idea="slot"', $html);
    }

    public function test_the_buttons_keep_their_order_on_every_battery_level(): void
    {
        $this->at('2026-11-09 21:45');
        $this->task('Kompass-App skizzieren');

        foreach ([Battery::Low, Battery::Okay, Battery::Good] as $battery) {
            app(Suggestions::class)->setBattery($this->user, $battery);
            $html = $this->board();

            $do = strpos($html, 'Jetzt machen');
            $mine = strpos($html, 'Meins behalten', $do);
            $another = strpos($html, 'Andere', $mine);
            $this->assertNotFalse($do, $battery->value);
            $this->assertTrue($do < $mine && $mine < $another, "order on battery {$battery->value}");
        }
    }

    public function test_no_idea_inside_a_pomodoro_block(): void
    {
        $this->at('2026-11-09 20:00');
        $this->task('Kompass-App skizzieren');
        $this->task('Formular', 'todos', Size::Quick);

        $this->assertSame(IdeaStatus::Hidden, $this->ideas()->current($this->user)->status);
        $this->assertStringNotContainsString('Idee aus Später', $this->board());
    }

    public function test_no_idea_inside_a_fixed_block(): void
    {
        $this->at('2026-11-09 18:20');
        $this->task('Kompass-App skizzieren');

        $offer = $this->ideas()->current($this->user);
        $this->assertSame(IdeaStatus::Hidden, $offer->status);
        $this->assertFalse($offer->slotIsNow);
        $this->assertStringNotContainsString('Idee aus Später', $this->board());
    }

    public function test_no_idea_during_a_session_even_in_free_time(): void
    {
        $this->at('2026-11-09 21:45');
        $this->task('Kompass-App skizzieren');
        $todo = $this->task('Formular', 'todos', Size::Quick);
        app(FocusSession::class)->start($this->user, $todo->id);

        $this->assertSame(IdeaStatus::Session, $this->ideas()->current($this->user)->status);
        $html = $this->board();
        $this->assertStringContainsString('data-shell-mode="session"', $html);
        $this->assertStringNotContainsString('Idee aus Später', $html);
        $this->assertNull(Evening::for($this->user, '2026-11-09')->idea_task_id, 'nothing is claimed during a session');
    }

    public function test_too_little_time_left_or_the_night_offers_nothing(): void
    {
        $this->task('Kompass-App skizzieren');

        $this->at('2026-11-09 23:40');
        $this->assertSame(IdeaStatus::Hidden, $this->ideas()->current($this->user)->status);

        $this->at('2026-11-10 02:00');
        $offer = $this->ideas()->current($this->user);
        $this->assertSame(IdeaStatus::Hidden, $offer->status);
        $this->assertSame('06:00', $offer->slot->from->format('H:i'), 'the next free time starts in the morning');
    }

    public function test_no_idea_when_a_deadline_does_not_fit_and_the_board_says_which(): void
    {
        $this->at('2026-11-09 21:45');
        $this->task('Kompass-App skizzieren');
        $this->task('Laborbericht', 'tasks', Size::Short, ['hard_deadline' => '2026-11-09']);

        $offer = $this->ideas()->current($this->user);
        $this->assertSame(IdeaStatus::Deadlines, $offer->status);
        $this->assertSame('Laborbericht', $offer->blocking->title);

        $html = $this->board();
        $this->assertStringNotContainsString('Jetzt machen', $html);
        $this->assertStringContainsString('Keine Idee aus Später, solange „Laborbericht“ nicht in deine Arbeitsblöcke passt.', $html);
    }

    public function test_deadline_work_that_fits_into_the_blocks_does_not_stop_the_idea(): void
    {
        $this->at('2026-11-09 21:45');
        $this->task('Kompass-App skizzieren');
        $this->task('Laborbericht', 'tasks', Size::Short, ['hard_deadline' => '2026-11-13']);
        $this->task('Präsentation', 'tasks', Size::Short, ['soft_deadline' => '2026-11-12']);

        $this->assertSame(IdeaStatus::Offer, $this->ideas()->current($this->user)->status);
    }

    public function test_a_slot_kept_mine_offers_nothing_and_says_the_rest_is_yours(): void
    {
        $this->at('2026-11-09 21:45');
        $this->task('Kompass-App skizzieren');
        $this->task('Fotoalbum Sommer');

        $offer = $this->ideas()->keepMine($this->user);
        $this->assertSame(IdeaStatus::Mine, $offer->status);
        $this->assertNull($offer->task);
        $this->assertSame(IdeaStatus::Mine, $this->ideas()->another($this->user)->status, '"Andere" brings nothing back');

        $this->at('2026-11-09 23:00');
        $this->assertSame(IdeaStatus::Mine, $this->ideas()->current($this->user)->status, 'still mine later in the slot');

        $html = $this->board();
        $this->assertStringContainsString('Der Rest gehört dir.', $html);
        $this->assertStringContainsString('Heute Abend kommt keine Idee mehr.', $html);
        $this->assertStringContainsString('Frei bis Mitternacht · gehört dir', $html);
        $this->assertStringNotContainsString('Jetzt machen', $html);
    }

    public function test_keep_mine_during_a_block_keeps_the_next_free_slot_and_only_that_one(): void
    {
        $this->task('Kompass-App skizzieren');

        $this->at('2026-11-09 20:00'); // inside Deep work
        $this->ideas()->keepMine($this->user);

        $this->at('2026-11-09 21:45');
        $this->assertSame(IdeaStatus::Mine, $this->ideas()->current($this->user)->status);

        $this->at('2026-11-10 12:00'); // the next day is not touched
        $this->assertSame(IdeaStatus::Offer, $this->ideas()->current($this->user)->status);
    }

    public function test_keep_mine_can_be_undone(): void
    {
        $this->at('2026-11-09 21:45');
        $this->task('Kompass-App skizzieren');
        $this->ideas()->keepMine($this->user);

        $this->assertSame(IdeaStatus::Offer, $this->ideas()->undoMine($this->user)->status);
    }

    public function test_the_same_slot_keeps_its_idea_across_reloads(): void
    {
        $this->at('2026-11-09 21:45');
        $this->task('Kompass-App skizzieren');
        $this->task('Fotoalbum Sommer');

        $first = $this->ideas()->current($this->user)->task->id;
        $this->at('2026-11-09 22:30');
        $this->assertSame($first, $this->ideas()->current($this->user)->task->id);
        $this->assertSame([$first], Evening::for($this->user, '2026-11-09')->shownIdeaIds());
    }

    public function test_no_idea_is_offered_twice_the_same_day(): void
    {
        $a = $this->task('Kompass-App skizzieren');
        $b = $this->task('Fotoalbum Sommer');
        $c = $this->task('Velo-Tour planen');

        $this->at('2026-11-09 17:00'); // free until Training
        $this->assertSame($a->id, $this->ideas()->current($this->user)->task->id);
        $this->assertSame($b->id, $this->ideas()->another($this->user)->task->id);

        $this->at('2026-11-09 21:45'); // the next free slot: neither A nor B again
        $this->assertSame($c->id, $this->ideas()->current($this->user)->task->id);

        $exhausted = $this->ideas()->another($this->user);
        $this->assertSame(IdeaStatus::Exhausted, $exhausted->status, 'no wrap-around to an idea seen today');
        $this->assertStringContainsString('Alle Ideen aus Später waren heute schon dran.', $this->board());

        $this->at('2026-11-10 12:00'); // a new day starts fresh
        $this->assertSame($a->id, $this->ideas()->current($this->user)->task->id);
    }

    public function test_a_finished_idea_makes_room_for_the_next_one_in_the_same_slot(): void
    {
        $this->at('2026-11-09 21:45');
        $a = $this->task('Kompass-App skizzieren');
        $b = $this->task('Fotoalbum Sommer');

        $this->assertSame($a->id, $this->ideas()->current($this->user)->task->id);
        $a->update(['is_completed' => true]);
        $this->assertSame($b->id, $this->ideas()->current($this->user)->task->id);
    }

    public function test_a_whole_free_day_gets_the_bolder_card_and_prefers_a_bigger_item(): void
    {
        $this->at('2026-11-14 10:00'); // Saturday, no blocks
        $this->task('Velo putzen');
        $big = $this->task('Fotoalbum Sommer', size: Size::Long);

        $offer = $this->ideas()->current($this->user);
        $this->assertSame(IdeaStatus::Offer, $offer->status);
        $this->assertTrue($offer->slot->wholeDay);
        $this->assertSame($big->id, $offer->task->id);

        $html = $this->board();
        $this->assertStringContainsString('Ganzer Tag frei · Idee aus Später', $html);
        $this->assertStringContainsString('data-idea="whole-day"', $html);

        // Still only a suggestion: nothing was started, moved or planned.
        $this->assertSame(Phase::Idle, app(FocusSession::class)->current($this->user)->phase);
        $this->assertSame('later', $big->fresh()->list);
    }

    public function test_an_empty_later_list_keeps_the_plain_free_time_card(): void
    {
        $this->at('2026-11-09 21:45');
        $this->task('Formular', 'todos', Size::Quick);

        $this->assertSame(IdeaStatus::Hidden, $this->ideas()->current($this->user)->status);
        $html = $this->board();
        $this->assertStringContainsString('Gerade kein Arbeitsblock', $html);
        $this->assertStringNotContainsString('Idee aus Später', $html);
    }

    public function test_do_it_now_starts_a_session_that_never_leads_into_work(): void
    {
        $this->at('2026-11-09 21:45');
        $idea = $this->task('Kompass-App skizzieren');
        $todo = $this->task('Formular', 'todos', Size::Quick);
        $this->board();

        $component = Livewire::actingAs($this->user)->test(Board::class)->call('start', $idea->id);
        $this->assertSame($idea->id, app(FocusSession::class)->current($this->user)->taskId);
        $this->assertNull(app(Suggestions::class)->nextInSession($this->user, $idea->id), 'no "Dann: …" into work');

        $this->travel(10)->minutes();
        $component->call('done');

        $this->assertTrue($idea->fresh()->is_completed);
        $this->assertFalse($todo->fresh()->is_completed);
        $this->assertNotSame($todo->id, app(FocusSession::class)->current($this->user)->taskId, 'free time is not filled with work');
    }

    public function test_keep_mine_and_another_work_from_the_board(): void
    {
        $this->at('2026-11-09 21:45');
        $this->task('Kompass-App skizzieren');
        $this->task('Fotoalbum Sommer');
        $this->board();

        Livewire::actingAs($this->user)->test(Board::class)
            ->assertSee('Kompass-App skizzieren')
            ->call('anotherIdea')
            ->assertSee('Fotoalbum Sommer')
            ->call('keepMine')
            ->assertSee('Der Rest gehört dir.')
            ->assertDontSee('Jetzt machen')
            ->call('undoMine')
            ->assertSee('Jetzt machen');
    }

    public function test_the_free_time_line_offers_keep_mine_in_both_layouts_while_working(): void
    {
        $this->at('2026-11-09 20:00');
        $this->task('Formular', 'todos', Size::Quick);

        $html = $this->board();
        $this->assertStringContainsString('Frei ab 21:30', $html);
        $this->assertStringContainsString('data-action="keep_mine" data-layout="werkbank" data-mode="board"', $html);
        $this->assertStringContainsString('data-action="keep_mine" data-layout="stapel" data-mode="board"', $html);
    }
}
