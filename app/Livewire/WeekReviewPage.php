<?php

namespace App\Livewire;

use App\Services\AppModules;
use App\Services\WeekReview;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The Wochenrückblick page (/app/review): one local week at a time, current
 * week by default, paged with prev/next. A read-only companion to Fortschritt
 * — it belongs to the same "progress" module, so hiding that module hides
 * this page too. See App\Services\WeekReview for what it shows.
 */
#[Layout('layouts.app')]
class WeekReviewPage extends Component
{
    /** 0 = this week, -1 = last week, … never positive: the future has nothing to review. */
    public int $weekOffset = 0;

    public function mount(): void
    {
        // Follow the module toggle like every other page in that module: a hidden
        // Fortschritt must not stay reachable through a stale link.
        if (! AppModules::isVisible(auth()->user(), 'progress')) {
            $this->redirectRoute(auth()->user()->defaultLandingRouteName(), navigate: true);
        }
    }

    public function previousWeek(): void
    {
        $this->weekOffset--;
    }

    public function nextWeek(): void
    {
        $this->weekOffset = min(0, $this->weekOffset + 1);
    }

    public function thisWeek(): void
    {
        $this->weekOffset = 0;
    }

    #[Computed]
    public function review(): array
    {
        $user = auth()->user();

        return WeekReview::for($user, WeekReview::weekStart($user, min(0, $this->weekOffset)));
    }

    public function render()
    {
        return view('livewire.week-review');
    }
}
