/**
 * Week plan editor (refactor step 4.1): drawing, moving and the popover form of /board/week.
 *
 * - Draw: press on an empty spot of the time grid and drag to where the block ends. With a mouse
 *   it starts at once and may cross several day columns; on a touch screen it starts after a long
 *   press (350 ms) so a swipe still scrolls, and a short tap creates one hour from there.
 * - Move: drag a block (long press on touch) to another time or day; the form then opens with the
 *   new place filled in and asks "Nur diese Woche / Jede Woche" as usual. A click opens the form.
 * - The form is a popover next to the block (the user never scrolls down to it); when it does not
 *   fit beside it, it is centred, and below 720 px it is a bottom sheet.
 * - The pause sheet's calendar runs here (two taps, no round trip per tap).
 *
 * The server decides everything: this file only measures pointer positions and calls $wire.
 */
const HOLD_MS = 350;
const MOVE_PX = 5;
const DAY_MIN = 24 * 60;

document.addEventListener('alpine:init', () => {
    window.Alpine.data('weekEditor', (cfg = {}) => ({
        day: cfg.day ?? 0,
        drag: null,
        anchor: null,
        pause: { from: null, to: null, note: '', month: cfg.month },

        init() {
            // After layout (and again once fonts and styles settle), so the grid opens at the morning, not at midnight.
            this.$nextTick(() => requestAnimationFrame(() => this.scrollToMorning()));
            window.addEventListener("load", () => { if (this.$refs.scroller?.scrollTop === 0) this.scrollToMorning(); }, { once: true });
            this._touchMove = (e) => { if (this.drag && this.drag.active) e.preventDefault(); };
            window.addEventListener('touchmove', this._touchMove, { passive: false });
        },

        destroy() {
            window.removeEventListener('touchmove', this._touchMove);
        },

        snap: () => cfg.snap ?? 15,

        pxPerHour() {
            // One hour in pixels, from the grid's --ph (a hidden day column on the phone measures 0).
            const ph = parseFloat(getComputedStyle(this.$refs.scroller ?? this.$root).getPropertyValue('--ph'));
            return ph > 0 ? ph : 40;
        },

        scrollToMorning() {
            const box = this.$refs.scroller;
            if (!box) return;
            box.scrollTop = Math.max(0, (cfg.firstHour ?? 7) - 0.5) * this.pxPerHour();
        },

        minuteAt(colEl, clientY) {
            const r = colEl.getBoundingClientRect();
            const m = (clientY - r.top) / (r.height / 24) * 60;
            const s = this.snap();
            return Math.max(0, Math.min(DAY_MIN, Math.round(m / s) * s));
        },

        visibleCols() {
            return [...this.$root.querySelectorAll('[data-col]')].filter((el) => el.getBoundingClientRect().width > 0);
        },

        colAt(clientX, fallback) {
            for (const el of this.visibleCols()) {
                const r = el.getBoundingClientRect();
                if (clientX >= r.left && clientX <= r.right) return el;
            }
            return fallback;
        },

        hm(min) {
            const m = Math.max(0, Math.min(DAY_MIN - 1, min));
            return String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
        },

        // ── pointer handling ────────────────────────────────────────────
        down(e) {
            if (e.button > 0) return;
            if (e.target.closest('[data-pause-ghost]')) return; // a ghost is only clicked: neither drawn over nor moved
            const col = e.target.closest('[data-col]');
            if (!col) return; // drawing on a paused day is fine: one-offs stay on paused days
            const block = e.target.closest('[data-block]');
            const touch = e.pointerType === 'touch';
            const minute = this.minuteAt(col, e.clientY);

            this.drag = {
                id: e.pointerId, touch, col, x: e.clientX, y: e.clientY, moved: false, active: !touch,
                startCol: col, curCol: col, m0: minute, m1: minute, block: null,
            };

            if (block) {
                const top = Number(block.dataset.top), len = Number(block.dataset.length);
                this.drag.block = { el: block, kind: block.dataset.kind, id: block.dataset.id, key: col.dataset.key, top, len, grab: minute - top };
            }

            if (touch) {
                this.drag.timer = setTimeout(() => {
                    if (!this.drag) return;
                    this.drag.active = true;
                    if (!this.drag.block) this.drag.m1 = this.drag.m0 + 60;
                    navigator.vibrate?.(10);
                    this.paint();
                }, HOLD_MS);
            } else if (!block) {
                e.preventDefault();
            }
        },

        move(e) {
            const d = this.drag;
            if (!d || e.pointerId !== d.id) return;
            const far = Math.hypot(e.clientX - d.x, e.clientY - d.y) > MOVE_PX;

            if (!d.active) {
                if (far) this.cancel(); // a swipe: let the page scroll
                return;
            }
            if (!far && !d.moved) return;
            d.moved = true;
            d.curCol = this.colAt(e.clientX, d.curCol);
            d.m1 = this.minuteAt(d.startCol, e.clientY);
            this.paint();
        },

        up(e) {
            const d = this.drag;
            if (!d || (e && e.pointerId !== d.id)) return;
            clearTimeout(d.timer);
            this.clearGhosts();
            this.drag = null;
            const cancelled = e?.type === 'pointercancel';

            if (d.block) {
                if (cancelled) return;
                const b = d.block;
                this.anchor = b.el.getBoundingClientRect();
                if (!d.moved) { this.$wire.openBlock(b.kind, Number(b.id), b.key); return; }
                const start = Math.max(0, Math.min(DAY_MIN - b.len, d.m1 - b.grab));
                const s = Math.round(start / this.snap()) * this.snap();
                this.anchorFromGhost(d.curCol, s, s + b.len);
                this.$wire.openBlock(b.kind, Number(b.id), b.key, d.curCol.dataset.key, this.hm(s), this.hm(Math.min(DAY_MIN - 1, s + b.len)));
                return;
            }

            if (cancelled) return;
            let start, end, cols;
            if (!d.active || !d.moved) {
                // A click (mouse) or a short tap (touch): one hour from there.
                if (!d.active && !d.touch) return;
                start = Math.min(d.m0, DAY_MIN - 60);
                end = start + 60;
                cols = [d.startCol];
            } else {
                start = Math.min(d.m0, d.m1);
                end = Math.max(d.m0, d.m1);
                if (end - start < this.snap()) end = start + this.snap();
                cols = this.range(d.startCol, d.curCol);
            }
            this.anchorFromGhost(cols[cols.length - 1], start, end, cols[0]);
            this.$wire.openNew(cols.map((c) => Number(c.dataset.weekday)), this.hm(start), this.hm(Math.min(end, DAY_MIN - 1)));
        },

        // A short tap on touch ends here before the hold timer fired.
        tap(e) {
            const d = this.drag;
            if (!d || !d.touch || d.active || e.pointerId !== d.id) return;
            clearTimeout(d.timer);
            this.drag = null;
            if (d.block) { this.anchor = d.block.el.getBoundingClientRect(); this.$wire.openBlock(d.block.kind, Number(d.block.id), d.block.key); return; }
            const start = Math.min(d.m0, DAY_MIN - 60);
            this.anchorFromGhost(d.startCol, start, start + 60);
            this.$wire.openNew([Number(d.startCol.dataset.weekday)], this.hm(start), this.hm(start + 60));
        },

        cancel() {
            if (!this.drag) return;
            clearTimeout(this.drag.timer);
            this.drag = null;
            this.clearGhosts();
        },

        range(a, b) {
            const cols = this.visibleCols();
            const [i, j] = [cols.indexOf(a), cols.indexOf(b)].sort((x, y) => x - y);
            return i < 0 || j < 0 ? [a] : cols.slice(i, j + 1);
        },

        paint() {
            this.clearGhosts();
            const d = this.drag;
            if (!d || !d.active) return;
            const ph = this.pxPerHour();

            if (d.block) {
                const b = d.block;
                const start = Math.max(0, Math.min(DAY_MIN - b.len, Math.round((d.m1 - b.grab) / this.snap()) * this.snap()));
                this.ghost(d.curCol, start, start + b.len, ph, this.hm(start) + '–' + this.hm(Math.min(DAY_MIN - 1, start + b.len)));
                b.el.classList.add('opacity-40');
                return;
            }

            let s = Math.min(d.m0, d.m1), e = Math.max(d.m0, d.m1);
            if (e - s < this.snap()) e = s + this.snap();
            const cols = this.range(d.startCol, d.curCol);
            cols.forEach((c, i) => this.ghost(c, s, e, ph, i === 0
                ? this.hm(s) + '–' + this.hm(Math.min(e, DAY_MIN - 1)) + (cols.length > 1 ? ' · ' + (cfg.daysLabel ?? '%').replace('%', cols.length) : '')
                : ''));
        },

        ghost(col, s, e, ph, label) {
            const g = document.createElement('div');
            g.dataset.ghost = '1';
            g.className = 'pointer-events-none absolute inset-x-1 z-10 rounded-card px-1.5 py-1 font-mono text-[10.5px] font-medium text-accent-ink';
            g.style.cssText = `top:${s / 60 * ph}px;height:${Math.max(12, (e - s) / 60 * ph)}px;border:2px dashed var(--t-accent);background:color-mix(in srgb, var(--t-accent) 14%, transparent)`;
            g.textContent = label;
            col.appendChild(g);
        },

        clearGhosts() {
            this.$root.querySelectorAll('[data-ghost]').forEach((g) => g.remove());
            this.$root.querySelectorAll('[data-block].opacity-40').forEach((b) => b.classList.remove('opacity-40'));
        },

        anchorFromGhost(col, s, e, firstCol) {
            const r = col.getBoundingClientRect();
            const ph = r.height / 24;
            const left = (firstCol ?? col).getBoundingClientRect().left;
            this.anchor = { left, right: r.right, top: r.top + s / 60 * ph, bottom: r.top + e / 60 * ph };
        },

        // ── the popover form ────────────────────────────────────────────
        place(el) {
            const run = () => {
                if (!el.isConnected) return;
                el.style.left = el.style.top = '';
                if (!window.matchMedia('(min-width: 45rem)').matches) { el.dataset.placement = 'sheet'; return; }
                const vw = window.innerWidth, vh = window.innerHeight, gap = 12;
                const w = el.offsetWidth, h = el.offsetHeight;
                const a = this.anchor ?? { left: vw / 2, right: vw / 2, top: vh / 3, bottom: vh / 3 };
                if (h > vh - 2 * gap) { el.dataset.placement = 'center'; return; }
                let left = a.right + gap;
                if (left + w > vw - gap) left = a.left - gap - w;
                if (left < gap) left = Math.max(gap, Math.min(vw - w - gap, (vw - w) / 2));
                const top = Math.max(gap, Math.min(vh - h - gap, a.top));
                el.dataset.placement = 'float';
                el.style.left = left + 'px';
                el.style.top = top + 'px';
            };
            // Step 5.2: whoever opened the form gets the focus back when it closes (Escape, save, cancel).
            const opener = document.activeElement;
            run();
            const ro = new ResizeObserver(run);
            ro.observe(el);
            const onResize = () => run();
            window.addEventListener('resize', onResize);
            // Alpine runs these when the morph removes the form (it used to leak the resize listener).
            (el._x_cleanups ??= []).push(() => {
                ro.disconnect();
                window.removeEventListener('resize', onResize);
                const lost = !document.activeElement || document.activeElement === document.body;
                if (lost && opener && opener !== document.body && opener.isConnected) opener.focus({ preventScroll: true });
            });
            el.querySelector('[data-autofocus]')?.focus({ preventScroll: true });
        },

        // ── pause calendar ──────────────────────────────────────────────
        cells() {
            const [y, m] = this.pause.month.split('-').map(Number);
            const first = new Date(Date.UTC(y, m - 1, 1));
            const offset = (first.getUTCDay() + 6) % 7;
            return Array.from({ length: 42 }, (_, i) => {
                const d = new Date(Date.UTC(y, m - 1, 1 - offset + i));
                return { iso: d.toISOString().slice(0, 10), n: d.getUTCDate(), out: d.getUTCMonth() !== m - 1, label: d.getUTCDate() + ". " + (cfg.months?.[d.getUTCMonth()] ?? "") + " " + d.getUTCFullYear() };
            });
        },

        monthLabel() {
            const [y, m] = this.pause.month.split('-').map(Number);
            return (cfg.months?.[m - 1] ?? m) + ' ' + y;
        },

        shiftMonth(n) {
            const [y, m] = this.pause.month.split('-').map(Number);
            this.pause.month = new Date(Date.UTC(y, m - 1 + n, 1)).toISOString().slice(0, 7);
        },

        pick(iso) {
            const p = this.pause;
            if (!p.from || p.to) { p.from = iso; p.to = null; return; }
            if (iso < p.from) { p.to = p.from; p.from = iso; } else { p.to = iso; }
        },

        inRange(iso) {
            const p = this.pause;
            return p.from && iso > p.from && iso < (p.to ?? p.from);
        },

        isEdge(iso) {
            const p = this.pause;
            return iso === p.from || iso === p.to;
        },

        dayCount() {
            const p = this.pause;
            if (!p.from) return 0;
            return Math.round((Date.parse(p.to ?? p.from) - Date.parse(p.from)) / 864e5) + 1;
        },

        savePause() {
            const p = this.pause;
            if (!p.from) return;
            this.$wire.pauseRange(p.from, p.to ?? p.from, p.note).then(() => {
                if (!this.$wire.pauseOpen) this.pause = { from: null, to: null, note: '', month: cfg.month };
            });
        },
    }));
});
