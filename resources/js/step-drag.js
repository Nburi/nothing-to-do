/**
 * Step 4.3c: moving the steps of a project or group page (docs/steps/4.3c.md).
 *
 * Three ways to lift a step, one way to put it down:
 *  - Mouse: drag the grip. It starts after a few pixels.
 *  - Touch: press and hold the step (350 ms, as the week editor), on the grip or anywhere on it. Only the
 *    long press lifts: a swipe, even one that starts on the grip, is a scroll and lifts nothing.
 *  - Keyboard: Space on the grip lifts, ↑ ↓ change the place, Page ↑ ↓ change the stage, Space drops,
 *    Escape cancels. Every change is announced in a live region.
 * While a step is lifted the lane it would land in lights up, a dashed slot shows the place, a bar names
 * what the drop would do (also for the board), and a dock at the bottom lists every stage, so a far stage
 * is one hover away. Every spot of the steps column belongs to a place (the nearest stage), so there is
 * no dead zone in between. The ⋯ menu moves without dragging at all (server-rendered buttons).
 *
 * The server decides everything (ProjectEditor::place(), through $wire.moveStep): this file measures
 * pointers, previews the result with the plain rules of step-order.js and puts the step where it will be
 * while the request runs. If the request fails the step goes back and a short plain note says so.
 */
import { describe, fmt, hasStages, laneOf, moved, settle } from './step-order';

const HOLD_MS = 350; // press and hold that lifts a step on a touch screen
const START_PX = 4; // a grip drag with a mouse starts after this much movement
const CANCEL_PX = 9; // a hold that moves further is a scroll or a swipe, not a lift
const EDGE_PX = 56; // near the top and bottom edge the page scrolls under the lifted step
const SCROLL_MAX = 14; // pixels per frame at the very edge

const DOCK_CHIP = 'inline-flex min-h-10 items-center gap-1.5 rounded-full border border-line bg-paper px-3 text-xs font-semibold text-ink-soft transition';
const DOCK_CHIP_OVER = 'inline-flex min-h-10 items-center gap-1.5 rounded-full border border-accent bg-accent px-3 text-xs font-semibold text-accent-fg transition';

const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const phone = () => window.matchMedia('(max-width: 719px)').matches;
const clamp = (n, low, high) => Math.min(Math.max(n, low), Math.max(low, high));
const stopEvent = (event) => event.preventDefault();

function make(tag, className, attributes = {}) {
    const node = document.createElement(tag);
    node.className = className;
    Object.entries(attributes).forEach(([name, value]) => node.setAttribute(name, value));

    return node;
}

