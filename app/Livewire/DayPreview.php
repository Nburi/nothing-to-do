<?php

namespace App\Livewire;

use App\Services\DayPreviewData;
use App\Services\ProgressStats;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Tagesüberblick — a read-only "here's your day" page, reached via a silent
 * dot on a header icon rather than a screen you have to remember to open.
 * Visiting it (not any explicit dismiss) is what clears that dot for the
 * rest of the local calendar day — see User::markDayPreviewSeen(). Every
 * read is a fresh, live query (no caching/snapshotting), same convention
 * Fortschritt's own numbers already follow: a second visit the same day
 * shows whatever is true right now, not what was true on the first visit.
 */
#[Layout('layouts.app')]
class DayPreview extends Component
{
    /** Pinned once at mount so a mid-page-life clock tick can't reshuffle the greeting pool underneath a click. */
    public ?string $greeting = null;

    public function mount(): void
    {
        auth()->user()->markDayPreviewSeen();
        $this->greeting = $this->buildGreeting();
    }

    /**
     * Greeting pools and streak milestones both live in config/day_preview.php,
     * deliberately not as class constants — so the copy can be edited without
     * touching code (a config file only). A milestone message (exact streak
     * day match) always wins over the pool for that one visit — deliberately
     * just text, never the confetti/ring overlay ProgressStats::celebrationFor()
     * drives elsewhere: that stays reserved for a task completion actually
     * crossing a threshold, not for opening a page. An emptied-out pool falls
     * back to one plain line rather than crashing on array_rand([]).
     */
    private function buildGreeting(): string
    {
        $user = auth()->user();
        $streak = $this->streakDays;

        $milestones = config('day_preview.milestones', []);
        if (array_key_exists($streak, $milestones)) {
            return str_replace(':name', $user->name, $milestones[$streak]);
        }

        $hour = (int) $user->localNow()->format('G');
        $poolKey = match (true) {
            $hour < 12 => 'morning',
            $hour < 18 => 'midday',
            default => 'evening',
        };

        $pool = config("day_preview.greetings.{$poolKey}", []);

        if (empty($pool)) {
            return "Guten Tag, {$user->name}.";
        }

        return str_replace(':name', $user->name, $pool[array_rand($pool)]);
    }

    #[Computed]
    public function streakDays(): int
    {
        return ProgressStats::currentStreak(auth()->user());
    }

    #[Computed]
    public function emergency(): ?array
    {
        return DayPreviewData::emergency(auth()->user());
    }

    #[Computed]
    public function schedule(): array
    {
        return DayPreviewData::schedule(auth()->user());
    }

    #[Computed]
    public function due(): array
    {
        return DayPreviewData::due(auth()->user());
    }

    #[Computed]
    public function today(): array
    {
        return DayPreviewData::todayTasks(auth()->user());
    }

    /** Whether "today" has no is_today tasks — gates both the prepare nudge and the Bastelidee tile. */
    #[Computed]
    public function todayIsEmpty(): bool
    {
        return $this->today['count'] === 0;
    }

    #[Computed]
    public function agenda(): ?array
    {
        return DayPreviewData::agenda(auth()->user());
    }

    #[Computed]
    public function craftIdea(): ?array
    {
        return DayPreviewData::craftIdea(auth()->user(), $this->todayIsEmpty);
    }

    #[Computed]
    public function goal(): array
    {
        return DayPreviewData::goal(auth()->user());
    }

    public function render()
    {
        return view('livewire.day-preview');
    }
}
