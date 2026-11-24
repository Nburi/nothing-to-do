# FUNCTIONS

What the app can do and how it works. This file was listed in CLAUDE.md but did not exist; it starts here with
the board order and grows with every step. The step notes in `docs/steps/` stay the detailed source.

## Board order and the calm board (step 4.8)

- **One order for everything on the board.** Loose tasks, projects and groups share one order per user
  (`board_position`). Weiter oben = tends to be more important; the pick engine reads it last, after deadline and
  „Wichtig". A project or group is one slot with its next two steps.
- **Reorder:** drag the grip (desktop, appears on hover), press and hold a card or row (phone, 350 ms), or focus the
  grip and press Space, move with ↑ ↓, Space again to drop, Escape to cancel. A drop only reorders the entries of that
  column (or of the band); other columns keep their order. Saved at once; a failed save puts the entry back.
- **„Wichtig" band:** marked To-dos and Tasks stand in a band at the top of the board (right of the timeline on the
  desktop, in the tray on the phone) and leave their column. Marked To-dos that are in the To-do session card stay in
  the card. The „Wichtig" tab shows every marked task.
- **Calm board:** at most two project or group cards at once. More wait in „Weitere Karten · n ruhen" under their
  column and are not suggested. „Nach oben holen" puts one at the top; the lowest card on show then rests.
- New tasks, projects and groups go to the bottom of the order.
