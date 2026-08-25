<?php

namespace App\Livewire;

use App\Models\FeatureAnnouncement;
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
 */
class FeatureAnnouncementToast extends Component
{
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

    public function dismiss(int $id): void
    {
        $announcement = FeatureAnnouncement::query()->published()->findOrFail($id);

        $announcement->dismissFor(auth()->user());

        unset($this->queue, $this->current, $this->remainingAfterCurrent);
    }

    public function render()
    {
        return view('livewire.feature-announcement-toast');
    }
}
