<?php

namespace Tests\Feature;

use App\Livewire\PrepareTomorrow;
use App\Models\AgendaEntry;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Tests\TestCase;

class PrepareDeadlineStripTest extends TestCase
{
    use RefreshDatabase;

    public function test_step_three_shows_what_is_due_on_the_target_day(): void
    {
        $user = User::factory()->create(); // evening mode (default): target = tomorrow
        $tomorrow = $user->localToday()->addDay();

        Task::factory()->for($user)->deadline($tomorrow->toDateString())->create(['title' => 'Bericht abgeben']);
        Task::factory()->for($user)->deadline($tomorrow->copy()->addDays(5)->toDateString())->create(['title' => 'Viel später']);
        AgendaEntry::factory()->for($user)->create([
            'type' => 'homework', 'title' => 'Aufgaben 4–6', 'subject' => 'Mathe', 'date' => $tomorrow->toDateString(),
        ]);

        $component = Livewire::actingAs($user)->test(PrepareTomorrow::class);
        $titles = $component->instance()->targetDeadlineItems->pluck('title')->all();

        $this->assertContains('Bericht abgeben', $titles);
        $this->assertContains('Aufgaben 4–6', $titles);
        $this->assertNotContains('Viel später', $titles);
        $component->assertSee('Bericht abgeben');
    }

    public function test_morning_mode_targets_today(): void
    {
        $user = User::factory()->create(['prepare_time_of_day' => 'morning']);
        Task::factory()->for($user)->deadline($user->localToday()->toDateString())->create(['title' => 'Heute fällig']);
        Task::factory()->for($user)->deadline($user->localToday()->addDay()->toDateString())->create(['title' => 'Morgen fällig']);

        $titles = Livewire::actingAs($user)->test(PrepareTomorrow::class)->instance()->targetDeadlineItems->pluck('title')->all();

        $this->assertSame(['Heute fällig'], $titles);
    }

    public function test_an_empty_day_renders_no_strip(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(PrepareTomorrow::class);

        $this->assertTrue($component->instance()->targetDeadlineItems->isEmpty());
        $component->assertDontSee('prep-dl-strip');
    }

    public function test_a_task_can_be_ticked_off_from_the_strip(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->deadline($user->localToday()->addDay()->toDateString())->create();

        Livewire::actingAs($user)->test(PrepareTomorrow::class)->call('toggleDeadlineTaskDone', $task->id);

        $this->assertTrue($task->fresh()->is_completed);
    }

    public function test_a_foreign_task_cannot_be_ticked_off(): void
    {
        $task = Task::factory()->for(User::factory()->create())->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs(User::factory()->create())->test(PrepareTomorrow::class)->call('toggleDeadlineTaskDone', $task->id);
    }
}
