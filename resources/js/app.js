import Sortable from 'sortablejs';

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
    el._sortable = Sortable.create(el, {
        group: 'board',
        animation: 160,
        easing: 'cubic-bezier(0.16, 1, 0.3, 1)',
        ghostClass: 'board-ghost',
        chosenClass: 'board-chosen',
        handle: handle ?? undefined,
        delay: 60,
        delayOnTouchOnly: true,
        ...sortableGroupBands(),
        // Mark the page as dragging so project cards can show a drop affordance.
        onStart: () => document.body.classList.add('dragging-task'),
        // Hovering the middle band of another card arms "drop here to group
        // these two" and — critically — blocks Sortable's own reorder shift
        // for as long as the pointer stays there (see groupZone below).
        onMove: (evt, originalEvent) => groupZone.consider(evt.related, evt.relatedRect, originalEvent ?? evt.originalEvent),
        onEnd: (evt) => {
            document.body.classList.remove('dragging-task');
            const armedId = groupZone.disarm();
            const draggedId = evt.item.dataset.id;

            // An armed grouping wins over the ordinary reorder: the server moves
            // the task itself, so persisting the destination's order here too
            // would fight it with a stale picture of the column.
            if (armedId && draggedId && armedId !== draggedId) {
                wire.groupTasks(parseInt(draggedId, 10), parseInt(armedId, 10));
                return;
            }

            const to = evt.to;
            // A drop onto a project card or group box lands in a zone with no
            // data-list; those zones' own onAdd handles it. Only persist real columns.
            if (to.dataset.list === undefined) return;
            const ids = Array.from(to.querySelectorAll('[data-id]')).map((n) => n.dataset.id);
            wire.reorder(to.dataset.list, to.dataset.today === 'true', ids);
        },
    });
    return el._sortable;
};

/**
 * A small label that follows the cursor while a card is armed for grouping
 * (see groupZone below). Deliberately NOT just a ring around the target
 * card: while dragging, the card being carried — or the browser's own
 * native drag-image snapshot — sits right on top of whatever is under the
 * cursor, which is exactly where a ring on the target would be. A label
 * pinned to the cursor itself is the one thing guaranteed to render above
 * that, in every browser.
 */
const groupArmLabel = (() => {
    let el = null;
    const ensure = () => {
        if (el) return el;
        el = document.createElement('div');
        el.className = 'group-arm-label';
        el.setAttribute('aria-hidden', 'true');
        document.body.appendChild(el);
        return el;
    };
    return {
        show(text, x, y) {
            const node = ensure();
            node.textContent = text;
            node.style.left = `${x}px`;
            node.style.top = `${y}px`;
            node.style.display = 'block';
        },
        move(x, y) {
            if (el) { el.style.left = `${x}px`; el.style.top = `${y}px`; }
        },
        hide() {
            if (el) el.style.display = 'none';
        },
    };
})();

/**
 * "Hover the middle of another card to group with it" — the desktop gesture
 * for creating a task group (see TaskBoard::groupTasks).
 *
 * Each card is split into three vertical bands: the top and bottom quarters
 * are ordinary reorder territory (SortableJS shifts siblings immediately,
 * same as always), the middle half is the group band. Returning `false`
 * from Sortable's onMove while the pointer sits in that middle band tells
 * Sortable not to touch the DOM at all — the hovered card stays exactly
 * where it is, no gap opens, nothing "slips away" — so the only way to see
 * a sibling move is to be in a reorder band, and the only way to arm a
 * group is to NOT be moving anything. The two states can never be
 * ambiguous. Arming is instant (no dwell timer): the band boundary itself
 * is the commitment, the same way crossing a card's own vertical midline
 * already decides above/below during a plain reorder.
 *
 * The armed card gets .group-arm (an indent + forest bracket, see app.css) —
 * a secondary cue that helps once the drag image is small enough to peek
 * past, but the cursor label above is the reliable indicator, since the
 * carried card (or the browser's own drag-image snapshot) usually sits
 * right on top of the card underneath it.
 */
