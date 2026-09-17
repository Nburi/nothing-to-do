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

    /**
     * Milestones that override the plain greeting pool with a quiet callout —
     * deliberately just text, never the confetti/ring overlay ProgressStats::
     * celebrationFor() drives elsewhere: that stays reserved for the moment a
     * task completion actually crosses a threshold, not for opening a page.
     *
     * @var array<int, string>
     */
    private const MILESTONE_MESSAGES = [
        7 => 'Tag 7 deiner Serie — eine Woche am Stück.',
        14 => 'Tag 14 deiner Serie — zwei Wochen ohne Lücke.',
        30 => 'Tag 30 deiner Serie. Das ist kein Zufall mehr.',
        50 => 'Tag 50 deiner Serie.',
        100 => 'Tag 100 deiner Serie.',
    ];

    /** @var array<string, array<int, string>> */
    private const GREETING_POOLS = [
        'morning' => ["Guten Morgen, :name.", "Auf geht's, :name.", 'Morgen. Kaffee zuerst?', 'Ein neuer Tag, :name.'],
        'midday' => ['Hey :name — dein Tag im Überblick.', 'Zweite Tageshälfte, gleiche Prioritäten.', "Auf geht's, :name."],
        'evening' => ['Guten Abend, :name.', 'Der Tag ist fast rum — hier steht noch was aus.', 'Später Einstieg heute, aber gut.'],
    ];

    public function mount(): void
    {
        auth()->user()->markDayPreviewSeen();
        $this->greeting = $this->buildGreeting();
    }

    private function buildGreeting(): string
    {
        $user = auth()->user();
        $streak = $this->streakDays;

        if (array_key_exists($streak, self::MILESTONE_MESSAGES)) {
            return self::MILESTONE_MESSAGES[$streak];
        }

        $hour = (int) $user->localNow()->format('G');
        $pool = match (true) {
            $hour < 12 => self::GREETING_POOLS['morning'],
            $hour < 18 => self::GREETING_POOLS['midday'],
            default => self::GREETING_POOLS['evening'],
        };

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
