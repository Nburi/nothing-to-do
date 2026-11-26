import Sortable from 'sortablejs';
import './week-editor';
import './step-drag';
import './board-order';

// Alpine is intentionally NOT imported or started here — Livewire 4 ships and
// boots its own Alpine. A second copy double-initialises every component.
// Livewire owns Alpine; we register data/helpers on top of it.
window.Sortable = Sortable;

// Register the service worker for offline resilience (app-shell caching + a calm
// custom offline page). Guarded for browsers/contexts without SW support; failure
// here has no functional impact on the app itself, only on the offline fallback.
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch((error) => {
            console.error('Service worker registration failed:', error);
        });
    });
}

/**
 * Primes the shared focus-timer AudioContext on the "Start" tap — a genuine
 * user gesture, required so the chime that later fires automatically (when a
 * phase ends) isn't blocked by the browser's autoplay policy.
 */
window.primeFocusAudio = function () {
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    if (!AudioCtx) return;
    if (!window._focusAudioCtx) window._focusAudioCtx = new AudioCtx();
    if (window._focusAudioCtx.state === 'suspended') window._focusAudioCtx.resume();
};

/**
 * Reads the browser's own timezone to suggest values for the app's two manual
 * timezone fields (see User::timezoneOffsetHours()/utcOffsetMinutes()): the base
 * UTC offset in hours during STANDARD (non-DST) time, and whether this zone
 * shifts forward for part of the year at all. Comparing Jan 1 and Jul 1 works
 * regardless of hemisphere — a DST shift is always further ahead of UTC than
 * standard time, so whichever reading is smaller is the DST one. Used both to
 * default a brand-new registration (resources/views/auth/register.blade.php)
 * and for the Settings page's "Automatisch erkennen" button.
 */
window.detectTimezoneDefaults = function () {
    const year = new Date().getFullYear();
    const jan = new Date(year, 0, 1).getTimezoneOffset();
    const jul = new Date(year, 6, 1).getTimezoneOffset();
    const standardOffsetMinutes = Math.max(jan, jul);

    return {
        offset: Math.round((-standardOffsetMinutes / 60) * 4) / 4,
        autoDst: jan !== jul,
    };
};

/** Decodes a VAPID public key (URL-safe base64) into the Uint8Array pushManager.subscribe() expects. */
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

/**
 * Requests notification permission, then subscribes this browser to real Web
 * Push and returns {endpoint, p256dh, auth} for the caller to persist
 * server-side — the server decides when to send, so this keeps working even
 * with every tab (and the whole browser) closed. Returns null if permission
 * was denied or the browser doesn't support it.
 */
window.subscribeToPush = async function (vapidPublicKey) {
    if (!('serviceWorker' in navigator) || typeof Notification === 'undefined') return null;

    const permission = await Notification.requestPermission();
    if (permission !== 'granted') return null;

    const registration = await navigator.serviceWorker.ready;
    let subscription = await registration.pushManager.getSubscription();
    if (!subscription) {
        subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
        });
    }

    const json = subscription.toJSON();
    return { endpoint: json.endpoint, p256dh: json.keys.p256dh, auth: json.keys.auth };
};

/** Unsubscribes this browser from Web Push. Returns the removed endpoint, or null if it wasn't subscribed. */
window.unsubscribeFromPush = async function () {
    if (!('serviceWorker' in navigator)) return null;

    const registration = await navigator.serviceWorker.ready;
    const subscription = await registration.pushManager.getSubscription();
    if (!subscription) return null;

    const endpoint = subscription.endpoint;
    await subscription.unsubscribe();
    return endpoint;
};

/** This browser's current push subscription endpoint, or null if not subscribed. */
window.currentPushSubscription = async function () {
    if (!('serviceWorker' in navigator)) return null;

    const registration = await navigator.serviceWorker.ready.catch(() => null);
    if (!registration) return null;

    const subscription = await registration.pushManager.getSubscription();
    return subscription ? subscription.endpoint : null;
};

/**
 * Drag & drop for a board zone (a column, or a column's Today area).
 * On drop we read the DESTINATION zone (evt.to) and persist its full id order
 * plus its list/today, so cross-column moves and in-column reordering both work.
 * Guarded so Livewire re-renders never double-initialise an element.
 *
 * `handle` restricts the drag-initiating target to a selector (e.g. a grip icon)
 * instead of the whole card — used on mobile, where the card body is already
 * claimed by swipeCard's horizontal-swipe/long-press gestures. Desktop cards
 * have no such gesture, so they're left draggable from anywhere (handle = null).
 */
window.boardSortable = function (el, wire, handle = null) {
    if (el._sortable) return el._sortable;
    const group = 'board';
    el._sortable = Sortable.create(el, {
        group,
        animation: 160,
        easing: 'cubic-bezier(0.16, 1, 0.3, 1)',
        ghostClass: 'board-ghost',
        chosenClass: 'board-chosen',
        handle: handle ?? undefined,
        delay: 60,
        delayOnTouchOnly: true,
        // Mark the page as dragging so drop affordances can show up.
        onStart: () => {
            document.body.classList.add('dragging-task');
        },
        onEnd: (evt) => {
            document.body.classList.remove('dragging-task');
            const to = evt.to;
            // Only persist real columns (a zone without data-list is not one).
            if (to.dataset.list === undefined) return;
            const ids = Array.from(to.querySelectorAll('[data-id]')).map((n) => n.dataset.id);
            wire.reorder(to.dataset.list, to.dataset.today === 'true', ids);
        },
    });
    return el._sortable;
};

/**
 * "Simple" concept's two zones — a Heute box and the rest of the flat list
 * (see TaskBoard::reorderSimple() / partials/board-simple.blade.php).
 * Deliberately its own small Sortable instance rather than reusing
 * boardSortable: that one's onEnd always calls wire.reorder(list, today,
 * ids), which would silently retriage a dragged task's `list` too —
 * Simple's own reorder must never touch `list` (see reorderSimple()'s
 * docblock), only `is_today`.
 *
 * Both zones share the plain 'board' group name so a task can be dragged
 * between them (in or out of Today), the same as a 3-Things column's own two
 * zones do.
 */
window.simpleListSortable = function (el, wire, handle = null) {
    if (el._sortable) return el._sortable;
    const group = 'board';
    el._sortable = Sortable.create(el, {
        group,
        animation: 160,
        easing: 'cubic-bezier(0.16, 1, 0.3, 1)',
        ghostClass: 'board-ghost',
        chosenClass: 'board-chosen',
        handle: handle ?? undefined,
        delay: 60,
        delayOnTouchOnly: true,
        onEnd: (evt) => {
            const to = evt.to;
            // A drop that isn't one of Simple's own two zones (e.g. rejected
            // by group matching) has nothing to persist here.
            if (to.dataset.today === undefined) return;
            const ids = Array.from(to.querySelectorAll('[data-id]')).map((n) => n.dataset.id);
            wire.reorderSimple(to.dataset.today === 'true', ids);
        },
    });
    return el._sortable;
};

/**
 * "Eisenhower" concept's four quadrants (see TaskBoard::reorderEisenhower(),
 * partials/board-eisenhower.blade.php). All four quadrant containers share
 * one Sortable group ('eisenhower') so a card can be dragged straight from
 * one to another, not just reordered within one — the vertical axis (row)
 * is read off `data-important`, the horizontal axis (column) off
 * `data-urgent`, both on the DESTINATION container, same shape as
 * boardSortable's own data-list/data-today read.
 *
 * Deliberately its own small instance rather than boardSortable: its onEnd
 * writes two flags instead of a list/today pair.
 *
 * The urgency axis (column) is one-way-lockable: a task with a hard
 * `deadline` has its urgency permanently decided by that deadline
 * (Task::effectiveDate() always prefers deadline over due_date — see
 * CLAUDE.md), so nothing a quadrant drop could write to due_date would ever
 * actually move it. Silently accepting the drop and letting it snap back on
 * the next render would be exactly the "UI lies" trap this app avoids
 * everywhere else — so `put` rejects a column-crossing drop for a card
 * carrying `data-urgency-locked="true"` up front, and the card visibly
 * refuses to leave instead. Moving between rows (importance) is always
 * allowed, locked or not — that axis never touches due_date at all.
 */
window.eisenhowerQuadrantSortable = function (el, wire, handle = null) {
    if (el._sortable) return el._sortable;
    el._sortable = Sortable.create(el, {
        group: {
            name: 'eisenhower',
            put: (to, from, dragEl) => {
                if (dragEl.dataset.urgencyLocked === 'true' && to.el.dataset.urgent !== from.el.dataset.urgent) {
                    return false;
                }

                return true;
            },
        },
        animation: 160,
        easing: 'cubic-bezier(0.16, 1, 0.3, 1)',
        ghostClass: 'board-ghost',
        chosenClass: 'board-chosen',
        handle: handle ?? undefined,
        delay: 60,
        delayOnTouchOnly: true,
        onEnd: (evt) => {
            const to = evt.to;
            if (to.dataset.important === undefined) return; // not a quadrant zone
            const ids = Array.from(to.querySelectorAll('[data-id]')).map((n) => n.dataset.id);
            wire.reorderEisenhower(to.dataset.important === 'true', to.dataset.urgent === 'true', ids);
        },
    });
    return el._sortable;
};

/**
 * "Kanban" concept's drag — one shared Sortable group ('kanban') spanning
 * all three columns (desktop grid + mobile tab panels, same "one group name
 * reused across every zone" shape as a 2×2/4-tab matrix concept would use).
 * onEnd reads the destination zone's `data-column` and its full id order,
 * handing both straight to TaskBoard::reorderKanban() — see
 * PLAN_LIST_CONCEPTS.md §1/§4 and board-kanban.blade.php.
 *
 * The 'done' zone is drop-only (`pull: false`, `sort: false`): a completed
 * task card never carries `data-id` (see task-card.blade.php's own
 * `@unless($task->is_completed)` — an app-wide convention, every concept's
 * board relies on it). A card being dropped INTO Erledigt still has its
 * data-id at the moment of drop (it was non-completed, and therefore
 * carried one, right up until it landed here), so completing a card by drag
 * works fine — but a card already sitting in Erledigt has none, so dragging
 * it back OUT (or reordering within the column) would silently fail to
 * persist and the UI would lie. Refusing to let Erledigt act as a drag
 * source/sort target sidesteps that entirely; the checkbox already on every
 * card is the one, already-established way to un-complete something
 * everywhere else in this app, and stays exactly that here too.
 */
window.kanbanColumnSortable = function (el, wire, handle = null) {
    if (el._sortable) return el._sortable;
    const isDone = el.dataset.column === 'done';
    el._sortable = Sortable.create(el, {
        group: isDone ? { name: 'kanban', pull: false } : 'kanban',
        sort: !isDone,
        animation: 160,
        easing: 'cubic-bezier(0.16, 1, 0.3, 1)',
        ghostClass: 'board-ghost',
        chosenClass: 'board-chosen',
        handle: handle ?? undefined,
        delay: 60,
        delayOnTouchOnly: true,
        onEnd: (evt) => {
            const to = evt.to;
            if (to.dataset.column === undefined) return;
            const ids = Array.from(to.querySelectorAll('[data-id]')).map((n) => n.dataset.id);
            wire.reorderKanban(to.dataset.column, ids);
        },
    });
    return el._sortable;
};

/**
 * Desktop drag for the Planer's two "Standardaufgabe" chips (see
 * App\Livewire\Planner::openStandardTask()) — dragging "ToDos erledigen" or
 * "Lernen" onto a day column opens the same quick-add sheet a click already
 * does, just pre-filled with the day it was dropped on instead of defaulting
 * to today. A chip here is a fixed,
 * reusable trigger, not real list content that moves and disappears — so
 * regardless of where a drag ends, the chip itself is always put straight
 * back into its own row afterward; `sort: false` plus this manual
 * `el.appendChild()` is simpler than a clone-pull mode for exactly that
 * reason (nothing to actually keep in the destination, ever).
 */
window.standardTaskDragSource = function (el, wire) {
    if (el._sortable) return el._sortable;
    el._sortable = Sortable.create(el, {
        group: { name: 'standard-task', put: false },
        sort: false,
        animation: 160,
        easing: 'cubic-bezier(0.16, 1, 0.3, 1)',
        ghostClass: 'board-ghost',
        chosenClass: 'board-chosen',
        delay: 60,
        delayOnTouchOnly: true,
        onEnd: (evt) => {
            const to = evt.to;
            const template = evt.item.dataset.standardTemplate;
            // Always reparent back to the origin row first — a Livewire
            // re-render will redraw this row anyway once openStandardTask
            // below runs, but a rejected/off-target drop needs this too.
            el.appendChild(evt.item);
            if (to !== el && to.dataset.date !== undefined && template) {
                wire.openStandardTask(template, to.dataset.date);
            }
        },
    });
    return el._sortable;
};

