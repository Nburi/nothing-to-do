<?php

namespace App\Livewire\Shell;

use App\Models\EventCategory;
use App\Models\Task;
use App\Models\User;
use App\Planning\Battery;
use App\Planning\Session\Phase;
use App\Planning\Session\SessionState;
use App\Services\FocusSession;
use App\Services\Planning\CrunchDecisions;
use App\Services\Planning\Suggestion;
use App\Services\Planning\Suggestions;
use App\Services\Planning\WeekPlanReader;
use App\Support\UserClock;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The new app (refactor step 2.2): board while idle, focus during a session (R1, CONVENTIONS 1).
 *
 * One component renders both layouts, Werkbank (≥ 720 px) and Stapel (< 720 px); CSS decides
 * which one shows, so a resize never loses state and both always offer the same actions (A11).
 * The server owns the session (FocusSession); the timer is an Alpine island that only counts
 * down what the server said (risk C5). Board and session are sibling regions of the shell root,
 * which is where step 3.1 hangs the signature moment "The board folds away" (CONVENTIONS 10).
 */
#[Layout('layouts.shell')]
class Board extends Component
{
    /** Items shown per list before "+ N more". */
    public const LIST_LIMIT = 40;

    /** A calm one-line message after an action (shown inline, never an alert). */
    public ?string $notice = null;

    // ---- reads -------------------------------------------------------------------------------

    #[Computed]
    public function user(): User
    {
        return Auth::user();
    }

