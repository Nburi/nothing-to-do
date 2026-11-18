<?php

namespace App\Livewire\Shell;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Settings of the new app (step 2.2): appearance now, the Shortcut token from step 2.3.
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