/**
 * Planer day-board — drag a task chip onto a day (see
 * App\Services\DayPlanner, App\Livewire\Planner). One Sortable instance per
 * day-column plus the backlog rail, all sharing **one** group name — a chip
 * can move between any of them — except a touch-driven drag is confined to
 * reordering within its own container (plannerTap below is the touch answer
 * to moving a chip *between* days — see its own docblock for why), so
 * Sortable's own touch-drag never fights the board's horizontal scroll.
 *
 * "Touch-driven" is decided per-gesture, from the pointerType of whichever
 * pointerdown most recently started a drag (plannerPointerIsTouch, tracked
 * by one document-level listener below) — deliberately NOT
 * `matchMedia('(pointer: coarse)')` (this file's earlier approach, and
 * still how plannerWave/plannerTap classify tiers for *display*). That
 * media query reports the device's coarsest available pointer, which is
 * `true` on any hybrid/touchscreen Windows laptop even while dragging with
 * a real mouse — and even in a plain headless Chromium with no pointer
 * hardware at all — not "is *this* drag a touch drag". Evaluated once at
 * Sortable-creation time as a static per-container group name (the
 * previous shape), a coarse-pointer-capable *device* silently and
 * permanently lost cross-container mouse drag on the Planer: every drop
 * outside a chip's own list was rejected by Sortable itself (mismatched
 * group names), so the chip just snapped back to "Nicht eingeplant" with
 * no error and no request ever reaching the server — reproduced via a real
 * Playwright drag (not the click-based preview tool, which can't trigger
 * native HTML5 drag at all) after `matchMedia('(pointer: coarse)')` turned
 * out to be `true` even in that plain headless browser. `put`/`pull` as
 * *functions* re-run on every drag instead, so the same physical machine
 * now correctly allows a mouse-driven cross-day move while still routing a
 * genuine touch drag into same-container-only reordering.
 *
 * classify()'s three tiers mirror App\Services\DayPlanner's own deadline
 * math client-side (duration/deadlineOffset come off the dragged chip's own
 * data attributes) so "which days are available" needs no round trip:
 * 'free' = before the chip's deadline and the day still has room, 'tight' =
 * before the deadline but the day's remaining capacity is already too
 * small, 'past' = the day is after the chip's own deadline. A day with no
 * Pomodoro blocks at all (capacityTotal 0) is never 'tight' — there is no
 * known constraint to warn about, so it reads as 'free'.
 */
const plannerDayGroup = 'planner-day';

/**
 * Updated by one document-level pointerdown listener (registered once,
 * below) rather than per-container — every planner chip drag starts with a
 * real pointerdown on its handle, so this is always current by the time
 * Sortable asks group.put/group.pull whether a cross-container drop is
 * allowed. `!== 'mouse'` (not `=== 'touch'`) so a pen digitizer gets the
 * same tap-to-sheet treatment as touch, mirroring plannerTap's own check.
 */
let plannerPointerIsTouch = false;
document.addEventListener('pointerdown', (e) => {
    plannerPointerIsTouch = e.pointerType !== 'mouse';
}, true);

function plannerChipInfo(el) {
    const rawOffset = el.dataset.deadlineOffset;

    return {
        type: el.dataset.type,
        id: el.dataset.id,
        duration: parseInt(el.dataset.duration, 10) || 0,
        deadlineOffset: rawOffset !== undefined && rawOffset !== '' ? parseInt(rawOffset, 10) : null,
        title: el.querySelector('.chip-title')?.textContent.trim() || '',
        subject: el.dataset.subject || null,
    };
}

function plannerClassifyDay(col, duration, deadlineOffset) {
    const offset = parseInt(col.dataset.dayOffset, 10);
    if (deadlineOffset !== null && offset > deadlineOffset) return 'past';

    const total = parseInt(col.dataset.capacityTotal, 10) || 0;
    if (total === 0) return 'free';

    const used = parseInt(col.dataset.capacityUsed, 10) || 0;
    return total - used >= duration ? 'free' : 'tight';
}

/**
 * Signature moment ("die Rollbahn"): every day column lights up in a short
 * left-to-right stagger the instant a chip is picked up, rather than all at
 * once — a visible runway sweeping out from today.
 *
 * TIER_CLASS is spelled out as complete literal strings, not built via
 * template-literal interpolation (`tier-${tier}`) — Tailwind's content
 * scanner regex-matches raw source text for whole class tokens and can't
 * resolve an interpolated one, so an interpolated class name silently gets
 * purged from the production build (see CLAUDE.md's Known Issues entry on
 * this exact trap, first hit by the drag-and-drop ghost/group classes).
 */
const TIER_CLASS = { free: 'tier-free', tight: 'tier-tight', past: 'tier-past' };

const plannerWave = {
    timers: [],
    start(chipEl) {
        const { duration, deadlineOffset } = plannerChipInfo(chipEl);
        document.querySelectorAll('[data-day-column]').forEach((col, i) => {
            const tier = plannerClassifyDay(col, duration, deadlineOffset);
            this.timers.push(setTimeout(() => col.classList.add(TIER_CLASS[tier]), i * 45));
        });
    },
    clear() {
        this.timers.forEach(clearTimeout);
        this.timers = [];
        document.querySelectorAll('[data-day-column]').forEach((col) => {
            col.classList.remove('tier-free', 'tier-tight', 'tier-past');
        });
    },
};

window.plannerDaySortable = function (el, wire) {
    if (el._sortable) return el._sortable;

    el._sortable = Sortable.create(el, {
        // A function, not a plain string: Sortable calls this fresh for
        // every cross-container drag (never for a same-container reorder,
        // which its own `isOwner` branch short-circuits before this is
        // reached — see sortable.esm.js), so it reads plannerPointerIsTouch
        // as of *this* gesture rather than baking in a static device check.
        group: { name: plannerDayGroup, put: () => !plannerPointerIsTouch, pull: () => !plannerPointerIsTouch },
        animation: 160,
        easing: 'cubic-bezier(0.16, 1, 0.3, 1)',
        ghostClass: 'board-ghost',
        chosenClass: 'board-chosen',
        handle: '[data-drag-handle]',
        draggable: '[data-chip]',
        delay: 60,
        delayOnTouchOnly: true,
        onStart: (evt) => plannerWave.start(evt.item),
        onEnd: (evt) => {
            plannerWave.clear();
            const to = evt.to;

            if (to.dataset.backlog !== undefined) {
                if (evt.from !== to && evt.item.dataset.type === 'task') {
                    wire.unassignTask(parseInt(evt.item.dataset.id, 10));
                } else if (evt.from !== to && evt.item.dataset.type === 'session') {
                    wire.removeSession(parseInt(evt.item.dataset.id, 10)); // only this part goes; the task's other parts stay
                }
                return; // a backlog-internal reorder isn't persisted — order there carries no meaning
            }

            const items = Array.from(to.querySelectorAll('[data-chip]')).map((n) => `${n.dataset.type}:${n.dataset.id}`);
            wire.assignDay(to.dataset.date, items);
        },
    });
    return el._sortable;
};

/**
 * Mobile tap → day-picker sheet. Touch drag across a 14-day horizontal
 * board fights the very scroll it needs — a "move into a container
 * you can't see" problem — so
 * touch gets this as the way to move a chip *between* days. Within one day,
 * plannerDaySortable's own touch-drag (via the dedicated handle) still
 * handles reordering, since that never needs to scroll.
 *
 * A tap, not a hold: pointerup opens the sheet immediately as long as the
 * touch never moved past a small tolerance — that tolerance is what tells
 * a tap apart from a horizontal scroll of the day row that happens to
 * start on a chip. Ignores anything starting on the drag handle or the "×"
 * button — those are their own gestures — so this never has to race or
 * cancel against Sortable's own touch-drag the way a timer-based long-press
 * would have.
 */
window.plannerTap = function (el, wire) {
    if (el._tap) return;
    el._tap = true;
    let start = null;

    el.addEventListener('pointerdown', (e) => {
        if (e.pointerType === 'mouse' || e.target.closest('[data-drag-handle], button')) return;
        start = { x: e.clientX, y: e.clientY };
    });

    el.addEventListener('pointermove', (e) => {
        if (start && (Math.abs(e.clientX - start.x) > 10 || Math.abs(e.clientY - start.y) > 10)) {
            start = null; // moved past tap tolerance — a scroll, not a tap
        }
    });

    el.addEventListener('pointerup', () => {
        if (!start) return;
        start = null;

        const chip = plannerChipInfo(el);
        const currentDate = el.closest('[data-day-column]')?.dataset.date ?? null;

        const days = Array.from(document.querySelectorAll('[data-day-column]'))
            .filter((col) => col.dataset.date !== currentDate)
            .map((col) => {
                const tier = plannerClassifyDay(col, chip.duration, chip.deadlineOffset);
                const total = parseInt(col.dataset.capacityTotal, 10) || 0;
                const used = parseInt(col.dataset.capacityUsed, 10) || 0;
                const hint = tier === 'past' ? 'nach Deadline' : total === 0 ? 'kein Block' : `${Math.max(0, total - used)} min frei`;

                return { date: col.dataset.date, label: col.dataset.dayLabel, tier, hint };
            });

        window.Alpine.store('plannerDayPicker').show(chip, days);
    });

    el.addEventListener('pointercancel', () => { start = null; });
    el.addEventListener('pointerleave', () => { start = null; });
};

/**
 * Drag & drop for Settings' "Header-Badges" list — every catalog badge (not
 * just the enabled ones), reordering persists the whole list at once via
 * Settings::reorderHeaderBadges(). A dedicated, isolated group name.
 */
window.headerBadgesSortable = function (el, wire) {
    if (el._sortable) return el._sortable;
    el._sortable = Sortable.create(el, {
        group: 'header-badges',
        animation: 160,
        easing: 'cubic-bezier(0.16, 1, 0.3, 1)',
        ghostClass: 'board-ghost',
        chosenClass: 'board-chosen',
        handle: '[data-key] > span:first-child',
        delay: 60,
        delayOnTouchOnly: true,
        onEnd: (evt) => {
            const keys = Array.from(evt.to.querySelectorAll('[data-key]')).map((n) => n.dataset.key);
            wire.reorderHeaderBadges(keys);
        },
    });
    return el._sortable;
};

/**
 * Streak flame ignition, part 1 — tracking where a "mark done" click
 * actually happened, for the flying-spark flourish below. Every place a
 * task can be completed by a click is a <button> carrying a
 * wire:click(.modifier)* attribute whose value starts with one of these
 * three action names (ManagesTasks::toggleComplete, or the Zeitplan
 * deadline strip's own toggleDeadlineTaskDone —
 * see CLAUDE.md's Schedule "All-day strip" section). Checked by scanning
 * the clicked button's own attributes rather than a CSS attribute
 * selector, since Livewire's dot-modifiers (.stop, .prevent, ...) change
 * the attribute *name* itself (wire:click vs wire:click.stop) — a CSS
 * selector would have to enumerate every modifier combination in use
 * across the app; a plain attribute scan doesn't care.
 *
 * Captured at click time, before the Livewire round trip even starts —
 * by the time 'celebrate' arrives, the clicked card has very likely
 * already been re-rendered, reordered, or removed (that's the whole point
 * of completing it), so only the coordinates are kept, never the element.
 *
 * Registered on the CAPTURE phase (the trailing `true`), not bubble —
 * board task cards use wire:click.stop on this exact button
 * (task-card.blade.php), and .stop calls stopPropagation() during the
 * bubble phase. A bubble-phase listener on `document` would never see
 * those clicks at all; capture runs first, before .stop has a chance to
 * cut the event off.
 */
