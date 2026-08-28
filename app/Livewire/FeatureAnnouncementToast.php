<?php

namespace App\Livewire;

use App\Models\FeatureAnnouncement;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The "here's what's new" toast — mounted once in layouts/app.blade.php (like
 * the celebration overlay), not inside any one page, so it can appear no
 * matter which page a user lands on first. Shows at most one unseen,
 * published App\Models\FeatureAnnouncement at a time, oldest-published first;
 * dismissing advances to the next one in the same queue. Renders nothing at
 * all once the queue is empty — same "zero footprint when there's nothing to
 * show" convention as the homework preview strip and the prepare prompt.
 *
 * Also carries the long-gap "welcome back" greeting (see
 * User::WELCOME_BACK_MESSAGES): AuthenticatedSessionController flashes an
 * already-chosen message into the session on a 14+ day return login; this
 * component reads it exactly once via session()->pull() in mount(), so it
 * survives however many further Livewire round trips happen on this page but
 * never reappears on a later page load once it's been read.
 */
class FeatureAnnouncementToast extends Component
{
    /**
     * How many announcements were queued when this toast instance first
     * mounted — captured once, never recomputed, so the "3 von 7" counter
     * counts down consistently as items are dismissed instead of both
     * numbers shrinking together.
     */
    public ?int $initialQueueTotal = null;

    /** A random "welcome back" line for a 14+ day return, or null on an ordinary login. */
    public ?string $welcomeMessage = null;

    public function mount(): void
    {
        $this->welcomeMessage = session()->pull('welcome_back_message');
        $this->initialQueueTotal = $this->queue->count();
    }

    /** Published announcements this user hasn't dismissed yet, oldest first. */
    #[Computed]
    public function queue()
    {
        return FeatureAnnouncement::query()->unseenBy(auth()->user())->get();
    }

    #[Computed]
    public function current(): ?FeatureAnnouncement
    {
        return $this->queue->first();
    }

    /** How many other unseen announcements remain after the current one. */
    #[Computed]
    public function remainingAfterCurrent(): int
    {
        return max(0, $this->queue->count() - 1);
    }

    /** 1-based position of the current announcement within this toast's original backlog. */
    #[Computed]
    public function positionInQueue(): int
    {
        return max(1, ($this->initialQueueTotal ?? $this->queue->count()) - $this->queue->count() + 1);
    }

    public function dismiss(int $id): void
    {
        if ($this->positionInQueue === 1) {
            // The welcome line, if any, was shown fused into this very card
            // (see the view's positionInQueue === 1 gate) — clear it here so
            // it can't resurface as a standalone "welcome only" card once the
            // backlog drains to empty a moment from now.
            $this->welcomeMessage = null;
        }

        $announcement = FeatureAnnouncement::query()->published()->findOrFail($id);

        $announcement->dismissFor(auth()->user());

        unset($this->queue, $this->current, $this->remainingAfterCurrent, $this->positionInQueue);
    }

    /**
     * Dismisses every still-queued announcement in one action, for a backlog
     * the user would rather clear than browse. Bulk-insert, same shape as
     * SchedulePause::pauseRange() — one query instead of one dismissFor()
     * call per row. Not behind the armed-double-click pattern: it carries the
     * same consequence as dismissing one at a time (nothing is deleted, just
     * marked seen), so it gets the same single-click weight the existing
     * "Verstanden" button already has.
     */
    public function skipAll(): void
    {
        $ids = $this->queue->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        // Whatever card this was clicked from was showing the welcome line
        // (it's always fused into position 1 — see the view), so clearing
        // the whole backlog also counts as having seen the greeting; without
        // this it would resurface as a standalone card right after.
        $this->welcomeMessage = null;

        $now = now();

        DB::table('feature_announcement_dismissals')->insertOrIgnore(
            $ids->map(fn (int $id) => [
                'feature_announcement_id' => $id,
                'user_id' => auth()->id(),
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );

        unset($this->queue, $this->current, $this->remainingAfterCurrent, $this->positionInQueue);
    }

    public function render()
    {
        return view('livewire.feature-announcement-toast');
    }
}
