import Sortable from 'sortablejs';

// Alpine is intentionally NOT imported or started here — Livewire 4 ships and
// boots its own Alpine. A second copy double-initialises every component.
// Livewire owns Alpine; we register data/helpers on top of it.
window.Sortable = Sortable;

/**
 * Drag & drop for a board zone (a column, or a column's Today area).
 * On drop we read the DESTINATION zone (evt.to) and persist its full id order
 * plus its list/today, so cross-column moves and in-column reordering both work.
 * Guarded so Livewire re-renders never double-initialise an element.
 */
window.boardSortable = function (el, wire) {
    if (el._sortable) return el._sortable;
    el._sortable = Sortable.create(el, {
        group: 'board',
        animation: 160,
        easing: 'cubic-bezier(0.16, 1, 0.3, 1)',
        ghostClass: 'board-ghost',
        chosenClass: 'board-chosen',
        delay: 60,
        delayOnTouchOnly: true,
        // Mark the page as dragging so project cards can show a drop affordance.
        onStart: () => document.body.classList.add('dragging-task'),
        onEnd: (evt) => {
            document.body.classList.remove('dragging-task');
            const to = evt.to;
            // A drop onto a project card lands in a zone with no data-list; the
            // project drop zone's own onAdd handles that. Only persist real columns.
            if (to.dataset.list === undefined) return;
            const ids = Array.from(to.querySelectorAll('[data-id]')).map((n) => n.dataset.id);
            wire.reorder(to.dataset.list, to.dataset.today === 'true', ids);
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

document.addEventListener('alpine:init', () => {
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
                if (Math.abs(dx) > Math.abs(dy) + 6) this.locked = 'h';
                else if (Math.abs(dy) > Math.abs(dx) + 6) {
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
                this.$wire.swipeIntent(this.id, intent);
            }, 150);
        },
    }));

    /**
     * scheduleEvent — drag an event on the timeline grid. Body drag moves it
     * (duration preserved); the top/bottom handles resize it. Times snap to 5'.
     * A double-tap opens the edit sheet (mobile); desktop uses the hover pencil.
     * Geometry (top/height in px) is derived from minutes via the grid's --ppm.
     *
     * cfg: { id, start, end }  (start/end in minutes from midnight)
     */
    window.Alpine.data('scheduleEvent', (cfg = {}) => ({
        id: cfg.id,
        start: cfg.start ?? 0,
        end: cfg.end ?? 0,
        ppm: 1,
        dayStart: 360,
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
                this.ppm = parseFloat(grid.dataset.ppm) || 1;
                this.dayStart = parseInt(grid.dataset.dayStart, 10) || 360;
            }
        },

        get top() {
            return (this.start - this.dayStart) * this.ppm;
        },
        get height() {
            return Math.max((this.end - this.start) * this.ppm, 16);
        },

        begin(kind, e) {
            if (e.button != null && e.button !== 0) return;
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
     * focusTimer — a live, client-side Pomodoro countdown for the header ring.
     * Seeded with the seconds left + the session length; ticks each second and
     * exposes the mm:ss label and the SVG stroke-dashoffset for the ring fill.
     *
     * cfg: { remaining, total, circ }
     */
    window.Alpine.data('focusTimer', (cfg = {}) => ({
        remaining: Math.max(0, cfg.remaining ?? 0),
        total: Math.max(1, cfg.total ?? 1),
        circ: cfg.circ ?? 264,
        timer: null,

        init() {
            this.timer = setInterval(() => {
                if (this.remaining > 0) this.remaining--;
                else clearInterval(this.timer);
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
    }));

    /**
     * freePaint — drag on an empty stretch of the Brief's timeline to mark free
     * working time. Reports the painted range (minutes, snapped to 5') to
     * Livewire, which merges it into the free-time blocks. A provisional overlay
     * follows the finger while painting.
     */
    window.Alpine.data('freePaint', () => ({
        ppm: 1,
        dayStart: 360,
        snap: 5,
        painting: false,
        startMin: 0,
        curMin: 0,

        init() {
            this.ppm = parseFloat(this.$el.dataset.ppm) || 1;
            this.dayStart = parseInt(this.$el.dataset.dayStart, 10) || 360;
        },

        yToMin(clientY) {
            const rect = this.$el.getBoundingClientRect();
            const raw = this.dayStart + (clientY - rect.top) / this.ppm;
            const snapped = Math.round(raw / this.snap) * this.snap;
            return Math.max(this.dayStart, Math.min(this.dayStart + rect.height / this.ppm, snapped));
        },

        begin(e) {
            if (e.button != null && e.button !== 0) return;
            this.painting = true;
            this.startMin = this.yToMin(e.clientY);
            this.curMin = this.startMin;
            this.$el.setPointerCapture?.(e.pointerId);
            if (e.cancelable) e.preventDefault();
        },

        move(e) {
            if (this.painting) this.curMin = this.yToMin(e.clientY);
        },

        end() {
            if (!this.painting) return;
            this.painting = false;
            const a = Math.min(this.startMin, this.curMin);
            const b = Math.max(this.startMin, this.curMin);
            if (b - a >= this.snap) this.$wire.addFreeBlock(a, b);
        },

        get provTop() {
            return (Math.min(this.startMin, this.curMin) - this.dayStart) * this.ppm;
        },
        get provHeight() {
            return Math.abs(this.curMin - this.startMin) * this.ppm;
        },
    }));
});