const COMPLETION_ACTIONS = /^(toggleComplete|toggleDeadlineTaskDone)\(/;
let lastCompletionOrigin = null;
document.addEventListener('click', (e) => {
    const button = e.target.closest('button');
    if (!button) return;
    const isCompletionToggle = [...button.attributes].some(
        (attr) => attr.name.startsWith('wire:click') && COMPLETION_ACTIONS.test(attr.value)
    );
    if (!isCompletionToggle) return;
    const r = button.getBoundingClientRect();
    lastCompletionOrigin = { x: r.left + r.width / 2, y: r.top + r.height / 2, at: Date.now() };
}, true);

/**
 * Streak flame ignition, part 2 — the flying spark itself. A small ember
 * launches from wherever the completing click happened (see above) and
 * arcs up to the header's streak badge, landing exactly as .flame-ignite
 * (app.css) starts — the flourish this app's plan called "der fliegende
 * Funke", building on the simpler in-place flicker ("der Flackermoment")
 * that already fires as a fallback below. Plain Web Animations API, no
 * new dependency: the flight path is different on every call (a curved
 * offset toward the badge), which a static CSS @keyframes rule can't
 * express, so this is computed and animated directly instead.
 */
function flyStreakSpark(origin, badgeEl, onArrive) {
    const target = badgeEl.getBoundingClientRect();
    const endX = target.left + target.width / 2;
    const endY = target.top + target.height / 2;
    const dx = endX - origin.x;
    const dy = endY - origin.y;
    // Bow the midpoint away from a straight line — mostly upward, with a
    // touch of sideways randomness so two sparks in a row don't trace the
    // exact same arc.
    const midX = dx * 0.5 + (Math.random() * 30 - 15);
    const midY = dy * 0.5 - Math.max(50, Math.abs(dy) * 0.35);

    const spark = document.createElement('div');
    spark.className = 'streak-spark-flight';
    spark.style.left = `${origin.x}px`;
    spark.style.top = `${origin.y}px`;
    document.body.appendChild(spark);

    const anim = spark.animate(
        [
            { transform: 'translate(-50%, -50%) scale(0.5)', opacity: 0 },
            { transform: 'translate(-50%, -50%) scale(1)', opacity: 1, offset: 0.12 },
            {
                transform: `translate(calc(-50% + ${midX}px), calc(-50% + ${midY}px)) scale(1.1)`,
                opacity: 1,
                offset: 0.55,
            },
            {
                transform: `translate(calc(-50% + ${dx}px), calc(-50% + ${dy}px)) scale(0.35)`,
                opacity: 0.9,
                offset: 1,
            },
        ],
        { duration: 620, easing: 'cubic-bezier(0.3, 0, 0.2, 1)' }
    );

    const finish = () => {
        spark.remove();
        onArrive();
    };
    anim.onfinish = finish;
    anim.oncancel = finish;
}

/**
 * Live header badges (see App\Livewire\HeaderBadgeRow, partials/header-badge
 * .blade.php and the .badge-* keyframes in app.css). The badge row is a
 * Livewire component of its own, and nothing on a page knows about it — so
 * one global hook keeps it honest instead of every action having to say so:
 *
 *  - after any message that carried a real action (not a poll, a live-model
 *    sync or a $refresh) succeeds, the row is refreshed; bursts (a drag, a
 *    run of quick ticks) coalesce into one refresh via a short debounce;
 *  - every 60s while the tab is visible, and the moment it becomes visible
 *    again — the Zeitplan badge goes stale with the clock alone;
 *  - a refresh that fails (offline, expired session) is silent: the badges
 *    keep their last values, and Livewire's own error modal / native
 *    "page expired" confirm never fire for a background refresh.
 *
 * headerBadgesSettled() lets the streak's 'celebrate' handler below wait for
 * a pending refresh — otherwise the flame could ignite on a badge that is
 * about to be replaced, or that doesn't exist yet (streak 0 → 1).
 *
 * The leaving halves of the signature moment (a badge that vanished, a value
 * that was replaced) are rendered by the server as short-lived ghost nodes —
 * see HeaderBadgeRow — because Livewire's morph removes keyed nodes without
 * calling any removal hook, so nothing here could animate them out.
 */
const HEADER_ROW = 'header-badge-row';
const HEADER_REFRESH_DEBOUNCE_MS = 120;
const HEADER_REFRESH_INTERVAL_MS = 60000;
const HEADER_IGNORED_ACTIONS = ['$refresh', '$commit', '__dispatch'];
let headerExpecting = 0;
let headerTimer = null;
let headerInFlight = false;
let headerDirty = false;
let headerWaiters = [];

const headerIsIdle = () => headerExpecting === 0 && headerTimer === null && !headerInFlight;

function headerNotifyIdle() {
    if (!headerIsIdle()) return;
    const waiters = headerWaiters;
    headerWaiters = [];
    waiters.forEach((resolve) => resolve());
}

window.headerBadgesSettled = () => new Promise((resolve) => {
    if (headerIsIdle()) resolve();
    else headerWaiters.push(resolve);
});

function refreshHeaderBadges() {
    const row = window.Livewire?.getByName(HEADER_ROW)[0];
    if (!row) return headerNotifyIdle();
    if (headerInFlight) {
        headerDirty = true;
        return;
    }
    headerInFlight = true;
    // Safety net: never let a lost message keep the row "in flight" forever.
    setTimeout(() => { headerInFlight = false; headerNotifyIdle(); }, 10000);
    Promise.resolve(row.$refresh()).catch(() => {});
}

function scheduleHeaderRefresh() {
    clearTimeout(headerTimer);
    headerTimer = setTimeout(() => {
        headerTimer = null;
        refreshHeaderBadges();
    }, HEADER_REFRESH_DEBOUNCE_MS);
}

document.addEventListener('livewire:init', () => {
    Livewire.interceptMessage(({ message, onSuccess, onFinish }) => {
        if (message.component.name === HEADER_ROW) {
            headerInFlight = true;
            onFinish(() => {
                headerInFlight = false;
                if (headerDirty) {
                    headerDirty = false;
                    scheduleHeaderRefresh();
                }
                headerNotifyIdle();
            });
            return;
        }

        const isRealAction = [...message.actions].some(
            (action) => !HEADER_IGNORED_ACTIONS.includes(action.name) && action.metadata?.type !== 'poll'
        );
        if (!isRealAction) return;

        headerExpecting++;
        onSuccess(() => scheduleHeaderRefresh());
        onFinish(() => {
            headerExpecting = Math.max(0, headerExpecting - 1);
            headerNotifyIdle();
        });
    });

    Livewire.interceptRequest(({ request, onError }) => {
        const headerOnly = [...request.messages].every((message) => message.component.name === HEADER_ROW);
        if (headerOnly) onError(({ preventDefault }) => preventDefault());
    });

    let headerInterval = null;
    const syncHeaderInterval = () => {
        if (document.visibilityState === 'visible') {
            scheduleHeaderRefresh();
            headerInterval ??= setInterval(scheduleHeaderRefresh, HEADER_REFRESH_INTERVAL_MS);
        } else {
            clearInterval(headerInterval);
            headerInterval = null;
        }
    };
    document.addEventListener('visibilitychange', syncHeaderInterval);
    if (document.visibilityState === 'visible') {
        headerInterval = setInterval(scheduleHeaderRefresh, HEADER_REFRESH_INTERVAL_MS);
    }
});

/**
 * Week plan ripple (see WeekPlan::saveEventForm and the .weekplan-ripple
 * keyframes in app.css) — after a block is saved spanning more than one
 * weekday, briefly flashes each affected day column in sequence, so "this
 * now applies here too" is something you see happen, not something you
 * have to trust an abstract chip list about.
 */
document.addEventListener('livewire:init', () => {
    Livewire.on('weekplan-ripple', ({ days }) => {
        [...days].sort((a, b) => a - b).forEach((day, i) => {
            setTimeout(() => {
                document.querySelectorAll(`[data-grid][data-weekday="${day}"]`).forEach((el) => {
                    el.classList.remove('weekplan-ripple');
                    void el.offsetWidth; // restart the animation if this day rippled moments ago
                    el.classList.add('weekplan-ripple');
                });
            }, i * 70);
        });
    });

    /**
     * Eisenhower's "Krisenring" (see TaskBoard::trackEisenhowerCrisisEntries()
     * and .eisenhower-crisis-ring in app.css) — a slow, calm wash across the
     * "Wichtig & Dringend" quadrant the instant a task genuinely lands there,
     * by drag, star-tap, date change, or a fresh quadrant-tap capture. Same
     * dispatch-driven shape as weekplan-ripple above, not an x-init/wire:key
     * trick — a nested x-data's x-init does not reliably re-fire on every
     * Livewire morph, only a genuine node replacement does (see CLAUDE.md
     * §10), so a dispatched event is what fires reliably on every relevant
     * update, not just the first render.
     */
    Livewire.on('eisenhower-crisis', () => {
        document.querySelectorAll('[data-eisenhower-quadrant="important_urgent"]').forEach((el) => {
            el.classList.remove('eisenhower-crisis-ring');
            void el.offsetWidth;
            el.classList.add('eisenhower-crisis-ring');
        });
    });

    /**
     * Streak flame ignition (see ProgressStats::celebrationFor(), the
     * --ember-* tokens and .flame-ignite keyframes in app.css) — the header's
     * streak badge actually catches fire the instant today's streak is
     * extended: 'celebrate' already fires exactly on that moment (a perfect
     * day, or a fresh streak record) from every place a task can be
     * completed (board, Zeitplan strip), same dispatch this
     * app's existing celebration overlay listens to.
     *
     * Two layered flourishes, in priority order:
     *  1. "Der fliegende Funke" — if a completion click was captured
     *     recently (lastCompletionOrigin, part 1 above) and the badge is
     *     currently in the DOM, a spark visibly flies from that click to
     *     the badge and ignites it on arrival.
     *  2. "Der Flackermoment" — the fallback, and the only thing that ever
     *     ran before the flying spark existed: ignite in place, no flight.
     *     Used whenever there's no usable origin (completed via some path
     *     this app doesn't have yet, or the click is already stale) — a
     *     spark starting from nowhere would be worse than no spark.
     * Both end the same way: remove/reflow/add .flame-ignite, same restart
     * shape as weekplan-ripple/eisenhower-crisis above. A no-op if the badge
     * isn't in the DOM this pageview (streak was 0 on the last page load, so
     * HeaderBadges::visibleFor() didn't render it) — it simply shows up
     * already lit on the next navigation, the same limitation every header
     * badge already has (see CLAUDE.md's Header-Badges section).
     *
     * The flying spark is driven by the Web Animations API, not a CSS
     * @keyframes rule — unlike .flame-ignite itself, it does NOT get
     * prefers-reduced-motion collapsed for free by the global rule in
     * app.css (that rule only touches CSS animation-duration/transition-
     * duration, which WAAPI calls bypass entirely). Checked explicitly
     * here instead: reduced motion always skips straight to the in-place
     * ignite, never the flight.
     */
    Livewire.on('celebrate', ({ kind }) => {
        if (kind !== 'perfect-day' && kind !== 'streak-record') return;

        const ignite = (el) => {
            el.classList.remove('flame-ignite');
            void el.offsetWidth;
            el.classList.add('flame-ignite');
        };

        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const origin = lastCompletionOrigin;
        const isFresh = !reducedMotion && origin && Date.now() - origin.at < 5000;
        lastCompletionOrigin = null;

        // The header badge row refreshes live now (see the live-badge block
        // above): wait for that refresh to land, so the spark flies to — and
        // the flame ignites on — the badge as it is after this completion,
        // including one that only exists because of it (streak 0 -> 1) and
        // one that a later morph would otherwise wipe the ignite class from.
        // Capped, so a slow or failed refresh only ever delays the flourish.
        Promise.race([window.headerBadgesSettled(), new Promise((resolve) => setTimeout(resolve, 1500))]).then(() => {
            document.querySelectorAll('[data-badge="streak"]').forEach((el) => {
                if (isFresh) {
                    flyStreakSpark(origin, el, () => ignite(el));
                } else {
                    ignite(el);
                }
            });
        });
    });
});

document.addEventListener('alpine:init', () => {
    /**
     * Shared draw state: which category chip — or typed Termin title — is
     * currently "armed" for drawing. cat and title are mutually exclusive;
     * clear() resets both so arming one mode always cancels the other.
     */
    window.Alpine.store('draw', {
        cat: null,
        title: null,
        color: null,
        get active() { return this.cat !== null || this.title !== null; },
        clear() { this.cat = null; this.title = null; this.color = null; },
    });
    /** The Planer's mobile tap-to-assign day-picker sheet — see plannerTap in this file. */
    window.Alpine.store('plannerDayPicker', {
        open: false,
        chip: null,
        days: [],
        show(chip, days) {
            this.chip = chip;
            this.days = days;
            this.open = true;
        },
        hide() { this.open = false; },
    });
    /**
     * Non-blocking "something happened" celebration — topographic rings plus a
     * handful of line-mark particles, fired only by the real milestones in
     * ProgressStats::celebrationFor() (daily goal reached, new all-time daily
     * record, a perfect day — every today-task cleared — or a new all-time
     * streak record) via a 'celebrate' browser event dispatched from wherever
     * a task was just completed (board or the Zeitplan
     * deadline strip — see the overlay mounted once in layouts/app.blade.php).
     * Auto-hides itself; there is nothing to confirm or dismiss, unlike a dialog.
     *
     * Three escalating tiers, indexed 0–2: goal/record is the baseline: a
     * perfect day (or a full board clear, the same visual size in a
     * different color — see the class bindings in layouts/app.blade.php) is
     * bigger and slower — the rarest, most personally-defined win short of a
     * streak record (see CLAUDE.md's Fortschritt section); a new streak
     * record is bigger and slower still — it's a perfect day that *also*
     * just beat every streak ever run before it.
     */
    window.Alpine.store('celebration', {
        visible: false,
        kind: null,
        label: '',
        particles: [],
        _timer: null,
        fire(kind, label) {
            const tier = kind === 'streak-record' ? 2 : (kind === 'perfect-day' || kind === 'full-clear') ? 1 : 0;
            const count = [12, 18, 24][tier];
            const distanceBase = [46, 58, 72][tier];
            const distanceRange = [36, 46, 56][tier];
            const delayRange = [120, 160, 200][tier];
            const duration = [1700, 2200, 2700][tier];

            this.kind = kind;
            this.label = label;
            this.particles = Array.from({ length: count }, (_, i) => {
                const angle = (Math.PI * 2 * i) / count + (Math.random() * 0.35 - 0.175);
                const distance = distanceBase + Math.random() * distanceRange;

                return {
                    id: i,
                    dx: Math.round(Math.cos(angle) * distance),
                    dy: Math.round(Math.sin(angle) * distance),
                    rotate: Math.round(Math.random() * 360),
                    delay: Math.round(Math.random() * delayRange),
                };
            });
            this.visible = true;
            clearTimeout(this._timer);
            this._timer = setTimeout(() => { this.visible = false; }, duration);
        },
    });
    /**
     * Open/closed state of the app-wide capture panel (see QuickCapture). This is
     * ephemeral UI state, so it lives here rather than on the Livewire component —
     * opening the panel then costs no round trip at all.
     *
     * Focus is handled imperatively here instead of via x-init/$watch in the Blade:
     * Livewire re-runs a component root's x-init on every action (see CLAUDE.md §10),
     * so a watcher registered there would be re-registered after every capture.
     */
    window.Alpine.store('quickCapture', {
        open: false,
        returnFocusTo: null,
        show(trigger = null, target = null, extra = {}) {
            if (this.open) return;
            this.returnFocusTo = trigger instanceof HTMLElement ? trigger : null;
            this.open = true;
            // Page-level defaults, set on <body> by the layout. Read here rather
            // than passed by each trigger so the N shortcut — which knows nothing
            // about the page — opens on exactly the same chip the "+" would.
            const defaults = document.body.dataset;
            target = target ?? defaults.captureTarget ?? null;
            // Wipe whatever the last session left behind (title, dates, the
            // confirmation line, validation errors) — the round trip lands
            // while the panel is still animating in. `target` lets a page whose
            // subject matches one of the chips open straight on that chip;
            // null means the usual Inbox default. `extra` carries anything a
            // specific trigger wants pre-filled beyond target — e.g. the
            // "Eisenhower" concept's quadrant "+" passing { important, dueDate }
            // (see partials/board-eisenhower.blade.php) — spread straight into
            // the same event so QuickCapture::resetPanel() picks it up by name.
            window.Livewire?.dispatch('quick-capture-opened', { target, ...extra });
            // Alpine.nextTick, not requestAnimationFrame: the panel's x-show has an
            // x-transition, so Alpine doesn't flip it off display:none synchronously —
            // nextTick is Alpine's own "wait until my DOM updates are flushed" API,
            // the earliest point focusing a still-hidden input can actually succeed.
            window.Alpine.nextTick(() => document.getElementById('quick-capture-title')?.focus());
        },
        hide() {
            if (!this.open) return;
            this.open = false;
            const el = this.returnFocusTo;
            this.returnFocusTo = null;
            // Hand focus back to whatever opened the panel, but only if it's
            // still on the page (a Livewire morph may have replaced it).
            if (el && document.body.contains(el)) el.focus();
        },
    });
    /**
     * The command palette (Strg/⌘+K) — see App\Livewire\CommandPalette. Open/closed
     * and the keyboard cursor are ephemeral UI state, so they live here rather than
     * on the Livewire component: opening costs no round trip, and moving the cursor
     * never touches the server. The cursor is simply "which [data-palette-item]
     * currently carries aria-selected" — no index mirrored into Alpine reactive
     * state, because Livewire re-renders the result list underneath it and a
     * mirrored index would point at a row that no longer exists.
     */
    window.Alpine.store('commandPalette', {
        open: false,
        index: 0,
        returnFocusTo: null,
        items() {
            return [...document.querySelectorAll('#command-palette-list [data-palette-item]')];
        },
        show(trigger = null) {
            if (this.open) return;
            this.returnFocusTo = trigger instanceof HTMLElement ? trigger : null;
            this.open = true;
            window.Livewire?.dispatch('command-palette-opened');
            window.Alpine.nextTick(() => {
                document.getElementById('command-palette-input')?.focus();
                this.reset();
            });
        },
        hide() {
            if (!this.open) return;
            this.open = false;
            const el = this.returnFocusTo;
            this.returnFocusTo = null;
            if (el && document.body.contains(el)) el.focus();
        },
        toggle(trigger = null) {
            this.open ? this.hide() : this.show(trigger);
        },
        /** Back to the first row — called whenever the result list was replaced. */
        reset() {
            this.select(0);
        },
        select(i) {
            const items = this.items();
            items.forEach((el) => el.setAttribute('aria-selected', 'false'));
            if (items.length === 0) {
                this.index = 0;
                return;
            }
            this.index = Math.max(0, Math.min(i, items.length - 1));
            const el = items[this.index];
            el.setAttribute('aria-selected', 'true');
            el.scrollIntoView({ block: 'nearest' });
        },
        move(_root, delta) {
            const n = this.items().length;
            if (n === 0) return;
            this.select((this.index + delta + n) % n);
        },
        /** Mouse hover moves the cursor too, so ↵ always opens what is highlighted. */
        point(_root, el) {
            const i = this.items().indexOf(el);
            if (i !== -1 && i !== this.index) this.select(i);
        },
        activate() {
            this.items()[this.index]?.click();
        },
        /** "Erfassen": hand the typed sentence to the capture panel, pre-filled. */
        capture(text) {
            const title = String(text ?? '').trim();
            this.hide();
            window.Alpine.nextTick(() => window.Alpine.store('quickCapture').show(null, null, null, { title }));
        },
    });
    // A navigation replaces the page but not this store — close the palette so it
    // never greets the next page already open.
    document.addEventListener('livewire:navigate', () => window.Alpine.store('commandPalette').hide());
    /**
     * onboarding — step position for the new-user tutorial (App\Livewire\Onboarding).
     * The slides themselves are static content, not server data, so it needs no seeded-order bookkeeping: init() only ever sets
     * `total` (the slide count, read out of the Blade view so the two can
     * never drift), which is harmless to re-apply on every Livewire re-render
     * the module-visibility step's toggles cause — it never touches `step`.
     */
    window.Alpine.store('onboarding', {
        step: 0,
        total: 1,
        init(total) {
            if (typeof total === 'number') this.total = total;
        },
        next() {
            if (this.step < this.total - 1) this.step++;
        },
        back() {
            if (this.step > 0) this.step--;
        },
    });
    /**
     * Shared behaviour for the small Markdown editors on the project page (brainstorm) and the
     * group page (note cards): an auto-growing textarea (x-ref="ta"), toolbar helpers that wrap or
     * prefix the current selection, and a short-lived "Gespeichert" flash. The toolbar markup itself
     * is partials/markdown-toolbar.blade.php. Spread it into a larger x-data when a page needs more
     * state of its own: x-data="{ ...markdownEditor({ minHeight: 288 }), tab: 'tasks' }".
     */
    window.Alpine.data('markdownEditor', (cfg = {}) => ({
        saved: false,
        _t: null,
        autosize() {
            const ta = this.$refs.ta;
            if (! ta) return;
            ta.style.height = 'auto';
            ta.style.height = Math.max(ta.scrollHeight, cfg.minHeight ?? 96) + 'px';
        },
        wrap(before, after) {
            const ta = this.$refs.ta;
            if (! ta) return;
            const s = ta.selectionStart, e = ta.selectionEnd, v = ta.value, sel = v.slice(s, e);
            ta.value = v.slice(0, s) + before + sel + after + v.slice(e);
            ta.focus();
            ta.setSelectionRange(s + before.length, s + before.length + sel.length);
            ta.dispatchEvent(new Event('input'));
            this.autosize();
        },
        prefixLines(prefix) {
            const ta = this.$refs.ta;
            if (! ta) return;
            const v = ta.value, ls = v.lastIndexOf('\n', ta.selectionStart - 1) + 1;
            let le = v.indexOf('\n', ta.selectionEnd);
            if (le === -1) le = v.length;
            const block = v.slice(ls, le).split('\n').map((l) => prefix + l).join('\n');
            ta.value = v.slice(0, ls) + block + v.slice(le);
            ta.focus();
            ta.setSelectionRange(ls, ls + block.length);
            ta.dispatchEvent(new Event('input'));
            this.autosize();
        },
        flashSaved() {
            this.saved = true;
            clearTimeout(this._t);
            this._t = setTimeout(() => (this.saved = false), 1600);
        },
    }));

    /**
     * splitSheet — the "Aufteilen" sheet's 14-day strip (partials of
     * livewire/split-task-sheet.blade.php). The selection lives here, not on
     * the server: tapping a day is instant, and only the app's proposal
     * (`$wire.suggest`) and saving cost a round trip.
     *
     * cfg: { days, deadlineOffset, selected, total, doneCount }
     *   days[]: { date, offset, weekday, dayNumber, label, capacityTotal,
     *             capacityUsed (without this task's own parts), locked }
     * A part's minutes are the total divided by the number of selected days,
     * rounded to 5 — the same rule DayPlanner::saveSplit() applies.
     */
    window.Alpine.data('splitSheet', (cfg = {}) => ({
        days: cfg.days ?? [],
        deadlineOffset: cfg.deadlineOffset ?? null,
        doneCount: cfg.doneCount ?? 0,
        selected: [...(cfg.selected ?? [])],
        total: cfg.total ?? 60,
        busy: false,
        saving: false,

        get count() { return this.selected.length; },
        get partsAfterwards() { return this.count + this.doneCount; },
        get per() {
            if (this.count === 0) return 0;
            return Math.max(5, Math.round(this.total / this.count / 5) * 5);
        },
        isSelected(date) { return this.selected.includes(date); },
        isAfterDeadline(day) { return this.deadlineOffset !== null && this.deadlineOffset >= 0 && day.offset > this.deadlineOffset; },
        isDeadlineDay(day) { return this.deadlineOffset !== null && day.offset === this.deadlineOffset; },
        free(day) {
            return day.capacityTotal - day.capacityUsed - (this.isSelected(day.date) ? this.per : 0);
        },
        /** Share of the day's Arbeit-Block time that is taken, with this task's part on top. */
        usedPct(day) {
            if (day.capacityTotal <= 0) return 0;
            const used = day.capacityUsed + (this.isSelected(day.date) ? this.per : 0);
            return Math.min(100, Math.round((used / day.capacityTotal) * 100));
        },
        partPct(day) {
            if (day.capacityTotal <= 0 || ! this.isSelected(day.date)) return 0;
            return Math.min(100, Math.round((this.per / day.capacityTotal) * 100));
        },
        overflows(day) {
            return day.capacityTotal > 0 && this.isSelected(day.date) && day.capacityUsed + this.per > day.capacityTotal;
        },
        toggle(day) {
            if (day.locked) return;
            if (this.isSelected(day.date)) {
                this.selected = this.selected.filter((d) => d !== day.date);
            } else {
                this.selected = [...this.selected, day.date].sort();
            }
        },
        get lateCount() {
            return this.days.filter((d) => this.isSelected(d.date) && this.isAfterDeadline(d)).length;
        },
        get tightCount() {
            return this.days.filter((d) => this.overflows(d)).length;
        },
        get blocklessCount() {
            return this.days.filter((d) => this.isSelected(d.date) && d.capacityTotal === 0).length;
        },
        get canSave() {
            return this.partsAfterwards >= 2 && this.count >= 1 && ! this.saving;
        },
        async propose(parts) {
            this.busy = true;
            try {
                const dates = await this.$wire.suggest(Math.max(1, parts), this.total);
                if (Array.isArray(dates) && dates.length) this.selected = dates;
            } finally {
                this.busy = false;
            }
        },
        step(delta) {
            const next = Math.min(this.days.filter((d) => ! d.locked).length, Math.max(1, this.count + delta));
            if (next !== this.count) this.propose(next);
        },
        bump(deltaMinutes) {
            this.total = Math.min(this.count * 600, Math.max(15, this.total + deltaMinutes));
        },
        async save() {
            if (! this.canSave) return;
            this.saving = true;
            try {
                await this.$wire.save(this.selected, this.total);
            } finally {
                this.saving = false;
            }
        },
    }));

    /**
     * swipeCard — native-feeling horizontal swipe for mobile task cards.
     * Tracks the finger 1:1, locks to the horizontal axis (vertical scroll still
     * works), resists past the threshold, springs back if abandoned. Visual action
     * panels are rendered by Blade; this exposes geometry (dx/progress/dir/reached).
     *
     * cfg: { id, left, right, wireMethod }  intent: 'todos'|'tasks'|'today'|'advance'|'menu'|null
     * wireMethod names the $wire action a committed non-menu/edit intent
     * calls (defaults to the base swipeIntent() — see board-kanban.blade.php
     * for a concept that needs its own, swipeIntentKanban).
     */
    window.Alpine.data('swipeCard', (cfg = {}) => ({
        id: cfg.id,
        leftIntent: cfg.left ?? null,
        rightIntent: cfg.right ?? null,
        // Which $wire method a committed non-edit/non-menu intent calls.
        // Defaults to the 3-Things board's own swipeIntent(); "Simple"/
        // "Eisenhower"/"Kanban" each pass their own swipeIntent<Concept>
        // instead (see TaskBoard::swipeIntentSimple()/swipeIntentEisenhower()/
        // swipeIntentKanban(), partials/board-<key>.blade.php) — same gesture,
        // different (isInbox()-unaware, or column/quadrant-shaped) handler.
        wireMethod: cfg.wireMethod ?? 'swipeIntent',
        dx: 0,
        dragging: false,
        flying: false,
        menuOpen: false,
        locked: null,
        sx: 0,
        sy: 0,
        threshold: 72,

        init() {
            const w = this.$el.offsetWidth || 320;
            this.threshold = Math.max(64, Math.round(w * 0.38));
        },

        get progress() {
            return Math.min(Math.abs(this.dx) / this.threshold, 1);
        },
        get dir() {
            return this.dx > 0 ? 'right' : this.dx < 0 ? 'left' : null;
        },
        get reached() {
            return Math.abs(this.dx) >= this.threshold;
        },
        get activeIntent() {
            if (this.dir === 'right') return this.rightIntent;
            if (this.dir === 'left') return this.leftIntent;
            return null;
        },

        down(e) {
            if (e.pointerType === 'mouse' || this.flying) return;
            this.sx = e.clientX;
            this.sy = e.clientY;
            this.dragging = true;
            this.locked = null;
        },

        move(e) {
            if (!this.dragging) return;
            const dx = e.clientX - this.sx;
            const dy = e.clientY - this.sy;

            if (this.locked === null) {
                if (Math.abs(dx) > Math.abs(dy) + 6) {
                    this.locked = 'h';
                } else if (Math.abs(dy) > Math.abs(dx) + 6) {
                    this.locked = 'v';
                    this.dragging = false;
                    return;
                } else return;
            }

            if (this.locked === 'h') {
                if (e.cancelable) e.preventDefault();
                const intent = dx > 0 ? this.rightIntent : this.leftIntent;
                if (!intent) {
                    this.dx = dx * 0.18; // dead side — resist, never commit
                    return;
                }
                const sign = Math.sign(dx);
                const abs = Math.abs(dx);
                const max = this.threshold * 1.6;
                this.dx = sign * (abs <= max ? abs : max + (abs - max) * 0.22);
            }
        },

        up() {
            if (!this.dragging && this.locked !== 'h') {
                this.spring();
                return;
            }
            const commit = this.locked === 'h' && this.reached && this.activeIntent;
            this.dragging = false;
            this.locked = null;
            if (commit) this.fire(this.activeIntent);
            else this.spring();
        },

        spring() {
            this.dx = 0;
        },

        fire(intent) {
            if (intent === 'menu') {
                this.spring();
                this.menuOpen = true;
                return;
            }
            if (intent === 'edit') {
                this.spring();
                this.$wire.startEdit(this.id);
                return;
            }
            this.flying = true;
            this.dx = Math.sign(this.dx) * (this.$el.offsetWidth + 24);
            setTimeout(() => {
                this.$wire[this.wireMethod](this.id, intent);
            }, 150);
        },
    }));

    /**
     * The height the lift raises a block to, and below which it lifts at all.
     *
     * NOT the same number as the `@container (min-height: 46px)` threshold in
     * resources/css/app.css, and deliberately so: a container query sizes
     * against the container's CONTENT box, while this is a min-height on the
     * wrapper, a border box. The body's 1px border top and bottom makes a 46px
     * wrapper a 44px container — two pixels short of its own threshold, so the
     * pencil the lift exists to reveal would never appear. 46 for the
     * threshold, 2 for the border, 2 of slack. Kept in step by hand with
     * DayWindow::LIFT_MIN_PX; neither can read the other.
     */
    const LIFT_MIN_PX = 50;

    /** How long a tap-lifted block stays up before lying back down. */
    const LIFT_HOLD_MS = 2600;

    /** "HH:MM" from minutes since midnight, for the live labels below. */
    const fmtHM = (m) => `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`;

    /**
     * scheduleEvent — drag an event on the timeline grid. Body drag moves it
     * (duration preserved); the top/bottom handles resize it. Times snap to 5'.
     * A double-tap opens the edit sheet (mobile); desktop uses the hover pencil.
     * Geometry is percentage-based (top/height in % of the grid's span), so the
     * grid may be any height — fixed px on desktop, viewport-filling on mobile.
     * Drag math measures the live px-per-minute from the grid at gesture start.
     *
     * cfg: { id, start, end }  (start/end in minutes from midnight)
     */
    window.Alpine.data('scheduleEvent', (cfg = {}) => ({
        id: cfg.id,
        start: cfg.start ?? 0,
        end: cfg.end ?? 0,
        bufBefore: cfg.bufBefore ?? 0,
        bufAfter: cfg.bufAfter ?? 0,
        ppm: 1,
        dayStart: 360,
        span: 1020,
        snap: 5,
        minLen: 10,
        kind: null,
        sy: 0,
        sx: 0,
        // Week view only: the day columns a body drag can land in (each carries
        // data-drop-date), the one the block started in, and the one it is over now.
        // dx is the px offset that snaps the block into the hovered column.
        dropCols: [],
        homeDate: null,
        targetDate: null,
        dx: 0,
        origStart: 0,
        origEnd: 0,
        moved: false,
        lastTap: 0,
        lifted: false,
        // True from the end of a drag until its save has come back: the live
        // labels stay up until then, so the old time never flashes back in
        // between releasing and the server's re-render.
        pending: false,
        pointerType: 'mouse',
        _liftT: null,

        init() {
            const grid = this.$el.closest('[data-grid]');
            if (grid) {
                this.dayStart = parseInt(grid.dataset.dayStart, 10) || 360;
                this.span = parseInt(grid.dataset.span, 10) || 1020;
            }
        },

        get top() {
            return ((this.start - this.dayStart) / this.span) * 100;
        },
        get height() {
            return ((this.end - this.start) / this.span) * 100;
        },

        /**
         * Position, plus the lift. `min-height` rather than a height override:
         * a block already tall enough cannot move, and the top edge — which
         * is the start time — stays put either way.
         */
        get blockStyle() {
            let style = `top:${this.top}%; height:${this.height}%;`;
            if (this.lifted) {
                // Resizing a lifted block from its bottom grip: the lifted
                // height itself grows/shrinks by exactly the dragged amount,
                // so the grip stays under the pointer 1:1. Without this the
                // block sat at >= LIFT_MIN_PX for the whole drag and the user
                // never saw it get shorter. (Top-resize and move need nothing:
                // the top edge is true geometry and already follows.)
                const grow = this.kind === 'bottom' ? (this.end - this.origEnd) * this.ppm : 0;
                style += ` min-height:${Math.max(16, LIFT_MIN_PX + grow)}px;`;
            }
            // Dragging into another day column (week view only) slides the block
            // sideways; `dx` stays 0 for every other gesture.
            if (this.dx) style += ` transform:translateX(${this.dx}px);`;
            return style;
        },

        /** Live labels — they follow a drag instead of showing the time it started at. */
        get timeLabel() {
            return `${fmtHM(this.start)}\u2013${fmtHM(this.end)}`;
        },
        get startLabel() {
            return fmtHM(this.start);
        },
        get departureLabel() {
            return fmtHM(Math.max(0, this.start - this.bufBefore));
        },

        /**
         * Weg-/Pufferzeit bands, in percent of the *block*, not of the grid —
         * so they follow a drag-move and a drag-resize for free, without a
         * second set of geometry to keep in sync.
         */
        get bufBeforeStyle() {
            const pct = (this.bufBefore / Math.max(1, this.end - this.start)) * 100;
            return `top:${-pct}%; height:${pct}%;`;
        },
        get bufAfterStyle() {
            const pct = (this.bufAfter / Math.max(1, this.end - this.start)) * 100;
            return `top:100%; height:${pct}%;`;
        },

        /**
         * Raise a block that is too short to carry its own content. Measured,
         * not derived from its duration: the same 30 minutes is a different
         * number of pixels on the phone, in the desktop week, and at any
         * other Tagesrahmen (see DayWindow::ppm).
         *
         * `auto` is the touch path — nothing will ever fire a "pointerleave"
         * there, so the lift settles itself.
         */
        lift(auto) {
            if (!this.lifted && this.$el.getBoundingClientRect().height >= LIFT_MIN_PX) return;
            // Only ever one block lifted at a time. Dispatched before the flag
            // is set, so the listener below settling *this* block is a no-op.
            if (!this.lifted) window.dispatchEvent(new CustomEvent('schedule-block-settle'));
            this.lifted = true;
            clearTimeout(this._liftT);
            if (auto) this._liftT = setTimeout(() => { this.lifted = false; }, LIFT_HOLD_MS);
        },

        settle() {
            clearTimeout(this._liftT);
            this.lifted = false;
        },

        begin(kind, e) {
            if (e.button != null && e.button !== 0) return;
            // Remembered for tap(): only a touch needs the lift to time itself
            // out, because only a mouse will ever fire a pointerleave.
            this.pointerType = e.pointerType || 'mouse';
            // A drag on a tap-lifted block must not have the lift time out from
            // under it halfway through — the grip would jump away mid-gesture.
            clearTimeout(this._liftT);
            const grid = this.$el.closest('[data-grid]');
            if (grid) this.ppm = grid.getBoundingClientRect().height / this.span;
            this.kind = kind;
            this.sy = e.clientY;
            this.sx = e.clientX;
            this.dx = 0;
            this.dropCols = [];
            this.homeDate = this.targetDate = grid?.dataset.dropDate ?? null;
            if (kind === 'move' && this.homeDate) {
                this.dropCols = [...document.querySelectorAll('[data-grid][data-drop-date]')].map((el) => {
                    const r = el.getBoundingClientRect();
                    return { date: el.dataset.dropDate, left: r.left, right: r.right };
                });
            }
            this.origStart = this.start;
            this.origEnd = this.end;
            this.moved = false;
            this.$el.setPointerCapture?.(e.pointerId);
            if (e.cancelable) e.preventDefault();
        },

        drag(e) {
            if (!this.kind) return;
            const dy = e.clientY - this.sy;
            if (Math.abs(dy) > 3 || (this.kind === 'move' && Math.abs(e.clientX - this.sx) > 3)) this.moved = true;
            const dMin = Math.round(dy / this.ppm / this.snap) * this.snap;
            const dur = this.origEnd - this.origStart;

            if (this.kind === 'move') {
                const ns = Math.max(0, Math.min(1440 - dur, this.origStart + dMin));
                this.start = ns;
                this.end = ns + dur;

                if (this.dropCols.length > 1) {
                    const home = this.dropCols.find((c) => c.date === this.homeDate);
                    // The column under the pointer; past either edge, the nearest one.
                    const over =
                        this.dropCols.find((c) => e.clientX >= c.left && e.clientX < c.right) ??
                        (e.clientX < this.dropCols[0].left ? this.dropCols[0] : this.dropCols[this.dropCols.length - 1]);
                    this.targetDate = over.date;
                    this.dx = Math.round(over.left - home.left);
                }
            } else if (this.kind === 'bottom') {
                this.end = Math.max(this.origStart + this.minLen, Math.min(1440, this.origEnd + dMin));
            } else if (this.kind === 'top') {
                this.start = Math.min(this.origEnd - this.minLen, Math.max(0, this.origStart + dMin));
            }
        },

        finish() {
            if (!this.kind) return;
            const kind = this.kind;
            this.kind = null;

            if (!this.moved) {
                this.start = this.origStart;
                this.end = this.origEnd;
                this.tap();
                return;
            }

            // A block that was actually dragged lies back down: its new
            // neighbourhood is what the user wants to look at now.
            this.settle();

            const hhmm = (m) =>
                `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`;

            const crossDay = kind === 'move' && this.targetDate && this.targetDate !== this.homeDate;

            this.pending = true;
            const saved = kind === 'move'
                ? (crossDay
                    ? this.$wire.moveEvent(this.id, hhmm(this.start), this.targetDate)
                    : this.$wire.moveEvent(this.id, hhmm(this.start)))
                : this.$wire.resizeEvent(this.id, hhmm(this.start), hhmm(this.end));
            // Hold the block over its new column — and keep the dragged time
            // label — until the server has re-rendered it there.
            Promise.resolve(saved).finally(() => {
                this.dx = 0;
                this.pending = false;
            });
        },

        /**
         * A single tap used to do nothing at all — it only armed the 320ms
         * double-tap window below. It now also lifts the block, which is how
         * touch reaches the edit pencil: `group-hover` never fires there, so
         * on a phone the pencil simply did not exist. The double-tap stays
         * exactly as it was, as the shortcut for anyone who knows it.
         */
        tap() {
            const now = Date.now();
            if (now - this.lastTap < 320) {
                this.settle();
                this.$wire.startEditEvent(this.id);
                this.lastTap = 0;
            } else {
                this.lastTap = now;
                // A mouse click on a hovered block must NOT arm the auto-settle:
                // the block would drop back down under a cursor that never left.
                this.lift(this.pointerType !== 'mouse');
            }
        },
    }));

    /**
     * scheduleDraw — draw a new category block on the timeline by clicking and
     * dragging on the empty grid background. Active only when a category chip in
     * the footer is selected ($store.draw.cat !== null). Times snap to 5 minutes;
     * a short tap (< minLen) defaults to a 30-minute block. Works on both the
     * fixed-px desktop week columns and the %-based mobile day column.
     *
     * cfg: { date }  (ISO date string for the day this column represents)
     */
    window.Alpine.data('scheduleDraw', (cfg = {}) => ({
        date: cfg.date ?? '',
        dayStart: 360,
        span: 1020,
        snap: 5,
        minLen: 10,
        drawing: false,
        startMin: 0,
        currentMin: 0,

        init() {
            this.dayStart = parseInt(this.$el.dataset.dayStart, 10) || 360;
            this.span = parseInt(this.$el.dataset.span, 10) || 1020;
        },

        yToMin(clientY) {
            const rect = this.$el.getBoundingClientRect();
            const ppm = rect.height / this.span;
            const raw = (clientY - rect.top) / ppm + this.dayStart;
            const clamped = Math.max(this.dayStart, Math.min(this.dayStart + this.span, raw));
            return Math.round(clamped / this.snap) * this.snap;
        },

        beginDraw(e) {
            if (!this.$store.draw.active) return;
            if (e.button != null && e.button !== 0) return;
            this.drawing = true;
            this.startMin = this.yToMin(e.clientY);
            this.currentMin = this.startMin;
            this.$el.setPointerCapture?.(e.pointerId);
            if (e.cancelable) e.preventDefault();
        },

        moveDraw(e) {
            if (!this.drawing) return;
            this.currentMin = this.yToMin(e.clientY);
        },

        finishDraw() {
            if (!this.drawing) return;

            let start = Math.min(this.startMin, this.currentMin);
            let end = Math.max(this.startMin, this.currentMin);

            if (end - start < this.minLen) {
                end = Math.min(this.dayStart + this.span, start + 30);
            }

            const { cat, title, color } = this.$store.draw;
            const hhmm = (m) =>
                `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`;

            // Keep the preview block showing its final shape until the server
            // responds and morphs in the real block — otherwise it vanishes for
            // the length of the round trip, an empty flash on anything but
            // localhost. Mirrors scheduleEvent's optimistic move/resize, which
            // never lets the block disappear mid-gesture either.
            if (title && end > start) {
                this.$wire.quickCreateTermin(title, color, this.date, hhmm(start), hhmm(end))
                    .finally(() => {
                        this.drawing = false;
                        // A one-off typed title — arm fresh for the next Termin
                        // rather than redrawing the same title again by accident.
                        this.$store.draw.clear();
                    });
            } else if (cat && end > start) {
                this.$wire.quickCreateCategoryBlock(cat, this.date, hhmm(start), hhmm(end))
                    .finally(() => { this.drawing = false; });
            } else {
                this.drawing = false;
            }
        },

        get previewStart() { return Math.min(this.startMin, this.currentMin); },
        get previewEnd()   { return Math.max(this.startMin, this.currentMin); },
        get previewTop()   { return ((this.previewStart - this.dayStart) / this.span) * 100; },
        get previewHeight(){ return ((this.previewEnd - this.previewStart) / this.span) * 100; },

        get previewColorStyle() {
            const token = this.$store.draw.color;
            if (!token) return '';
            // CSS vars use plain token names: --forest, --contour, --ink-faint, etc.
            const v = token === 'ink' ? '--ink-faint' : `--${token}`;
            return `background: rgb(var(${v}) / 0.25); outline-solid: 2px solid rgb(var(${v}) / 0.55); outline-offset: -1px`;
        },
    }));

    /**
     * Shared 250ms heartbeat for every focus ring. Driven by a Web Worker on
     * purpose: browsers throttle (and, after a few minutes, nearly freeze)
     * main-thread timers on a page that is hidden or covered by another window —
     * exactly the "timer on my second screen" case — while a worker's timers
     * keep their pace. Falls back to a plain interval if workers are unavailable
     * (e.g. blocked by CSP). Ring state is derived from an absolute deadline, not
     * counted tick by tick, so even a late tick lands on the right second.
     */
    const focusTicker = (() => {
        const subscribers = new Set();
        let worker = null;
        let fallback = null;

        const run = () => subscribers.forEach((fn) => fn());
        const startFallback = () => {
            if (fallback === null) fallback = setInterval(run, 250);
        };
        const ensureRunning = () => {
            if (worker !== null || fallback !== null) return;
            try {
                const url = URL.createObjectURL(new Blob(['setInterval(() => postMessage(0), 250)'], { type: 'text/javascript' }));
                worker = new Worker(url);
                worker.onmessage = run;
                worker.onerror = () => {
                    worker?.terminate();
                    worker = null;
                    startFallback();
                };
            } catch (e) {
                startFallback();
            }
        };
        const stopIfIdle = () => {
            if (subscribers.size > 0) return;
            worker?.terminate();
            worker = null;
            if (fallback !== null) clearInterval(fallback);
            fallback = null;
        };

        // Catch up the instant the tab/window becomes visible again.
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') run();
        });

        return {
            subscribe(fn) {
                subscribers.add(fn);
                ensureRunning();
            },
            unsubscribe(fn) {
                subscribers.delete(fn);
                stopIfIdle();
            },
        };
    })();

    /**
     * focusTimer — a live, client-side Pomodoro countdown for the header ring.
     * Seeded with the seconds left + the phase length; derives the mm:ss label
     * and the SVG stroke-dashoffset for the ring fill from an absolute deadline
     * (so it counts second by second and never drifts, whatever the browser does
     * to timers). Chimes when it reaches 0 and calls the server's
     * handlePhaseComplete — which either continues into the next phase
     * (autostart enabled) or freezes awaiting a manual continue (disabled).
     * Either way, the server-driven re-render that follows swaps in fresh state
     * (see wire:key on the ring in schedule-strip.blade.php, keyed on
     * phase+cycle so a transition always reinitialises this component with a
     * fresh seed). If that call fails or lands a hair early, it is retried
     * every few seconds until the ring is replaced — it never stays stuck at 0:00.
     *
     * The ring element carries data-remaining, refreshed by the 5s poll; when the
     * server's figure disagrees with the local clock by more than a couple of
     * seconds (clock skew, another device paused/changed the session) the
     * deadline is re-anchored to the server.
     *
     * cfg: { id, remaining, total, circ }
     */
    window.Alpine.data('focusTimer', (cfg = {}) => ({
        id: cfg.id,
        remaining: Math.max(0, cfg.remaining ?? 0),
        total: Math.max(1, cfg.total ?? 1),
        circ: cfg.circ ?? 264,
        endsAt: 0,
        lastServerRemaining: Math.max(0, cfg.remaining ?? 0),
        completedAt: null,
        _tick: null,

        init() {
            this.endsAt = Date.now() + this.remaining * 1000;
            this._tick = () => this.tick();
            focusTicker.subscribe(this._tick);
        },
        destroy() {
            if (this._tick) focusTicker.unsubscribe(this._tick);
            this._tick = null;
        },
        tick() {
            const now = Date.now();

            // Re-anchor when a poll brought a server figure that disagrees with us.
            const server = parseInt(this.$el?.dataset?.remaining ?? '', 10);
            if (Number.isFinite(server) && server !== this.lastServerRemaining) {
                this.lastServerRemaining = server;
                const local = Math.max(0, Math.ceil((this.endsAt - now) / 1000));
                if (Math.abs(server - local) > 2) {
                    this.endsAt = now + server * 1000;
                }
            }

            const left = Math.max(0, Math.ceil((this.endsAt - now) / 1000));
            if (left !== this.remaining) this.remaining = left;
            if (left > 0) return;

            // Reached zero: chime once, then keep (re)trying the server hand-off.
            if (this.completedAt === null) {
                try { this.chime(); } catch (e) { /* audio is optional */ }
            } else if (now - this.completedAt < 3000) {
                return;
            }
            this.completedAt = now;
            Promise.resolve(this.$wire.call('handlePhaseComplete', this.id)).catch(() => {});
        },
        get mmss() {
            const m = Math.floor(this.remaining / 60);
            const s = this.remaining % 60;
            return `${m}:${String(s).padStart(2, '0')}`;
        },
        get offset() {
            return this.circ * (this.remaining / this.total);
        },
        /** A few short synthesised pulses — no audio file, no new package. */
        chime() {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            if (!window._focusAudioCtx) window._focusAudioCtx = new AudioCtx();
            const ctx = window._focusAudioCtx;
            if (ctx.state === 'suspended') ctx.resume();

            [0, 0.7, 1.4].forEach((offset) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = 880;
                osc.connect(gain);
                gain.connect(ctx.destination);

                const start = ctx.currentTime + offset;
                gain.gain.setValueAtTime(0, start);
                gain.gain.linearRampToValueAtTime(0.2, start + 0.02);
                gain.gain.linearRampToValueAtTime(0, start + 0.3);

                osc.start(start);
                osc.stop(start + 0.35);
            });
        },
    }));

});

