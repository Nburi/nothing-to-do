# Api parity · the new app over REST and MCP

Built 2026-11-27 on `feature/api-parity` (from `refactor/refactor` at 57b2311, without the open PRs #49 and #50).
Three things Niels decided in the review: **MCP yes** (d39, the shared board order must move through MCP), the
note „API und MCP auffrischen, damit auch alles weiterhin in der neuen App möglich ist“ (d45), and **purge deleted
items after 30 days** (d42). Nothing in the new model is switched here: the raw-SQL reads of the old
`deadline`/`due_date` columns stay a separate later item (TODO, „1.1 follow-up“).

## 1. One board order for the old entry points (d39)

`BoardOrder::placeTasks($user, $ids)` takes task ids in their new order and lets the **open loose tasks** among them
swap the `board_position` they already hold, the same local rule as a drop on the new board (nothing else moves).
Steps of a project or group (they sit where their card sits) and finished or foreign tasks are skipped.

| Entry point | Before | Now |
|---|---|---|
| MCP `set_task_order` | `sort_order` only | `sort_order` **and** the board order; new `refs` (`task:12`, `project:3`, `group:4`) orders cards and tasks together |
| REST `POST /api/tasks/reorder` | `sort_order` only | the same |
| Old board drops (`reorder`, `reorderSimple`, `reorderEisenhower`, `reorderKanban`) | `sort_order` only | the same |

`sort_order` is still written, so the old screens read what they always read. The API now also **shows** the order:
`board_position` on every task, project and group, and a `ref` on every project and group card, and gets
`POST /api/board/order` (`refs`) and `POST /api/board/top` (`ref`, „Nach oben holen“); MCP `bring_to_top`.
A drop on the board adds the resting cards to a drop that contains a card (so a drop never changes which cards are
awake); the API call does not, it does exactly what it is told. A client that moves cards should send the whole column.

## 2. Audit: what the new app can do, and what REST and MCP say

`ok` = possible, `new` = closed in this PR, `gap` = not possible yet (see „Open gaps“).

| New app | REST | MCP |
|---|---|---|
| Capture (C, sheet, shortcut) | ok `POST /capture`, `POST /tasks` | ok `create_task(s)` |
| Sort the Inbox (list, size, area, dates) | ok `PATCH /tasks/{id}` | ok `update_task(s)` |
| Start / done / pause / stop a session, swap („Anderer Vorschlag“) | ok `/session/*`, `/suggestion/another` | ok `control_session`, `change_suggestion` |
| Battery (evening and today) | ok `PUT /evening/battery` | ok `change_suggestion` |
| „Wichtig“ marker | ok `is_important` | ok `is_important` |
| „Bis wann?“ offer (PR #50, not on this base) | ok: the offer only writes `soft_deadline` | ok: the same |
| Board order (drag, band, „Nach oben holen“) | new `POST /board/order`, `/board/top`, `board_position`, `ref` | new `set_task_order` (+`refs`), `bring_to_top` |
| Edit / move one item (4.9) | ok `PATCH /tasks/{id}` (same `TaskMutator` path) | ok `update_task` |
| Delete one item | different: `DELETE /tasks/{id}` and `delete_task` delete **for good**; the app soft-deletes and keeps it 30 days | different, same |
| Projects, stages, steps, note cards, place, „wartet auf“ | ok (4.3e) | ok (4.3e) |
| Delete a project or group (steps stay) | new `DELETE /projects/{id}`, `/task-groups/{id}` | new `delete_project`, `dissolve_task_group` (confirm by name) |
| Move a board task into / out of a project or group | new `PUT /tasks/{id}/bundle` | new `set_task_bundle` |
| Stage order in the roadmap | new `PATCH /stages/{id}` with `position` | new `update_project_stage` with `position` |
| Crunch: forecast, drop, shrink | ok | ok (`get_forecast`; drop and shrink = `update_task` list `later` / `size`) |
| Crunch: „Verschieben“ | new `POST /tasks/{id}/move` | new `move_task` |
| Crunch: „Mehr Zeit“ | new `POST /forecast/more-time` (undo: `DELETE /schedule-events/{id}`) | new `add_more_time` |
| Termine, repeating blocks, pauses | ok REST `schedule-events`, MCP `create/update_week_plan_block`, `pause_schedule`; REST has no pauses | ok |
| Termin colour (`termin_color`) | ok: `color` (the five palette keys) is mirrored into `termin_color` on every write; gap: „Standard“ cannot be set back | the same |
| Categories (work time, pomodoro switch) and their task link | ok | ok |
| Areas (tasks) and area ↔ category links | ok (`/areas`, `area_ids`) | new `list/create/update/delete_area`, `area_ids` on `create_category`, `update_category` |
| Reminders | ok | ok |
| Language and evening-nudge times | new `locale`, `nudge_times` on `GET/PATCH /me` | new `locale`, `nudge_times` in `get_settings`, `update_settings` |
| Ideas page (a „Später“ task, „Jetzt machen“, done) | ok: `GET /tasks?list=later`, `POST /session/start`, `is_completed` | ok |
| Purge of deleted items | n/a (server job) | n/a |

## 3. Open gaps (bigger, documented in TODO.md, not built)

1. **Evening reset** (`begin`, `close`, answering the battery inside the reset): no endpoint. `PUT /evening/battery` sets
   the battery, but a client cannot open or close the reset screen or see its stage. Needs a payload for `ResetStage`.
2. **Idea offer on the board** („Anderer Vorschlag“ for the free time, „Meins behalten“, its undo): `FreeTimeIdeas` has no
   endpoint, and the suggestion payload carries no idea. Needs a payload for `IdeaOffer`.
3. **Week plan, occurrence level**: change or cancel „this one / this and the following / all“, move one occurrence, bring a
   cancelled one back (`WeekPlanEditor::changeOccurrence`, `cancelOccurrence`, `moveOccurrence`, `restoreOccurrence`,
   `restoreGhost`). REST and MCP edit whole blocks and templates only. REST has no pauses and no week-plan blocks (MCP has).
4. **„Standard“ colour of a Termin**: `color` is mirrored to `termin_color`, so a Termin cannot be set back to „Standard“
   (`termin_color` null) through the API, and `termin_color` is not in the schedule-event payload. Needs a decision: a
   `termin_color` field (nullable palette key) on `POST/PATCH /schedule-events` and in the payload.
5. **Restore and trash**: `DELETE /tasks/{id}` and `delete_task` delete for good while the app keeps a deleted task for
   30 days. There is no trash view in the app yet, so there is nothing to restore through the API either. Decide with the trash view.
6. **To-do session** (4.7): the card is computed on the board (`TodoSession`); starting it is `POST /session/start`, but the
   card itself (the 25-minute set) has no payload.
7. **Sunday review** (`SundayReview`): MCP `get_week_review` still answers from the old `WeekReview`; REST has none.
8. **Capture tokens** (create, revoke) and **soft-deleted categories** (undo on the Categories page): UI only.
9. **Language of the API answers** stays German (TODO 5.1); not touched.
10. The switch of raw-SQL reads (`scopeBoardOrdered`, `DayPlanner`, API/MCP resources) to the new task columns is a
    separate later item (TODO „1.1 follow-up“).

## 4. Purge of deleted items (d42)

`php artisan app:purge-deleted` (daily at 03:30 in the scheduler) removes tasks whose `deleted_at` is **more than 30 days**
old. `--dry-run` only counts. Details and limits:

- A task deleted exactly 30 days ago is still kept (the cutoff is `now() − 30 days`, compared with `<`).
- Only `tasks.deleted_at` is touched. **Projects and groups are not soft-deleted** (`ProjectEditor::deleteProject` removes the
  row at once and keeps the steps as plain tasks), so there is nothing to purge for them. Soft-deleted **categories** are left
  alone: forcing one would set `category_id` to null on every past event of it (`nullOnDelete`), which is a separate decision (TODO).
- Tasks are removed one by one, so the database cascades run: day plans, work sessions and links of that task go with it;
  tasks that pointed at it (`waits_on_id`, `repeated_from_id`, a suggestion) lose the link and stay.
- No migration. The Datenschutz page now says a deleted task is kept for 30 days.

## 5. Decisions I made (please check)

- **Tasks only in the purge**, not projects, groups or categories (see above). Say so if the categories should go too.
- **`move_task` / `POST /tasks/{id}/move`: with `date` the hard deadline moves, without it the soft one.** The app asks
  for a „sure“ tick before a hard move; the API call with an explicit `date` is that intent.
- **`delete_project`, `dissolve_task_group`, `delete_area` need `confirm_name`** like `delete_task` needs `confirm_title`;
  the REST `DELETE` has no confirm (like `DELETE /tasks/{id}`).
- **`PUT /tasks/{id}/bundle` does not move a step straight from one project to another**: release it first (same rule as the
  app, where only a loose task can be pulled in).
- **Order through the API does not add the resting cards** (see 1).
- **A line in the Datenschutz page** (legal text): please read it.

## Tests

`SharedOrderEntryPointsTest`, `Api/ApiParityTest`, `Mcp/McpApiParityTest`, `Commands/PurgeDeletedTest`.
Automatic: A150 to A153. Niels: C140 to C142.
