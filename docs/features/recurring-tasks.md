# Wiederkehrende Aufgaben

A task can repeat: **Täglich / Werktags / Wöchentlich / Monatlich** (`tasks.repeat_rule`, one of
`Task::REPEAT_RULES`). Set in the edit sheet ("Wiederholen" chip row) or via the REST API (`repeat_rule`).

- **Completing** a repeating task creates its next occurrence as a fresh task (`Task::syncRepeat()`): same
  title/list/notes/importance/duration/project/group, never flagged Heute. `repeated_from_id` (nullOnDelete)
  points the successor back at its origin.
- **Next date** (`Task::nextOccurrenceDate()`): counted from the task's own date when it has one (finishing
  Friday's task early still means "next Friday"), from today when it has none, and always the first
  occurrence *strictly after today* — a habit neglected for weeks returns as one fresh task, not a pile of
  overdue ones. Monthly uses `addMonthNoOverflow` (31.10. → 30.11.). Both `deadline` and `due_date` shift by
  the same number of days so their distance is kept; a task with neither gets the occurrence date as `due_date`
  so the repetition is visible.
- **Un-completing** removes the successor **only while it is untouched** (not completed). A finished successor
  is history and stays. Completing twice never creates two successors (`repeatSuccessor()->exists()` guard).
- Hooked at every place a completion can flip, next to `syncLinkedAgendaEntry()`: `ManagesTasks::toggleComplete`,
  `ManagesDeadlineItems::toggleDeadlineTaskDone`, `Progress::toggleStreakTask`, `TaskMutator::applyUpdate`
  (REST API + MCP `complete_task`/`reopen_task`/`update_task`). Kanban's column move reuses `toggleComplete`.
- Cards show a small loop icon (`partials/task-repeat-marker`, shared by the three card partials); a completed
  repeating card also says **"nächste: morgen"** — the moment you finish it is the moment you wonder when it
  returns. (The hint is `inline-block` so the completed title's line-through does not run through it.)

Not built (ideas): hiding a far-off occurrence until it is near ("Erscheint 2 Tage vorher"), custom intervals
("alle 3 Tage", "jeden 2. Montag"), "Wiederholen" in QuickCapture, `repeat_rule` on the MCP `create_task` /
`update_task` input schemas (the shared TaskMutator already handles it — only the tool schemas need the field),
skipping an occurrence without completing it.

Verification: `tests/Feature/RecurringTasksTest.php` (18 tests). No live browser pass (Chrome extension was
unresponsive) — an eyeball check of the chip row in the edit sheet and the card hint is still owed.