/** A ghost is a copy of a row that lives outside Alpine and Livewire: no directives, no ids. */
function scrub(node) {
    [...node.attributes].forEach((attribute) => {
        if (attribute.name === 'id' || attribute.name === 'data-step' || /^(x-|@|:|wire:)/.test(attribute.name)) node.removeAttribute(attribute.name);
    });
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('stepDrag', (cfg = {}) => {
        const T = cfg.text ?? {};
        let root = null;
        let wire = null;
        let S = null; // a drag with a pointer
        let K = null; // a lift with the keyboard
        let noteTimer = 0;
        const ui = { live: null, effect: null, dock: null, note: null };
        const offs = [];

        // ---- the page as a model, and putting a node at a place ---------------------------------------

        const lanes = () => [...root.querySelectorAll('[data-lane]')];
        const rowsOf = (lane) => [...lane.children].filter((child) => child.matches('[data-step]'));
        const visibleRows = () => [...root.querySelectorAll('[data-step]:not([data-lifted])')];
        const gripOf = (row) => row.querySelector('[data-grip]');

        function readModel() {
            const steps = {};
            root.querySelectorAll('[data-step]').forEach((row) => {
                steps[row.dataset.step] = { title: row.dataset.title ?? '', list: row.dataset.list ?? '', waits: row.dataset.waitsOn ? Number(row.dataset.waitsOn) : null };
            });

            return {
                lanes: lanes().map((el) => ({ key: el.dataset.lane, index: Number(el.dataset.index), name: el.dataset.name ?? '', ids: rowsOf(el).map((row) => Number(row.dataset.step)) })),
                steps,
            };
        }

        /** Put a node into a lane at a slot (counted without the node itself, null = the end). */
        function insertAt(node, key, slot) {
            const list = root.querySelector('[data-lane="' + key + '"]');
            if (!list) return;
            const rows = rowsOf(list).filter((row) => row !== node && !row.hasAttribute('data-lifted'));
            const before = slot === null || slot === undefined || slot >= rows.length ? null : rows[slot];
            list.insertBefore(node, before ?? list.querySelector(':scope > [data-lane-empty]'));
        }

        /** Siblings that move because of a change slide to their new place instead of jumping. */
        function flip(nodes, mutate) {
            if (reduced() || nodes.length === 0 || !nodes[0].animate) return mutate();
            const before = new Map(nodes.map((node) => [node, node.getBoundingClientRect().top]));
            mutate();
            nodes.forEach((node) => {
                const dy = before.get(node) - node.getBoundingClientRect().top;
                if (Math.abs(dy) > 1) node.animate([{ transform: 'translateY(' + dy + 'px)' }, { transform: 'none' }], { duration: 170, easing: 'cubic-bezier(.16,1,.3,1)' });
            });
        }

        function analyse(model, id, lane, slot) {
            const settled = settle(moved(model, id, lane, slot), id);

            return { desc: describe(model, settled.model, id, settled.cleared, T) };
        }

        // ---- the small things that sit on the page ---------------------------------------------------

        /** Said to screen readers; cleared first so the same sentence twice is still read twice. */
        function say(text) {
            if (!ui.live) return;
            ui.live.textContent = '';
            setTimeout(() => { if (ui.live) ui.live.textContent = text; }, 30);
        }

        function showEffect(desc) {
            if (!ui.effect) {
                ui.effect = make('div', 'pointer-events-none fixed inset-x-3 top-3 z-[66] mx-auto flex max-w-md flex-col gap-0.5 rounded-card bg-surface px-3 py-2 text-sm text-ink shadow-card', { 'aria-hidden': 'true' });
                document.body.appendChild(ui.effect);
            }
            ui.effect.style.border = 'var(--t-bw) solid var(--t-line)';
            ui.effect.style.borderLeft = '5px solid ' + (desc.tone === 'warn' ? 'var(--t-warn)' : 'var(--t-accent)');
            const head = make('b', 'font-semibold');
            head.textContent = desc.head;
            const lines = (phone() ? desc.lines.slice(0, 2) : desc.lines).map((line) => {
                const span = make('span', 'text-xs text-ink-soft');
                span.textContent = line;

                return span;
            });
            ui.effect.replaceChildren(head, ...lines);
        }

        function hideEffect() {
            ui.effect?.remove();
            ui.effect = null;
        }

        function showDock(model) {
            if (model.lanes.length < 2) return;
            const dock = make('div', 'fixed inset-x-3 bottom-[max(12px,env(safe-area-inset-bottom))] z-[65] mx-auto flex max-w-xl flex-wrap justify-center gap-1.5 rounded-panel bg-surface p-2 shadow-card', { 'aria-hidden': 'true' });
            dock.style.border = 'var(--t-bw) solid var(--t-line)';
            model.lanes.forEach((lane) => {
                const chip = make('span', DOCK_CHIP, { 'data-dock-lane': lane.key });
                const name = make('span', 'max-w-[10rem] truncate');
                name.textContent = lane.index >= 0 ? fmt(T.dock_stage, { number: lane.index + 1, name: lane.name }) : T.dock_none;
                const count = make('small', 'font-mono text-[10.5px] opacity-70');
                count.textContent = String(lane.ids.length);
                chip.append(name, count);
                dock.appendChild(chip);
            });
            document.body.appendChild(dock);
            ui.dock = dock;
        }

        function markTarget(laneKey, dockKey = null) {
            root.querySelectorAll('[data-lane-box]').forEach((box) => {
                box.toggleAttribute('data-target', laneKey !== null && box.querySelector('[data-lane="' + laneKey + '"]') !== null);
            });
            ui.dock?.querySelectorAll('[data-dock-lane]').forEach((chip) => {
                chip.className = chip.dataset.dockLane === dockKey ? DOCK_CHIP_OVER : DOCK_CHIP;
            });
        }

        /** A calm, plain note when a move could not be saved (never a stack trace, never a native dialog). */
        function note(text) {
            clearTimeout(noteTimer);
            ui.note?.remove();
            const bar = make('div', 'pointer-events-none fixed inset-x-0 top-3 z-[67] flex justify-center px-4', { role: 'status' });
            const body = make('p', 'pointer-events-auto flex max-w-md items-center gap-3 rounded-card bg-deep px-4 py-2.5 text-sm font-medium text-on-deep shadow-card');
            const message = make('span', 'min-w-0 wrap-break-word');
            message.textContent = text;
            const close = make('button', 'shrink-0 rounded-full px-1 text-xs font-semibold underline underline-offset-4 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-on-deep', { type: 'button' });
            close.textContent = T.close;
            close.addEventListener('click', () => { bar.remove(); ui.note = null; });
            body.append(message, close);
            bar.appendChild(body);
            document.body.appendChild(bar);
            ui.note = bar;
            noteTimer = setTimeout(() => { bar.remove(); ui.note = null; }, 8000);
            say(text);
        }

        function flash(row) {
            row.setAttribute('data-flash', '');
            setTimeout(() => row.removeAttribute('data-flash'), 1300);
        }

        // ---- saving: the step is at its place at once, the server confirms ----------------------------

        async function save(row, id, lane, position, origin, focusAfter = false) {
            row.setAttribute('data-saving', '');
            row.setAttribute('aria-busy', 'true');
            try {
                await wire.moveStep(id, lane, position);
                const now = root.querySelector('[data-step="' + id + '"]');
                if (now) flash(now);
                if (focusAfter) now?.querySelector('[data-grip]')?.focus({ preventScroll: true });
            } catch (error) {
                // Nothing was saved: the page shows what the server still has, so the step goes back.
                if (root.contains(row)) insertAt(row, origin.key, origin.index);
                note(error?.status === 419 ? T.expired : T.failed);
            } finally {
                row.removeAttribute('data-saving');
                row.removeAttribute('aria-busy');
            }
        }

        // ---- a drag with a pointer -------------------------------------------------------------------

        function makeGhost(row, rect) {
            const copy = row.cloneNode(true);
            copy.querySelectorAll('[x-show]').forEach((node) => node.remove());
            [copy, ...copy.querySelectorAll('*')].forEach(scrub);
            copy.removeAttribute('data-pressing');
            copy.style.borderColor = 'var(--t-accent)';
            const ghost = make('div', 'pointer-events-none fixed left-0 top-0 z-[70] shadow-card', { 'aria-hidden': 'true' });
            ghost.style.width = rect.width + 'px';
            ghost.style.willChange = 'transform';
            ghost.style.borderRadius = getComputedStyle(row).borderRadius;
            ghost.appendChild(copy);
            document.body.appendChild(ghost);

            return ghost;
        }

        function makeSlot(height) {
            const slot = make('li', 'flex items-center justify-center rounded-card border-2 border-dashed border-accent bg-accent-soft font-mono text-[10.5px] uppercase tracking-wider text-accent-ink', { 'data-ph': '', 'aria-hidden': 'true' });
            slot.style.height = height + 'px';
            slot.textContent = T.drop_here ?? '';

            return slot;
        }

        function begin(event, row, mode) {
            S = { id: Number(row.dataset.step), row, mode, pid: event.pointerId, x0: event.clientX, y0: event.clientY, x: event.clientX, y: event.clientY, live: false, timer: 0, raf: 0, ghost: null, slot: null, off: { x: 0, y: 0 }, model: null, origin: null, target: null, an: null };
            if (mode === 'hold') {
                row.setAttribute('data-pressing', '');
                S.timer = setTimeout(lift, HOLD_MS);
            }
            window.addEventListener('pointermove', onMove);
            window.addEventListener('pointerup', onUp);
            window.addEventListener('pointercancel', onCancel);
            window.addEventListener('keydown', onEscape, true);
            window.addEventListener('blur', onBlur);
            document.addEventListener('contextmenu', stopEvent);
            document.addEventListener('selectstart', stopEvent);
        }

        function stopSession() {
            if (!S) return;
            clearTimeout(S.timer);
            cancelAnimationFrame(S.raf);
            window.removeEventListener('pointermove', onMove);
            window.removeEventListener('pointerup', onUp);
            window.removeEventListener('pointercancel', onCancel);
            window.removeEventListener('keydown', onEscape, true);
            window.removeEventListener('blur', onBlur);
            document.removeEventListener('contextmenu', stopEvent);
            document.removeEventListener('selectstart', stopEvent);
            try { document.documentElement.releasePointerCapture(S.pid); } catch { /* it was never captured */ }
            S.row.removeAttribute('data-pressing');
            S.ghost?.remove();
            ui.dock?.remove();
            ui.dock = null;
            hideEffect();
            markTarget(null);
            delete document.body.dataset.stepLift;
            document.body.classList.remove('select-none', 'cursor-grabbing');
            S = null;
        }

        function lift() {
            if (!S || S.live) return;
            const row = S.row;
            const rect = row.getBoundingClientRect();
            S.model = readModel();
            const from = laneOf(S.model, S.id);
            S.origin = { key: from.key, index: from.ids.indexOf(S.id) };
            S.off = { x: clamp(S.x - rect.left, 8, rect.width - 8), y: clamp(S.y - rect.top, 8, rect.height - 8) };
            S.ghost = makeGhost(row, rect);
            S.slot = makeSlot(rect.height);
            try { document.documentElement.setPointerCapture(S.pid); } catch { /* a pointer that cannot be captured still drags */ }
            row.removeAttribute('data-pressing');
            row.after(S.slot);
            row.setAttribute('data-lifted', '');
            S.live = true;
            document.body.dataset.stepLift = '';
            document.body.classList.add('select-none', 'cursor-grabbing');
            showDock(S.model);
            try { navigator.vibrate?.(8); } catch { /* not every browser has it */ }
            placeGhost();
            update();
            S.raf = requestAnimationFrame(tick);
        }

        function placeGhost() {
            const look = reduced() ? '' : ' scale(1.02) rotate(.4deg)';
            S.ghost.style.transform = 'translate(' + (S.x - S.off.x) + 'px,' + (S.y - S.off.y) + 'px)' + look;
        }

        /** Every spot of the steps column is a place: the nearest stage, and between its steps by their middles. */
        function hit(x, y) {
            const over = document.elementFromPoint(x, y)?.closest('[data-dock-lane]');
            if (over) return { key: 'dock:' + over.dataset.dockLane, lane: over.dataset.dockLane, slot: null, via: 'dock' };

            const boxes = [...root.querySelectorAll('[data-lane-box]')];
            if (boxes.length === 0) return null;
            const rects = boxes.map((box) => box.getBoundingClientRect());
            if (x < Math.min(...rects.map((r) => r.left)) - 24 || x > Math.max(...rects.map((r) => r.right)) + 24) return null;

            let best = 0;
            let distance = Infinity;
            rects.forEach((r, i) => {
                const d = y < r.top ? r.top - y : y > r.bottom ? y - r.bottom : 0;
                if (d < distance) { distance = d; best = i; }
            });
            const lane = boxes[best].querySelector('[data-lane]');
            if (!lane) return null;

            let slot = 0;
            rowsOf(lane).filter((row) => row !== S.row && !row.hasAttribute('data-lifted')).forEach((row) => {
                const r = row.getBoundingClientRect();
                if (y > r.top + r.height / 2) slot++;
            });

            return { key: lane.dataset.lane + ':' + slot, lane: lane.dataset.lane, slot, via: 'lane' };
        }

        function update() {
            if (!S || !S.live) return;
            const target = hit(S.x, S.y);
            if ((target ? target.key : '') === (S.target ? S.target.key : '')) return;
            S.target = target;
            enter(target);
        }

        function enter(target) {
            const nodes = [...visibleRows(), S.slot];
            if (target === null) {
                // Outside every place: letting go here cancels, so the slot goes back to where the step is from.
                flip(nodes, () => insertAt(S.slot, S.origin.key, S.origin.index));
                S.an = null;
                hideEffect();
                markTarget(null);

                return;
            }
            S.an = analyse(S.model, S.id, target.lane, target.slot);
            flip(nodes, () => insertAt(S.slot, target.lane, target.slot));
            markTarget(target.lane, target.via === 'dock' ? target.lane : null);
            S.ghost.style.opacity = target.via === 'dock' ? '0.35' : ''; // the dock stays readable under the finger
            showEffect(S.an.desc);
        }

        function tick() {
            if (!S || !S.live) return;
            const dockTop = ui.dock ? ui.dock.getBoundingClientRect().top : window.innerHeight;
            const bottom = dockTop - EDGE_PX;
            let dy = 0;
            if (S.y < EDGE_PX) dy = -Math.ceil(((EDGE_PX - S.y) / EDGE_PX) * SCROLL_MAX);
            else if (S.y > bottom && S.y < dockTop) dy = Math.ceil(((S.y - bottom) / EDGE_PX) * SCROLL_MAX);
            if (dy !== 0) {
                window.scrollBy(0, dy);
                update();
            }
            S.raf = requestAnimationFrame(tick);
        }

        function onMove(event) {
            if (!S || event.pointerId !== S.pid) return;
            S.x = event.clientX;
            S.y = event.clientY;
            if (!S.live) {
                const travelled = Math.hypot(S.x - S.x0, S.y - S.y0);
                if (S.mode === 'hold') {
                    if (travelled > CANCEL_PX) stopSession();
                } else if (travelled >= START_PX) {
                    lift();
                }

                return;
            }
            placeGhost();
            update();
        }

        function end(kind) {
            if (!S) return;
            const s = S;
            stopSession();
            if (!s.live) return;

            const { row, slot, target, an } = s;
            if (kind !== 'drop' || target === null || an === null || an.desc.same) {
                // Cancelled, or put down where it was: the slot closes and the step shows again.
                flip(visibleRows(), () => { slot.remove(); row.removeAttribute('data-lifted'); });

                return;
            }
            // The step is where it will be while the server saves it.
            flip(visibleRows(), () => { slot.replaceWith(row); row.removeAttribute('data-lifted'); });
            save(row, s.id, target.lane, target.slot, s.origin);
        }

        const onUp = (event) => { if (S && event.pointerId === S.pid) end('drop'); };
        const onCancel = (event) => { if (S && event.pointerId === S.pid) end('cancel'); };
        const onBlur = () => end('cancel');
        const onEscape = (event) => {
            if (!S || event.key !== 'Escape') return;
            event.preventDefault();
            event.stopPropagation();
            end('cancel');
        };

        /** After the lift a touch must not scroll the page (the listener has to exist before the touch starts). */
        const onTouchMove = (event) => { if (S && S.live && event.cancelable) event.preventDefault(); };

        function onDown(event) {
            if (S || K || !event.isPrimary) return;
            if (event.pointerType === 'mouse' && event.button !== 0) return;
            const row = event.target.closest('[data-step]');
            if (!row || !root.contains(row)) return;
            if (event.target.closest('button:not([data-grip]), a, select, input, textarea, label, [data-step-menu]')) return;

            // Touch (and pen): press and hold, on the grip or anywhere on the step, so a swipe always scrolls.
            // Mouse: the grip only, and it starts after a few pixels.
            if (event.pointerType !== 'mouse') return begin(event, row, 'hold');
            if (event.target.closest('[data-grip]') === null) return;
            event.preventDefault(); // no text selection, no native drag
            begin(event, row, 'grip');
        }

        // ---- a lift with the keyboard ----------------------------------------------------------------

        function focusGrip(row) {
            gripOf(row)?.focus({ preventScroll: true });
        }

        function placeKeyboardRow(lane, slot) {
            K.moving = true;
            insertAt(K.row, lane, slot);
            focusGrip(K.row);
            K.row.scrollIntoView({ block: 'nearest' });
            K.moving = false;
        }

        function endKeyboard() {
            if (!K) return;
            K.row.removeAttribute('data-kb');
            gripOf(K.row)?.setAttribute('aria-pressed', 'false');
            hideEffect();
            delete document.body.dataset.stepLift;
            K = null;
        }

        function kbLift(row) {
            const id = Number(row.dataset.step);
            const model = readModel();
            const from = laneOf(model, id);
            K = { id, row, model, from: { key: from.key, index: from.ids.indexOf(id) }, lane: from.key, slot: from.ids.indexOf(id), moving: false };
            row.setAttribute('data-kb', '');
            gripOf(row)?.setAttribute('aria-pressed', 'true');
            document.body.dataset.stepLift = '';
            showEffect({ tone: 'ok', head: T.lift_head, lines: [T.lift_line] });
            say(fmt(hasStages(model) ? T.lifted : T.lifted_list, { title: row.dataset.title }));
        }

        function kbStep(direction) {
            const model = readModel();
            const keys = model.lanes.map((lane) => lane.key);
            const here = laneOf(model, K.id);
            const at = here.ids.indexOf(K.id);
            const k = keys.indexOf(here.key);
            let lane = here.key;
            let slot = at;

            if (direction === 'down') {
                if (at < here.ids.length - 1) slot = at + 1;
                else if (keys[k + 1]) { lane = keys[k + 1]; slot = 0; } else return say(T.at_bottom);
            } else if (direction === 'up') {
                if (at > 0) slot = at - 1;
                else if (keys[k - 1]) { lane = keys[k - 1]; slot = model.lanes[k - 1].ids.length; } else return say(T.at_top);
            } else if (direction === 'pgdown' || direction === 'pgup') {
                if (!hasStages(model)) return say(T.no_stages);
                const next = keys[k + (direction === 'pgdown' ? 1 : -1)];
                if (!next) return say(direction === 'pgdown' ? T.last_stage : T.first_stage);
                lane = next;
                slot = 0;
            } else if (direction === 'home') {
                slot = 0;
            } else {
                slot = here.ids.length - 1;
            }
            if (lane === here.key && slot === at) return say(direction === 'home' || direction === 'up' ? T.at_top : T.at_bottom);

            const settled = settle(moved(model, K.id, lane, slot), K.id);
            const desc = describe(K.model, settled.model, K.id, settled.cleared, T);
            K.lane = lane;
            K.slot = slot;
            placeKeyboardRow(lane, slot);
            showEffect(desc);
            say([desc.head, ...desc.lines].join('. ').replace(/\.\./g, '.'));
        }

        function kbDrop() {
            const lifted = K;
            const here = laneOf(readModel(), lifted.id);
            const index = here.ids.indexOf(lifted.id);
            endKeyboard();
            if (here.key === lifted.from.key && index === lifted.from.index) {
                say(T.dropped_same);
                focusGrip(lifted.row);

                return;
            }
            save(lifted.row, lifted.id, here.key, index, lifted.from, true);
        }

        function kbCancel(refocus = true) {
            const lifted = K;
            K.moving = true;
            insertAt(lifted.row, lifted.from.key, lifted.from.index);
            if (refocus) focusGrip(lifted.row);
            K.moving = false;
            endKeyboard();
            say(T.cancelled);
        }

        function onKey(event) {
            const grip = event.target.closest?.('[data-grip]');
            if (!grip || !root.contains(grip) || S) return;
            const row = grip.closest('[data-step]');
            if (!row) return; // the grip of a stage (board-order.js), not of a step
            if (!K) {
                if ((event.key === ' ' || event.key === 'Enter') && !event.repeat) {
                    event.preventDefault();
                    kbLift(row);
                }

                return;
            }
            if (K.row !== row) return;
            const direction = { ArrowUp: 'up', ArrowDown: 'down', PageUp: 'pgup', PageDown: 'pgdown', Home: 'home', End: 'end' }[event.key];
            if (direction) {
                event.preventDefault();
                kbStep(direction);
            } else if (event.key === ' ' || event.key === 'Enter') {
                event.preventDefault();
                kbDrop();
            } else if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                kbCancel();
            }
        }

        /** Leaving the grip with Tab or a click ends the lift: the step goes back, nothing half-done stays. */
        function onFocusOut() {
            if (!K || K.moving) return;
            setTimeout(() => {
                if (K && !K.moving && document.activeElement !== gripOf(K.row)) kbCancel(false);
            }, 0);
        }

        /** The page was re-rendered by the server (a save, another action): a lifted step is put back at its place. */
        function resync() {
            if (S && S.live) {
                if (!root.contains(S.row)) return end('cancel');
                S.row.setAttribute('data-lifted', '');
                insertAt(S.slot, S.target ? S.target.lane : S.origin.key, S.target ? S.target.slot : S.origin.index);
            }
            if (K) {
                if (!root.contains(K.row)) return endKeyboard();
                K.row.setAttribute('data-kb', '');
                gripOf(K.row)?.setAttribute('aria-pressed', 'true');
                placeKeyboardRow(K.lane, K.slot);
            }
        }

        return {
            init() {
                root = this.$el;
                wire = this.$wire;
                ui.live = make('div', 'sr-only', { 'aria-live': 'polite', 'aria-atomic': 'true' });
                document.body.appendChild(ui.live);
                root.addEventListener('pointerdown', onDown);
                root.addEventListener('keydown', onKey);
                root.addEventListener('focusout', onFocusOut);
                window.addEventListener('touchmove', onTouchMove, { passive: false });
                // A failed move is handled here, calmly: no error dialog, no "page expired" confirm().
                offs.push(wire.$interceptRequest('moveStep', ({ onError }) => { onError(({ preventDefault }) => preventDefault()); }));
                offs.push(wire.$interceptMessage(({ onSuccess }) => { onSuccess(({ onMorphed }) => { onMorphed(resync); }); }));
            },
            destroy() {
                if (S) end('cancel');
                endKeyboard();
                offs.splice(0).forEach((off) => off?.());
                root?.removeEventListener('pointerdown', onDown);
                root?.removeEventListener('keydown', onKey);
                root?.removeEventListener('focusout', onFocusOut);
                window.removeEventListener('touchmove', onTouchMove);
                clearTimeout(noteTimer);
                Object.values(ui).forEach((node) => node?.remove());
            },
        };
    });
});
