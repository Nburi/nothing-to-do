# Errors

Errors that may come back, and how they were fixed.

## SQLite date columns hold two formats (2026-11-08)

**Symptom:** a paused day still showed a block that had been edited "this week only" in the form.

**Cause:** SQLite has no real DATE type. A raw insert (`SchedulePause::pauseRange()`,
`ScheduleEvent::materializeRange()`) stores `2026-11-10`; a write through Eloquent with a `date` cast
stores `2026-11-10 00:00:00`. `ScheduleEvent::scopeVisible()` compared the two columns as strings, so
the pause did not match an occurrence the form had saved.

**Fix:** compare by calendar day: `whereRaw('date(schedule_pauses.date) = date(schedule_events.date)')`.
Found by the property test in `tests/Feature/Refactor/WeekPlanEditorTest.php`, which checks after random
edit sequences that the new `WeekPlanReader` and the old screens show the same blocks.

**Rule:** never compare date columns with `whereColumn` or `=` on SQLite. Use `whereDate()`, the
`forRange()` / `forDay()` scopes, or `date(...)` on both sides.
