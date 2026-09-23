<?php

namespace Tests\Feature;

use App\Livewire\OverdueRescue;
use App\Livewire\TaskBoard;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OverdueRescueTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Wednesday 2026-09-23. */
    private function user(): User
    {
        Carbon::setTestNow('2026-09-23 12:00:00');

        return User::factory()->create(['timezone_offset' => 0, 'timezone_auto_dst' => false]);
    }

    public function test_it_renders_nothing_visible_when_nothing_is_overdue(): void
    {
        $user = $this->user();
        Task::factory()->for($user)->todos()->create(['title' => 'Heute fällig', 'due_date' => '2026-09-23']);

        Livewire::actingAs($user)->test(OverdueRescue::class)
            ->assertDontSee('warten noch auf ein neues Datum')
            ->assertDontSee('wartet noch');
    }

    public function test_it_lists_only_soft_overdue_open_board_tasks_of_this_user(): void
    {
        $user = $this->user();
        $project = Project::factory()->for($user)->create();
        Task::factory()->for($user)->todos()->create(['title' => 'Soft alt', 'due_date' => '2026-09-20']);
        Task::factory()->for($user)->todos()->create(['title' => 'Hart alt', 'deadline' => '2026-09-20']);
        Task::factory()->for($user)->todos()->completed()->create(['title' => 'Erledigt alt', 'due_date' => '2026-09-20']);
        Task::factory()->for($user)->create(['title' => 'Im Projekt', 'list' => 'projects', 'project_id' => $project->id, 'due_date' => '2026-09-20']);
        Task::factory()->for(User::factory()->create())->todos()->create(['title' => 'Fremd', 'due_date' => '2026-09-20']);

        $component = Livewire::actingAs($user)->test(OverdueRescue::class);

        $component->assertSee('1 Aufgabe wartet')
            ->assertSee('Soft alt')
            ->assertDontSee('Erledigt alt')
            ->assertDontSee('Im Projekt')
            ->assertDontSee('Fremd')
            ->assertSee('Eine weitere hat eine verpasste Deadline');
        $this->assertSame(['Soft alt'], $component->instance()->softOverdue->pluck('title')->all());
    }

    public function test_the_local_day_decides_what_is_overdue(): void
    {
        // 23:30 UTC on the 22nd is already the 23rd at UTC+2 — a task due on the 22nd is overdue there.
        Carbon::setTestNow('2026-09-22 23:30:00');
        $user = User::factory()->create(['timezone_offset' => 2, 'timezone_auto_dst' => false]);
        Task::factory()->for($user)->todos()->create(['title' => 'Gestern', 'due_date' => '2026-09-22']);

        Livewire::actingAs($user)->test(OverdueRescue::class)->assertSee('Gestern');
    }

    /** @return array<string, array{0: string, 1: ?string}> */
    public static function targets(): array
    {
        return [
            'today' => ['today', '2026-09-23'],
            'tomorrow' => ['tomorrow', '2026-09-24'],
            'monday' => ['monday', '2026-09-28'],
            'clear' => ['clear', null],
        ];
    }

    #[DataProvider('targets')]
    public function test_every_target_moves_all_soft_overdue_tasks_and_leaves_the_rest(string $when, ?string $expected): void
    {
        $user = $this->user();
        $a = Task::factory()->for($user)->todos()->create(['due_date' => '2026-09-20']);
        $b = Task::factory()->for($user)->tasks()->create(['due_date' => '2026-09-01']);
        $future = Task::factory()->for($user)->todos()->create(['due_date' => '2026-10-05']);
        $hard = Task::factory()->for($user)->todos()->create(['deadline' => '2026-09-20']);

        Livewire::actingAs($user)->test(OverdueRescue::class)
            ->call('reschedule', $when)
            ->assertDispatched('captured');

        $this->assertSame($expected, $a->fresh()->due_date?->toDateString());
        $this->assertSame($expected, $b->fresh()->due_date?->toDateString());
        $this->assertSame('2026-10-05', $future->fresh()->due_date->toDateString());
        $this->assertSame('2026-09-20', $hard->fresh()->deadline->toDateString());
    }

    public function test_an_unknown_target_does_nothing(): void
    {
        $user = $this->user();
        $task = Task::factory()->for($user)->todos()->create(['due_date' => '2026-09-20']);

        Livewire::actingAs($user)->test(OverdueRescue::class)->call('reschedule', 'yesterday');

        $this->assertSame('2026-09-20', $task->fresh()->due_date->toDateString());
    }

    public function test_the_confirmation_names_the_count_and_undo_restores_every_old_date(): void
    {
        $user = $this->user();
        $a = Task::factory()->for($user)->todos()->create(['due_date' => '2026-09-20']);
        $b = Task::factory()->for($user)->todos()->create(['due_date' => '2026-09-10']);

        $component = Livewire::actingAs($user)->test(OverdueRescue::class)
            ->call('reschedule', 'tomorrow')
            ->assertSee('2 Aufgaben auf morgen gelegt.')
            ->assertSee('Rückgängig')
            ->assertDontSee('warten noch auf ein neues Datum');

        $component->call('undo')->assertSet('appliedLabel', null)->assertSee('2 Aufgaben warten');

        $this->assertSame('2026-09-20', $a->fresh()->due_date->toDateString());
        $this->assertSame('2026-09-10', $b->fresh()->due_date->toDateString());
    }

    public function test_undo_never_reopens_a_task_finished_in_the_meantime(): void
    {
        $user = $this->user();
        $task = Task::factory()->for($user)->todos()->create(['due_date' => '2026-09-20']);

        $component = Livewire::actingAs($user)->test(OverdueRescue::class)->call('reschedule', 'today');
        $task->update(['is_completed' => true, 'completed_at' => now()]);
        $component->call('undo');

        $this->assertSame('2026-09-23', $task->fresh()->due_date->toDateString());
    }

    public function test_a_task_finished_after_the_banner_rendered_is_not_moved(): void
    {
        $user = $this->user();
        $stale = Task::factory()->for($user)->todos()->create(['due_date' => '2026-09-20']);
        Task::factory()->for($user)->todos()->create(['due_date' => '2026-09-19']);

        $component = Livewire::actingAs($user)->test(OverdueRescue::class);
        $stale->update(['is_completed' => true, 'completed_at' => now()]);
        $component->call('reschedule', 'tomorrow')->assertSee('1 Aufgabe auf morgen gelegt.');

        $this->assertSame('2026-09-20', $stale->fresh()->due_date->toDateString());
    }

    public function test_the_previous_dates_cannot_be_written_from_the_client(): void
    {
        $user = $this->user();
        $mine = Task::factory()->for($user)->todos()->create(['due_date' => '2026-09-20']);

        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($user)->test(OverdueRescue::class)
            ->set('previousDates', [$mine->id => '2030-01-01']);
    }

    public function test_dismiss_hides_the_banner_for_the_rest_of_the_local_day_only(): void
    {
        $user = $this->user();
        Task::factory()->for($user)->todos()->create(['title' => 'Alt', 'due_date' => '2026-09-20']);

        Livewire::actingAs($user)->test(OverdueRescue::class)
            ->assertSee('1 Aufgabe wartet')
            ->call('dismiss')
            ->assertDontSee('1 Aufgabe wartet');

        // Next local day: the dismissal has expired and the banner is back.
        Carbon::setTestNow('2026-09-24 12:00:00');
        Livewire::actingAs($user)->test(OverdueRescue::class)->assertSee('1 Aufgabe wartet');
    }

    public function test_the_banner_is_on_the_board_but_not_on_other_pages(): void
    {
        $user = $this->user();
        Task::factory()->for($user)->todos()->create(['title' => 'Alt', 'due_date' => '2026-09-20']);

        $this->actingAs($user)->get(route('app'))->assertSee('wartet noch auf ein neues Datum', false);
        $this->actingAs($user)->get(route('settings'))->assertDontSee('wartet noch auf ein neues Datum', false);
    }

    public function test_the_board_re_renders_after_a_move(): void
    {
        $user = $this->user();
        Task::factory()->for($user)->todos()->create(['due_date' => '2026-09-20']);

        Livewire::actingAs($user)->test(OverdueRescue::class)
            ->call('reschedule', 'today')
            ->assertDispatched('captured');
    }
}
