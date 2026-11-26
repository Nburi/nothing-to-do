/**
 * Popovers that stay inside the screen (docs/steps/ui-fixes.md).
 *
 * One Alpine helper for every ⋯ menu and small dropdown of the shell (item menu, step menu, battery). The
 * panel is `position: fixed` and is placed from the trigger once it is open and measured:
 *  - it opens below the trigger when it fits there, otherwise above it,
 *  - when it fits on neither side it takes the larger side and scrolls inside (max-height),
 *  - it is shifted sideways until it is inside the viewport (8 px margin).
 * A scroll of the page (or of any panel that is not the popover itself) and a resize close it: a fixed
 * panel would hang loose from its trigger otherwise.
 *
 * Usage: `x-data="popover()"` on the wrapper, `x-ref="trigger"` on the button, `@click="toggle()"` on it,
 * `x-ref="panel"` and `:style="style"` on the panel. Anything may set `open = false` to close it.
 *  - `phone: true` (default) keeps the bottom sheet below 720 px: nothing is placed there, `style` stays empty.
 *    `phone: false` places the panel at every width (the battery chip has no sheet).
 *  - `align`: 'end' (the panel's right edge on the trigger's right edge, default) or 'start'.
 * The panel's width comes from its own classes; it must not be `position: absolute` or carry a top/left.
 */
const MARGIN = 8; // the least distance to every edge of the viewport
const GAP = 4; // between trigger and panel

/**
 * Where a panel of the given natural size goes next to a trigger rectangle inside a viewport: top, left and the
 * height it may take (less than its natural one when it has to scroll). Pure, so it is easy to reason about.
 */
export function placePopover(trigger, size, viewport, align = 'end') {
    const room = { below: viewport.height - trigger.bottom - GAP - MARGIN, above: trigger.top - GAP - MARGIN };
    const up = size.height > room.below && room.above > room.below;
    const height = Math.max(0, Math.min(size.height, up ? room.above : room.below));
    const left = align === 'start' ? trigger.left : trigger.right - size.width;
    return {
        top: up ? trigger.top - GAP - height : trigger.bottom + GAP,
        left: Math.max(MARGIN, Math.min(left, viewport.width - size.width - MARGIN)),
        height,
        up,
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('popover', ({ align = 'end', phone = true } = {}) => ({
        open: false,
        wide: ! phone,
        style: {},
        close: null,

        init() {
            this.$watch('open', (open) => (open && this.wide ? this.watch() : this.unwatch()));
        },

        destroy() {
            this.unwatch();
        },

        toggle() {
            if (this.open) { this.open = false; return; }
            this.wide = ! phone || window.matchMedia('(min-width: 720px)').matches;
            // Placed again as soon as it is measured (a microtask later, before the browser paints).
            this.style = this.wide ? { top: '0px', left: '0px' } : {};
            this.open = true;
        },

        watch() {
            this.unwatch();
            this.close = (event) => {
                if (event.type === 'scroll' && this.$refs.panel?.contains(event.target)) return;
                this.open = false;
            };
            window.addEventListener('scroll', this.close, { capture: true, passive: true });
            window.addEventListener('resize', this.close);
            this.$nextTick(() => this.place());
        },

        unwatch() {
            if (! this.close) return;
            window.removeEventListener('scroll', this.close, { capture: true });
            window.removeEventListener('resize', this.close);
            this.close = null;
        },

        place() {
            const panel = this.$refs.panel, trigger = this.$refs.trigger;
            if (! this.open || ! panel || ! trigger) return;
            // Measured without a height limit: scrollHeight is the whole content, the border sits outside of it.
            panel.style.maxHeight = 'none';
            const natural = panel.scrollHeight + (panel.offsetHeight - panel.clientHeight);
            panel.style.maxHeight = '';
            const p = placePopover(trigger.getBoundingClientRect(), { width: panel.offsetWidth, height: natural },
                { width: window.innerWidth, height: window.innerHeight }, align);
            this.style = { top: p.top + 'px', left: p.left + 'px', maxHeight: p.height + 'px', overflowY: 'auto' };
            panel.dataset.placed = p.up ? 'up' : 'down';
        },
    }));
});
