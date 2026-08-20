<?php

namespace Tests\Feature;

use App\Livewire\Schedule;
use App\Models\AgendaEntry;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class ScheduleDeadlineItemsTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $this->actingAs($user);

        return $user;
    }

    /** Monday of the week the Schedule component defaults to for this user. */
    private function weekStartFor(User $user): Carbon
    {
        return $user->localToday()->copy()->startOfWeek();
    }

    public function test_a_task_deadline_is_shown_on_its_own_date(): void
    {
        $user = $this->actingUser();
        $date = $this->weekStartFor($user)->addDays(2);
        $task = Task::factory()->for($user)->deadline($date->toDateString())->create(['title' => 'Bericht abgeben']);

        Livewire::test(Schedule::class)
            ->assertOk()
            ->assertSee('Bericht abgeben');

        $items = Livewire::test(Schedule::class)->instance()->deadlineItems;
        $onDay = $items->get($date->toDateString(), collect());

        $this->assertTrue($onDay->contains(fn (array $i) => $i['id'] === $task->id && ! $i['isPreview']));
    }

    public function test_a_hard_deadline_also_shows_an_advance_preview(): void
    {
        $user = $this->actingUser(['deadline_preview_enabled' => true, 'deadline_preview_days' => 2]);
        $weekStart = $this->weekStartFor($user);
        $date = $weekStart->copy()->addDays(4);
        $task = Task::factory()->for($user)->deadline($date->toDateString())->create(['title' => 'Prüfungsvorbereitung']);

        $items = Livewire::test(Schedule::class)->instance()->deadlineItems;
        $previewDay = $items->get($date->copy()->subDays(2)->toDateString(), collect());

        $this->assertTrue($previewDay->contains(
            fn (array $i) => $i['id'] === $task->id && $i['isPreview'] === true && $i['daysUntil'] === 2
        ));
    }

    public function test_agenda_exam_and_homework_preview_the_same_way(): void
    {
        $user = $this->actingUser(['deadline_preview_enabled' => true, 'deadline_preview_days' => 3]);
        $weekStart = $this->weekStartFor($user);
        $date = $weekStart->copy()->addDays(5);
        $entry = AgendaEntry::factory()->for($user)->exam()->create(['date' => $date->toDateString(), 'title' => 'Mathe-Prüfung']);

        $items = Livewire::test(Schedule::class)->instance()->deadlineItems;

        $onDay = $items->get($date->toDateString(), collect());
        $this->assertTrue($onDay->contains(fn (array $i) => $i['id'] === $entry->id && $i['kind'] === 'agenda' && ! $i['isPreview']));

        $previewDay = $items->get($date->copy()->subDays(3)->toDateString(), collect());
        $this->assertTrue($previewDay->contains(fn (array $i) => $i['id'] === $entry->id && $i['isPreview'] === true));
    }

    public function test_no_preview_is_shown_when_the_setting_is_disabled(): void
    {
        $user = $this->actingUser(['deadline_preview_enabled' => false, 'deadline_preview_days' => 2]);
        $weekStart = $this->weekStartFor($user);
        $date = $weekStart->copy()->addDays(4);
        Task::factory()->for($user)->deadline($date->toDateString())->create();

        $items = Livewire::test(Schedule::class)->instance()->deadlineItems;
        $previewDay = $items->get($date->copy()->subDays(2)->toDateString(), collect());

        $this->assertTrue($previewDay->isEmpty());
    }

    public function test_a_custom_preview_day_count_is_respected(): void
    {
        $user = $this->actingUser(['deadline_preview_enabled' => true, 'deadline_preview_days' => 5]);
        $weekStart = $this->weekStartFor($user);
        $date = $weekStart->copy()->addDays(6);
        $task = Task::factory()->for($user)->deadline($date->toDateString())->create();

        $items = Livewire::test(Schedule::class)->instance()->deadlineItems;

        // 2 days before (the old default) must NOT carry a preview...
        $twoDaysBefore = $items->get($date->copy()->subDays(2)->toDateString(), collect());
        $this->assertFalse($twoDaysBefore->contains(fn (array $i) => $i['id'] === $task->id && $i['isPreview']));

        // ...only exactly 5 days before does.
        $fiveDaysBefore = $items->get($date->copy()->subDays(5)->toDateString(), collect());
        $this->assertTrue($fiveDaysBefore->contains(fn (array $i) => $i['id'] === $task->id && $i['isPreview']));
    }

    public function test_a_soft_wunschtermin_never_gets_an_advance_preview(): void
    {
        $user = $this->actingUser(['deadline_preview_enabled' => true, 'deadline_preview_days' => 2]);
        $weekStart = $this->weekStartFor($user);
        $date = $weekStart->copy()->addDays(4);
        $task = Task::factory()->for($user)->dueDate($date->toDateString())->create();

        $items = Livewire::test(Schedule::class)->instance()->deadlineItems;

        $onDay = $items->get($date->toDateString(), collect());
        $this->assertTrue($onDay->contains(fn (array $i) => $i['id'] === $task->id && $i['subtype'] === 'due' && ! $i['isPreview']));

        $previewDay = $items->get($date->copy()->subDays(2)->toDateString(), collect());
        $this->assertFalse($previewDay->contains(fn (array $i) => $i['id'] === $task->id));
    }

    public function test_a_completed_task_is_hidden(): void
    {
        $user = $this->actingUser();
        $weekStart = $this->weekStartFor($user);
        $date = $weekStart->copy()->addDays(1);
        Task::factory()->for($user)->deadline($date->toDateString())->completed()->create(['title' => 'Bereits erledigt']);

        Livewire::test(Schedule::class)->assertDontSee('Bereits erledigt');
    }

    public function test_an_agenda_entry_done_by_the_user_is_hidden(): void
    {
        $user = $this->actingUser();
        $weekStart = $this->weekStartFor($user);
        $date = $weekStart->copy()->addDays(1);
        AgendaEntry::factory()->for($user)->homework()->done()->create(['date' => $date->toDateString(), 'title' => 'Erledigte Hausaufgabe']);

        Livewire::test(Schedule::class)->assertDontSee('Erledigte Hausaufgabe');
    }

    public function test_toggling_a_task_deadline_marks_it_complete(): void
    {
        $user = $this->actingUser();
        $task = Task::factory()->for($user)->deadline(now()->toDateString())->create();

        Livewire::test(Schedule::class)->call('toggleDeadlineTaskDone', $task->id);

        $this->assertTrue($task->refresh()->is_completed);
    }

    public function test_toggling_a_homework_derived_task_via_the_deadline_strip_also_completes_the_agenda_entry(): void
    {
        $user = $this->actingUser();
        $entry = AgendaEntry::factory()->for($user)->homework()->create(['date' => now()->toDateString()]);
        $task = Task::factory()->for($user)->tasks()->create([
            'deadline' => now()->toDateString(),
            'agenda_entry_id' => $entry->id,
        ]);

        Livewire::test(Schedule::class)->call('toggleDeadlineTaskDone', $task->id);

        $this->assertTrue($task->refresh()->is_completed);
        $this->assertTrue($entry->fresh()->isDoneFor($user));

        Livewire::test(Schedule::class)->call('toggleDeadlineTaskDone', $task->id);

        $this->assertFalse($task->refresh()->is_completed);
        $this->assertFalse($entry->fresh()->isDoneFor($user));
    }

    public function test_toggling_an_agenda_entry_marks_it_done_for_the_user(): void
    {
        $user = $this->actingUser();
        $entry = AgendaEntry::factory()->for($user)->homework()->create(['date' => now()->toDateString()]);

        Livewire::test(Schedule::class)->call('toggleDeadlineAgendaDone', $entry->id);

        $this->assertTrue($entry->refresh()->isDoneFor($user));
    }

    public function test_a_user_cannot_toggle_another_users_task(): void
    {
        $this->actingUser();
        $other = Task::factory()->for(User::factory())->deadline(now()->toDateString())->create();

        // Resolved through the owner relationship, so another user's id never
        // matches — the write is rejected before it can run.
        $this->expectException(ModelNotFoundException::class);

        Livewire::test(Schedule::class)->call('toggleDeadlineTaskDone', $other->id);
    }

    public function test_a_user_cannot_toggle_an_agenda_entry_they_cannot_see(): void
    {
        $this->actingUser();
        $other = AgendaEntry::factory()->for(User::factory())->homework()->create(['date' => now()->toDateString()]);

        // Resolved through AgendaEntry::scopeVisibleTo(), same as everywhere else in the app.
        $this->expectException(ModelNotFoundException::class);

        Livewire::test(Schedule::class)->call('toggleDeadlineAgendaDone', $other->id);
    }
}
