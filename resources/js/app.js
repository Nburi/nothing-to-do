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
});
