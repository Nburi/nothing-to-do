# ui-fixes: ⋯ menus stay on screen, delete in the header menu, scrollbar gutter

Branch `feature/ui-fixes`, based on `refactor/refactor` (57b2311). Three findings of Niels after the PR round (tests C48/C49 and
decision d-scroll-jump). No data, no migration, no new `.env` key.

## 1. Popovers flip upward (`resources/js/popover.js`)

One Alpine helper, `popover({ align, phone })`, replaces the three hand-made placements (the item menu guessed a 250 px height,
the step menu and the battery chip hung `absolute` under their trigger and were cut off at the bottom of the screen).
The panel is `position: fixed` and, once open, is measured and placed from the trigger:
below when it fits, otherwise above; on neither side it takes the larger side and scrolls inside (`max-height`); sideways it is
pushed into the viewport (8 px margin). A scroll of the page (or of any panel but the popover) and a resize close it.
`placePopover()` holds the arithmetic and is exported.

Used by: `shell/item-menu` (rows, Ideen cards), `shell/step-menu` (project and group steps), `shell/battery` (`phone: false`:
no sheet, placed at every width), `shell/owner-menu` (new, see 2). Below 720 px the bottom sheets are untouched (`style` stays empty).
Everything else closes by setting `open = false`; the helper watches `open`, so no menu has to call a hide method.
Not changed: the `#tag` suggestion list under the note field (short) and the week editor's own block popover (it already
places itself and centres when it does not fit).

## 2. Delete moved into the header menu

A ⋯ button (`shell/owner-menu`) sits next to „Umbenennen“ in the header of a project and of a group page. Its one entry,
„Projekt löschen“ / „Gruppe löschen“, calls the same `askDelete` as before; the button at the end of the page is gone. The
confirm (`shell/confirm-delete`) is capped at `max-w-md` (28rem) at every width and centred: a card from 720 px, a bottom sheet
below, never a full-width bar on a narrow desktop window. „Behalten“ stays first in the DOM; the delete rules, the redirect and
the notice are untouched (see 4.3d).

## 3. Scrollbar gutter (decision d-scroll-jump)

`html { scrollbar-gutter: stable; }`. With classic scrollbars the content was 10 px narrower while the document overflowed
(the fold made it overflow for a moment), so centred cards moved. Now the room is always kept; the page is the same width
with and without a scrollbar. Phones and overlay scrollbars are unaffected. Side effect: a thin empty strip on the right of
short pages in a desktop window with classic scrollbars (C122 asks Niels).

## Checks

A130 (`UiFixesTest`), B60–B62 (browser), C120–C122 (Niels).
