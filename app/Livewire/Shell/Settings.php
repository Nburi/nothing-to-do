<?php

namespace App\Livewire\Shell;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Settings of the new app (step 2.2): appearance, the evening nudge times (step 3.3) and the
 * Shortcut token (step 2.3).
 * Reached from the gear in the top bar on both layouts.
 */
#[Layout('layouts.shell')]
class Settings extends Component
{
    public function render(): View
    {
        return view('livewire.shell.settings')->title(__('shell.settings.title').' · nothing-to-do');
    }
}
