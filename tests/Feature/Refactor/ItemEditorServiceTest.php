<?php

namespace Tests\Feature\Refactor;

use App\Models\Area;
use App\Models\Task;
use App\Models\User;
use App\Planning\Size;
use App\Services\FocusSession;
use App\Services\Planning\ItemEditor;
use App\Services\Planning\ItemLocked;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Step 4.9: editing, moving and deleting one loose item (the rules of App\Services\Planning\ItemEditor).
 */
class ItemEditorServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ItemEditor $items;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');
        $this->travelTo(CarbonImmutable::parse('2026-11-25 10:00', 'Europe/Zurich'));
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
        $this->items = app(ItemEditor::class);
    }

    private function task(string $title, string $list = 'todos', array $extra = [], ?User $user = null): Task
    {
        return Task::forceCreate(array_merge(['user_id' => ($user ?? $this->user)->id, 'title' => $title, 'list' => $list], $extra));
    }

    public function test_an_edit_changes_the_given_fields_and_keeps_the_rest(): void
    {
        $area = Area::factory()->for($this->user)->create();
        $task = $this->task('Alt', 'tasks', ['notes' => 'Bleibt', 'first_step' => 'Datei öffnen', 'size' => Size::Long]);

        $result = $this->items->update($this->user, $task->id, [
            'title' => '  Neu  ',
            'soft_deadline' => '2026-12-01',
            'area_id' => $area->id,
        ]);

        $fresh = $result['task'];
        $this->assertSame('Neu', $fresh->title);
        $this->assertSame('2026-12-01', $fresh->soft_deadline->toDateString());
        $this->assertSame($area->id, $fresh->area_id);
        $this->assertSame('Bleibt', $fresh->notes);
        $this->assertSame('Datei öffnen', $fresh->first_step);
        $this->assertSame(Size::Long, $fresh->size);
        $this->assertSame('tasks', $fresh->list);
        $this->assertFalse($result['moved']);
    }

    public function test_blank_optional_fields_are_cleared(): void
    {
        $task = $this->task('A', 'todos', ['notes' => 'x', 'first_step' => 'y', 'size' => Size::Short, 'soft_deadline' => '2026-12-05']);

        $fresh = $this->items->update($this->user, $task->id, ['notes' => '  ', 'first_step' => '', 'size' => '', 'soft_deadline' => ''])['task'];

        $this->assertNull($fresh->notes);
        $this->assertNull($fresh->first_step);
        $this->assertNull($fresh->size);
        $this->assertNull($fresh->soft_deadline);
    }

    public function test_the_title_is_required_and_has_a_limit(): void
    {
        $task = $this->task('A');

        foreach (['', '   ', str_repeat('x', 501)] as $bad) {
            try {
                $this->items->update($this->user, $task->id, ['title' => $bad]);
                $this->fail('A bad title was accepted.');
            } catch (ValidationException $e) {
                $this->assertTrue($e->validator->errors()->has('title'));
            }
        }

        $this->assertSame('A', $task->fresh()->title);
    }

    public function test_a_changed_date_must_not_lie_in_the_past_but_an_unchanged_overdue_date_may_stay(): void
    {
        $task = $this->task('Überfällig', 'tasks', ['hard_deadline' => '2026-11-20']);

        // Renaming an overdue task works: its date is not touched.
        $fresh = $this->items->update($this->user, $task->id, ['title' => 'Überfällig 2', 'hard_deadline' => '2026-11-20'])['task'];
        $this->assertSame('Überfällig 2', $fresh->title);
        $this->assertSame('2026-11-20', $fresh->hard_deadline->toDateString());

        try {
            $this->items->update($this->user, $task->id, ['hard_deadline' => '2026-11-21']);
            $this->fail('A new date in the past was accepted.');
        } catch (ValidationException $e) {
            $this->assertSame(__('sort.errors.past'), $e->validator->errors()->first('hard_deadline'));
        }

        // Today is fine.
        $this->items->update($this->user, $task->id, ['hard_deadline' => '2026-11-25']);
        $this->assertSame('2026-11-25', $task->fresh()->hard_deadline->toDateString());
    }

    public function test_a_foreign_area_and_a_wrong_list_or_size_are_refused(): void
    {
        $other = User::factory()->create();
        $foreignArea = Area::factory()->for($other)->create();
        $task = $this->task('A');

        foreach ([['area_id', $foreignArea->id], ['list', 'done'], ['size', 'huge'], ['soft_deadline', 'morgen']] as [$field, $value]) {
            try {
                $this->items->update($this->user, $task->id, [$field => $value]);
                $this->fail("{$field} = {$value} was accepted.");
            } catch (ValidationException $e) {
                $this->assertTrue($e->validator->errors()->has($field), $field);
            }
        }
    }

    public function test_a_move_goes_to_the_bottom_of_the_new_list_and_of_the_board_order(): void
    {
        $first = $this->task('Erstes', 'tasks', ['sort_order' => 0]);
        $last = $this->task('Letztes', 'tasks', ['sort_order' => 4]);
        $moving = $this->task('Wandert', 'todos');

        $result = $this->items->move($this->user, $moving->id, 'tasks');

        $this->assertTrue($result['moved']);
        $this->assertSame('tasks', $result['task']->list);
        $this->assertSame(5, $result['task']->sort_order);
        $this->assertGreaterThan($last->fresh()->board_position, $result['task']->board_position);
        $this->assertGreaterThan($first->fresh()->board_position, $result['task']->board_position);
    }

    public function test_an_edit_that_keeps_the_list_keeps_the_place(): void
    {
        $a = $this->task('A', 'todos');
        $this->task('B', 'todos');
        $position = $a->fresh()->board_position;

        $this->items->update($this->user, $a->id, ['title' => 'A neu']);

        $this->assertSame($position, $a->fresh()->board_position);
    }

    public function test_a_move_to_later_drops_the_hard_date_and_a_move_out_of_today_lists_clears_today(): void
    {
        $task = $this->task('Hart', 'tasks', ['hard_deadline' => '2026-12-10', 'soft_deadline' => '2026-12-08', 'is_today' => true, 'today_date' => '2026-11-25']);

        $fresh = $this->items->move($this->user, $task->id, 'later')['task'];

        $this->assertSame('later', $fresh->list);
        $this->assertNull($fresh->hard_deadline);
        $this->assertSame('2026-12-08', $fresh->soft_deadline->toDateString());
        $this->assertFalse($fresh->is_today);
        $this->assertNull($fresh->today_date);
    }

    public function test_undo_puts_an_edited_and_moved_item_back_exactly(): void
    {
        $task = $this->task('Vorher', 'tasks', ['hard_deadline' => '2026-12-10', 'size' => Size::Long, 'notes' => 'n', 'is_today' => true, 'today_date' => '2026-11-25']);
        $position = $task->fresh()->board_position;

        $result = $this->items->update($this->user, $task->id, ['title' => 'Nachher', 'list' => 'later', 'size' => 'quick']);
        $this->items->undo($this->user, $task->id, $result['before']);

        $back = $task->fresh();
        $this->assertSame('Vorher', $back->title);
        $this->assertSame('tasks', $back->list);
        $this->assertSame(Size::Long, $back->size);
        $this->assertSame('2026-12-10', $back->hard_deadline->toDateString());
        $this->assertSame($position, $back->board_position);
        $this->assertTrue($back->is_today);
        $this->assertSame('2026-11-25', $back->today_date->toDateString());
    }

    public function test_delete_is_a_soft_delete_and_restore_brings_it_back_where_it_was(): void
    {
        $task = $this->task('Weg', 'tasks', ['size' => Size::Short, 'sort_order' => 3]);
        $position = $task->fresh()->board_position;

        $this->items->delete($this->user, $task->id);

        $this->assertNull(Task::find($task->id));
        $this->assertSoftDeleted('tasks', ['id' => $task->id]);

        $back = $this->items->restore($this->user, $task->id);
        $this->assertSame('Weg', $back->title);
        $this->assertSame($position, $back->board_position);
        $this->assertSame(3, $back->sort_order);
        $this->assertNotNull(Task::find($task->id));
    }

    public function test_the_task_of_a_running_session_cannot_be_deleted(): void
    {
        $task = $this->task('Läuft', 'tasks');
        app(FocusSession::class)->start($this->user, $task->id);

        try {
            $this->items->delete($this->user, $task->id);
            $this->fail('The running task was deleted.');
        } catch (ItemLocked) {
            $this->assertNotNull(Task::find($task->id));
        }

        // Any other item is free to go.
        $other = $this->task('Frei', 'todos');
        $this->items->delete($this->user, $other->id);
        $this->assertNull(Task::find($other->id));
    }

    public function test_a_foreign_done_or_deleted_item_is_not_found(): void
    {
        $other = User::factory()->create();
        $foreign = $this->task('Fremd', 'todos', [], $other);
        $done = $this->task('Fertig', 'todos', ['is_completed' => true]);
        $gone = $this->task('Schon weg', 'todos');
        $gone->delete();

        foreach ([$foreign->id, $done->id, $gone->id, 999999] as $id) {
            try {
                $this->items->update($this->user, $id, ['title' => 'X']);
                $this->fail("Item {$id} was edited.");
            } catch (ModelNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_a_foreign_item_cannot_be_deleted_or_restored(): void
    {
        $other = User::factory()->create();
        $foreign = $this->task('Fremd', 'todos', [], $other);

        try {
            $this->items->delete($this->user, $foreign->id);
            $this->fail('A foreign item was deleted.');
        } catch (ModelNotFoundException) {
            $this->assertNotNull(Task::find($foreign->id));
        }

        $foreign->delete();
        $this->expectException(ModelNotFoundException::class);
        $this->items->restore($this->user, $foreign->id);
    }
}