/**
 * Presence heartbeat — what makes "online" mean *using the
 * app*, not *left a tab open on Tuesday*.
 *
 * Polling rather than websockets on purpose: real presence channels would mean
 * Reverb plus a long-running daemon on the production box, and this app has
 * deliberately avoided both (no queue worker either — see CLAUDE.md §9). One
 * tiny POST a minute, only while the tab is in the foreground, buys the same
 * answer for a class of ~25 people.
 *
 * Gated on visibilityState, and the interval is torn down when the tab goes to
 * the background — a backgrounded tab must go offline on its own within
 * User::PRESENCE_TTL_SECONDS rather than beating forever.
 */
(() => {
    const endpoint = document.querySelector('meta[name="presence-url"]')?.content;
    if (! endpoint) return; // guest — nothing to report

    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const INTERVAL_MS = 60_000;
    let timer = null;

    const beat = () => {
        if (document.visibilityState !== 'visible') return;

        fetch(endpoint, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
            credentials: 'same-origin',
            keepalive: true,
        }).catch(() => {
            // Offline or a dropped request: the next beat retries, and going
            // stale is the correct outcome in the meantime.
        });
    };

    const start = () => {
        if (timer !== null) return;
        beat();
        timer = setInterval(beat, INTERVAL_MS);
    };

    const stop = () => {
        if (timer === null) return;
        clearInterval(timer);
        timer = null;
    };

    document.addEventListener('visibilitychange', () => {
        document.visibilityState === 'visible' ? start() : stop();
    });

    if (document.visibilityState === 'visible') start();
})();