/**
 * Sortable's `invertedSwapThreshold`, mirrored here so groupZone's own band
 * math can't drift from the one Sortable actually reorders by. 0.5 means the
 * outer quarter at each end is a reorder band and the middle half is the
 * group band — see GROUP_BAND_FRACTION below and the sortableGroupBands()
 * options both zones spread into their config.
 */
const INVERTED_SWAP_THRESHOLD = 0.5;

/** Fraction of a card's height taken by ONE reorder band, at each end. */
const GROUP_BAND_FRACTION = INVERTED_SWAP_THRESHOLD / 2;

/**
 * The Sortable options that make the three bands real.
 *
 * `invertSwap: true` is the load-bearing one, and the whole reason the
 * earlier version of this gesture did not work. With Sortable's default
 * (`invertSwap: false`, `swapThreshold: 1`) the swap zone is the *entire*
 * card — see _getSwapDirection in sortablejs: the regular branch tests
 * `mouseOnAxis > targetS1 + targetLength * (1 - swapThreshold) / 2`, which
 * with swapThreshold 1 is simply "anywhere inside the target". So the
 * hovered card is reordered out from under the cursor the instant the
 * pointer touches its leading edge, long before the pointer can reach the
 * middle. No onMove guard can rescue that, because by the time the pointer
 * is in the middle band the card that was there has already moved.
 *
 * `invertSwap: true` switches to the inverted branch, which swaps only in
 * the outer `invertedSwapThreshold / 2` of each end and returns 0 —
 * literally "no swap" — everywhere in between. Sortable itself then holds
 * the card still in the middle band, so it stays put under the cursor and
 * can be grouped onto. The direction it returns in the outer bands is
 * `mouseOnAxis > mid ? 1 : -1`: the top band inserts the dragged card
 * *before* the target (order it above), the bottom band after it.
 */
const sortableGroupBands = () => ({
    invertSwap: true,
    invertedSwapThreshold: INVERTED_SWAP_THRESHOLD,
});

const groupZone = {
    el: null,
    lastX: 0,
    lastY: 0,

    /**
     * Called on every Sortable onMove with the card currently hovered, its
     * rect, and the raw pointer/mouse/touch event. Returns whether Sortable
     * should proceed with its normal reorder move (true) or hold still (false).
     */
    consider(related, relatedRect, originalEvent) {
        const point = originalEvent?.touches?.[0] ?? originalEvent;
        if (point && typeof point.clientX === 'number') {
            this.lastX = point.clientX;
            this.lastY = point.clientY;
            groupArmLabel.move(this.lastX, this.lastY);
        }

        const card = related && related.dataset && related.dataset.id ? related : null;

        if (!card || !relatedRect || !point || typeof point.clientY !== 'number') {
            this.reset();
            return true;
        }

        // Exactly the band Sortable itself refuses to swap in (see
        // sortableGroupBands above) — deliberately the same number, so the
        // visual cue can never disagree with what a release actually does.
        const band = relatedRect.height * GROUP_BAND_FRACTION;
        const inGroupBand = point.clientY > relatedRect.top + band && point.clientY < relatedRect.bottom - band;

        if (!inGroupBand) {
            this.reset();
            return true;
        }

        if (card !== this.el) {
            this.reset();
            this.el = card;
            card.classList.add('group-arm');
            const title = card.dataset.title;
            groupArmLabel.show(title ? `Gruppieren mit „${title}“` : 'Gruppieren', this.lastX, this.lastY);
        }

        return false; // hold the card still — this is the group band, not a reorder
    },

    /** Clear any highlight, keeping nothing armed. */
    reset() {
        if (this.el) this.el.classList.remove('group-arm');
        this.el = null;
        groupArmLabel.hide();
    },

    /** Read out whatever was armed at drop time, then clean up. */
    disarm() {
        const id = this.el ? this.el.dataset.id : null;
        this.reset();
        return id;
    },
};

