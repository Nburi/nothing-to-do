<?php

namespace Tests\Feature\Refactor;

use App\Livewire\Shell\Board;
use App\Models\EventCategory;
use App\Models\Task;
use App\Models\User;
use App\Planning\Battery;
use App\Planning\Session\Phase;
use App\Planning\Size;
use App\Services\FocusSession;
use App\Services\Planning\WeekPlanEditor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Step 2.2: the new shell wired to the phase 1 services (Suggestions, FocusSession, crunch
 * shrink). The engines have their own tests; these check the shell calls them correctly.
 */
class ShellBoardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');
        $this->travelTo(CarbonImmutable::parse('2026-11-09 20:00', 'Europe/Zurich'));
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
    }

    private function block(string $from = '19:30', string $to = '21:30'): void
    {
        $deep = EventCategory::factory()->for($this->user)->pomodoro()->create(['name' => 'Deep work']);
        (new WeekPlanEditor)->addRepeating($this->user, [1, 2, 3, 4, 5], $from, $to, $deep->id);
    }

    private function task(string $title, string $list = 'todos', ?Size $size = Size::Quick, array $extra = []): Task
    {
        return Task::forceCreate(array_merge(['user_id' => $this->user->id, 'title' => $title, 'list' => $list, 'size' => $size], $extra));
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/board')->assertRedirect('/login');
    }

    public function test_the_board_shows_four_lists_and_one_suggestion_with_where_to_start(): void
    {
        $this->block();
        $this->task('Gedanke', 'inbox', null);
        $this->task('Formular', 'todos', Size::Quick, ['first_step' => 'Seite 1 ausfüllen']);
        $this->task('Bericht', 'tasks', Size::Long);
        $this->task('Fotoalbum', 'later', null);

        $this->actingAs($this->user)->get('/board')
            ->assertOk()
            ->assertSee(['Inbox', 'To-dos', 'Tasks', 'Später'])
            ->assertSee(['Gedanke', 'Bericht', 'Fotoalbum'])
            ->assertSee('Hier starten')
            ->assertSee('Seite 1 ausfüllen')
            ->assertSee('data-shell-mode="board"', false);
    }

    public function test_an_empty_account_gets_a_welcome_not_a_blank_board(): void
    {
        $this->actingAs($this->user)->get('/board')
            ->assertOk()
            ->assertSee('Noch nichts da.')
            ->assertSee('Nichts wartet. Erfasse, was dir durch den Kopf geht.');
    }

    public function test_outside_blocks_it_says_when_the_next_block_starts_instead_of_suggesting(): void
    {
        $this->block('21:00', '22:00');
        $this->task('Formular');

        $this->actingAs($this->user)->get('/board')
            ->assertOk()
            ->assertSee('Gerade kein Arbeitsblock')
            ->assertSee('heute um 21:00')
            ->assertDontSee('Hier starten');
    }

    public function test_start_runs_a_session_on_the_server_and_the_board_folds_into_session_mode(): void
    {
        $this->block();
        $task = $this->task('Formular');

        Livewire::actingAs($this->user)->test(Board::class)
            ->call('start')
            ->assertSee('data-shell-mode="session"', false)
            ->assertSee('in Session')
            ->assertSee('25:00');

        $state = app(FocusSession::class)->current($this->user);
        $this->assertSame(Phase::Work, $state->phase);
        $this->assertSame($task->id, $state->taskId);
    }

    /**
     * Step 3.1: the board stays rendered (inert) during a session, so the switch is a CSS fold on
     * elements Livewire keeps, not a page swap (risk C5). CONVENTIONS 10: this must not be tidied
     * away, which is what this test guards.
     */
    public function test_the_board_stays_rendered_but_inert_during_a_session_so_it_can_fold(): void
    {
        $this->block();
        $task = $this->task('Formular');
        app(FocusSession::class)->start($this->user, $task->id);

        $html = $this->actingAs($this->user)->get('/board')->assertOk()->getContent();
        $xpath = $this->xpath($html);

        foreach (['werkbank', 'stapel'] as $layout) {
            $root = "//*[@data-layout-root='{$layout}']";
            $this->assertSame(1, $xpath->query("{$root}/section[@data-region='board'][@inert][@aria-hidden='true']")->length, "{$layout}: folded board, inert");
            $this->assertSame(1, $xpath->query("{$root}/section[@data-region='session']//*[@data-session-card]")->length, "{$layout}: session card");
        }
        $this->assertSame(4, $xpath->query("//*[@data-layout-root='werkbank']//*[contains(@class,'fold-col')]")->length, 'four columns fold');
        $this->assertSame(4, $xpath->query("//*[@data-layout-root='stapel']//*[contains(@class,'fold-pile')]")->length, 'four piles slide into the drawer');
        $this->assertSame(1, $xpath->query("//*[@data-layout-root='werkbank']//*[contains(concat(' ', @class, ' '),' fold-rail ')]")->length);
        $this->assertSame(1, $xpath->query("//*[@data-layout-root='stapel']//*[contains(concat(' ', @class, ' '),' fold-drawer ')]")->length);
    }

    public function test_starts_from_the_board_fold_and_starts_inside_a_session_do_not(): void
    {
        $this->block();
        $this->task('Formular');
        $this->task('Bericht', 'tasks', Size::Long);

        $board = $this->actingAs($this->user)->get('/board')->assertOk()->getContent();
        $xpath = $this->xpath($board);

        $this->assertSame(0, $xpath->query("//section[@data-region='board'][@inert]")->length, 'nothing is inert while idle');
        $this->assertSame(0, $xpath->query("//section[@data-region='session']")->length);
        $starts = $xpath->query("//*[@data-action='start'][@data-mode='board']");
        $this->assertGreaterThan(0, $starts->length);
        foreach ($starts as $button) {
            $this->assertNotNull($xpath->query('ancestor::*[@data-fold-from]', $button)->item(0), 'every start knows which card it grows from');
        }
        // DOMDocument drops attributes named @click, so the handlers are read from the raw HTML.
        preg_match_all('/<button(?:[^>"]|"[^"]*")*data-action="start"(?:[^>"]|"[^"]*")*data-mode="board"(?:[^>"]|"[^"]*")*>/', $board, $tags);
        $this->assertCount($starts->length, $tags[0]);
        foreach ($tags[0] as $tag) {
            $this->assertStringContainsString('@click="foldInto($el, () => $wire.start(', $tag, 'every start from the board folds');
            $this->assertStringNotContainsString('wire:click="start', $tag);
        }
        $this->assertStringContainsString('x-data="shellFold"', $board);

        // In a session the list buttons start the next session the usual way (no fold).
        app(FocusSession::class)->start($this->user, Task::where('title', 'Formular')->first()->id);
        preg_match_all('/<button(?:[^>"]|"[^"]*")*data-action="start"(?:[^>"]|"[^"]*")*data-mode="session"(?:[^>"]|"[^"]*")*>/', $this->get('/board')->getContent(), $tags);
        $this->assertNotEmpty($tags[0]);
        foreach ($tags[0] as $tag) {
            $this->assertStringNotContainsString('foldInto', $tag);
        }
    }

    public function test_the_fold_keeps_niels_timing_and_respects_reduced_motion(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $js = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('--fold-ms: 520ms', $css, "Niels' pick from the phase 3 preview");
        $this->assertStringContainsString('const FOLD_MS = 520', $js);
        $this->assertMatchesRegularExpression('/prefers-reduced-motion: reduce\)\s*\{\s*\.shell \[data-region="board"\]/', $css, 'reduced motion: instant change');
        $this->assertStringContainsString('reducedMotion()', $js, 'no card flight under reduced motion');
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);

        return new \DOMXPath($dom);
    }

    public function test_a_task_can_be_started_by_hand_from_a_list_outside_blocks(): void
    {
        $task = $this->task('Bericht', 'tasks', Size::Long);

        Livewire::actingAs($this->user)->test(Board::class)->call('start', $task->id);

        $this->assertSame($task->id, app(FocusSession::class)->current($this->user)->taskId);
    }

    public function test_done_completes_the_task_and_ending_the_session_brings_the_board_back(): void
    {
        $this->block();
        $first = $this->task('Formular');
        $this->task('Trikot', 'todos');

        $board = Livewire::actingAs($this->user)->test(Board::class)->call('start', $first->id);
        $this->travel(5)->minutes();
        $board->call('done')->assertSee('Trikot');

        $this->assertTrue($first->fresh()->is_completed);

        $board->call('stop')
            ->assertSee('data-shell-mode="board"', false)
            ->assertSee('1 erledigt heute');
    }

    public function test_pause_and_resume_go_through_the_server(): void
    {
        $task = $this->task('Formular');
        $board = Livewire::actingAs($this->user)->test(Board::class)->call('start', $task->id);

        $board->call('pause')->assertSee('pausiert');
        $this->assertTrue(app(FocusSession::class)->current($this->user)->isPaused());

        $board->call('resume')->assertSee('in Session');
        $this->assertFalse(app(FocusSession::class)->current($this->user)->isPaused());
    }

    public function test_another_and_battery_change_the_suggestion(): void
    {
        $this->block();
        $quick = $this->task('Formular', 'todos', Size::Quick);
        $long = $this->task('Bericht', 'tasks', Size::Long, ['hard_deadline' => '2026-11-12']);

        $board = Livewire::actingAs($this->user)->test(Board::class);
        $first = $board->instance()->suggestion->task->id;

        $board->call('another');
        $this->assertNotSame($first, $board->instance()->suggestion->task->id);

        $board->call('setBattery', 'low')->assertSee('Akku: Leer');
        $this->assertSame(Battery::Low, $board->instance()->suggestion->battery);
        $this->assertContains($board->instance()->suggestion->task->id, [$quick->id, $long->id]);
    }

    public function test_shrink_makes_the_task_one_size_smaller_and_says_so(): void
    {
        $task = $this->task('Bericht', 'tasks', Size::Long);

        Livewire::actingAs($this->user)->test(Board::class)
            ->call('shrink', $task->id)
            ->assertSet('notice', 'Ist jetzt Short.');

        $this->assertSame(Size::Short, $task->fresh()->size);
    }

    public function test_another_users_task_is_refused_calmly_and_left_alone(): void
    {
        $other = User::factory()->create();
        $foreign = Task::forceCreate(['user_id' => $other->id, 'title' => 'Fremd', 'list' => 'tasks', 'size' => Size::Long]);

        Livewire::actingAs($this->user)->test(Board::class)
            ->call('start', $foreign->id)
            ->assertSet('notice', 'Diese Aufgabe gibt es nicht mehr. Die Liste ist jetzt aktuell.')
            ->call('shrink', $foreign->id)
            ->assertSet('notice', 'Diese Aufgabe gibt es nicht mehr. Die Liste ist jetzt aktuell.')
            ->assertDontSee('Fremd');

        $this->assertSame(Phase::Idle, app(FocusSession::class)->current($this->user)->phase);
        $this->assertSame(Size::Long, $foreign->fresh()->size);
    }

    public function test_an_unknown_battery_level_changes_nothing(): void
    {
        Livewire::actingAs($this->user)->test(Board::class)->call('setBattery', 'turbo')->assertSee('Akku: Okay');
    }

    public function test_a_long_title_and_umlauts_render_in_both_layouts(): void
    {
        $title = str_repeat('Größere Überlegung zum Wochenplan ', 12);
        $this->task(trim($title), 'tasks', Size::Long);

        $html = $this->actingAs($this->user)->get('/board')->assertOk()->getContent();

        $this->assertGreaterThanOrEqual(2, substr_count($html, e(trim($title))), 'the title shows in the Werkbank column and the Stapel pile');
    }

    public function test_settings_of_the_new_app_show_the_theme_card(): void
    {
        $this->actingAs($this->user)->get('/board/settings')
            ->assertOk()
            ->assertSee('Einstellungen')
            ->assertSee('Aussehen')
            ->assertSee('Topografie');
    }
}