/**
 * "Strg/⌘+K" (or a bare "/") opens the command palette from anywhere. The modifier
 * form fires even while typing — a modified K is never text — and intentionally
 * takes over the browser's own Strg+K (focus search bar); the bare "/" gets the same
 * typing guards as "N" below, since it is a real character.
 */
document.addEventListener('keydown', (event) => {
    const store = window.Alpine?.store('commandPalette');
    if (!store) return;

    const isModK = (event.metaKey || event.ctrlKey) && !event.altKey && !event.shiftKey && (event.key === 'k' || event.key === 'K');
    if (isModK) {
        event.preventDefault();
        // The capture panel sits at the same z-index — never stack the two.
        if (window.Alpine.store('quickCapture')?.open) return;
        store.toggle(event.target instanceof HTMLElement ? event.target : null);
        return;
    }

    if (event.key !== '/' || event.metaKey || event.ctrlKey || event.altKey || event.defaultPrevented) return;

    const el = event.target;
    if (el instanceof HTMLElement) {
        const tag = el.tagName;
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || el.isContentEditable) return;
    }
    if (store.open || window.Alpine.store('quickCapture')?.open) return;

    event.preventDefault();
    store.show(el instanceof HTMLElement ? el : null);
});

/**
 * "N" opens the capture panel from anywhere in the app. Deliberately a bare key
 * (no modifier): every modifier combination in the 1–9/letter range is already
 * spoken for by the browser itself. The guards below are what make that safe —
 * the shortcut never fires while the user is actually typing somewhere.
 */
