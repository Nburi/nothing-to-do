<?php

namespace Tests\Feature\Refactor;

use App\Livewire\Shell\Ideas;
use App\Models\Area;
use App\Models\Task;
use App\Models\User;
use App\Planning\Session\Phase;
use App\Planning\Size;
use App\Services\FocusSession;
use App\Services\Planning\EveningReset;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Step 3.4: "Ideen", the browse view of Later as cards (R6: Bastelideen becomes the browse view of
 * Later). Reached from the Werkbank top bar and the Stapel bottom tab.
 */
class IdeasViewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');
        $this->travelTo(CarbonImmutable::parse('2026-11-14 10:00', 'Europe/Zurich'));
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
    }

    private function task(string $title, string $list = 'later', array $extra = []): Task
    {
        return Task::forceCreate(array_merge(['user_id' => $this->user->id, 'title' => $title, 'list' => $list], $extra));
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/board/ideas')->assertRedirect('/login');
    }

    public function test_every_open_later_item_is_a_card_with_area_size_and_where_to_start(): void
    {
        $area = Area::factory()->for($this->user)->create(['name' => 'Eigene Projekte']);
        $this->task('Kompass-App skizzieren', extra: ['first_step' => 'Drei Screens auf Papier', 'area_id' => $area->id, 'size' => Size::Long]);
        $this->task('Fotoalbum Sommer');
        $this->task('Erledigte Idee', extra: ['is_completed' => true]);
        $this->task('Formular', 'todos');
        Task::forceCreate(['user_id' => User::factory()->create()->id, 'title' => 'Fremde Idee', 'list' => 'later']);

        $this->actingAs($this->user)->get('/board/ideas')
            ->assertOk()
            ->assertSee(['Ideen', '2 Ideen', 'Kompass-App skizzieren', 'Eigene Projekte', 'Long', 'Drei Screens auf Papier', 'Fotoalbum Sommer', 'Noch kein Anfang notiert'])
            ->assertSee(['Jetzt machen', 'Erledigt'])
            ->assertDontSee(['Erledigte Idee', 'Formular', 'Fremde Idee']);
    }

    public function test_tonights_free_time_idea_is_marked(): void
    {
        $this->task('Kompass-App skizzieren');

        $this->actingAs($this->user)->get('/board/ideas')->assertOk()->assertSee('Heute vorgeschlagen');
    }

    public function test_an_empty_later_list_gets_a_finished_empty_state(): void
    {
        $this->actingAs($this->user)->get('/board/ideas')
            ->assertOk()
            ->assertSee('Noch keine Ideen.')
            ->assertSee('Idee erfassen')
            ->assertSee('data-ideas-empty', false);
    }

    public function test_long_titles_with_umlauts_render(): void
    {
        $title = str_repeat('Überlegung für später, ', 17);
        $this->task(trim($title));

        $this->actingAs($this->user)->get('/board/ideas')->assertOk()->assertSee('Überlegung für später', false);
    }

    public function test_do_it_now_starts_a_session_with_the_idea_and_goes_to_the_board(): void
    {
        $idea = $this->task('Kompass-App skizzieren');

        Livewire::actingAs($this->user)->test(Ideas::class)
            ->call('doNow', $idea->id)
            ->assertRedirect(route('shell.board'));

        $state = app(FocusSession::class)->current($this->user);
        $this->assertSame(Phase::Work, $state->phase);
        $this->assertSame($idea->id, $state->taskId);
    }

    public function test_do_it_now_waits_while_a_work_session_runs(): void
    {
        $todo = $this->task('Formular', 'todos', ['size' => Size::Quick]);
        $idea = $this->task('Kompass-App skizzieren');
        app(FocusSession::class)->start($this->user, $todo->id);

        Livewire::actingAs($this->user)->test(Ideas::class)
            ->call('doNow', $idea->id)
            ->assertNoRedirect()
            ->assertSee('Erst nach dieser Session. Die Idee wartet hier.');

        $this->assertSame($todo->id, app(FocusSession::class)->current($this->user)->taskId);
    }

    public function test_done_is_undoable_instead_of_confirmed(): void
    {
        $idea = $this->task('Kompass-App skizzieren');

        $component = Livewire::actingAs($this->user)->test(Ideas::class)
            ->call('done', $idea->id)
            ->assertSee('Erledigt: Kompass-App skizzieren')
            ->assertSee('Rückgängig')
            ->assertSee('Noch keine Ideen.');
        $this->assertTrue($idea->fresh()->is_completed);

        $component->call('undoDone')->assertSee('Zurückgeholt.')->assertSee('Kompass-App skizzieren');
        $this->assertFalse($idea->fresh()->is_completed);
    }

    public function test_a_foreign_or_vanished_idea_is_refused_calmly(): void
    {
        $foreign = Task::forceCreate(['user_id' => User::factory()->create()->id, 'title' => 'Fremd', 'list' => 'later']);
        $todo = $this->task('Formular', 'todos');

        Livewire::actingAs($this->user)->test(Ideas::class)
            ->call('done', $foreign->id)
            ->assertSee('Diese Idee gibt es nicht mehr. Die Liste ist jetzt aktuell.')
            ->call('doNow', $todo->id)
            ->assertNoRedirect();

        $this->assertFalse($foreign->fresh()->is_completed);
        $this->assertSame(Phase::Idle, app(FocusSession::class)->current($this->user)->phase);
    }

    public function test_reachable_from_the_werkbank_top_bar_and_the_stapel_tab(): void
    {
        app(EveningReset::class)->close($this->user);
        app(EveningReset::class)->answerBattery($this->user, null);
        $this->task('Kompass-App skizzieren');

        $html = $this->actingAs($this->user)->get('/board')->assertOk()->getContent();
        $url = preg_quote(route('shell.ideas'), '/');

        $this->assertMatchesRegularExpression('/<a href="'.$url.'"[^>]*data-action="browse_later" data-layout="werkbank" data-mode="board"/', $html);
        $this->assertMatchesRegularExpression('/<a href="'.$url.'"[^>]*data-action="browse_later" data-layout="stapel" data-mode="board"/', $html);
    }

    public function test_the_inbox_tab_of_the_ideas_page_opens_the_inbox_on_the_board(): void
    {
        $this->actingAs($this->user)->get('/board/ideas')->assertOk()->assertSee(route('shell.board', ['open' => 'inbox']), false);
        $this->actingAs($this->user)->get('/board?open=inbox')->assertOk()->assertSee("x-init=\"peek = 'inbox'\"", false);
        $this->actingAs($this->user)->get('/board?open=nonsense')->assertOk()->assertDontSee('x-init="peek', false);
    }
}
