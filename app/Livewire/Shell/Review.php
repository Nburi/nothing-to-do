<?php

namespace App\Livewire\Shell;

use App\Models\User;
use App\Services\Planning\SundayReview;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The quiet Sunday review (refactor step 4.4, Niels' picks 2026-11-17): one calm screen,
 * variant A "two halves". Left what moved in the week, right the next seven days (room against
 * planned work). Read-only: it changes nothing and warns about nothing (the warning is step 4.2).
 *
 * Reached from a quiet line on the board on Sunday and Monday (both layouts) or by its URL.
 * The old /app/review redirects here since the switch preparation (5.4).
 */
#[Layout('layouts.shell')]
class Review extends Component
{
    #[Computed]
    public function user(): User
    {
        return Auth::user();
    }

    #[Computed]
    public function review(): array
    {
        return app(SundayReview::class)->for($this->user);
    }

    public static function weekUrl(): string
    {
        return route('shell.week');
    }

    public function render(): View
    {
        return view('livewire.shell.review')->title(__('shell.review.title').' · nothing-to-do');
    }
}