/**
 * A group box, both as a drop target and as a source: dropping a card here
 * (from anywhere) assigns it to the group and keeps its list — an Inbox card
 * lands in that group's Inbox. Dragging a card OUT of the box (to a plain
 * board column) releases it again, the reverse gesture.
 *
 * `sort: false` — the box only ever previews up to two tasks (never the full
 * group), so reordering within that partial view isn't meaningful; the true
 * order is only set from inside the group's own dashboard.
 *
 * `handle` mirrors boardSortable's: mobile restricts the drag start to the
 * card's grip icon (its body is already claimed by swipeCard's gestures),
 * desktop leaves the whole card draggable.
 */
window.groupDropZone = function (el, wire, handle = null) {
    if (el._sortable) return el._sortable;
    el._sortable = Sortable.create(el, {
        group: 'board',
        animation: 160,
        easing: 'cubic-bezier(0.16, 1, 0.3, 1)',
        ghostClass: 'board-ghost',
        chosenClass: 'board-chosen',
        handle: handle ?? undefined,
        sort: false,
        draggable: '[data-id]',
        delay: 60,
        delayOnTouchOnly: true,
        ...sortableGroupBands(),
        onStart: () => document.body.classList.add('dragging-task'),
        onMove: (evt, originalEvent) => groupZone.consider(evt.related, evt.relatedRect, originalEvent ?? evt.originalEvent),
        onAdd: (evt) => {
            const taskId = evt.item.dataset.id;
            const groupId = el.dataset.groupId;
            evt.item.remove(); // the server decides what the box shows next
            if (taskId && groupId) {
                wire.assignTaskToGroup(parseInt(taskId, 10), parseInt(groupId, 10));
            }
        },
        onEnd: (evt) => {
            document.body.classList.remove('dragging-task');
            const armedId = groupZone.disarm();
            const taskId = evt.item.dataset.id;

            // Held over the group band of another card on the way out: merge
            // into that card's group (or a fresh one) instead — same as
            // boardSortable's own onEnd, and takes priority over a plain drop.
            if (armedId && taskId && armedId !== taskId) {
                wire.groupTasks(parseInt(taskId, 10), parseInt(armedId, 10));
                return;
            }

            const to = evt.to;
            if (to === el) return; // dropped back into the same box — nothing changed

            // Any other receiving zone (a project card, another group's box,
            // the "new project" zone) already fully handles the move via its
            // own onAdd, including releasing this box's group membership —
            // only a plain board column has no such handler of its own.
            if (to.dataset.list === undefined) return;
            if (!taskId) return;

            const ids = Array.from(to.querySelectorAll('[data-id]')).map((n) => n.dataset.id);
            wire.reorder(to.dataset.list, to.dataset.today === 'true', ids);
            wire.ungroupTask(parseInt(taskId, 10));
        },
    });
    return el._sortable;
};

/**
 * Drag & drop for the emergency-mode arrange screen — a single, isolated
 * list (its own group name, never 'board') so it can never accept a drop
 * from, or be dragged into, the main board's columns. On drop we persist
 * the whole list's order in one shot, same as boardSortable.
 */
window.emergencySortable = function (el, wire) {
    if (el._sortable) return el._sortable;
    el._sortable = Sortable.create(el, {
        group: 'emergency-sequence',
        animation: 160,
        easing: 'cubic-bezier(0.16, 1, 0.3, 1)',
        ghostClass: 'board-ghost',
        chosenClass: 'board-chosen',
        handle: '[data-id] > span:first-child',
        delay: 60,
        delayOnTouchOnly: true,
        onEnd: (evt) => {
            const ids = Array.from(evt.to.querySelectorAll('[data-id]')).map((n) => n.dataset.id);
            wire.reorderTasks(ids);
        },
    });
    return el._sortable;
};

