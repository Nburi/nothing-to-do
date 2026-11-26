<?php

namespace Tests\Feature\Refactor;

use App\Livewire\Shell\Board;
use App\Livewire\Shell\Ideas;
use App\Livewire\Shell\ItemEditor;
use App\Models\Area;
use App\Models\Task;
use App\Models\User;
use App\Planning\Size;
use App\Services\FocusSession;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Step 4.9: the ⋯ menu, the edit sheet and the toast with "Rückgängig" on the board and the Ideen page.
 */
class ItemEditorComponentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');
        $this->travelTo(CarbonImmutable::parse('2026-11-25 10:00', 'Europe/Zurich'));
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
        $this->actingAs($this->user);
    }

    private function task(string $title, string $list = 'todos', array $extra = []): Task
    {
        return Task::forceCreate(array_merge(['user_id' => $this->user->id, 'title' => $title, 'list' => $list], $extra));
    }

    public function test_the_sheet_opens_with_the_values_of_the_item(): void
    {
        $area = Area::factory()->for($this->user)->create(['name' => 'Haushalt']);
        $task = $this->task('Steuern', 'tasks', ['size' => Size::Long, 'hard_deadline' => '2026-12-15', 'area_id' => $area->id, 'first_step' => 'Belege suchen', 'notes' => 'Ordner im Büro']);

        Livewire::test(ItemEditor::class)
            ->assertDontSee('Bearbeiten')
            ->dispatch('item-edit', id: $task->id)
            ->assertSet('open', true)
            ->assertSet('title', 'Steuern')
            ->assertSet('list', 'tasks')
            ->assertSet('size', 'long')
            ->assertSet('hardDeadline', '2026-12-15')
            ->assertSet('areaId', $area->id)
            ->assertSet('firstStep', 'Belege suchen')
            ->assertSet('notes', 'Ordner im Büro')
            ->assertSee('Bearbeiten')
            ->assertSee('Haushalt');
    }

    public function test_saving_writes_everything_closes_the_sheet_and_offers_undo(): void
    {
        $task = $this->task('Alt', 'todos');
        $area = Area::factory()->for($this->user)->create();

        Livewire::test(ItemEditor::class)
            ->dispatch('item-edit', id: $task->id)
            ->set('title', 'Neu')
            ->set('list', 'tasks')
            ->set('size', 'block')
            ->set('hardDeadline', '2026-12-24')
            ->set('softDeadline', '2026-12-20')
            ->set('areaId', $area->id)
            ->set('firstStep', 'Anfangen')
            ->set('notes', 'Notiz')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('open', false)
            ->assertDispatched('items-changed')
            ->assertSee('«Neu» ist jetzt bei Tasks.')
            ->assertSee('Rückgängig');

        $fresh = $task->fresh();
        $this->assertSame('Neu', $fresh->title);
        $this->assertSame('tasks', $fresh->list);
        $this->assertSame(Size::Block, $fresh->size);
        $this->assertSame('2026-12-24', $fresh->hard_deadline->toDateString());
        $this->assertSame('2026-12-20', $fresh->soft_deadline->toDateString());
        $this->assertSame($area->id, $fresh->area_id);
        $this->assertSame('Anfangen', $fresh->first_step);
        $this->assertSame('Notiz', $fresh->notes);
    }

    public function test_undo_after_a_save_restores_the_item(): void
    {
        $task = $this->task('Alt', 'todos', ['size' => Size::Quick]);

        Livewire::test(ItemEditor::class)
            ->dispatch('item-edit', id: $task->id)
            ->set('title', 'Neu')
            ->set('list', 'later')
            ->call('save')
            ->call('undoLast')
            ->assertSee('Änderung zurückgenommen.')
            ->assertDispatched('items-changed');

        $fresh = $task->fresh();
        $this->assertSame('Alt', $fresh->title);
        $this->assertSame('todos', $fresh->list);
        $this->assertSame(Size::Quick, $fresh->size);
    }

    public function test_a_bad_form_keeps_the_sheet_open_and_shows_a_message_per_field(): void
    {
        $task = $this->task('Alt', 'tasks');

        Livewire::test(ItemEditor::class)
            ->dispatch('item-edit', id: $task->id)
            ->set('title', '   ')
            ->set('softDeadline', '2026-11-01')
            ->set('notes', str_repeat('n', 5001))
            ->call('save')
            ->assertSet('open', true)
            ->assertHasErrors(['title', 'soft_deadline', 'notes'])
            ->assertSee('Gib dem Eintrag einen Titel.')
            ->assertSee('Das Datum liegt in der Vergangenheit.')
            ->assertSee('Höchstens 5000 Zeichen in den Notizen.');

        $this->assertSame('Alt', $task->fresh()->title);
    }

    public function test_choosing_later_hides_the_hard_date_and_the_size_chips_hide_for_the_inbox(): void
    {
        $task = $this->task('Idee', 'tasks', ['hard_deadline' => '2026-12-15']);

        Livewire::test(ItemEditor::class)
            ->dispatch('item-edit', id: $task->id)
            ->assertSee('Muss bis')
            ->assertSee('Quick')
            ->call('pick', 'later')
            ->assertDontSee('Muss bis')
            ->call('pick', 'inbox')
            ->assertDontSee('Quick')
            ->call('pick', 'nonsense')
            ->assertSet('list', 'inbox');
    }

    public function test_a_size_chip_toggles(): void
    {
        $task = $this->task('A', 'todos', ['size' => Size::Short]);

        Livewire::test(ItemEditor::class)
            ->dispatch('item-edit', id: $task->id)
            ->call('pickSize', 'short')
            ->assertSet('size', null)
            ->call('pickSize', 'long')
            ->assertSet('size', 'long')
            ->call('pickSize', 'enormous')
            ->assertSet('size', 'long');
    }

    public function test_move_from_the_menu_changes_only_the_list(): void
    {
        $task = $this->task('Wandert', 'todos', ['size' => Size::Quick, 'notes' => 'bleibt']);

        Livewire::test(ItemEditor::class)
            ->dispatch('item-move', id: $task->id, list: 'later')
            ->assertSee('«Wandert» ist jetzt bei Später.')
            ->assertSee('Rückgängig')
            ->assertDispatched('items-changed');

        $fresh = $task->fresh();
        $this->assertSame('later', $fresh->list);
        $this->assertSame('bleibt', $fresh->notes);
        $this->assertSame(Size::Quick, $fresh->size);
    }

    public function test_moving_to_the_list_it_is_in_does_nothing_and_a_bad_list_is_told_calmly(): void
    {
        $task = $this->task('Bleibt', 'todos');

        Livewire::test(ItemEditor::class)
            ->dispatch('item-move', id: $task->id, list: 'todos')
            ->assertNotDispatched('items-changed')
            ->assertSet('toast', null)
            ->dispatch('item-move', id: $task->id, list: 'archive')
            ->assertSee('Wähle Inbox, To-dos, Tasks oder Später.');

        $this->assertSame('todos', $task->fresh()->list);
    }

    public function test_delete_leaves_a_toast_and_undo_brings_the_item_back(): void
    {
        $task = $this->task('Weg damit', 'tasks');

        $component = Livewire::test(ItemEditor::class)
            ->dispatch('item-delete', id: $task->id)
            ->assertSee('«Weg damit» gelöscht.')
            ->assertSee('Rückgängig')
            ->assertDispatched('items-changed');

        $this->assertNull(Task::find($task->id));

        $component->call('undoLast')->assertSee('«Weg damit» ist wieder da.');

        $this->assertNotNull(Task::find($task->id));
    }

    public function test_delete_from_the_sheet_closes_it(): void
    {
        $task = $this->task('Aus dem Sheet', 'todos');

        Livewire::test(ItemEditor::class)
            ->dispatch('item-edit', id: $task->id)
            ->call('delete')
            ->assertSet('open', false)
            ->assertSee('«Aus dem Sheet» gelöscht.');

        $this->assertNull(Task::find($task->id));
    }

    public function test_the_task_of_a_running_session_is_refused_with_a_calm_message(): void
    {
        $task = $this->task('Läuft', 'tasks');
        app(FocusSession::class)->start($this->user, $task->id);

        Livewire::test(ItemEditor::class)
            ->dispatch('item-delete', id: $task->id)
            ->assertSee('Das läuft gerade in deiner Session. Beende die Session zuerst.')
            ->assertNotDispatched('items-changed');

        $this->assertNotNull(Task::find($task->id));
    }

    public function test_an_item_that_is_gone_or_foreign_is_told_calmly_and_nothing_breaks(): void
    {
        $foreign = Task::forceCreate(['user_id' => User::factory()->create()->id, 'title' => 'Fremd', 'list' => 'todos']);
        $component = Livewire::test(ItemEditor::class);

        foreach (['item-edit', 'item-delete'] as $event) {
            $component->dispatch($event, id: $foreign->id)->assertSee('Den Eintrag gibt es nicht mehr.')->assertSet('open', false);
        }
        $component->dispatch('item-move', id: 999999, list: 'later')->assertSee('Den Eintrag gibt es nicht mehr.');

        $this->assertSame('Fremd', $foreign->fresh()->title);
    }

    public function test_the_item_vanishes_while_the_sheet_is_open(): void
    {
        $task = $this->task('Verschwindet', 'todos');

        $component = Livewire::test(ItemEditor::class)->dispatch('item-edit', id: $task->id)->set('title', 'Neu');
        $task->delete();

        $component->call('save')->assertSet('open', false)->assertSee('Den Eintrag gibt es nicht mehr.');
    }

    public function test_the_item_id_and_the_undo_state_cannot_be_set_from_the_browser(): void
    {
        $task = $this->task('A');
        $component = Livewire::test(ItemEditor::class);

        foreach (['taskId' => $task->id, 'undo' => ['kind' => 'edit', 'id' => $task->id, 'before' => ['list' => 'bogus']]] as $property => $value) {
            try {
                $component->set($property, $value);
                $this->fail("{$property} could be set from the browser.");
            } catch (CannotUpdateLockedPropertyException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // ---- the pages around it ------------------------------------------------------------------

    public function test_every_board_row_has_the_menu_and_the_board_mounts_the_editor(): void
    {
        $this->task('Eins', 'todos');
        $this->task('Zwei', 'tasks');
        $this->task('Drei', 'inbox');
        $this->task('Vier', 'later');

        $this->get('/board')->assertOk()
            ->assertSee('data-item-menu', false)
            ->assertSee('Bearbeiten')
            ->assertSee('Nach Später')
            ->assertSee('Löschen')
            ->assertSeeLivewire(ItemEditor::class);
    }

    public function test_the_menu_of_a_row_does_not_offer_the_list_it_is_in(): void
    {
        $this->task('Nur ein To-do', 'todos');

        $html = $this->get('/board')->assertOk()->getContent();

        $this->assertStringContainsString("list: 'tasks'", $html);
        $this->assertStringContainsString("list: 'later'", $html);
        $this->assertStringContainsString("list: 'inbox'", $html);
        $this->assertStringNotContainsString("list: 'todos'", $html);
    }

    public function test_the_board_refreshes_when_an_item_changed(): void
    {
        $task = $this->task('Auf dem Board', 'todos');

        $board = Livewire::test(Board::class)->assertSee('Auf dem Board');
        $task->delete();

        $board->dispatch('items-changed')->assertDontSee('Auf dem Board');
    }

    public function test_an_idea_card_has_the_menu_and_the_page_refreshes_when_an_idea_changed(): void
    {
        $idea = $this->task('Kompass-App', 'later');

        $this->get('/board/ideas')->assertOk()
            ->assertSee('data-item-menu', false)
            ->assertSee('Bearbeiten')
            ->assertSee('Nach Tasks')
            ->assertSeeLivewire(ItemEditor::class);

        $page = Livewire::test(Ideas::class)->assertSee('Kompass-App');
        $idea->delete();
        $page->dispatch('items-changed')->assertDontSee('Kompass-App');
    }
}
