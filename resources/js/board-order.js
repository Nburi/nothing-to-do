/**
 * Step 4.8 (C31): ordering the cards and rows of the board (docs/steps/4.8.md).
 *
 * One list (a column, a Stapel sheet, the „Wichtig" band) is one `<ul data-order-list x-data="boardOrder(...)">`;
 * its direct children `<li data-entry="task:1|project:2|group:3" data-title="…">` are the slots of the order.
 *
 *  - Mouse: drag the grip (it appears on hover and focus). It starts after a few pixels.
 *  - Touch: press and hold the card or row (350 ms). Only the long press lifts: a swipe is a scroll.
 *  - Keyboard: Space on the grip lifts, ↑ ↓ change the place, Space drops, Escape cancels. Every step is
 *    announced in a live region.
 * A dashed slot shows where the drop lands. The server decides (Board::reorder): this file only measures,
 * puts the entry where it will be while the request runs and puts it back with a plain note if that fails.
 * Entries never leave their list: a project card belongs to the column its next step is in.
 *
 * The same script orders the stages of a project page (docs/steps/board-followups.md): `cfg.method` names the
 * Livewire method that saves (default `reorder`) and `cfg.zone` a selector inside each entry that is the lift
 * handle and the ghost (the stage's heading line), so a tall entry full of steps is lifted by its heading only.
 */
const HOLD_MS = 350; // press and hold that lifts an entry on a touch screen
const START_PX = 4; // a grip drag with a mouse starts after this much movement
const CANCEL_PX = 9; // a hold that moves further is a scroll or a swipe, not a lift
const EDGE_PX = 56; // near the top and bottom edge the page scrolls under the lifted entry
const SCROLL_MAX = 14; // pixels per frame at the very edge

const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/** ":name" placeholders of a translation, replaced in one pass so a replaced value is never read again. */
const fmt = (text, vars = {}) => String(text ?? '').replace(/:([a-z]+)/g, (match, key) => (key in vars ? vars[key] : match));

function make(tag, className, attributes = {}) {
    const node = document.createElement(tag);
    node.className = className;
    Object.entries(attributes).forEach(([name, value]) => node.setAttribute(name, value));

    return node;
}

