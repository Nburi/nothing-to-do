<?php

namespace App\Livewire;

use App\Services\HeaderBadges;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The header's badge row as a Livewire component of its own, so it can be
 * refreshed independently of whichever page component just handled an action.
 * Before this, the row was plain Blade in the layout and only caught up on the
 * next full page load (a completed task left "goal" stuck on its old count).
 *
 * The component has no actions. It re-renders on `$refresh`, which the global
 * hook in resources/js/app.js fires after any real action on the page
 * succeeds, on a visible-tab interval and when the tab regains focus.
 *
 * What to show is still entirely App\Services\HeaderBadges::visibleFor() — a
 * badge without anything to show is dropped, not rendered as a zero.
 *
 * $seen remembers the previous render's badges, so the view can tell a value
 * that just changed (or a badge that just appeared) from one that was simply
 * there — and can render a badge that just vanished one last time, as a
 * "leaving" ghost that collapses away, instead of dropping it out of the DOM
 * mid-glance. (Doing that client-side, from a morph hook, doesn't work: the
 * morph removes keyed nodes without calling its removal hooks.) The first
 * render after mount deliberately flags nothing: arriving on a page is not
 * an event. Locked, since the client has no business editing it.
 */
class HeaderBadgeRow extends Component
{
    /** @var array<string, array<string, mixed>>|null badge key => badge as last rendered; null until the first render */
    #[Locked]
    public ?array $seen = null;

    public function render(): View
    {
        $current = HeaderBadges::visibleFor(auth()->user());
        $previous = $this->seen;

        $badges = array_map(function (array $badge) use ($previous): array {
            $appeared = $previous !== null && ! array_key_exists($badge['key'], $previous);
            $before = $previous[$badge['key']] ?? null;

            return $badge + [
                'appeared' => $appeared,
                'changed' => $before !== null && $before['text'] !== $badge['text'],
                // The value that was just replaced, rolled out by the view.
                'previousText' => $before !== null && $before['text'] !== $badge['text'] ? $before['text'] : null,
            ];
        }, $current);

        $this->seen = collect($current)->keyBy('key')->all();

        return view('livewire.header-badge-row', [
            'badges' => $this->withLeavingBadges($badges, $previous),
        ]);
    }

    /**
     * Badges that were in the previous render but not in this one, put back at
     * the position they had, flagged as leaving.
     *
     * @param  list<array<string, mixed>>  $badges
     * @param  array<string, array<string, mixed>>|null  $previous
     * @return list<array<string, mixed>>
     */
    private function withLeavingBadges(array $badges, ?array $previous): array
    {
        if ($previous === null) {
            return $badges;
        }

        $currentKeys = array_column($badges, 'key');
        $result = $badges;
        $index = 0;

        foreach ($previous as $key => $badge) {
            if (in_array($key, $currentKeys, true)) {
                $index = array_search($key, array_column($result, 'key'), true) + 1;

                continue;
            }

            array_splice($result, $index, 0, [$badge + ['leaving' => true]]);
            $index++;
        }

        return $result;
    }
}
