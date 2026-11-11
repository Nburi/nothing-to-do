# Errors that may come back

Errors and surprises worth remembering, with the fix. Newest first.

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
