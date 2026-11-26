# FUNCTIONS

What the app can do and how it works. This file was listed in CLAUDE.md but did not exist; it starts here with
the board order and grows with every step. The step notes in `docs/steps/` stay the detailed source.

## Deadline flow (decisions d-bis-wann, d-stage-soft, d-reset-pause; notes in `docs/steps/deadline-flow.md`)

- **„Bis wann?" after marking „Wichtig".** Marking a task that has no date of its own (no hard, soft or stage date, not in
  Later) shows one quiet card: Heute · Morgen · Diese Woche · Ohne Datum. A date is written as the *soft* deadline;
  „Rückgängig" takes it back. It is a bottom sheet on the phone and a small floating card on the desktop, never modal, gone after
  14 s, and works in board and session mode. **Asked once per task, ever** (`tasks.deadline_asked_at`). „Diese Woche" is the
  Sunday and is left out when it would repeat Heute or Morgen.
- **A stage's target date is a soft deadline for its open steps.** The planning engine (pick, start-by, crunch forecast)
  uses the earlier of the step's own soft date and its stage's target date. Derived, never written to the step; moving
  the stage date moves the steps. The hard deadline is not touched.
- **„Abschalten" by hand pauses the pomodoro.** Started while work runs, the timer waits for the ten minutes and runs on
  afterwards (time up, „Schon fertig", a stopped session and a reload are all covered). A break and a pause the
  user made are left alone.

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
