<?php

namespace Tests\Support;

use App\Livewire\Schedule;
use App\Livewire\WeekPlan;
use App\Livewire\WeekReviewPage;
use Illuminate\Support\Facades\Route;

/**
 * Step 5.4: /app/schedule, /app/weekplan and /app/review redirect into the shell, so no real
 * route renders the old components any more. They are kept (and still tested) until the old
 * screens are deleted after the switch; this mounts each one at a test-only URL, with the same
 * middleware as the real routes had, so the HTTP-level tests of the old HTML keep running.
 *
 * Delete this trait together with the old screens.
 */
trait LegacyScreens
{
    /** The test-only URL that renders the old screen: 'schedule', 'weekplan' or 'review'. */
    protected function legacy(string $screen): string
    {
        $component = [
            'schedule' => Schedule::class,
            'weekplan' => WeekPlan::class,
            'review' => WeekReviewPage::class,
        ][$screen];

        if (! Route::has('legacy.'.$screen)) {
            Route::middleware(['web', 'auth'])->get('/_legacy/'.$screen, $component)->name('legacy.'.$screen);
            app('router')->getRoutes()->refreshNameLookups();
        }

        return '/_legacy/'.$screen;
    }
}
