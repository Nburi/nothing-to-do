<?php

namespace App\Livewire\Shell;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * The first-run tour of the new app (step 5.1): three taps that explain the loop (capture, sort,
 * evening start with the board folding away). Calm and skippable.
 *
 * It sits in the shell layout, so a new account sees it on whatever page of the new app it opens
 * first. It opens when the account has not seen it yet (users.shell_tour_seen_at is null; the
 * migration stamped everyone who existed before) or when the page is called with ?tour=1 (the
 * "Rundgang" card in the settings). Finishing and skipping are the same: the first one stamps the
 * column, a replay changes nothing. Moving between the three steps is client-side only (Alpine),
 * so the server is asked once, at the end.
 */
class Tour extends Component
{
    public bool $open = false;

    public function mount(): void
    {
        $user = Auth::user();

        $this->open = $user !== null && ($user->needsShellTour() || request()->boolean('tour'));
    }

    public function finish(): void
    {
        $user = Auth::user();

        if ($user->needsShellTour()) {
            $user->markShellTourSeen();
        }

        $this->open = false;
    }

    public function render(): View
    {
        return view('livewire.shell.tour');
    }
}
