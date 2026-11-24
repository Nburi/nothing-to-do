<?php

namespace Tests\Feature\Refactor;

use App\Livewire\Shell\InboxSort;
use App\Models\Area;
use App\Models\Task;
use App\Models\User;
use App\Planning\Size;
use App\Services\Planning\InboxSort as InboxSortService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A9 (R3): sorting moves an Inbox item to To-do, Task or Later and applies the default size.
 * Plus the sort card: one item at a time, skip, undo, inline German messages.
 */
class InboxSortTest extends TestCase
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

    private function inbox(string $title, int $order = 0): Task
    {
        return Task::forceCreate(['user_id' => $this->user->id, 'title' => $title, 'list' => 'inbox', 'sort_order' => $order]);
    }

    public function test_sorting_moves_to_todo_task_or_later_and_applies_the_default_size(): void
    {
        $sort = new InboxSortService;
        $a = $this->inbox('Formular');
        $b = $this->inbox('Bericht');
        $c = $this->inbox('Fotoalbum');

        $sort->sort($this->user, $a->id, ['list' => 'todos']);
        $sort->sort($this->user, $b->id, ['list' => 'tasks', 'size' => 'long']);
        $sort->sort($this->user, $c->id, ['list' => 'later']);

        $this->assertSame('todos', $a->fresh()->list);
        $this->assertSame(Size::Short, $a->fresh()->size, 'a missing size is written as Short (G1)');
        $this->assertSame('tasks', $b->fresh()->list);
        $this->assertSame(Size::Long, $b->fresh()->size);
        $this->assertSame('later', $c->fresh()->list);
        $this->assertNull($c->fresh()->size, 'Later asks for no size');
    }

    public function test_dates_area_and_first_step_are_stored_and_later_keeps_no_hard_deadline(): void
    {
        $sort = new InboxSortService;
        $area = Area::factory()->for($this->user)->create(['name' => 'Schule']);
        $task = $this->inbox('Laborbericht');
        $idea = $this->inbox('Kompass-App');

        $sort->sort($this->user, $task->id, [
            'list' => 'tasks', 'size' => 'block', 'hard_deadline' => '2026-11-12', 'soft_deadline' => '2026-11-11',
            'area_id' => $area->id, 'first_step' => '  Die Einleitung  ',
        ]);
        $sort->sort($this->user, $idea->id, ['list' => 'later', 'hard_deadline' => '2026-11-20', 'soft_deadline' => '2026-12-01']);

        $task = $task->fresh();
        $this->assertSame('2026-11-12', $task->hard_deadline->toDateString());
        $this->assertSame('2026-11-11', $task->soft_deadline->toDateString());
        $this->assertSame($area->id, $task->area_id);
        $this->assertSame('Die Einleitung', $task->first_step);
        $this->assertNull($idea->fresh()->hard_deadline);
        $this->assertSame('2026-12-01', $idea->fresh()->soft_deadline->toDateString());
    }

    public function test_a_sorted_item_lands_at_the_end_of_its_new_list(): void
    {
        Task::forceCreate(['user_id' => $this->user->id, 'title' => 'Alt', 'list' => 'todos', 'sort_order' => 7]);
        $task = $this->inbox('Neu');

        (new InboxSortService)->sort($this->user, $task->id, ['list' => 'todos']);

        $this->assertSame(8, $task->fresh()->sort_order);
    }

    public function test_bad_input_is_refused_with_german_messages_per_field(): void
    {
        $task = $this->inbox('Formular');
        $foreignArea = Area::factory()->for(User::factory()->create())->create();

        try {
            (new InboxSortService)->sort($this->user, $task->id, [
                'list' => 'somewhere', 'size' => 'huge', 'hard_deadline' => '2026-11-01',
                'area_id' => $foreignArea->id, 'first_step' => str_repeat('x', 256),
            ]);
            $this->fail('accepted bad input');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $this->assertSame('Wähle To-do, Task oder Später.', $errors['list'][0]);
            $this->assertSame('Wähle Quick, Short, Long oder Block.', $errors['size'][0]);
            $this->assertSame('Das Datum liegt in der Vergangenheit.', $errors['hard_deadline'][0]);
            $this->assertSame('Diesen Bereich gibt es nicht.', $errors['area_id'][0]);
            $this->assertSame('Höchstens 255 Zeichen. Der Rest passt in die Notizen.', $errors['first_step'][0]);
        }

        $this->assertSame('inbox', $task->fresh()->list);
    }

    public function test_only_own_inbox_items_can_be_sorted(): void
    {
        $foreign = Task::forceCreate(['user_id' => User::factory()->create()->id, 'title' => 'Fremd', 'list' => 'inbox']);
        $todo = Task::forceCreate(['user_id' => $this->user->id, 'title' => 'Schon sortiert', 'list' => 'todos']);

        foreach ([$foreign, $todo] as $task) {
            try {
                (new InboxSortService)->sort($this->user, $task->id, ['list' => 'later']);
                $this->fail('sorted a task that is not in the own Inbox');
            } catch (ModelNotFoundException) {
                $this->assertNotSame('later', $task->fresh()->list);
            }
        }
    }

    public function test_undo_puts_the_item_back_exactly_as_it_was(): void
    {
        $sort = new InboxSortService;
        $task = $this->inbox('Formular', 3);

        $result = $sort->sort($this->user, $task->id, ['list' => 'tasks', 'size' => 'long', 'hard_deadline' => '2026-11-12']);
        $sort->undo($this->user, $task->id, $result['before']);

        $task = $task->fresh();
        $this->assertSame('inbox', $task->list);
        $this->assertNull($task->size);
        $this->assertNull($task->hard_deadline);
        $this->assertSame(3, $task->sort_order);
    }

    public function test_the_card_goes_through_the_inbox_one_at_a_time_with_skip_and_undo(): void
    {
        $first = $this->inbox('Erstes', 0);
        $second = $this->inbox('Zweites', 1);

        $card = Livewire::actingAs($this->user)->test(InboxSort::class)
            ->dispatch('open-sort')
            ->assertSet('open', true)
            ->assertSee('Erstes')
            ->assertSee('1 von 2');

        $card->call('skip')->assertSee('Zweites');

        $card->call('pick', 'todos')->call('place')
            ->assertHasNoErrors()
            ->assertSet('toast', 'Bei To-dos · Short')
            ->assertDispatched('inbox-changed')
            ->assertSee('Erstes');
        $this->assertSame('todos', $second->fresh()->list);

        $card->call('undo')->assertSee('Zurück in der Inbox.');
        $this->assertSame('inbox', $second->fresh()->list);
    }

    public function test_the_card_shows_field_errors_inline_and_ends_with_an_empty_inbox(): void
    {
        $task = $this->inbox('Formular');

        $card = Livewire::actingAs($this->user)->test(InboxSort::class)->call('start')
            ->set('hardDeadline', '2026-11-01')
            ->call('place')
            ->assertHasErrors('hard_deadline')
            ->assertSet('more', true)
            ->assertSee('Das Datum liegt in der Vergangenheit.');
        $this->assertSame('inbox', $task->fresh()->list);

        $card->set('hardDeadline', '')->call('place')
            ->assertHasNoErrors()
            ->assertSee('Inbox ist leer');
    }

    public function test_the_phone_deck_throws_the_top_card_onto_a_pile_with_the_chosen_size(): void
    {
        $first = $this->inbox('Erstes', 0);
        $second = $this->inbox('Zweites', 1);
        Task::forceCreate(['user_id' => $this->user->id, 'title' => 'Alt', 'list' => 'tasks']);

        $deck = Livewire::actingAs($this->user)->test(InboxSort::class)->call('start');
        $this->assertSame(['tasks' => 1], $deck->instance()->counts);

        $deck->set('size', 'long')->call('placeTo', 'tasks')
            ->assertHasNoErrors()
            ->assertSet('toast', 'Bei Tasks · Long')
            ->assertSee('Zweites');
        $this->assertSame(['tasks' => 2], $deck->instance()->counts);
        $this->assertSame(Size::Long, $first->fresh()->size);

        $deck->call('placeTo', 'later')->assertSet('toast', 'Bei Später');
        $this->assertSame('later', $second->fresh()->list);
        $this->assertNull($second->fresh()->size, 'the size chip is ignored on Später');
    }

    public function test_the_deck_refuses_an_unknown_pile_and_keeps_the_card_on_a_validation_error(): void
    {
        $task = $this->inbox('Formular');

        $deck = Livewire::actingAs($this->user)->test(InboxSort::class)->call('start')
            ->call('placeTo', 'trash')
            ->assertSet('toast', null);
        $this->assertSame('inbox', $task->fresh()->list);

        $deck->set('hardDeadline', '2026-11-01')->call('placeTo', 'tasks')
            ->assertHasErrors('hard_deadline')
            ->assertSet('more', true)
            ->assertSee('Formular');
        $this->assertSame('inbox', $task->fresh()->list);
    }

    public function test_the_board_offers_sorting_when_the_inbox_has_something(): void
    {
        $this->inbox('Gedanke');

        $this->actingAs($this->user)->get('/board')
            ->assertOk()
            ->assertSee('Sortieren (1)')
            ->assertSeeLivewire(InboxSort::class);
    }
}
