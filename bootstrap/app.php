<?php

use App\Http\Middleware\RecordModuleVisit;
use App\Services\ErrorStats;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        // No-ops on every route that isn't a module page — see the
        // middleware's own docblock for why this is registered globally
        // instead of attached per-route.
        $middleware->web(append: [RecordModuleVisit::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Every rendered HTML error page (404/403/419/500/...) is counted for
        // the admin "Fehler-Statistiken" page — but never a Livewire action
        // or the JSON API, both already excluded by expectsJson() here. No
        // return value: Laravel's own default rendering still runs
        // afterwards, which is what actually picks up resources/views/errors/
        // {404,4xx,5xx}.blade.php. See App\Services\ErrorStats::record().
        $exceptions->render(function (Throwable $e, Request $request): void {
            if (! $request->expectsJson()) {
                ErrorStats::record($e, $request);
            }
        });
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Both need production cron running `php artisan schedule:run` every
        // minute — see CLAUDE.md §9 Deployment.
        $schedule->command('app:advance-pomodoro-phases')->everyMinute()->withoutOverlapping();
        $schedule->command('app:send-event-start-notifications')->everyMinute()->withoutOverlapping();
        $schedule->command('app:send-event-upcoming-notifications')->everyMinute()->withoutOverlapping();
        $schedule->command('app:send-prepare-reminders')->everyMinute()->withoutOverlapping();
        $schedule->command('app:send-progress-reminders')->everyMinute()->withoutOverlapping();
        $schedule->command('app:send-day-preview-notifications')->everyMinute()->withoutOverlapping();
        $schedule->command('app:promote-day-plans-to-today')->everyMinute()->withoutOverlapping();
        $schedule->command('app:evaluate-streak-days')->everyMinute()->withoutOverlapping();
        $schedule->command('app:prune-error-occurrences')->daily()->withoutOverlapping();
    })
    ->create();