/**
 * A project card as a drop target: receive-only member of the 'board' group.
 * Dropping a task here assigns it to the project (server is the source of truth),
 * so we pull the moved node straight back out and let Livewire re-render.
 */
window.projectDropZone = function (el, wire) {
    if (el._sortable) return el._sortable;
    el._sortable = Sortable.create(el, {
        group: { name: 'board', pull: false, put: true },
        sort: false,
        draggable: '[data-id]', // nothing inside is draggable; the card stays a link
        onAdd: (evt) => {
            const taskId = evt.item.dataset.id;
            const projectId = el.dataset.projectId;
            evt.item.remove(); // don't leave the card visually holding the task
            if (taskId && projectId) {
                wire.assignTaskToProject(parseInt(taskId, 10), parseInt(projectId, 10));
            }
        },
    });
    return el._sortable;
};

/**
 * The "drop here for a new project" zone at the top of the desktop Projekte
 * column — only visually shown while dragging (see body.dragging-task in the
 * Blade), but always mounted so Sortable can accept a drop the moment one
 * starts. Desktop's drag-and-drop equivalent of the mobile long-press ->
 * "Neues Projekt mit diesem Namen" entry: same createProjectFromTask() action.
 */
window.newProjectDropZone = function (el, wire) {
    if (el._sortable) return el._sortable;
    el._sortable = Sortable.create(el, {
        group: { name: 'board', pull: false, put: true },
        sort: false,
        draggable: '[data-id]',
        onAdd: (evt) => {
            const taskId = evt.item.dataset.id;
            evt.item.remove();
            if (taskId) {
                wire.createProjectFromTask(parseInt(taskId, 10));
            }
        },
    });
    return el._sortable;
};

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
    /** Which task id (if any) the mobile long-press project-picker sheet is open for. */
    window.Alpine.store('projectPicker', { taskId: null });
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
        show(trigger = null, target = null, groupId = null) {
            if (this.open) return;
            this.returnFocusTo = trigger instanceof HTMLElement ? trigger : null;
            this.open = true;
            // Page-level defaults, set on <body> by the layout. Read here rather
            // than passed by each trigger so the N shortcut — which knows nothing
            // about the page — opens on exactly the same chip the "+" would.
            const defaults = document.body.dataset;
            target = target ?? defaults.captureTarget ?? null;
            groupId = groupId ?? (defaults.captureGroup ? parseInt(defaults.captureGroup, 10) : null);
            // Wipe whatever the last session left behind (title, dates, the
            // confirmation line, validation errors) — the round trip lands
            // while the panel is still animating in. `target` lets a page whose
            // subject matches one of the chips open straight on that chip;
            // null means the usual Inbox default.
            window.Livewire?.dispatch('quick-capture-opened', { target, groupId });
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
     * swipeCard — native-feeling horizontal swipe for mobile task cards.
     * Tracks the finger 1:1, locks to the horizontal axis (vertical scroll still
     * works), resists past the threshold, springs back if abandoned. Visual action
     * panels are rendered by Blade; this exposes geometry (dx/progress/dir/reached).
     *
     * cfg: { id, left, right }  intent: 'todos'|'tasks'|'today'|'menu'|null
     */
    window.Alpine.data('swipeCard', (cfg = {}) => ({
        id: cfg.id,
        leftIntent: cfg.left ?? null,
        rightIntent: cfg.right ?? null,
        dx: 0,
        dragging: false,
        flying: false,
        menuOpen: false,
        locked: null,
        sx: 0,
        sy: 0,
        threshold: 72,
        longPressTimer: null,
        longPressFired: false,
        longPressMs: 500,

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
            this.longPressFired = false;
            clearTimeout(this.longPressTimer);
            // A hold with no directional lock opens the project-picker sheet —
            // both swipe axes already have jobs, so long-press is the only free
            // gesture left for a quick "assign to project" on mobile.
            this.longPressTimer = setTimeout(() => {
                if (!this.dragging || this.locked !== null) return;
                this.longPressFired = true;
                this.dragging = false;
                this.dx = 0;
                this.$store.projectPicker.taskId = this.id;
            }, this.longPressMs);
        },

        move(e) {
            if (!this.dragging) return;
            const dx = e.clientX - this.sx;
            const dy = e.clientY - this.sy;

            if (this.locked === null) {
                if (Math.abs(dx) > Math.abs(dy) + 6) {
                    this.locked = 'h';
                    clearTimeout(this.longPressTimer);
                } else if (Math.abs(dy) > Math.abs(dx) + 6) {
                    this.locked = 'v';
                    this.dragging = false;
                    clearTimeout(this.longPressTimer);
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
            clearTimeout(this.longPressTimer);
            if (this.longPressFired) {
                this.longPressFired = false;
                return;
            }
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
                this.$wire.swipeIntent(this.id, intent);
            }, 150);
        },
    }));

    /**
     * prepare store — the ordered id queues driving the "Vorbereitung für
     * morgen" swipe-stack (see prepareSwipeCard below). Seeded once per page
     * mount from the server's computed task lists; from then on ordering/
     * phase/"später" requeueing is entirely client-side, so a phase emptying
     * out never needs a Livewire round trip. Re-seeded fresh on every
     * wire:navigate visit — that's what makes deferred order genuinely
     * session-local. Step 3 (schedule) has no queue of its own — it's the
     * open-ended Zeitplan timeline, so it only ever advances to 'done' via an
     * explicit "Später planen"/"Fertig" tap (goDone()), never automatically.
     */
    window.Alpine.store('prepare', {
        phase: 'inbox', // 'inbox' | 'review' | 'schedule' | 'done'
        inboxOrder: [],
        reviewOrder: [],
        inboxTotal: 0,
        reviewTotal: 0,
        flaggedCount: 0, // tasks swiped right ("für morgen") during phase 2 — the Done screen's tally
        seeded: false,

        // Livewire re-morphs the component root on every action, which re-runs
        // this element's x-init — without a guard that would silently wipe the
        // client-tracked order (and any "später" requeues) after every single
        // swipe. Alpine also auto-calls a store's init() once with no arguments
        // as soon as the store is registered, before x-init ever runs — that
        // call is distinguished by cfg.inbox being undefined and ignored too.
        init(cfg = {}) {
            if (this.seeded || cfg.inbox === undefined) return;
            this.seeded = true;
            this.inboxOrder = [...(cfg.inbox ?? [])];
            this.reviewOrder = [...(cfg.review ?? [])];
            this.inboxTotal = this.inboxOrder.length;
            this.reviewTotal = this.reviewOrder.length;
            this.flaggedCount = 0;
            // Steps 1–2 skip forward when their queue is already empty; step 3
            // is always visited (it's open-ended, never auto-skipped) — it's
            // the floor this never falls past on init.
            this.phase = this.inboxOrder.length
                ? 'inbox'
                : (this.reviewOrder.length ? 'review' : 'schedule');
        },

        order(phase) {
            return phase === 'inbox' ? this.inboxOrder : this.reviewOrder;
        },
        stackIndexOf(phase, id) {
            return this.order(phase).indexOf(id);
        },

        /** Commit or skip: gone from this session for good. */
        remove(phase, id) {
            const arr = this.order(phase);
            const i = arr.indexOf(id);
            if (i !== -1) arr.splice(i, 1);
            this.advanceIfEmpty();
        },

        /** "später": move to the back of the same phase's queue. */
        requeue(phase, id) {
            const arr = this.order(phase);
            const i = arr.indexOf(id);
            if (i !== -1) {
                arr.splice(i, 1);
                arr.push(id);
            }
        },

        /** Bridges phase 1 → phase 2: a task just sorted into Todos/Tasks becomes reviewable this same session. */
        enqueueReview(id) {
            if (!this.reviewOrder.includes(id)) {
                this.reviewOrder.push(id);
                this.reviewTotal++;
            }
        },

        advanceIfEmpty() {
            if (this.phase === 'inbox' && this.inboxOrder.length === 0) {
                this.phase = this.reviewOrder.length ? 'review' : 'schedule';
            } else if (this.phase === 'review' && this.reviewOrder.length === 0) {
                this.phase = 'schedule';
            }
        },

        /** Manual, explicit advance out of the open-ended schedule step — "Später planen" and "Fertig" both just move on. */
        goDone() {
            this.phase = 'done';
        },

        get stepIndex() {
            return { inbox: 0, review: 1, schedule: 2, done: 3 }[this.phase] ?? 0;
        },

        get remainingLabel() {
            if (this.phase === 'inbox') return `${this.inboxOrder.length} von ${this.inboxTotal}`;
            if (this.phase === 'review') return `${this.reviewOrder.length} von ${this.reviewTotal}`;
            return '';
        },
    });

    /**
     * prepareSwipeCard — 4-directional swipe for the "Vorbereitung für
     * morgen" triage stack (steps 1–2). Same pointer handling / threshold /
     * resistance / dead-side-damping math as swipeCard above, generalised
     * from one axis (dx) to two (dx and dy): the first move past a small
     * deadzone locks the gesture to the horizontal or vertical axis, exactly
     * like swipeCard already does — the difference here is the vertical axis
     * drives an action instead of ceding to page scroll (there is no
     * scrolling list of cards to protect).
     *
     * Each configured direction resolves via a `kind`:
     *   'commit'  — fly off screen, then call the configured $wire method (if
     *               any) and remove the card from its queue for good.
     *   'defer'   — "später": continue off the same edge, but only requeue
     *               it at the back of the same queue — no $wire call at all.
     *   'popover' — mirrors swipeCard's existing 'menu' intent: must reach
     *               threshold, then springs back and opens the inline
     *               deadline popover without advancing or removing the card.
     *   null / unconfigured — dead side, resists and never commits, exactly
     *               like swipeCard's unconfigured sides.
     *
     * Desktop fast-path: arrow keys (← → ↓ ↑) resolve the top card exactly
     * like a completed drag — see key() below — so a mouse user never has to
     * reach for the button row at all.
     *
     * cfg: { id, phase, deadline, dueDate, right, left, down, up }
     * each direction is null or { kind, wire?, args? }
     */
    window.Alpine.data('prepareSwipeCard', (cfg = {}) => ({
        id: cfg.id,
        phase: cfg.phase,
        dirs: {
            right: cfg.right ?? null,
            left: cfg.left ?? null,
            down: cfg.down ?? null,
            up: cfg.up ?? null,
        },
        dx: 0,
        dy: 0,
        dragging: false,
        flying: false,
        locked: null,
        dateOpen: false,
        deadline: cfg.deadline ?? '',
        dueDate: cfg.dueDate ?? '',
        sx: 0,
        sy: 0,
        thresholdH: 72,
        thresholdV: 72,

        init() {
            this.thresholdH = Math.max(64, Math.round((this.$el.offsetWidth || 320) * 0.38));
            this.thresholdV = Math.max(64, Math.round((this.$el.offsetHeight || 220) * 0.38));
        },

        get stackIndex() {
            return this.$store.prepare.stackIndexOf(this.phase, this.id);
        },
        get isTop() {
            return this.stackIndex === 0;
        },
        /** Outer stack position (peek offset/scale) — the live drag transform lives on the inner face card instead, so reveal panels (siblings of the face card) stay put while it slides. */
        get stackStyle() {
            const i = this.stackIndex;
            if (i < 0 || i > 2) return 'display: none';
            if (i === 0) return 'z-index: 3;';
            const offset = i * 10;
            const scale = 1 - i * 0.045;
            return `transform: translateY(${offset}px) scale(${scale}); z-index: ${3 - i}; pointer-events: none;`;
        },

        get progress() {
            if (this.dx !== 0) return Math.min(Math.abs(this.dx) / this.thresholdH, 1);
            if (this.dy !== 0) return Math.min(Math.abs(this.dy) / this.thresholdV, 1);
            return 0;
        },
        get dir() {
            if (this.dx !== 0) return this.dx > 0 ? 'right' : 'left';
            if (this.dy !== 0) return this.dy > 0 ? 'down' : 'up';
            return null;
        },
        get reached() {
            if (this.dx !== 0) return Math.abs(this.dx) >= this.thresholdH;
            if (this.dy !== 0) return Math.abs(this.dy) >= this.thresholdV;
            return false;
        },

        down(e) {
            if (e.pointerType === 'mouse' || this.flying || this.dateOpen || !this.isTop) return;
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
                if (Math.abs(dx) > Math.abs(dy) + 6) this.locked = 'h';
                else if (Math.abs(dy) > Math.abs(dx) + 6) this.locked = 'v';
                else return;
            }

            if (e.cancelable) e.preventDefault();

            if (this.locked === 'h') {
                const dir = dx > 0 ? 'right' : 'left';
                if (!this.dirs[dir]) { this.dx = dx * 0.18; return; }
                const sign = Math.sign(dx);
                const abs = Math.abs(dx);
                const max = this.thresholdH * 1.6;
                this.dx = sign * (abs <= max ? abs : max + (abs - max) * 0.22);
            } else {
                const dir = dy > 0 ? 'down' : 'up';
                if (!this.dirs[dir]) { this.dy = dy * 0.18; return; }
                const sign = Math.sign(dy);
                const abs = Math.abs(dy);
                const max = this.thresholdV * 1.6;
                this.dy = sign * (abs <= max ? abs : max + (abs - max) * 0.22);
            }
        },

        up() {
            if (!this.dragging) return;
            this.dragging = false;
            this.locked = null;
            const dir = this.dir;
            const cfgDir = dir ? this.dirs[dir] : null;
            const commit = this.reached && cfgDir;
            if (commit) this.resolve(dir, cfgDir);
            else this.spring();
        },

        spring() {
            this.dx = 0;
            this.dy = 0;
        },

        /** Button fallback: synthesise a past-threshold gesture and resolve it exactly like a completed swipe. */
        trigger(dirName) {
            if (!this.isTop || this.flying || this.dateOpen) return;
            const cfgDir = this.dirs[dirName];
            if (!cfgDir) return;
            if (dirName === 'right') this.dx = this.thresholdH;
            else if (dirName === 'left') this.dx = -this.thresholdH;
            else if (dirName === 'down') this.dy = this.thresholdV;
            else if (dirName === 'up') this.dy = -this.thresholdV;
            this.resolve(dirName, cfgDir);
        },

        /**
         * Desktop keyboard fast-path: focus the card (tab or click) then use
         * arrow keys — same resolution as a completed drag or a button tap.
         * A key with no configured direction (e.g. ArrowUp on an inbox card)
         * is simply ignored, exactly like a dead-side drag never commits.
         */
        key(e) {
            const map = { ArrowRight: 'right', ArrowLeft: 'left', ArrowDown: 'down', ArrowUp: 'up' };
            const dirName = map[e.key];
            if (!dirName) return;
            e.preventDefault();
            this.trigger(dirName);
        },

        resolve(dirName, cfgDir) {
            if (cfgDir.kind === 'popover') {
                this.spring();
                this.dateOpen = true;
                return;
            }

            if (cfgDir.kind === 'defer') {
                this.flying = true;
                this.dy = (this.$el.offsetHeight || 220) + 60;
                setTimeout(() => {
                    this.$store.prepare.requeue(this.phase, this.id);
                    this.flying = false;
                    this.dx = 0;
                    this.dy = 0;
                }, 180);
                return;
            }

            // 'commit' — right/left only in this feature.
            if (this.phase === 'review' && dirName === 'right') this.$store.prepare.flaggedCount++;
            this.flying = true;
            const w = this.$el.offsetWidth || 320;
            this.dx = dirName === 'right' ? w + 24 : -(w + 24);
            setTimeout(() => {
                if (cfgDir.wire) this.$wire[cfgDir.wire](...(cfgDir.args ?? []));
                if (this.phase === 'inbox') this.$store.prepare.enqueueReview(this.id);
                this.$store.prepare.remove(this.phase, this.id);
            }, 150);
        },

        quickSetDate() {
            this.$wire.quickSetDates(this.id, this.deadline || null, this.dueDate || null);
        },
    }));

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
        ppm: 1,
        dayStart: 360,
        span: 1020,
        snap: 5,
        minLen: 10,
        kind: null,
        sy: 0,
        origStart: 0,
        origEnd: 0,
        moved: false,
        lastTap: 0,

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

        begin(kind, e) {
            if (e.button != null && e.button !== 0) return;
            const grid = this.$el.closest('[data-grid]');
            if (grid) this.ppm = grid.getBoundingClientRect().height / this.span;
            this.kind = kind;
            this.sy = e.clientY;
            this.origStart = this.start;
            this.origEnd = this.end;
            this.moved = false;
            this.$el.setPointerCapture?.(e.pointerId);
            if (e.cancelable) e.preventDefault();
        },

        drag(e) {
            if (!this.kind) return;
            const dy = e.clientY - this.sy;
            if (Math.abs(dy) > 3) this.moved = true;
            const dMin = Math.round(dy / this.ppm / this.snap) * this.snap;
            const dur = this.origEnd - this.origStart;

            if (this.kind === 'move') {
                const ns = Math.max(0, Math.min(1440 - dur, this.origStart + dMin));
                this.start = ns;
                this.end = ns + dur;
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

            const hhmm = (m) =>
                `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`;

            if (kind === 'move') this.$wire.moveEvent(this.id, hhmm(this.start));
            else this.$wire.resizeEvent(this.id, hhmm(this.start), hhmm(this.end));
        },

        tap() {
            const now = Date.now();
            if (now - this.lastTap < 320) {
                this.$wire.startEditEvent(this.id);
                this.lastTap = 0;
            } else {
                this.lastTap = now;
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
            return `background: rgb(var(${v}) / 0.25); outline: 2px solid rgb(var(${v}) / 0.55); outline-offset: -1px`;
        },
    }));

    /**
     * focusTimer — a live, client-side Pomodoro countdown for the header ring.
     * Seeded with the seconds left + the phase length; ticks each second and
     * exposes the mm:ss label and the SVG stroke-dashoffset for the ring fill.
     * Chimes when it reaches 0 and calls the server's handlePhaseComplete —
     * which either continues into the next phase (autostart enabled) or
     * freezes awaiting a manual continue (disabled). Either way, the
     * server-driven re-render that follows swaps in fresh state (see wire:key
     * on the ring in schedule-strip.blade.php, keyed on phase+cycle so a
     * transition always reinitialises this component with a fresh seed).
     *
     * cfg: { id, remaining, total, circ }
     */
    window.Alpine.data('focusTimer', (cfg = {}) => ({
        id: cfg.id,
        remaining: Math.max(0, cfg.remaining ?? 0),
        total: Math.max(1, cfg.total ?? 1),
        circ: cfg.circ ?? 264,
        timer: null,

        init() {
            this.timer = setInterval(() => {
                if (this.remaining > 0) {
                    this.remaining--;
                    if (this.remaining === 0) {
                        this.chime();
                        clearInterval(this.timer);
                        this.$wire.call('handlePhaseComplete', this.id);
                    }
                }
            }, 1000);
        },
        destroy() {
            clearInterval(this.timer);
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
 * Presence heartbeat — what makes "online" in a class agenda mean *using the
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
