<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Settings extends Component
{
    public string $resetTime = '01:00';

    // Brief
    public string $briefWhen = 'evening';

    public string $briefTime = '19:00';

    // Pomodoro rhythm
    public int $pWork = 25;

    public int $pShortBreak = 5;

    public int $pLongBreak = 15;

    public int $pLongEvery = 4;

    public function mount(): void
    {
        $user = auth()->user();

        $this->resetTime = $user->task_reset_time ?? '01:00';
        $this->briefWhen = $user->brief_when ?? 'evening';
        $this->briefTime = $user->brief_time ?? '19:00';
        $this->pWork = $user->pomodoro_work ?? 25;
        $this->pShortBreak = $user->pomodoro_short_break ?? 5;
        $this->pLongBreak = $user->pomodoro_long_break ?? 15;
        $this->pLongEvery = $user->pomodoro_long_every ?? 4;
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
            'briefWhen' => ['required', 'in:evening,morning'],
            'briefTime' => ['required', 'date_format:H:i'],
            'pWork' => ['required', 'integer', 'between:5,120'],
            'pShortBreak' => ['required', 'integer', 'between:1,60'],
            'pLongBreak' => ['required', 'integer', 'between:1,120'],
            'pLongEvery' => ['required', 'integer', 'between:2,12'],
        ]);

        auth()->user()->update([
            'brief_when' => $data['briefWhen'],
            'brief_time' => $data['briefTime'],
            'pomodoro_work' => $data['pWork'],
            'pomodoro_short_break' => $data['pShortBreak'],
            'pomodoro_long_break' => $data['pLongBreak'],
            'pomodoro_long_every' => $data['pLongEvery'],
        ]);

        $this->dispatch('schedule-saved');
    }

    /** Run the Brief now, regardless of the configured time (for trying it out). */
    public function startBrief()
    {
        return $this->redirect(route('brief'), navigate: true);
    }

    public function render()
    {
        return view('livewire.settings');
    }
}
