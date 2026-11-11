<?php

namespace Tests\Feature\Refactor;

use App\Models\EventCategory;
use App\Models\Task;
use App\Models\User;
use App\Planning\Size;
use App\Services\FocusSession;
use App\Services\Planning\WeekPlanEditor;
use App\Shell\ActionRegistry;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * A11 (R7, CONVENTIONS 2, risk C8): every available action of the registry has an entry point
 * in Werkbank and in Stapel, in board mode and in session mode. Both layouts are in the same
 * HTML (CSS picks one at 720 px), so one render per mode covers both layouts.
 */
class ActionParityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Monday 9 November 2026, 20:00 in Zurich, inside a pomodoro block, so there is a pick.
        $this->travelTo(CarbonImmutable::parse('2026-11-09 20:00', 'Europe/Zurich'));
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
        $deep = EventCategory::factory()->for($this->user)->pomodoro()->create(['name' => 'Deep work']);
        (new WeekPlanEditor)->addRepeating($this->user, [1, 2, 3, 4, 5], '19:30', '21:30', $deep->id);

        foreach ([['Formular', 'todos', Size::Quick], ['Bericht', 'tasks', Size::Long], ['Idee', 'later', null], ['Gedanke', 'inbox', null]] as $i => [$title, $list, $size]) {
            Task::forceCreate(['user_id' => $this->user->id, 'title' => $title, 'list' => $list, 'size' => $size, 'sort_order' => $i]);
        }
    }

    public function test_every_available_action_has_both_entry_points_in_board_mode(): void
    {
        $html = $this->actingAs($this->user)->get('/board')->assertOk()->assertSee('data-shell-mode="board"', false)->getContent();

        $this->assertParity($html, 'board');
    }

    public function test_every_available_action_has_both_entry_points_in_session_mode(): void
    {
        $task = Task::where('title', 'Bericht')->first();
        app(FocusSession::class)->start($this->user, $task->id);

        $html = $this->actingAs($this->user)->get('/board')->assertOk()->assertSee('data-shell-mode="session"', false)->getContent();

        $this->assertParity($html, 'session');
    }

    public function test_the_registry_holds_the_twelve_actions_of_the_plan(): void
    {
        $this->assertSame(
            ['capture', 'sort', 'start', 'swap', 'battery', 'browse_later', 'reset', 'move_event', 'week_plan', 'pause_days', 'crunch', 'keep_mine'],
            array_map(fn ($a) => $a->key, ActionRegistry::all()),
        );

        foreach (ActionRegistry::all() as $action) {
            $this->assertNotSame('shell.actions.'.$action->key, $action->label(), "{$action->key} needs a label in lang/de/shell.php");
        }
    }

    public function test_an_entry_point_for_an_unknown_action_fails_loudly(): void
    {
        try {
            Blade::render('<x-shell.action key="teleport" layout="werkbank" mode="board">x</x-shell.action>');
            $this->fail('An unknown action rendered');
        } catch (\Throwable $e) {
            $root = $e;
            while ($root->getPrevious() !== null) {
                $root = $root->getPrevious();
            }
            $this->assertInstanceOf(InvalidArgumentException::class, $root);
            $this->assertStringContainsString('Unknown shell action [teleport]', $e->getMessage());
        }
    }

    private function assertParity(string $html, string $mode): void
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $xpath = new DOMXPath($dom);
        $missing = [];

        foreach (ActionRegistry::available() as $action) {
            foreach (ActionRegistry::LAYOUTS as $layout) {
                // Entries inside the folded, inert board (step 3.1) cannot be reached, so they do not count.
                $query = sprintf('//*[@data-action="%s"][@data-layout="%s"][@data-mode="%s"][not(ancestor-or-self::*[@inert])]', $action->key, $layout, $mode);
                if ($xpath->query($query)->length === 0) {
                    $missing[] = "{$action->key} in {$layout} ({$mode} mode)";
                }
            }
        }

        $this->assertSame([], $missing, 'Actions without an entry point');
    }
}