document.addEventListener('keydown', (event) => {
    if (event.key !== 'n' && event.key !== 'N') return;
    if (event.metaKey || event.ctrlKey || event.altKey) return;
    if (event.defaultPrevented) return;

    const el = event.target;
    if (el instanceof HTMLElement) {
        const tag = el.tagName;
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || el.isContentEditable) return;
    }

    const store = window.Alpine?.store('quickCapture');
    if (!store || store.open) return;

    event.preventDefault();
    store.show(el instanceof HTMLElement ? el : null);
});

/**
 * FeatureAnnouncement::linkHref() appends `?highlight=<css selector>` to an
 * internal "ansehen" link so an admin can point an announcement at a
 * specific block or setting, not just a whole page. Reads the param on
 * every arrival (a hard load fires DOMContentLoaded; a wire:navigate SPA
 * jump fires livewire:navigated instead — Livewire also fires the latter
 * once after the very first page load, so the param is stripped from the
 * URL the moment it's consumed to keep that from double-firing). No
 * built-in registry of valid selectors — this is a developer-facing field,
 * same trust level as any other admin-only input in this app.
 */
function highlightFromQueryParam() {
    const params = new URLSearchParams(window.location.search);
    const selector = params.get('highlight');
    if (!selector) return;

    params.delete('highlight');
    const query = params.toString();
    history.replaceState(null, '', window.location.pathname + (query ? `?${query}` : ''));

    let target;
    try {
        target = document.querySelector(selector);
    } catch {
        return; // an admin-typed selector can be invalid CSS — fail quietly, not loudly
    }
    if (!target) return;

    // Not 'smooth': a page that's still settling (webfonts, images, the
    // Settings nav's own IntersectionObserver wiring) can shift layout height
    // mid-animation and strand a smooth scroll well short of the target.
    target.scrollIntoView({ behavior: 'auto', block: 'center' });
    target.classList.remove('announcement-highlight');
    void target.offsetWidth; // restart the animation if it's already mid-flash
    target.classList.add('announcement-highlight');
    setTimeout(() => target.classList.remove('announcement-highlight'), 1400);
}
document.addEventListener('DOMContentLoaded', highlightFromQueryParam);
// Deferred a tick: wire:navigate resets scroll to top as part of handling its
// own 'livewire:navigated' dispatch, which would otherwise win a same-tick
// race against our own scrollIntoView and strand it back at 0.
document.addEventListener('livewire:navigated', () => setTimeout(highlightFromQueryParam, 0));

