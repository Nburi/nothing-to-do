<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Settings extends Component
{
    public string $resetTime = '01:00';

    public function mount(): void
    {
        $this->resetTime = auth()->user()->task_reset_time ?? '01:00';
    }

    public function save(): void
    {
        $data = $this->validate([
            'resetTime' => ['required', 'date_format:H:i'],
        ]);

        auth()->user()->update(['task_reset_time' => $data['resetTime']]);

        $this->dispatch('saved');
    }

    public function render()
    {
        return view('livewire.settings');
    }
}
