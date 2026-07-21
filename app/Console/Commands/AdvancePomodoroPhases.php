<?php

namespace App\Console\Commands;

use App\Models\ScheduleEvent;
use App\Services\PomodoroSessionService;
use Illuminate\Console\Command;

/**
 * Ticks every active Pomodoro session forward independently of any open tab
 * or Livewire request — without this, a phase never actually advances (and
 * so never notifies) once the browser is closed. Run every minute by the
 * scheduler (see bootstrap/app.php).
 */
class AdvancePomodoroPhases extends Command
{
    protected $signature = 'app:advance-pomodoro-phases';

    protected $description = 'Advance (or freeze) every Pomodoro session whose current phase has elapsed, sending push notifications as needed';

    public function handle(PomodoroSessionService $pomodoro): int
    {
        $ticked = 0;

        ScheduleEvent::query()
            ->whereNotNull('pomodoro_phase')
            ->whereNotNull('pomodoro_started_at')
            ->with('user')
            ->chunkById(100, function ($events) use ($pomodoro, &$ticked) {
                foreach ($events as $event) {
                    $pomodoro->handleTick($event, $event->user);
                    $ticked++;
                }
            });

        $this->info("Checked {$ticked} active Pomodoro session(s).");

        return self::SUCCESS;
    }
}