/**
 * Theme switch without a reload (step 2.1). App\Livewire\ThemePicker saves the choice and
 * dispatches the new stylesheet; swapping the <style id="theme-vars"> repaints the whole
 * page, old screens included. data-mode drives the `dark:` variant and the flame colours.
 */
window.addEventListener('theme-applied', (event) => {
    const { css, mode, chrome } = event.detail || {};
    const style = document.getElementById('theme-vars');
    if (style && typeof css === 'string') style.textContent = css;
    if (mode) document.documentElement.dataset.mode = mode;
    if (chrome) {
        document.querySelectorAll('meta[name="theme-color"]').forEach((meta) => {
            const dark = (meta.getAttribute('media') || '').includes('dark');
            meta.setAttribute('content', dark ? chrome.dark : chrome.light);
        });
    }
});

/**
 * "The board folds away" (refactor step 3.1, CONVENTIONS 10: the signature moment, never tidy
 * it away). The shell root's Alpine part. Niels' picks from the phase 3 preview: variant A
 * "Falten" (each column folds like a card towards the rail), 520 ms until the card stands.
 *
 * How it stays out of Livewire's way (risk C5):
 * - The board region stays rendered during a session, so the morph keeps its elements and the
 *   server's switch of data-shell-mode is a plain CSS transition (resources/css/app.css, "fold").
 * - The fold state lives on <body> (shell-folding / shell-entering / shell-returning), which
 *   Livewire never morphs.
 * - A click on Start folds at once (before the server answers) and sends the ghost card (see
 *   launchGhost, C1) flying from the started card to the session card's place, so the timer is
 *   up when the fold ends whatever the round trip takes; the server's card takes over when the
 *   ghost has landed. A reload or another device shows the session without any motion. Under
 *   reduced motion it is an instant change, and the board stays until the session is there.
 */
const FOLD_MS = 520;
const FLIGHT_EASE = 'cubic-bezier(0.16, 1, 0.3, 1)';

document.addEventListener('alpine:init', () => {
    const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const flag = (name, on) => document.body.classList.toggle(name, on);

    window.Alpine.data('shellFold', () => ({
        peek: null,
        mode: null,
        from: null,
        ghost: null,
        timers: {},

        init() {
            this.mode = this.$el.dataset.shellMode;
            this.observer = new MutationObserver(() => {
                const mode = this.$el.dataset.shellMode;
                if (mode === this.mode) return;
                const was = this.mode;
                this.mode = mode;
                if (mode === 'session') this.enter();
                else if (was === 'session') this.leave();
            });
            this.observer.observe(this.$el, { attributes: true, attributeFilter: ['data-shell-mode'] });
        },

        destroy() {
            this.observer?.disconnect();
            this.dropGhost();
            Object.values(this.timers).forEach(clearTimeout);
            ['shell-folding', 'shell-entering', 'shell-returning'].forEach((name) => flag(name, false));
        },

        /** Start a task from the board: fold now, ask the server, unfold again if it said no. */
        foldInto(el, call) {
            const card = el.closest('[data-fold-from]');
            this.from = card ? { rect: card.getBoundingClientRect(), at: performance.now() } : null;
            this.peek = null;
            // Under reduced motion nothing moves, so the board stays until the session is there.
            if (!reducedMotion()) flag('shell-folding', true);
            this.ghost = card ? this.launchGhost(card, this.from.rect) : null;

            return Promise.resolve()
                .then(call)
                .catch(() => {})
                .finally(() => {
                    // Two frames later the morph has surely landed. Still on the board (task
                    // gone, no connection): the columns come back and the notice explains.
                    requestAnimationFrame(() => requestAnimationFrame(() => {
                        flag('shell-folding', false);
                        if (this.$root.dataset.shellMode !== 'session') {
                            this.from = null;
                            this.dropGhost(true);
                        }
                    }));
                });
        },

        /**
         * The optimistic session card (finding C1, "Verzögerung vor dem Timer"). The fold must not
         * wait for the server: from the click, the ghost (shell/session-ghost.blade.php) flies from
         * the started card to the place the session card will stand and shows its title and the
         * timer by FOLD_MS, so the timer is up when the columns are gone, however long the round
         * trip takes. enter() hands over to the server's card once the ghost has landed.
         */
        launchGhost(card, from) {
            const root = this.$root.querySelector('[data-session-ghost]');
            if (!root || reducedMotion()) return null;

            const text = (node) => node?.textContent.trim() ?? '';
            const area = card.querySelector('[data-fold-area]');
            const size = card.querySelector('[data-fold-size]');
            const start = [...(card.querySelector('[data-fold-start]')?.childNodes ?? [])]
                .filter((node) => node.nodeType === Node.TEXT_NODE).map((node) => node.textContent).join('').trim();
            const part = (selector) => root.querySelector(selector);

            part('[data-ghost-title]').textContent = text(card.querySelector('[data-fold-title]'));
            part('[data-ghost-area]').hidden = !area;
            part('[data-ghost-area-name]').textContent = text(area);
            part('[data-ghost-dot]').style.background = area?.firstElementChild?.style.background ?? '';
            part('[data-ghost-size]').hidden = !size;
            part('[data-ghost-size]').textContent = text(size);
            part('[data-ghost-start]').hidden = start === '';
            part('[data-ghost-start-text]').textContent = start;

            // Where the session card will stand: the middle of what the session view leaves free,
            // right of the 92 px rail on Werkbank, between the top row and the drawer on Stapel.
            const werkbank = getComputedStyle(this.$root.querySelector('[data-layout-root="werkbank"]')).display !== 'none';
            const viewWidth = document.documentElement.clientWidth;
            const width = Math.min(520, werkbank ? viewWidth - 92 - 64 : viewWidth - 32);
            root.style.width = `${width}px`;
            root.hidden = false;
            const height = root.offsetHeight;
            const left = (werkbank ? 92 + (viewWidth - 92) / 2 : viewWidth / 2) - width / 2;
            const top = Math.max(8, (werkbank ? window.innerHeight / 2 + 17 : window.innerHeight / 2 - 38) - height / 2);
            root.style.left = `${left}px`;
            root.style.top = `${top}px`;

            const flight = root.animate(
                [
                    { transformOrigin: 'top left', transform: `translate(${from.left - left}px, ${from.top - top}px) scale(${from.width / width}, ${from.height / height})` },
                    { transformOrigin: 'top left', transform: 'none' },
                ],
                { duration: FOLD_MS, easing: FLIGHT_EASE, fill: 'both' },
            );
            // The content would stretch with the card; it fades in while the card settles and
            // stands complete when the fold ends.
            const fades = [...root.firstElementChild.children].map((child) => child.animate(
                [{ opacity: 0 }, { opacity: 0, offset: 0.4 }, { opacity: 1 }],
                { duration: FOLD_MS, easing: 'ease-out', fill: 'both' },
            ));
            card.style.visibility = 'hidden'; // the ghost is the card now; the board's copy would show twice

            return {
                landed: flight.finished.catch(() => {}),
                rect: () => root.getBoundingClientRect(),
                drop: (fade = false) => {
                    const done = () => {
                        flight.cancel();
                        fades.forEach((animation) => animation.cancel());
                        root.hidden = true;
                        root.removeAttribute('style');
                    };
                    card.style.visibility = '';
                    if (fade) root.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 160 }).finished.then(done, done);
                    else done();
                },
            };
        },

        dropGhost(fade = false) {
            const ghost = this.ghost;
            this.ghost = null;
            ghost?.drop(fade);
        },

        /**
         * Step 5.2: the part of the page the focus was on has just gone inert or left the page (the board
         * folded away, the session ended). Put the focus on the page heading, which names the new state,
         * so keyboard and screen reader users are not left on nothing. A focus that is somewhere
         * usable (a field, a sheet) stays where it is.
         */
        refocus() {
            const active = document.activeElement;
            const lost = !active || active === document.body || !document.contains(active) || active.closest('[inert]');
            if (lost) this.$root.querySelector('h1')?.focus({ preventScroll: true });
        },

        enter() {
            this.pulse('shell-entering', FOLD_MS + 400);
            this.$nextTick(() => this.refocus());
            const from = this.from;
            const ghost = this.ghost;
            this.from = null;
            this.ghost = null;
            if (!from || reducedMotion()) return;

            // The top row rises right after the card has landed; a slow answer leaves nothing to wait for.
            const waited = performance.now() - from.at;
            this.$root.style.setProperty('--fold-arrive-delay', `${Math.max(0, FOLD_MS - 200 - waited)}ms`);

            const card = [...this.$root.querySelectorAll('[data-session-card]')].find((c) => c.getClientRects().length > 0);
            if (!ghost || !card) {
                ghost?.drop();
                return;
            }
            // The ghost stands in for the server's card until it has landed, then they swap in one frame.
            card.style.visibility = 'hidden';
            ghost.landed.then(() => requestAnimationFrame(() => this.handOver(ghost, card)));
        },

        handOver(ghost, card) {
            const at = ghost.rect();
            card.style.visibility = '';
            ghost.drop();

            const to = card.getBoundingClientRect();
            if (!to.width || !to.height) return;
            const dx = at.left - to.left;
            const dy = at.top - to.top;
            const sx = at.width / to.width;
            const sy = at.height / to.height;
            // The guess where the card stands was close: only a real difference needs a short glide.
            if (Math.abs(dx) < 1.5 && Math.abs(dy) < 1.5 && Math.abs(sx - 1) < 0.01 && Math.abs(sy - 1) < 0.01) return;
            card.animate(
                [
                    { transformOrigin: 'top left', transform: `translate(${dx}px, ${dy}px) scale(${sx}, ${sy})` },
                    { transformOrigin: 'top left', transform: 'none' },
                ],
                { duration: 240, easing: FLIGHT_EASE },
            );
        },

        leave() {
            this.pulse('shell-returning', FOLD_MS + 400);
            this.$nextTick(() => this.refocus());
        },

        pulse(name, ms) {
            clearTimeout(this.timers[name]);
            flag(name, true);
            this.timers[name] = setTimeout(() => flag(name, false), ms);
        },
    }));
});

/**
 * Session timer island (refactor step 2.2, risk C5). The server owns the session; this only
 * counts down the seconds the server sent and, when a phase runs out, asks the server to
 * render again (it then shows the break or the next phase). Wall-clock based, so a tab that
 * slept catches up at once. The element is keyed by the session state, so every server
 * change re-creates it with fresh numbers.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('sessionTimer', (remaining, total, running) => ({
        left: remaining,
        total,
        running,
        startedAt: Date.now(),
        handle: null,
        init() {
            if (!this.running) return;
            this.handle = setInterval(() => this.tick(), 1000);
            this.onVisible = () => this.tick();
            document.addEventListener('visibilitychange', this.onVisible);
        },
        tick() {
            const elapsed = Math.floor((Date.now() - this.startedAt) / 1000);
            this.left = Math.max(0, remaining - elapsed);
            if (this.left === 0 && this.handle) {
                clearInterval(this.handle);
                this.handle = null;
                this.$wire.tick();
            }
        },
        destroy() {
            if (this.handle) clearInterval(this.handle);
            if (this.onVisible) document.removeEventListener('visibilitychange', this.onVisible);
        },
        get label() {
            const m = Math.floor(this.left / 60);
            const s = this.left % 60;
            return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
        },
        offset(circumference) {
            return (circumference * (this.left / this.total)).toFixed(2);
        },
    }));
});

/**
 * The capture line of the new app (step 2.3, R3, CONVENTIONS 5): one keyboard-first line on
 * every page. C opens it on desktop, + on mobile. It posts JSON (ShellCaptureController), so a
 * dropped connection keeps the text in the field with a calm message, and the board refreshes
 * through the Livewire event `capture-saved`. After Enter the line stays open for the next
 * thought (Niels' pick in the phase 2 preview); Esc closes it.
 */
