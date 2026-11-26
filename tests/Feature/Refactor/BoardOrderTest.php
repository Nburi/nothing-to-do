<?php

namespace Tests\Feature\Refactor;

use App\Livewire\Shell\Board;
use App\Models\EventCategory;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\User;
use App\Planning\Size;
use App\Services\Planning\BoardOrder;
use App\Services\Planning\Suggestions;
use App\Services\Planning\EveningReset;
use App\Services\Planning\WeekPlanEditor;
use App\Services\Projects\NextSteps;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Step 4.8 (C31, C29; Niels 2026-11-24, variant A): one shared order for tasks, projects and groups,
 * the „Wichtig" band at the top, and the calm board (at most two cards at once).
 * Monday 9 November 2026, 20:00 in Zurich.
 */
class BoardOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');
        $this->travelTo(CarbonImmutable::parse('2026-11-09 20:00', 'Europe/Zurich'));
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);

        app(EveningReset::class)->close($this->user);
        app(EveningReset::class)->answerBattery($this->user, null);
    }

    private function block(): void
    {
        $deep = EventCategory::factory()->for($this->user)->pomodoro()->create(['name' => 'Deep work']);
        (new WeekPlanEditor)->addRepeating($this->user, [1, 2, 3, 4, 5], '19:30', '21:30', $deep->id);
    }

    private function task(string $title, string $list = 'tasks', array $extra = [], ?User $owner = null): Task
    {
        return Task::forceCreate(array_merge([
            'user_id' => ($owner ?? $this->user)->id, 'title' => $title, 'list' => $list, 'size' => Size::Short,
        ], $extra));
    }

    private function project(string $name, ?User $owner = null): Project
    {
        $p = new Project(['name' => $name]);
        $p->user_id = ($owner ?? $this->user)->id;
        $p->save();

        return $p;
    }

    private function group(string $name, string $list = 'tasks'): TaskGroup
    {
        $g = new TaskGroup(['name' => $name, 'list' => $list]);
        $g->user_id = $this->user->id;
        $g->save();

        return $g;
    }

    private function step(Project|TaskGroup $card, string $title, array $extra = []): Task
    {
        $key = $card instanceof Project ? 'project_id' : 'group_id';

        return $this->task($title, 'tasks', [$key => $card->id, ...$extra]);
    }

    private function board()
    {
        return Livewire::actingAs($this->user)->test(Board::class);
    }

    /** The refs of a column in the order the board shows them. */
    private function column(string $list = 'tasks'): array
    {
        return $this->board()->instance()->entries[$list]->pluck('ref')->all();
    }

    // ── one shared number space ──────────────────────────────────────────

    public function test_new_rows_land_below_everything_in_one_number_space(): void
    {
        $a = $this->task('A');
        $p = $this->project('P');
        $g = $this->group('G');
        $b = $this->task('B');

        $this->assertSame([1, 2, 3, 4], [$a->fresh()->board_position, $p->fresh()->board_position, $g->fresh()->board_position, $b->fresh()->board_position]);
    }

    public function test_the_numbers_are_per_user(): void
    {
        $other = User::factory()->create();
        $this->task('Meins');
        $theirs = $this->task('Ihres', 'tasks', [], $other);

        $this->assertSame(1, $theirs->fresh()->board_position);
    }

    // ── the order of a column: cards and rows mixed ──────────────────────

    public function test_cards_and_rows_stand_in_one_order_not_cards_first(): void
    {
        $a = $this->task('Erste Task');
        $p = $this->project('Projekt');
        $this->step($p, 'Schritt');
        $b = $this->task('Zweite Task');

        $this->assertSame(['task:'.$a->id, 'project:'.$p->id, 'task:'.$b->id], $this->column());
    }

    public function test_a_drop_puts_a_row_between_two_cards_and_leaves_the_other_column_alone(): void
    {
        $p = $this->project('Projekt');
        $this->step($p, 'Schritt');
        $g = $this->group('Gruppe');
        $this->step($g, 'Gruppenschritt');
        $a = $this->task('Eine Task');
        $todoA = $this->task('To-do A', 'todos');
        $todoB = $this->task('To-do B', 'todos');
        $before = $this->column('todos');

        $this->board()->call('reorder', ['task:'.$a->id, 'project:'.$p->id, 'group:'.$g->id]);

        $this->assertSame(['task:'.$a->id, 'project:'.$p->id, 'group:'.$g->id], $this->column());
        $this->assertSame($before, $this->column('todos'), 'the To-dos column keeps its order');
        $this->assertNotNull($todoA->fresh()->board_position);
        $this->assertNotNull($todoB->fresh()->board_position);
    }

    public function test_a_drop_only_swaps_the_places_the_entries_already_held(): void
    {
        $a = $this->task('A');
        $x = $this->task('X', 'todos');
        $b = $this->task('B');
        $c = $this->task('C');
        $held = [$a->fresh()->board_position, $x->fresh()->board_position, $b->fresh()->board_position, $c->fresh()->board_position];

        $changed = app(BoardOrder::class)->place($this->user, ['task:'.$c->id, 'task:'.$a->id, 'task:'.$b->id]);

        $this->assertSame(3, $changed);
        $this->assertSame($held[1], $x->fresh()->board_position, 'an entry that was not part of the drop keeps its place');
        $this->assertSame([$held[0], $held[2], $held[3]], [$c->fresh()->board_position, $a->fresh()->board_position, $b->fresh()->board_position]);
    }

    public function test_a_drop_that_changes_nothing_writes_nothing(): void
    {
        $a = $this->task('A');
        $b = $this->task('B');

        $this->assertSame(0, app(BoardOrder::class)->place($this->user, ['task:'.$a->id, 'task:'.$b->id]));
    }

    public function test_foreign_unknown_and_finished_entries_are_never_trusted(): void
    {
        $other = User::factory()->create();
        $mine = $this->task('Meine');
        $mine2 = $this->task('Meine zweite');
        $theirs = $this->task('Fremde', 'tasks', [], $other);
        $theirProject = $this->project('Fremdes Projekt', $other);
        $done = $this->task('Erledigt', 'tasks', ['is_completed' => true]);
        $theirPos = [$theirs->fresh()->board_position, $theirProject->fresh()->board_position];

        app(BoardOrder::class)->place($this->user, ['task:'.$theirs->id, 'project:'.$theirProject->id, 'task:'.$done->id, 'task:999999', 'bogus', 'task:'.$mine2->id, 'task:'.$mine->id]);

        $this->assertSame($theirPos, [$theirs->fresh()->board_position, $theirProject->fresh()->board_position]);
        $this->assertSame(['task:'.$mine2->id, 'task:'.$mine->id], $this->column());
    }

    public function test_a_reorder_with_garbage_does_not_break_the_board(): void
    {
        $this->task('A');

        $this->board()->call('reorder', ['x', 5, null, [], 'task:abc', 'project:']);

        $this->assertCount(1, $this->column());
    }

    // ── the engine reads the shared order as its last criterion ──────────

    public function test_the_engine_reads_a_card_at_its_own_place_not_before_every_loose_task(): void
    {
        $this->block();
        $loose = $this->task('Lose Task'); // place 1
        $p = $this->project('Projekt'); // place 2
        $this->step($p, 'Schritt im Projekt');

        // Both are Short work without a deadline: the board order decides.
        $this->assertSame('Lose Task', app(Suggestions::class)->current($this->user)->task->title);

        app(BoardOrder::class)->place($this->user, ['project:'.$p->id, 'task:'.$loose->id]);

        $this->assertSame('Schritt im Projekt', app(Suggestions::class)->current($this->user)->task->title);
    }

    public function test_the_marker_still_beats_the_board_order_in_the_engine(): void
    {
        $this->block();
        $first = $this->task('Oben');
        $marked = $this->task('Unten, aber wichtig', 'tasks', ['is_important' => true]);

        $this->assertSame($marked->id, app(Suggestions::class)->current($this->user)->task->id);
        $this->assertNotSame($first->id, $marked->id);
    }

    // ── C29: the calm board ──────────────────────────────────────────────

    private function threeProjects(): array
    {
        $out = [];
        foreach (['Eins', 'Zwei', 'Drei'] as $name) {
            $p = $this->project($name);
            $this->step($p, 'Schritt '.$name);
            $out[$name] = $p;
        }

        return $out;
    }

    public function test_the_board_shows_at_most_two_cards_even_with_three_projects(): void
    {
        $p = $this->threeProjects();

        $board = $this->board()->instance();

        $this->assertSame(['project:'.$p['Eins']->id, 'project:'.$p['Zwei']->id], $board->entries['tasks']->pluck('ref')->all());
        $this->assertSame([$p['Drei']->id], $board->resting['tasks']->pluck('id')->all());
    }

    public function test_the_resting_card_shows_as_one_line_with_a_way_to_wake_it(): void
    {
        $this->threeProjects();

        $this->board()
            ->assertSeeHtml('data-resting="tasks"')
            ->assertSee('Weitere Karten · 1 ruhen')
            ->assertSee('Drei')
            ->assertDontSee('Schritt Drei');
    }

    public function test_a_resting_card_is_not_suggested_and_does_not_count_on_the_board(): void
    {
        $this->block();
        $p = $this->threeProjects();
        $next = app(NextSteps::class);

        $this->assertContains(
            Task::where('title', 'Schritt Drei')->value('id'),
            $next->hiddenTaskIds($this->user),
        );
        $this->assertSame(2, $this->board()->instance()->counts['tasks']);
        $this->assertNotSame('Schritt Drei', app(Suggestions::class)->current($this->user)->task->title);
        $this->assertSame('Drei', $p['Drei']->name);
    }

    public function test_loose_tasks_never_rest_only_cards_do(): void
    {
        $this->threeProjects();
        foreach (range(1, 5) as $i) {
            $this->task('Lose '.$i);
        }

        $this->assertCount(5, $this->board()->instance()->lists['tasks']);
    }

    public function test_a_project_with_nothing_to_do_does_not_use_up_one_of_the_two_places(): void
    {
        $this->project('Leer'); // no steps, no card
        $a = $this->project('A');
        $this->step($a, 'Schritt A');
        $b = $this->project('B');
        $this->step($b, 'Schritt B');
        $c = $this->project('C');
        $this->step($c, 'Schritt C');

        $this->assertSame(['project:'.$a->id, 'project:'.$b->id], $this->column());
    }

    public function test_waking_a_card_takes_the_top_and_the_lowest_awake_card_rests(): void
    {
        $p = $this->threeProjects();

        $this->board()->call('wake', 'project:'.$p['Drei']->id);

        $this->assertSame(['project:'.$p['Drei']->id, 'project:'.$p['Eins']->id], $this->column());
        $this->assertSame([$p['Zwei']->id], $this->board()->instance()->resting['tasks']->pluck('id')->all());
    }

    public function test_a_drop_never_changes_which_cards_are_awake(): void
    {
        $p = $this->threeProjects();
        $a = $this->task('Lose Task'); // place 4, below the three cards; the column shows [Eins, Zwei, Lose]

        // The loose task goes to the top. "Drei" rested at place 3 between them: it must not wake up.
        $this->board()->call('reorder', ['task:'.$a->id, 'project:'.$p['Eins']->id, 'project:'.$p['Zwei']->id]);

        $this->assertSame(['task:'.$a->id, 'project:'.$p['Eins']->id, 'project:'.$p['Zwei']->id], $this->column());
        $this->assertSame([$p['Drei']->id], $this->board()->instance()->resting['tasks']->pluck('id')->all());
    }

    public function test_waking_is_limited_to_the_users_own_cards(): void
    {
        $other = User::factory()->create();
        $theirs = $this->project('Fremd', $other);
        $before = $theirs->fresh()->board_position;
        $this->threeProjects();

        $this->board()->call('wake', 'project:'.$theirs->id);

        $this->assertSame($before, $theirs->fresh()->board_position);
    }

    public function test_a_group_counts_as_a_card_too(): void
    {
        $p = $this->project('P');
        $this->step($p, 'Schritt P');
        $g = $this->group('G');
        $this->step($g, 'Schritt G');
        $q = $this->project('Q');
        $this->step($q, 'Schritt Q');

        $this->assertSame(['project:'.$p->id, 'group:'.$g->id], $this->column());
        $this->assertSame([$q->id], $this->board()->instance()->resting['tasks']->pluck('id')->all());
    }

    // ── the „Wichtig" band ───────────────────────────────────────────────

    public function test_marked_tasks_stand_in_a_band_in_the_shared_order_and_leave_their_column(): void
    {
        $plain = $this->task('Normal');
        $m1 = $this->task('Wichtig eins', 'tasks', ['is_important' => true]);
        $m2 = $this->task('Wichtig zwei', 'tasks', ['is_important' => true]);
        $inbox = $this->task('Im Inbox', 'inbox', ['is_important' => true, 'size' => null]);

        $board = $this->board()->instance();

        $this->assertSame([$m1->id, $m2->id], $board->band->pluck('id')->all());
        $this->assertSame([$plain->id], $board->lists['tasks']->pluck('id')->all());
        $this->assertSame([$inbox->id], $board->lists['inbox']->pluck('id')->all(), 'the Inbox keeps its marked tasks for the sort');
        $this->assertCount(3, $board->important, 'the tab still holds every marked task');
        $this->assertSame(3, $board->counts['tasks'], 'a column still counts what its list holds');
    }

    public function test_the_band_can_be_ordered_like_any_other_container(): void
    {
        $m1 = $this->task('Wichtig eins', 'tasks', ['is_important' => true]);
        $m2 = $this->task('Wichtig zwei', 'tasks', ['is_important' => true]);

        $this->board()->call('reorder', ['task:'.$m2->id, 'task:'.$m1->id]);

        $this->assertSame([$m2->id, $m1->id], $this->board()->instance()->band->pluck('id')->all());
    }

    public function test_the_band_shows_on_the_board(): void
    {
        $this->task('Zahnarzt anrufen', 'tasks', ['is_important' => true]);

        $this->board()->assertSeeHtml('data-important-reminder')->assertSeeHtml('data-entry="task:');
    }

    // ── the old numbers: nobody loses their order ────────────────────────

    public function test_the_migration_reproduces_the_old_board_for_existing_rows(): void
    {
        $b = $this->task('B', 'tasks', ['sort_order' => 1]);
        $a = $this->task('A', 'tasks', ['sort_order' => 0]);
        $marked = $this->task('Markiert', 'tasks', ['sort_order' => 5, 'is_important' => true]);
        $g = $this->group('Gruppe');
        $p2 = $this->project('Zweites');
        $p1 = $this->project('Erstes');
        DB::table('projects')->where('id', $p2->id)->update(['sort_order' => 2]);
        DB::table('projects')->where('id', $p1->id)->update(['sort_order' => 1]);
        $done = $this->task('Erledigt', 'tasks', ['is_completed' => true]);
        $inProject = $this->step($p1, 'Schritt');

        // The state before the migration: no positions at all.
        foreach (['tasks', 'projects', 'task_groups'] as $table) {
            DB::table($table)->update(['board_position' => null]);
        }
        $migration = require base_path('database/migrations/2026_11_24_000001_add_board_position.php');
        Artisan::call('migrate:rollback', ['--path' => 'database/migrations/2026_11_24_000001_add_board_position.php', '--realpath' => false, '--force' => true]);
        $migration->up();

        $order = fn () => collect([
            ['project', $p1->fresh()], ['project', $p2->fresh()], ['group', $g->fresh()],
            ['task', $marked->fresh()], ['task', $a->fresh()], ['task', $b->fresh()],
        ])->map(fn ($x) => $x[1]->board_position)->all();

        $this->assertSame([1, 2, 3, 4, 5, 6], $order(), 'projects, then groups, then loose tasks (marked first, then the old order)');
        $this->assertNull($done->fresh()->board_position, 'finished work gets no place');
        $this->assertNull($inProject->fresh()->board_position, 'a step sits in its card');
    }
}
