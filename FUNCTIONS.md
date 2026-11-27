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
- **A deleted task is kept for 30 days, then removed for good.** The daily command `app:purge-deleted` (03:30) force-deletes
  only tasks whose `deleted_at` is more than 30 days old (a task deleted exactly 30 days ago is still kept); its day plans,
  parts and links go with it. Projects and groups are not soft-deleted, soft-deleted categories are left alone.
  `app:purge-deleted --dry-run` only counts. The Datenschutz page says so.
- A move goes to the bottom of the new list; an edit that keeps the list keeps the place. A date already in the past is
  only refused when it is changed.
- Rules live in `App\Services\Planning\ItemEditor`; the sheet and the toast in `App\Livewire\Shell\ItemEditor`.

## API and MCP in the new app (api parity, 2026-11-27)
What the app can do is possible over REST and MCP too; `docs/steps/api-parity.md` has the full audit and the open gaps.
- **Board order:** `board_position` is on every task, project and group (`ref` on cards: `project:3`). `POST /api/board/order`
  (`refs`) and `POST /api/board/top` (`ref`, „Nach oben holen“); MCP `set_task_order` (`ids` or `refs`) and `bring_to_top`.
  The old entry points (REST `tasks/reorder`, `set_task_order`, the old board's drops) write `sort_order` and move the board order too:
  the tasks swap the places they already hold, nothing else moves.
- **Crunch:** `POST /api/tasks/{id}/move` / `move_task` („Verschieben“: with `date` the hard deadline, without it the soft one a
  week on) and `POST /api/forecast/more-time` / `add_more_time` („Mehr Zeit“: one block of a pomodoro category within 14 days).
- **Projects and groups:** `DELETE /api/projects/{id}`, `DELETE /api/task-groups/{id}` (MCP `delete_project`,
  `dissolve_task_group`, confirmed by name): steps always stay as plain tasks. `PUT /api/tasks/{id}/bundle` / `set_task_bundle`
  moves a board task in or out; `position` on `PATCH /api/stages/{id}` / `update_project_stage` moves a stage in the roadmap.
- **Areas over MCP:** `list_areas`, `create_area`, `update_area`, `delete_area`; `area_ids` on `create_category`, `update_category`.
- **Settings:** `locale` and `nudge_times` (evening nudge per ISO weekday) on `GET/PATCH /api/me`, `get_settings`, `update_settings`.
- `DELETE /api/tasks/{id}` and `delete_task` stay permanent (no 30 days, no undo); the docs page says so.