/** A ghost is a copy of an entry that lives outside Alpine and Livewire: no directives, no ids. */
function scrub(node) {
    [...node.attributes].forEach((attribute) => {
        if (attribute.name === 'id' || /^(x-|@|:|wire:|data-entry)/.test(attribute.name)) node.removeAttribute(attribute.name);
    });
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('boardOrder', (cfg = {}) => {
        const T = cfg.text ?? {};
        const method = cfg.method ?? 'reorder';
        const zone = cfg.zone ?? null;
        let root = null;
        let wire = null;
        let S = null; // a lift with a pointer
        let K = null; // a lift with the keyboard
        let hold = null; // a touch that may become a lift
        let noteTimer = 0;
        let raf = 0;
        const ui = { live: null, note: null };
        const offs = [];

        const entries = () => [...root.children].filter((child) => child.matches('[data-entry]'));
        const visible = () => entries().filter((entry) => !entry.hasAttribute('data-lifted'));
        const titleOf = (entry) => entry.dataset.title ?? '';
        /** The part of an entry that is lifted, measured and copied: the zone when there is one, else the entry. */
        const bodyOf = (entry) => (zone && entry.querySelector(zone)) || entry;

        /** Said to screen readers; cleared first so the same sentence twice is still read twice. */
        function say(text) {
            if (!ui.live) return;
            ui.live.textContent = '';
            setTimeout(() => { ui.live.textContent = text; }, 30);
        }

        /** A calm, plain note when a drop could not be saved (never a stack trace, never a native dialog). */
        function note(text) {
            clearTimeout(noteTimer);
            ui.note?.remove();
            const bar = make('div', 'pointer-events-none fixed inset-x-0 top-3 z-[67] flex justify-center px-4', { role: 'status' });
            const body = make('p', 'pointer-events-auto max-w-md rounded-card bg-deep px-4 py-2.5 text-sm font-medium text-on-deep shadow-card');
            body.textContent = text;
            bar.appendChild(body);
            document.body.appendChild(bar);
            ui.note = bar;
            noteTimer = setTimeout(() => { bar.remove(); ui.note = null; }, 6000);
            say(text);
        }

        /** After the last entry, but before whatever the list holds below its entries ("+ N weitere", the empty hint). */
        const tailOf = (list) => (list.length ? list[list.length - 1].nextElementSibling : null);

        function slotBefore(node, index, list) {
            // index counts the entries without the lifted one; null = the end
            const others = list.filter((entry) => entry !== node);
            const before = index === null || index >= others.length ? tailOf(others) : others[index];
            if (before !== node) root.insertBefore(node, before);
        }

        // ---- saving: the entry is at its place at once, the server confirms --------------------------

        async function save(entry, origin, focusAfter) {
            const refs = entries().map((e) => e.dataset.entry);
            entry.setAttribute('aria-busy', 'true');
            try {
                await wire[method](refs);
                const now = root.querySelector('[data-entry="' + entry.dataset.entry + '"]');
                if (focusAfter) now?.querySelector('[data-grip]')?.focus({ preventScroll: true });
            } catch (error) {
                // Nothing was saved: the page shows what the server still has, so the entry goes back.
                if (root.contains(entry)) slotBefore(entry, origin, entries());
                note(T.failed);
            } finally {
                entry.removeAttribute('aria-busy');
            }
        }

        // ---- a lift with a pointer --------------------------------------------------------------------

        function lift(entry, event, how) {
            const body = bodyOf(entry);
            const rect = body.getBoundingClientRect();
            const copy = body.cloneNode(true);
            [copy, ...copy.querySelectorAll('*')].forEach(scrub);
            copy.style.borderColor = 'var(--t-accent)';
            const ghost = make('div', 'pointer-events-none fixed left-0 top-0 z-[70] shadow-card', { 'aria-hidden': 'true' });
            ghost.style.width = rect.width + 'px';
            ghost.style.willChange = 'transform';
            ghost.style.borderRadius = getComputedStyle(entry).borderRadius;
            if (body !== entry) ghost.classList.add('rounded-panel', 'bg-surface', 'px-4', 'py-3');
            ghost.appendChild(copy);
            document.body.appendChild(ghost);

            const slot = make(entry.tagName === 'LI' ? 'li' : 'div', 'rounded-card border-2 border-dashed border-accent bg-accent-soft', { 'data-ph': '', 'aria-hidden': 'true' });
            slot.style.height = (body !== entry ? rect.height + 24 : rect.height) + 'px';
            const origin = visible().indexOf(entry);
            entry.setAttribute('data-lifted', '');
            entry.style.display = 'none';
            root.insertBefore(slot, entry);

            S = { entry, ghost, slot, origin, how, dx: event.clientX - rect.left, dy: event.clientY - rect.top, y: event.clientY, id: event.pointerId };
            document.body.style.userSelect = 'none';
            if (how === 'touch') navigator.vibrate?.(8);
            place(event);
            say(fmt(T.lifted, { title: titleOf(entry), place: origin + 1, count: visible().length }));
            tick();
        }

        /** Move the ghost with the pointer and the dashed slot to the nearest place. */
        function place(event) {
            S.y = event.clientY;
            S.ghost.style.transform = 'translate(' + (event.clientX - S.dx) + 'px,' + (event.clientY - S.dy) + 'px)';
            const others = visible();
            let index = others.length;
            for (let i = 0; i < others.length; i++) {
                const r = bodyOf(others[i]).getBoundingClientRect();
                if (event.clientY < r.top + r.height / 2) { index = i; break; }
            }
            const before = index >= others.length ? tailOf(others) : others[index];
            if (before !== S.slot && S.slot.nextElementSibling !== before) root.insertBefore(S.slot, before);
        }

        /** Near the top or bottom edge the page scrolls under the lifted entry. */
        function tick() {
            if (!S) return;
            const h = window.innerHeight;
            const speed = S.y < EDGE_PX ? -SCROLL_MAX * (1 - S.y / EDGE_PX) : S.y > h - EDGE_PX ? SCROLL_MAX * (1 - (h - S.y) / EDGE_PX) : 0;
            if (speed !== 0) window.scrollBy(0, speed);
            raf = requestAnimationFrame(tick);
        }

        function end(how) {
            if (!S) return;
            const { entry, ghost, slot, origin } = S;
            cancelAnimationFrame(raf);
            if (how === 'drop') root.insertBefore(entry, slot);
            slot.remove();
            ghost.remove();
            entry.removeAttribute('data-lifted');
            entry.style.display = '';
            document.body.style.userSelect = '';
            S = null;
            if (how !== 'drop') return say(T.cancelled);

            const index = entries().indexOf(entry);
            if (index === origin) return say(T.cancelled);
            say(fmt(T.dropped, { title: titleOf(entry), place: index + 1, count: entries().length }));
            save(entry, origin, false);
        }

        // ---- the keyboard -------------------------------------------------------------------------------

        function liftKey(entry) {
            K = { entry, origin: entries().indexOf(entry) };
            entry.setAttribute('data-kb', '');
            say(fmt(T.lifted, { title: titleOf(entry), place: K.origin + 1, count: entries().length }));
        }

        function stepKey(delta) {
            const list = entries();
            const at = list.indexOf(K.entry);
            const to = Math.min(Math.max(at + delta, 0), list.length - 1);
            if (to === at) return;
            K.moving = true; // moving the node blurs its grip for a moment: that is no "Tab away"
            root.insertBefore(K.entry, delta > 0 ? list[to].nextSibling : list[to]);
            K.entry.querySelector('[data-grip]')?.focus({ preventScroll: true });
            K.moving = false;
            K.entry.scrollIntoView({ block: 'nearest', behavior: reduced() ? 'auto' : 'smooth' });
            say(fmt(T.moved, { place: to + 1, count: list.length }));
        }

        function endKey(how) {
            if (!K) return;
            const { entry, origin } = K;
            K = null;
            entry.removeAttribute('data-kb');
            if (how === 'drop' && entries().indexOf(entry) !== origin) {
                say(fmt(T.dropped, { title: titleOf(entry), place: entries().indexOf(entry) + 1, count: entries().length }));
                save(entry, origin, true);
            } else {
                if (how !== 'drop' && entries().indexOf(entry) !== origin) slotBefore(entry, origin, entries());
                say(T.cancelled);
            }
        }

        // ---- events ---------------------------------------------------------------------------------------

        function onDown(event) {
            if (S || K || event.button > 0) return;
            const entry = event.target.closest('[data-entry]');
            if (!entry || entry.parentElement !== root) return;
            // A zone entry (a stage) is lifted by its heading line only; its steps have their own lift.
            if (zone && (!event.target.closest(zone) || !entry.querySelector(zone))) return;
            if (zone && event.target.closest('button:not([data-grip]), a, select, input, textarea')) return;

            if (event.pointerType === 'mouse') {
                if (!event.target.closest('[data-grip]')) return;
                event.preventDefault();
                hold = { entry, x: event.clientX, y: event.clientY, how: 'mouse', id: event.pointerId };
            } else {
                hold = { entry, x: event.clientX, y: event.clientY, how: 'touch', id: event.pointerId, event };
                hold.timer = setTimeout(() => {
                    const h = hold;
                    hold = null;
                    if (h) lift(h.entry, { clientX: h.x, clientY: h.y, pointerId: h.id }, 'touch');
                }, HOLD_MS);
                entry.setAttribute('data-pressing', '');
            }
        }

        function onMove(event) {
            if (hold && event.pointerId === hold.id) {
                const moved = Math.hypot(event.clientX - hold.x, event.clientY - hold.y);
                if (hold.how === 'mouse' && moved > START_PX) {
                    const h = hold;
                    hold = null;
                    lift(h.entry, event, 'mouse');
                } else if (hold.how === 'touch' && moved > CANCEL_PX) {
                    clearHold();
                }
            } else if (S && event.pointerId === S.id) {
                place(event);
            }
        }

        function clearHold() {
            if (!hold) return;
            clearTimeout(hold.timer);
            hold.entry.removeAttribute('data-pressing');
            hold = null;
        }

        function onUp(event) {
            if (S && event.pointerId === S.id) end('drop');
            else if (hold && event.pointerId === hold.id) clearHold();
        }

        function onCancel(event) {
            if (S && event.pointerId === S.id) end('cancel');
            else clearHold();
        }

        /** While an entry is held lifted by touch, the page must not scroll (non-passive on purpose). */
        function onTouchMove(event) {
            if (S?.how === 'touch' && event.cancelable) event.preventDefault();
        }

        function onKey(event) {
            const grip = event.target.closest?.('[data-grip]');
            if (S && event.key === 'Escape') { event.preventDefault(); return end('cancel'); }
            if (!grip) return;
            const entry = grip.closest('[data-entry]');
            if (!entry || entry.parentElement !== root) return;
            if (zone && !grip.closest(zone)) return; // the grip of a step inside a stage belongs to the step lift

            if (K) {
                if (event.key === 'ArrowUp' || event.key === 'ArrowDown') { event.preventDefault(); stepKey(event.key === 'ArrowUp' ? -1 : 1); }
                else if (event.key === ' ' || event.key === 'Enter') { event.preventDefault(); endKey('drop'); }
                else if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); endKey('cancel'); }
            } else if (event.key === ' ' || event.key === 'Enter') {
                event.preventDefault();
                liftKey(entry);
            }
        }

        function onFocusOut(event) {
            // Leaving the grip with Tab drops where it is, so nothing stays half done.
            if (K && !K.moving && event.target.closest?.('[data-grip]') && !root.contains(event.relatedTarget)) endKey('drop');
        }

        const stopMenu = (event) => { if (S || hold) event.preventDefault(); };

        return {
            init() {
                root = this.$el;
                wire = this.$wire;
                ui.live = make('div', 'sr-only', { 'aria-live': 'polite', 'aria-atomic': 'true' });
                document.body.appendChild(ui.live);
                root.addEventListener('pointerdown', onDown);
                root.addEventListener('keydown', onKey);
                root.addEventListener('focusout', onFocusOut);
                root.addEventListener('contextmenu', stopMenu);
                window.addEventListener('pointermove', onMove);
                window.addEventListener('pointerup', onUp);
                window.addEventListener('pointercancel', onCancel);
                window.addEventListener('touchmove', onTouchMove, { passive: false });
                // A failed drop is handled here, calmly: no error dialog, no "page expired" confirm().
                offs.push(wire.$interceptRequest(method, ({ onError }) => { onError(({ preventDefault }) => preventDefault()); }));
            },
            destroy() {
                if (S) end('cancel');
                if (K) endKey('cancel');
                clearHold();
                offs.splice(0).forEach((off) => off?.());
                root?.removeEventListener('pointerdown', onDown);
                root?.removeEventListener('keydown', onKey);
                root?.removeEventListener('focusout', onFocusOut);
                root?.removeEventListener('contextmenu', stopMenu);
                window.removeEventListener('pointermove', onMove);
                window.removeEventListener('pointerup', onUp);
                window.removeEventListener('pointercancel', onCancel);
                window.removeEventListener('touchmove', onTouchMove);
                clearTimeout(noteTimer);
                Object.values(ui).forEach((node) => node?.remove());
            },
        };
    });
});