    #[Computed]
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now();
    }

    #[Computed]
    public function session(): SessionState
    {
        return app(FocusSession::class)->current($this->user, $this->now);
    }

    public function inSession(): bool
    {
        return $this->session->phase !== Phase::Idle;
    }

    #[Computed]
    public function suggestion(): Suggestion
    {
        return app(Suggestions::class)->current($this->user, $this->now);
    }

    /** @return array<string, Collection<int, Task>> */
    #[Computed]
    public function lists(): array
    {
        $tasks = Task::forUser($this->user)->active()
            ->with('area')
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->groupBy('list');

        $out = [];
        foreach (Task::LISTS as $list) {
            $out[$list] = $tasks->get($list, collect());
        }

        return $out;
    }

    /** @return array<string, int> */
    #[Computed]
    public function counts(): array
    {
        return array_map(fn (Collection $c) => $c->count(), $this->lists);
    }

    #[Computed]
    public function sessionTask(): ?Task
    {
        $id = $this->session->taskId;

        return $id === null ? null : Task::forUser($this->user)->with('area')->find($id);
    }

    #[Computed]
    public function nextInSession(): ?Task
    {
        $task = $this->sessionTask;
        if ($task === null) {
            return null;
        }

        $nextId = app(Suggestions::class)->nextInSession($this->user, $task->id, $this->now);

        return $nextId === null ? null : Task::forUser($this->user)->find($nextId);
    }

    /** Seconds left in the running phase and its full length, for the timer island. */
    #[Computed]
    public function timer(): array
    {
        $state = $this->session;
        $remaining = app(FocusSession::class)->remainingSeconds($this->user, $this->now);
        $total = match ($state->phase) {
            Phase::Work => 25 * 60,
            Phase::ShortBreak => 5 * 60,
            Phase::LongBreak => 15 * 60,
            default => 0,
        };

        return [
            'remaining' => $remaining,
            'total' => $total,
            'paused' => $state->isPaused(),
            'running' => $state->phase->isTimed() && ! $state->isPaused(),
            // Changes whenever the server state changes, so the island re-initialises.
            'key' => implode('-', [$state->phase->value, $state->taskId, $state->phaseStartedAt, $state->pausedAt, $state->pausedSeconds]),
        ];
    }

    /** Tasks finished today (local day), newest first. */
    #[Computed]
    public function doneToday(): Collection
    {
        $tz = UserClock::timezone($this->user, $this->now);
        $start = $this->now->setTimezone($tz)->startOfDay()->utc();

        return Task::forUser($this->user)
            ->where('is_completed', true)
            ->where('completed_at', '>=', $start)
            ->orderByDesc('completed_at')
            ->limit(5)
            ->get();
    }

    /**
     * Today's blocks on a time line around now: pomodoro blocks are "work", others "fixed".
     *
     * @return array{from: int, to: int, now: float, segments: list<array>, free: string, ticks: list<array>}
     */
    #[Computed]
    public function timeline(): array
    {
        $tz = UserClock::timezone($this->user, $this->now);
        $local = $this->now->setTimezone($tz);
        $date = $local->toDateString();

        $categories = EventCategory::forUser($this->user)->get()->keyBy('id');
        $blocks = app(WeekPlanReader::class)->blocks($this->user, $date, $date)[$date] ?? [];

        $from = max(0, min($local->hour - 1, 18));
        $to = min(24, max($from + 6, $local->hour + 4));
        $segments = [];
        $freeUntil = null;

        foreach ($blocks as $block) {
            $start = $block->start->setTimezone($tz);
            $end = $block->end->setTimezone($tz);
            $category = $block->categoryId !== null ? $categories->get($block->categoryId) : null;
            $isWork = (bool) $category?->pomodoro_enabled;

            $s = $start->hour + $start->minute / 60;
            $e = $end->toDateString() !== $date ? 24.0 : $end->hour + $end->minute / 60;
            $from = (int) min($from, floor($s));
            $to = (int) max($to, ceil($e));

            $segments[] = [
                'start' => $s,
                'end' => $e,
                'kind' => $isWork ? 'work' : 'fixed',
                'label' => $block->title ?? $category?->name ?? '',
                'time' => $start->format('H:i'),
            ];

            if (! $isWork && $start->greaterThan($local) && ($freeUntil === null || $start->lessThan($freeUntil))) {
                $freeUntil = $start;
            }
        }

        $to = min(24, $to);
        $ticks = [];
        $step = ($to - $from) > 8 ? 3 : 1;
        for ($h = $from; $h <= $to; $h += $step) {
            $ticks[] = ['hour' => $h, 'label' => sprintf('%02d', $h % 24)];
        }

        return [
            'from' => $from,
            'to' => $to,
            'now' => $local->hour + $local->minute / 60,
            'segments' => $segments,
            'ticks' => $ticks,
            'free' => $freeUntil
                ? __('shell.free_until', ['time' => $freeUntil->format('H:i')])
                : __('shell.free_tonight'),
        ];
    }

    /** "um 19:30" today, "morgen um 07:00", or a weekday. */
    public function whenLabel(?CarbonImmutable $at): ?string
    {
        if ($at === null) {
            return null;
        }

        $tz = UserClock::timezone($this->user, $this->now);
        $local = $at->setTimezone($tz);
        $today = $this->now->setTimezone($tz)->startOfDay();
        $days = (int) $today->diffInDays($local->startOfDay());
        $time = $local->format('H:i');

        return match (true) {
            $days === 0 => __('shell.when.today', ['time' => $time]),
            $days === 1 => __('shell.when.tomorrow', ['time' => $time]),
            default => __('shell.when.day', ['day' => $local->locale(app()->getLocale())->isoFormat('dddd'), 'time' => $time]),
        };
    }

    // ---- actions -----------------------------------------------------------------------------

    /** Start the suggestion, a task picked from a list, or (no id) the carried-over task. */
    public function start(?int $taskId = null): void
    {
        $this->notice = null;
        $taskId ??= $this->session->phase === Phase::Idle ? $this->suggestion->task?->id : null;

        if ($taskId === null && $this->session->taskId === null) {
            $this->notice = __('shell.errors.nothing_to_start');

            return;
        }

        $this->guard(fn () => app(FocusSession::class)->start($this->user, $taskId, CarbonImmutable::now()));
        $this->dispatch('shell-peek-close');
    }

    public function another(): void
    {
        $this->notice = null;
        app(Suggestions::class)->another($this->user, CarbonImmutable::now());
        $this->fresh();
    }

    public function setBattery(string $level): void
    {
        $battery = Battery::tryFrom($level);
        if ($battery === null) {
            return;
        }

        app(Suggestions::class)->setBattery($this->user, $battery, CarbonImmutable::now());
        $this->fresh();
    }

    public function shrink(int $taskId): void
    {
        $this->notice = null;
        $this->guard(function () use ($taskId) {
            $before = Task::forUser($this->user)->active()->findOrFail($taskId)->effectiveSize();
            $task = app(CrunchDecisions::class)->shrink($this->user, $taskId);
            $after = $task->effectiveSize();

            $this->notice = $after === $before
                ? __('shell.cannot_shrink')
                : __('shell.shrunk', ['size' => __('shell.sizes.'.$after->value)]);
        });
    }

    public function done(): void
    {
        $taskId = $this->session->taskId;
        if ($taskId === null) {
            return;
        }

        $this->guard(fn () => app(FocusSession::class)->done($this->user, $taskId, CarbonImmutable::now()));
    }

    public function pause(): void
    {
        app(FocusSession::class)->pause($this->user, CarbonImmutable::now());
        $this->fresh();
    }

    public function resume(): void
    {
        app(FocusSession::class)->resume($this->user, CarbonImmutable::now());
        $this->fresh();
    }

    public function stop(): void
    {
        app(FocusSession::class)->stop($this->user, CarbonImmutable::now());
        $this->fresh();
        $this->dispatch('shell-peek-close');
    }

    /** Called by the timer island when a phase ran out: the server moves the state on. */
    public function tick(): void
    {
        // Nothing to do: every render reads the current state with elapsed time applied.
    }

    private function guard(callable $action): void
    {
        try {
            $action();
        } catch (ModelNotFoundException) {
            $this->notice = __('shell.errors.task_gone');
        }

        $this->fresh();
    }

    /** Drop this request's cached reads, so the render after an action shows the new state. */
    private function fresh(): void
    {
        foreach (['now', 'session', 'suggestion', 'lists', 'counts', 'sessionTask', 'nextInSession', 'timer', 'doneToday', 'timeline'] as $computed) {
            unset($this->{$computed});
        }
    }

    public function render(): View
    {
        return view('livewire.shell.board', [
            'mode' => $this->inSession() ? 'session' : 'board',
        ])->title(__('shell.nav.board').' · nothing-to-do');
    }
}
