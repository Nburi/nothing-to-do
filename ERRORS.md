# Errors that may come back

Errors and surprises worth remembering, with the fix. Newest first.

## SQLite date columns hold two formats, so `whereColumn` / `=` on dates silently misses (2026-11-09)

**Symptom.** On a day paused in the Wochenplan, the Zeitplan still showed a block that had been
edited "this week only" through the event form. No error anywhere.

**Cause.** SQLite has no date type. A date written through Eloquent (`'date'` cast) is stored as
`2026-07-20 00:00:00`; a date written by a raw insert (`SchedulePause::pauseRange()`,
`ScheduleEvent::materializeRange()`) is stored as `2026-07-20`. `ScheduleEvent::scopeVisible()`
compared `schedule_pauses.date` with `schedule_events.date` via `whereColumn`, which is a plain
string comparison, so the two formats never matched and the pause did not apply to form-edited rows.

**Fix.** Compare through SQLite's `date()`: `whereRaw('date(schedule_pauses.date) = date(schedule_events.date)')`.
Regression test: `SchedulePauseTest::test_pause_hides_a_template_occurrence_that_was_saved_through_eloquent`.

**Rule.** Never compare two date columns (or a date column with a string) with `whereColumn` or `=` on
SQLite. Use `whereDate()` for a column against a value, and `date(a) = date(b)` for column against column.
(See also the `whereDate` entry in CLAUDE.md Known Issues.)

## Livewire tests no longer throw `ModelNotFoundException` (2026-11-06)

**Symptom.** After updating Livewire from 4.3.1 to 4.4.8, every ownership test that did
`$this->expectException(ModelNotFoundException::class)` before a `Livewire::test(...)->call(...)`
failed with "Failed asserting that exception ... is thrown" (19 tests).

**Cause.** Livewire 4.4 handles `ModelNotFoundException`, `AuthorizationException` and
`HttpException` inside component calls in tests the same way as in a real request: they become an
HTTP error response instead of being rethrown. The guards in the components still work; the data of
the other user stays untouched.

**Fix.** Assert the response instead of the exception:

```php
Livewire::test(TaskBoard::class)
    ->call('startFocusTimer', $foreignEvent->id)
    ->assertStatus(404);
```

Where it matters, also assert that the foreign row is unchanged (`assertDatabaseHas`).
