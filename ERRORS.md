# Errors that may come back

Errors and surprises worth remembering, with the fix. Newest first.

## A stored local instant reads back an hour off (2026-11-16)

**Symptom.** In step 3.4 the free-time idea changed on every reload and "Andere" ran out after one tap.
`evenings.idea_slot_until` never matched the slot it was written for.

**Cause.** The slot's end was a `CarbonImmutable` in the user's timezone (Europe/Zurich). Eloquent writes a
datetime as its wall clock (`Y-m-d H:i:s`) without converting it, and reads it back as UTC (the app timezone),
so 00:00 Zurich came back as 00:00 UTC, one hour later.

**Fix.** Convert to UTC before writing (`$slot->until->utc()`), like every other timestamp of the evening row,
which are written from `CarbonImmutable::now()`. Rule: never hand a local-zone Carbon to an Eloquent datetime
column; compare instants with `getTimestamp()`.

## A push command crashes every tick on a server without VAPID keys (2026-11-16)

**Symptom.** `php artisan app:send-evening-nudges` on the dev worktree failed with
`[VAPID] Public key should be 65 bytes long when decoded.` before looking at a single user.

**Cause.** `AppServiceProvider` builds `WebPush` with the VAPID keys from `.env`; when they are empty,
building `PushNotifier` throws. Injecting it into `handle()` therefore breaks the whole command, even when
nobody is due. (The older push commands have the same shape.)

**Fix.** Resolve `PushNotifier` only when a push is actually sent, inside the guarded block
(`EveningNudge::sendIfDue`). Regression test in `NudgeSchedulerTest`.

**Rule:** never inject `PushNotifier` into a scheduled command's `handle()`; resolve it at send time.

## `npm run build` in a worktree: "Can't resolve 'tailwindcss'" (2026-11-16)

**Symptom.** The build stopped at `resources/css/app.css` with `Can't resolve 'tailwindcss'`.

**Cause.** The worktree's `node_modules` was installed before the Tailwind 4 move (step 2.1) and still had
`tailwindcss` 3.4; `package-lock.json` says 4.3.5.

**Fix.** `npm ci` in the worktree (installs exactly the lockfile; no package changes). After switching to a
branch with a changed `package-lock.json`, run `npm ci` before building.

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

## Vite build: "`@layer base` is used but no matching `@tailwind base` directive" (2026-11-09)

**Symptom.** After the move to Tailwind 4 (step 2.1), `npm run build` in a git worktree failed with a
Tailwind 3 PostCSS error, although the project no longer has a PostCSS config.

**Cause.** Vite looks for `postcss.config.js` in parent folders. A worktree lives inside the main checkout
(`.claude/worktrees/…`), so it found the main checkout's Tailwind 3 config.

**Fix.** `vite.config.js` sets `css: { postcss: {} }`, so Vite never searches upwards. Tailwind 4 runs as
the `@tailwindcss/vite` plugin and needs no PostCSS config.