document.addEventListener('alpine:init', () => {
    const config = () => {
        try {
            return JSON.parse(document.getElementById('capture-config')?.textContent || '{}');
        } catch (e) {
            return {};
        }
    };
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    window.Alpine.store('capture', {
        sheetOpen: false,
        toast: null,
        lastId: null,
        timer: null,
        open() {
            const inline = [...document.querySelectorAll('[data-capture-inline]')].find((el) => el.getClientRects().length > 0);
            if (inline) {
                inline.dispatchEvent(new CustomEvent('capture-focus'));
            } else {
                this.sheetOpen = true;
            }
        },
        saved(message, id) {
            this.toast = message;
            this.lastId = id;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => { this.toast = null; this.lastId = null; }, 6000);
            window.Livewire?.dispatch('capture-saved');
        },
        async undo() {
            const id = this.lastId;
            if (!id) return;
            const cfg = config();
            this.lastId = null;
            try {
                const response = await fetch(`${cfg.url}/${id}`, {
                    method: 'DELETE',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                });
                const body = await response.json().catch(() => ({}));
                this.toast = body.message || cfg.messages?.offline;
            } catch (e) {
                this.toast = cfg.messages?.offline;
            }
            clearTimeout(this.timer);
            this.timer = setTimeout(() => { this.toast = null; }, 4000);
            window.Livewire?.dispatch('capture-saved');
        },
    });

    window.Alpine.data('captureLine', (variant) => ({
        text: '',
        saving: false,
        error: '',
        offline: false,
        active: variant === 'sheet',
        focus() {
            this.active = true;
            this.$nextTick(() => this.$refs.input?.focus());
        },
        close() {
            this.error = '';
            if (variant === 'sheet') {
                window.Alpine.store('capture').sheetOpen = false;
            } else if (this.text.trim() === '') {
                this.active = false;
            }
            this.$refs.input?.blur();
        },
        async save() {
            const cfg = config();
            if (this.saving) return;
            if (this.text.trim() === '') {
                this.error = cfg.messages?.empty || '';
                return;
            }
            this.saving = true;
            this.error = '';
            this.offline = false;
            try {
                const response = await fetch(cfg.url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({ text: this.text }),
                });
                const body = await response.json().catch(() => ({}));
                if (response.status === 422) {
                    this.error = body.errors?.text?.[0] || body.message || cfg.messages?.empty;
                } else if (!response.ok) {
                    throw new Error(String(response.status));
                } else {
                    this.text = '';
                    window.Alpine.store('capture').saved(body.message, body.id);
                    this.$nextTick(() => this.$refs.input?.focus());
                }
            } catch (e) {
                this.offline = true;
                this.error = cfg.messages?.offline || '';
            } finally {
                this.saving = false;
            }
        },
    }));
});

document.addEventListener('keydown', (event) => {
    if (!document.body.hasAttribute('data-shell')) return;
    if (event.key !== 'c' && event.key !== 'C') return;
    if (event.metaKey || event.ctrlKey || event.altKey || event.repeat) return;
    const target = event.target;
    if (target.closest?.('input, textarea, select, [contenteditable="true"]')) return;
    // An open dialog (the sort card, a list sheet) keeps its own keys.
    const openDialog = [...document.querySelectorAll('[role="dialog"][aria-modal="true"]')].some((d) => d.getClientRects().length > 0);
    if (openDialog) return;
    event.preventDefault();
    window.Alpine?.store('capture')?.open();
});

/**
 * The phone's sort deck (step 2.3, Niels' option A on 2026-11-09): the top Inbox card follows
 * the finger 1:1; released over a pile (To-dos, Tasks, Später) it flies onto that pile and the
 * server sorts it; anywhere else it springs back. Tapping a pile throws the card the same way.
 * With the details open the card scrolls, and only its grip drags. Reduced motion: no flight.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('sortDeck', () => ({
        dragging: false,
        flying: false,
        landing: false,
        sx: 0,
        sy: 0,
        dx: 0,
        dy: 0,
        hot: null,
        hotLabel: '',
        hotColor: '',
        down(event) {
            if (this.flying) return;
            const card = event.currentTarget;
            const scrolls = card.scrollHeight > card.clientHeight + 1;
            if (event.target.closest('button, input, select, textarea, a, label')) return;
            if (scrolls && !event.target.closest('[data-grip]')) return;
            this.dragging = true;
            this.sx = event.clientX;
            this.sy = event.clientY;
            this.dx = 0;
            this.dy = 0;
            // Shrink around the finger, not the card's centre, so the card stays under it.
            const r = card.getBoundingClientRect();
            this.origin = `${event.clientX - r.left}px ${event.clientY - r.top}px`;
            card.setPointerCapture?.(event.pointerId);
        },
        move(event) {
            if (!this.dragging) return;
            this.dx = event.clientX - this.sx;
            this.dy = event.clientY - this.sy;
            this.setHot(this.pileAt(event.clientX, event.clientY));
        },
        up() {
            if (!this.dragging) return;
            this.dragging = false;
            const target = this.hot;
            this.setHot(null);
            if (target) {
                this.throwTo(target);
            } else {
                this.dx = 0;
                this.dy = 0;
            }
        },
        setHot(pile) {
            this.hot = pile ? pile.dataset.sortTarget : null;
            this.hotLabel = pile ? pile.dataset.sortLabel : '';
            this.hotColor = pile ? pile.dataset.sortColor : '';
        },
        pileAt(x, y) {
            for (const pile of this.$root.querySelectorAll('[data-sort-target]')) {
                const r = pile.getBoundingClientRect();
                // A little generous upwards: the card covers the pile before the finger reaches it.
                if (x >= r.left - 8 && x <= r.right + 8 && y >= r.top - 48 && y <= r.bottom + 8) return pile;
            }
            return null;
        },
        async throwTo(list) {
            if (this.flying) return;
            this.flying = true;
            const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const card = this.$refs.card;
            const pile = this.$root.querySelector(`[data-sort-target="${list}"]`);
            if (card && pile && !reduce) {
                const c = card.getBoundingClientRect();
                const p = pile.getBoundingClientRect();
                this.dx += (p.left + p.width / 2) - (c.left + c.width / 2);
                this.dy += (p.top + 24) - (c.top + c.height / 2);
                this.landing = true;
                await new Promise((resolve) => setTimeout(resolve, 280));
            }
            try {
                await this.$wire.placeTo(list);
            } finally {
                // On success the next card replaces this one; on a validation error it comes back.
                this.flying = false;
                this.landing = false;
                this.dx = 0;
                this.dy = 0;
            }
        },
        get cardStyle() {
            const moving = this.dragging || this.flying;
            // Separate translate / rotate / scale: the card follows the finger 1:1 (no transition
            // on translate while dragging), only the shrink over a pile is eased.
            const ease = 'cubic-bezier(.2,.8,.2,1)';
            const transition = this.dragging
                ? 'scale 150ms ease-out'
                : `translate 280ms ${ease}, rotate 280ms ${ease}, scale 280ms ${ease}, opacity 280ms ease`;
            // Over a pile the card shrinks, so the pile it is about to land on stays visible.
            const scale = this.landing ? 0.3 : (this.hot ? 0.5 : 1);
            return {
                translate: `${this.dx}px ${this.dy}px`,
                rotate: `${this.dx * 0.05}deg`,
                scale: String(scale),
                transformOrigin: this.origin || '50% 50%',
                opacity: this.landing ? 0 : 1,
                transition,
                zIndex: moving ? 30 : 'auto',
                boxShadow: this.dragging ? '0 18px 40px -12px rgba(0,0,0,.35)' : undefined,
            };
        },
    }));
});

/**
 * A lifted timeline block (see the `lift()` in the scheduleEvent component)
 * lies back down as soon as the next pointer lands anywhere that is not a
 * block. Touches that start *on* a block are deliberately skipped: that tap
 * is on its way to lifting one, and its own lift() settles every other block
 * itself, so handling it here too would drop a block mid-hover on desktop.
 *
 * Bubble phase, not capture, on purpose — the edit pencil's own
 * `@pointerdown.stop` has to be able to keep this from firing while it is
 * being clicked, or the pencil would be hidden again before the click lands.
 */
document.addEventListener('pointerdown', (e) => {
    if (e.target instanceof Element && e.target.closest('[data-schedule-block]')) return;

    window.dispatchEvent(new CustomEvent('schedule-block-settle'));
});

/*
 * Step 4.3b: note cards on project and group pages.
 * - noteComposer: Enter saves (Shift+Enter = new line); typing "#" offers the user's tags.
 * - notePile: drag a note between the "Lose" pile and the stage piles (desktop; on the phone the
 *   stage chip opens a sheet instead). The server decides; Livewire re-renders the piles.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('noteComposer', (cfg = {}) => ({
        tags: cfg.tags ?? [],
        matches: [],
        active: 0,
        query() {
            const el = this.$refs.field;
            const before = el.value.slice(0, el.selectionStart);
            const m = before.match(/(?:^|[\s(])#([\p{L}\p{N}_-]*)$/u);
            return m ? m[1].toLowerCase() : null;
        },
        suggest() {
            const q = this.query();
            this.matches = q === null ? [] : this.tags.filter((t) => t.startsWith(q) && t !== q).slice(0, 6);
            this.active = 0;
        },
        pick(tag) {
            const el = this.$refs.field;
            const pos = el.selectionStart;
            const before = el.value.slice(0, pos).replace(/#([\p{L}\p{N}_-]*)$/u, '#' + tag + ' ');
            el.value = before + el.value.slice(pos);
            el.setSelectionRange(before.length, before.length);
            el.dispatchEvent(new Event('input'));
            this.matches = [];
            el.focus();
        },
        key(e) {
            if (this.matches.length && ['ArrowDown', 'ArrowUp', 'Tab'].includes(e.key)) {
                e.preventDefault();
                if (e.key === 'Tab') return this.pick(this.matches[this.active]);
                this.active = (this.active + (e.key === 'ArrowDown' ? 1 : this.matches.length - 1)) % this.matches.length;
                return;
            }
            if (e.key === 'Escape' && this.matches.length) {
                e.preventDefault();
                this.matches = [];
                return;
            }
            if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
                e.preventDefault();
                if (this.matches.length) return this.pick(this.matches[this.active]);
                this.$el.closest('form').requestSubmit();
            }
        },
    }));

    window.Alpine.data('notePile', () => ({
        init() {
            if (!window.Sortable || this.$el._sortable) return;
            this.$el._sortable = window.Sortable.create(this.$el, {
                group: 'notes',
                animation: 160,
                handle: '[data-note-grip]',
                draggable: '[data-note]',
                ghostClass: 'board-ghost',
                onEnd: (evt) => {
                    const id = Number(evt.item.dataset.note);
                    const to = evt.to.dataset.pile;
                    if (evt.from === evt.to && evt.oldDraggableIndex === evt.newDraggableIndex) return;
                    this.$wire.setNoteStage(id, to, evt.newDraggableIndex);
                },
            });
        },
    }));
});

/**
 * Step 5.2 · x-restore-focus: whoever opened an overlay gets the focus back when it closes.
 * x-trap does this itself, but not for overlays that move the focus into themselves in the same
 * tick (the sort card, the crunch screen, the capture sheet): it would "restore" to the overlay.
 * Use it with x-trap.noreturn. Without a value the overlay comes and goes with its element (a
 * Livewire @if); with a value ("$store.capture.sheetOpen") it follows that expression.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.directive('restore-focus', (el, { expression }, { effect, evaluateLater, cleanup }) => {
        let opener = document.activeElement;
        const restore = () => {
            const target = opener;
            opener = null;
            if (!target || target === document.body || !target.isConnected) return;
            const active = document.activeElement;
            if (!active || active === document.body || el.contains(active)) target.focus({ preventScroll: true });
        };

        if (!expression) {
            cleanup(restore);
            return;
        }

        const read = evaluateLater(expression);
        let was = false;
        effect(() => read((open) => {
            if (open && !was) opener = document.activeElement;
            if (!open && was) restore();
            was = !!open;
        }));
    });
});

/**
 * Step 5.2 · Form errors belong to their field.
 * The views print a validation message as `<p data-field-error role="alert">` right after the field
 * (or after the label / group that holds it). This links each one to that field with
 * aria-describedby (and aria-invalid on a real input), so a screen reader reads the message with
 * the field and not just somewhere on the page. One place, so a new form gets it for free.
 */
(() => {
    const FIELD = 'input:not([type="hidden"]), select, textarea';
    const GROUP = '[role="radiogroup"], [role="group"]';
    let queued = false;
    let nextId = 0;

    const fieldFor = (error) => {
        // Walk back through the siblings; when there are none left, climb one level (three at most).
        let node = error;
        for (let climb = 0; climb < 3 && node && node.parentElement; climb++) {
            for (let sibling = node.previousElementSibling; sibling; sibling = sibling.previousElementSibling) {
                if (sibling.matches('[data-field-error]')) continue;
                if (sibling.matches(FIELD) || sibling.matches(GROUP)) return sibling;
                const inner = sibling.querySelector(`${GROUP}, ${FIELD}`);
                if (inner) return inner;
            }
            node = node.parentElement;
            if (node.matches('form, [role="dialog"], section, main')) break;
        }
        return null;
    };

    const link = () => {
        queued = false;
        document.querySelectorAll('[data-field-error]:not([data-field-linked])').forEach((error) => {
            error.dataset.fieldLinked = '';
            const field = fieldFor(error);
            if (!field) return;
            if (!error.id) error.id = `field-error-${++nextId}`;
            const described = (field.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
            if (!described.includes(error.id)) described.push(error.id);
            field.setAttribute('aria-describedby', described.join(' '));
            if (field.matches(FIELD)) field.setAttribute('aria-invalid', 'true');
        });
    };

    const schedule = () => {
        if (queued) return;
        queued = true;
        requestAnimationFrame(link);
    };

    document.addEventListener('DOMContentLoaded', () => {
        link();
        // A morph may strip what was added (it renders the server's HTML again), so link again then too.
        new MutationObserver(schedule).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['data-field-linked'] });
    });
})();
