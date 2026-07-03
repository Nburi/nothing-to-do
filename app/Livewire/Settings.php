<?php

namespace App\Livewire;

use App\Models\ScheduleEvent;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Settings extends Component
{
    public string $resetTime = '01:00';

    // Pomodoro rhythm
    public int $pWork = 25;

    public int $pShortBreak = 5;

    public int $pLongBreak = 15;

    public int $pLongEvery = 4;

    // Timezone
    public float $timezoneOffset = 0;

    public bool $timezoneAutoDst = false;

    // Add-category form
    public string $newCategoryName = '';

    public string $newCategoryColor = 'contour';

    public function mount(): void
    {
        $user = auth()->user();

        $this->resetTime = $user->task_reset_time ?? '01:00';
        $this->pWork = $user->pomodoro_work ?? 25;
        $this->pShortBreak = $user->pomodoro_short_break ?? 5;
        $this->pLongBreak = $user->pomodoro_long_break ?? 15;
        $this->pLongEvery = $user->pomodoro_long_every ?? 4;
        $this->timezoneOffset = (float) ($user->timezone_offset ?? 0);
        $this->timezoneAutoDst = $user->timezone_auto_dst ?? false;
    }

    public function save(): void
    {
        $data = $this->validate([
            'resetTime' => ['required', 'date_format:H:i'],
        ]);

        auth()->user()->update(['task_reset_time' => $data['resetTime']]);

        $this->dispatch('saved');
    }

    public function saveSchedule(): void
    {
        $data = $this->validate([
            'pWork' => ['required', 'integer', 'min:1'],
            'pShortBreak' => ['required', 'integer', 'min:1'],
            'pLongBreak' => ['required', 'integer', 'min:1'],
            'pLongEvery' => ['required', 'integer', 'min:1'],
        ]);

        auth()->user()->update([
            'pomodoro_work' => $data['pWork'],
            'pomodoro_short_break' => $data['pShortBreak'],
            'pomodoro_long_break' => $data['pLongBreak'],
            'pomodoro_long_every' => $data['pLongEvery'],
        ]);

        $this->dispatch('schedule-saved');
    }

    public function saveTimezone(): void
    {
        $data = $this->validate([
            'timezoneOffset' => ['required', 'numeric', 'between:-12,14'],
            'timezoneAutoDst' => ['boolean'],
        ]);

        auth()->user()->update([
            'timezone_offset' => $data['timezoneOffset'],
            'timezone_auto_dst' => $data['timezoneAutoDst'],
        ]);

        $this->dispatch('timezone-saved');
    }

    /** The user's categories, for the settings list. */
    #[Computed]
    public function categories(): Collection
    {
        return auth()->user()->eventCategories()->ordered()->get();
    }

    public function addCategory(): void
    {
        $this->newCategoryName = trim($this->newCategoryName);

        $data = $this->validate([
            'newCategoryName' => ['required', 'string', 'max:255'],
            'newCategoryColor' => ['required', Rule::in(ScheduleEvent::EVENT_COLORS)],
        ]);

        auth()->user()->eventCategories()->create([
            'name' => $data['newCategoryName'],
            'color' => $data['newCategoryColor'],
            'sort_order' => auth()->user()->eventCategories()->count(),
        ]);

        $this->reset(['newCategoryName', 'newCategoryColor']);
    }

    public function renameCategory(int $id, string $name): void
    {
        $name = trim($name);

        if ($name === '' || mb_strlen($name) > 255) {
            return;
        }

        auth()->user()->eventCategories()->whereKey($id)->update(['name' => $name]);
    }

    public function setCategoryColor(int $id, string $color): void
    {
        if (! in_array($color, ScheduleEvent::EVENT_COLORS, true)) {
            return;
        }

        auth()->user()->eventCategories()->whereKey($id)->update(['color' => $color]);
    }

    public function toggleCategoryPomodoro(int $id): void
    {
        $category = auth()->user()->eventCategories()->findOrFail($id);
        $category->update(['pomodoro_enabled' => ! $category->pomodoro_enabled]);
    }

    public function deleteCategory(int $id): void
    {
        auth()->user()->eventCategories()->whereKey($id)->delete();
    }

    public function render()
    {
        return view('livewire.settings');
    }
}
