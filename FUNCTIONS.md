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

## Editing, moving and deleting single items (step 4.9)

- **The ⋯ button** on every row of a list (Inbox, To-dos, Tasks, Später, the „Wichtig" band, the phone's list sheets, the
  session peek) and on every card of the Ideen page. Desktop: shows on hover or focus; touch: always there.
  Menu: **Bearbeiten**, **Verschieben nach** (every other list), **Löschen**.
- **Bearbeiten** opens one sheet (card on the desktop, bottom sheet on the phone) with title, list, size, hard and soft
  date, area, first step and notes. Enter in the title saves, Esc closes. Later has no hard date.
- **Every change leaves a toast with „Rückgängig"** (10 s): a save, a move and a delete. Undo puts the item back exactly,
  also its place in the list. There is no confirm dialog.
- **Delete is a soft delete** (`tasks.deleted_at`): day plans and parts stay, so undo loses nothing. The task of a running
  session cannot be deleted. The REST API and MCP `delete_task` still delete for real.
- A move goes to the bottom of the new list; an edit that keeps the list keeps the place. A date already in the past is
  only refused when it is changed.
- Rules live in `App\Services\Planning\ItemEditor`; the sheet and the toast in `App\Livewire\Shell\ItemEditor`.

## Board follow-ups (step 5.4)

- **The work block under the To-do session card.** Below the card the board lists what the engine plans for the rest of
  today's work block (the pomodoro block running now, else the next one today): session after session, the tasks of the
  pick engine's ranking filled into the block's 25 minute slots, with the time of each session and the minutes of each
  task. The heading folds the list. Tasks that do not fit today are counted ("+ n weitere …"). No work block left today:
  no list. Same ranking as the pick (area filter, category link, only the steps the board shows), always at battery Okay.
- **Stages by drag.** On a project page a stage's heading has a grip next to the arrow buttons: drag it with the mouse,
  press and hold the heading on the phone, or Space / ↑ ↓ / Space on the grip. The arrow buttons stay. Same saving, same
  "Rückgängig" and same release of contradicting „wartet auf" links as the buttons.
- **Category links.** In Einstellungen › Kategorien every work-time category can be pointed at a project or a group
  ("Nächste Schritte vorschlagen aus"). In that category's blocks its next steps are suggested first, after everything with
  a deadline and ahead of „Wichtig"; a linked step is also found when its area is not one of the category's. Old links to a
  project or group from the old app work as they are; Agenda links become a text hint.
