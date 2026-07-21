# nothing-to-do — Project Guide (`CLAUDE.md`)

> This is the central document for the project. It is kept current at all times.
> If you solve a problem that could recur, document it under **Known Issues & Solutions**.

---

## 1. What is `nothing-to-do`?

A **personal productivity system** for a single user (not a team tool). It is built around
the **"3 Things" framework**, which sorts work into three types by size and shape:

- **To-Do** — a small task; several can be cleared in one work session.
- **Task** — a larger thing, but still a single work step.
- **Project** — a container for non-urgent, multi-part work. Built: a fourth **Projekte** list shows
  one card per project (name + next task + progress), and each card opens a dedicated project page.

Incoming items land in an **Inbox** and get triaged into **To-Dos** or **Tasks**. Each item can
be flagged **today** (focus for the day), **important**, and given a **deadline** (hard, external)
and/or a **due date** (soft, self-imposed).

The product goal is **speed and calm**: fast capture, minimal clicks, a clear overview, no feature bloat.
The app should feel reliable and quiet enough to be used every single day.

**Target user:** a Swiss upper-secondary student and competitive orienteering athlete. One account, his own tasks.

---

## 2. My role & working standard

I act as an **experienced senior web developer** with a strong sense for exceptional user experience.
I care about: fine detail (hover states, timing, feedback), rich and fluid interactions (gestures,
transitions), clean and maintainable code, and pragmatic quality — I will spend extra effort when it
measurably improves the user's experience.

I am a **co-creator, not just an implementer.** If I see a better solution than the one described,
I say so, with reasoning.

---

## 3. Global rules (apply for the entire project lifetime)

1. **Git commits** — commit independently and sensibly after each meaningful step (setup, a finished
   feature, a bugfix). Commit messages in English, precise. **Never push** — the user does that.
2. **Git branches** — if you implement a new big feature or a bugfix work on a feature / bugfix branch, never directly on `main`/`master`. Also check if a branch already exists.
   **Merge only** when the user tells you with "finish", "fertig", "deploy" or "merge". Merge with a descriptive message, that it doesn't just say "Merge branch 'feature/task-inline-actions'".
3. **Automatic error checking** — after every change, check the code for errors (linting, compilation,
   `php artisan` checks, tests). Fix what is found before moving on.
4. **Two failures = stop** — if a command or action fails **twice in a row in the same session**, stop
   immediately. Describe (a) what I did, (b) why, (c) what failed and the exact error message. Then wait
   for instructions. Do not keep guessing.
5. **Stack changes** — before adding or removing any dependency, package, tool, or framework, **ask the
   user first**, with a short justification.
6. **To-do list** — keep a visible, current to-do list throughout the session so the user always knows
   what is done, in progress, and upcoming.
7. **Deployment notes** — whenever something must be done manually on the Linux production server
   (migrations, `.env` variables, new dependencies, cron jobs), tell the user explicitly with a clear,
   numbered checklist.
8. **Maintain this file** — keep `CLAUDE.md` current. Document solved problems under *Known Issues*.
9. **No `confirm()` for destructive actions** — never use `confirm()`, `window.confirm()`, Livewire's
   `wire:confirm`, or any other blocking browser dialog to confirm a delete/remove action. Always use
   the double-click "armed" pattern instead (click once arms the button — red background, 2s timeout,
   resets on outside-click/Escape — click again within that window to actually delete). See *Known
   Issues* for the exact Alpine snippet.
10. Call me by my name, every time I ask something or give you a task.

---

## 4. Technical context

- **Local development:** Windows (Claude Code runs locally on Windows).
  - PHP is a standalone install at **`C:\php\php.exe`** (currently 8.5.8), with `C:\php\php.ini`
    hand-configured (copied from `php.ini-development`; `extension_dir` set to `C:\php\ext`;
    `curl/fileinfo/gd/intl/mbstring/openssl/pdo_sqlite/sqlite3/zip` enabled — none are on by default
    in a fresh Windows PHP build, and Laravel needs all of them). Composer lives as `composer.phar`
    under `~/.config/herd-lite/bin` — run it through the standalone PHP above, not herd-lite's own
    bundled PHP (that one's stuck on 8.4.0, too old for this project's locked deps; see *Known
    Issues*). Node/npm are at `C:\Program Files\nodejs\`.
  - None of these are reliably on a fresh shell's live PATH (a just-installed tool needs a process
    restart to pick up its PATH entry) — prefer full paths over bare `php`/`composer`/`npm`/`node`
    until confirmed with `Get-Command`.
  - Run PHP/Composer/Artisan/npm commands via **PowerShell**; use Bash for git.
- **Production server:** Linux.
- **Deployment:** the user pushes source to GitHub and pulls it onto the Linux server. Any change to the
  stack or infrastructure ships with a full deployment checklist (see §9).
- **Framework:** Laravel (PHP) — fixed, non-negotiable base.
- **Authentication:** user accounts are required; each user sees only their own data.

---

## 5. Tech stack

- **Framework:** Laravel 13.15 (PHP 8.4)
- **Interactivity:** Livewire 4 (server-driven). Alpine ships **inside** Livewire — never import a
  second copy (double-init). `resources/js/app.js` adds only SortableJS + the swipe component.
- **Auth:** Laravel Breeze (Blade stack), fully restyled.
- **API auth:** Laravel Sanctum (personal access tokens) — powers the token-authenticated JSON API used by
  Apple Shortcuts and other integrations; see §7 "API (Apple Shortcuts)".
- **Push notifications:** Web Push (VAPID) via `minishlink/web-push` — a `push_subscriptions` table, a
  service-worker `push`/`notificationclick` handler, and two per-minute scheduled commands drive
  notifications that arrive even with the browser fully closed; see §7 Schedule "Notifications".
- **Drag & drop:** SortableJS (`window.boardSortable`).
- **Swipe gestures:** hand-rolled Pointer-Events Alpine component (`swipeCard`) — no library.
- **Styling:** Tailwind CSS **v3** (PostCSS). Topografie tokens are CSS custom properties (space-separated
  RGB channels) so one `prefers-color-scheme` media query flips the whole "map" day↔night and Tailwind
  opacity modifiers (`bg-paper/85`) still work. Font: self-hosted **Space Grotesk** (Fontsource).
- **Database:** SQLite (development), MySQL (production-ready).
- **Build:** Vite 8. **Tests:** PHPUnit (272 tests).
- **PWA:** installable from Chrome/Edge — `public/manifest.json`, generated icons (`public/icons/`,
  via `php artisan icons:generate`, see §7), a service worker (`public/sw.js`) caching the app shell
  with a custom offline page (`public/offline.html`), registered from `resources/js/app.js`.

> Note: Breeze converted the project from Laravel 13's default Tailwind v4 to v3 (config files + v3
> package). We standardized on v3 (its working setup). `@tailwindcss/vite@4` lingers unused in
> package.json — harmless, safe to remove later.

---

## 6. Requirements

See **`docs/REQUIREMENTS.md`** for the full, structured requirements (data model, sort order,
interactions, desktop & mobile layouts, accounts, future Projects extension).

---

## 7. Architecture

### Models
- **`User`** (Breeze) `hasMany` **`Task`**, **`Project`**, **`ScheduleEvent`**, **`EventTemplate`**,
  **`EventCategory`**. Carries the Pomodoro rhythm settings; `pomodoro()` returns the rhythm array
  (`work/short_break/long_break/long_every`), consumed by `PomodoroCycle` and any category's focus timer.
  `pomodoro_autostart` (bool, default `false`) governs whether a phase transition *after* the first
  (always-manual) work session continues on its own or freezes awaiting a manual continue — see
  `ScheduleEvent::pomodoroPhaseNow()` below. `notify_event_start`/`notify_pomo_start`/`notify_break_start`
  (bools, default `false`) independently gate the three browser-notification triggers (Settings'
  Benachrichtigungen card) — see §7 Schedule "Notifications". Also carries manual timezone settings —
  `timezone_offset` (a plain UTC-offset integer entered by the
  user, e.g. `+1`, not an IANA zone; defaults to `0` so an unconfigured account behaves exactly like the
  server clock) and `timezone_auto_dst` (adds +1 hour automatically while European DST is active, detected
  by borrowing `Europe/Zurich`'s own transition dates via `DateTime::format('I')` rather than hand-rolled
  EU rules). `localNow()`/`localToday()` return the shifted "now"/calendar day; every wall-clock-sensitive
  read (task/project deadline buckets, the Zeitplan's "today", the completed-task reset window) goes
  through these instead of a bare `now()`/`Carbon::today()` (which read the server's UTC clock and would
  otherwise silently misplace "today" near midnight local time). The Pomodoro countdown itself
  (`pomodoroPhaseNow`) deliberately keeps using the raw, unshifted `now()` — it's a pure elapsed-time diff
  against `pomodoro_started_at`, so a timezone shift would cancel out at best and corrupt the countdown at
  worst if applied inconsistently. Configured in **Settings**' Zeitzone card (`saveTimezone()`).
- **`Project`** — `user_id, name, brainstorm, external_url, sort_order, timestamps`. `hasMany Task`; `activeTasks` is the ordered
  uncompleted working set. `externalServiceName()` detects the service label from the URL (Jira, GitHub, Linear, etc.).
  Scopes: `forUser`, `ordered`.
- **`Task`** — `user_id, title, list, project_id, is_today, is_important, deadline(date), due_date(date),
  is_completed, completed_at, sort_order, timestamps`. See `docs/REQUIREMENTS.md` §2 for field meaning.
  - `list` is a **string** (`inbox|todos|tasks|projects`), not a DB enum. `BOARD_LISTS` are the three
    drag/quick-add columns; a task in the `projects` list also carries a `project_id` and lives on its
    project page (never on the main board — see the `onBoard` scope = `project_id IS NULL`).
  - Scopes: `forUser`, `active`, `inList`, `onBoard`, `boardOrdered` (important → due within 4 days → manual order).
  - Deadline logic lives on the model: `effectiveDate()` = `deadline ?? due_date`, `isUrgent`, `isOverdue`,
    `effectiveDateLabel` (heute/morgen/weekday/d.m./überfällig).
  - Today focus is plain `is_today` — no decoupled planning field.
- **`EventCategory`** — `user_id, name, color, pomodoro_enabled, sort_order`. A reusable, user-configured
  category (Schule/Training/Arbeiten/Abmachen by default). `hasMany` `ScheduleEvent` and `EventTemplate`
  (both `nullOnDelete` — deleting a category leaves existing blocks intact, falling back to their stored
  title/colour snapshot). Scopes `forUser`, `ordered`. Managed via **Settings**' Kategorien card.
- **`ScheduleEvent`** — `user_id, template_id?, category_id?, title?, color, date, start_time, end_time,
  is_cancelled, pomodoro_started_at?, pomodoro_phase?, pomodoro_cycle, timestamps`. Every event is either a
  **Termin** (free-text `title`/`color`, `category_id` null) or a **Kategorie** block (`category_id` set).
  `isAppointment()` / `isCategory()` branch on that. `displayTitle()`/`colorToken()` prefer the **live**
  category name/colour, falling back to the `title`/`color` snapshot written at creation time if the
  category was later deleted. Times are `HH:MM` strings; `toMinutes/fromMinutes/startMinutes/endMinutes/
  durationMinutes`, `isActive/isPast/progress/secondsRemaining(now)` for the strip + timeline.
  `pomodoro_phase`/`pomodoro_cycle` are the **discrete, persisted** current Pomodoro phase — `null` phase
  means never started (a manual "Start" tap is required for the first session, always — reaching the
  block's scheduled time never starts it automatically). `pomodoro_started_at` (nullable timestamp) is the
  start of the *current* phase: non-null while ticking, `null` while frozen awaiting a manual continue
  (phase/cycle stay put in that frozen state, so `PomodoroCycle::next()` can tell what a continue would
  start). `pomodoroPhaseNow(now, rhythm, autostart)` reads this state: if frozen, returns
  `awaiting_next: true` plus `next_phase`/`next_cycle`; if running, returns `remaining_seconds`/
  `total_seconds`, and — only when `$autostart` is true — self-heals by cascading forward through however
  many phases have fully elapsed (via `PomodoroCycle::next()` in a loop) in case the client's local timer
  never fired the transition (backgrounded tab, etc.); with autostart off it never cascades — a finished
  phase just clamps to `remaining_seconds: 0` until something explicitly writes the next phase. Scopes
  `forUser/visible/forDay/forRange/ordered`. `materializeRange()` fills recurring-template occurrences for a
  date range — **idempotent and delete-safe** (skips any (template,date) that already has a row, including
  an `is_cancelled` tombstone), carrying the template's `category_id` through.
- **`EventTemplate`** — `user_id, category_id?, name, color, duration, default_start?, is_recurring,
  recurrence(ISO weekday mask "1,2,3,4,5"), sort_order`. Reusable Termin/Kategorie blueprint; `occursOn(date)`,
  `displayName()`/`colorToken()` (same live-vs-snapshot preference as `ScheduleEvent`). A recurring
  Termin/Kategorie *is* a recurring template that materialises.

### Routing & "controllers"
- `/` → board if authed, else the landing (`welcome`). `/app` → `TaskBoard` (auth). `/dashboard` →
  redirect to `/app` (Breeze's post-login target). Profile + Breeze auth routes.
- There is **no task controller** — the `TaskBoard` Livewire component *is* the controller. Every mutation
  resolves the task through `auth()->user()->tasks()` (`userTask()` helper), so the frontend is never trusted.

### Frontend & state
- One full-page Livewire component, `App\Livewire\TaskBoard` (`#[Layout('layouts.app')]`), view at
  `resources/views/livewire/task-board.blade.php` (+ `partials/`). **Class-based** component (ASCII
  filename) — not Livewire 4's default emoji single-file component.
- **Server state** (the source of truth) lives in Livewire computed properties. **Ephemeral UI state**
  (active mobile tab, live swipe offset, drag-in-progress, open menus) lives in Alpine.
- Drag (`reorder`) persists the destination zone's full id order + its list/today. Swipe (`swipeIntent`)
  and the desktop click/checkbox/menu all call Livewire actions.
- **Quick actions on the card face** (`partials/task-card.blade.php` / `task-card-mobile.blade.php` /
  `project-task-card.blade.php`), for the mutations common enough to bypass the full edit sheet:
  - **Quick date**: no dedicated icon — the date badge itself is the button once a deadline/due-date is set.
    When neither is set, a faint "+ Termin" ghost placeholder takes its place (hover-revealed on desktop,
    always-on but muted on mobile — there's no hover there). Either opens a small Alpine popover with just
    the two date inputs, auto-saving on change via `ManagesTasks::quickSetDates()`. Deliberately *not* a
    third icon in the action row (pencil + delete only) — an icon-per-action there got cramped fast.
  - **Double-tap to edit**: tapping a task's title toggles `is_important` (unchanged); a *second* tap within
    320ms — tracked by a local `x-data="{ lastTap: 0 }"` wrapper, timed the same way `scheduleEvent.tap()`
    times a double-tap — also opens the edit sheet (`startEdit`). Deliberately built on the plain `click`
    event (already proven reliable here) rather than native `dblclick`: these title buttons don't set
    `touch-action: none`, so a real double-tap risks the browser's own double-tap-to-zoom intercepting a
    native `dblclick` before it ever fires. The two toggles this fires en route (once per tap) cancel out,
    so `is_important` ends up unchanged — a small, harmless side effect of reusing the tap that's already
    there instead of adding a new gesture surface.
  - **Mobile only:** a long-press on a task card (extends `swipeCard`'s `down()`/`move()`/`up()` with a 500ms
    timer, cancelled by any directional swipe lock) opens `partials/project-picker-sheet.blade.php` via the
    `Alpine.store('projectPicker')` store, calling the existing `TaskBoard::assignTaskToProject()` — the
    touch equivalent of desktop's drag-onto-a-project-card, since neither swipe direction was free to reuse.

### Projects (built)
- A fourth **Projekte** column (desktop) / 5th bottom-nav tab (mobile) lists `Project` cards
  (`partials/project-card.blade.php`): name + next active task + a `done/total` progress bar.
- A card opens **`App\Livewire\ProjectPage`** (`/app/projects/{project}`, `route('project.show')`):
  the project's tasks, a quick-add (creates `list=projects` tasks with `project_id`), a collapsible
  "Aus der Inbox hinzufügen" picker (`assignToProject`), rename + delete (delete releases active tasks
  back to the inbox), and per-task release (`removeFromProject`).
- The page has an **Aufgaben ⇄ Brainstorming** switch (Alpine, no round-trip). There is also an **external link** field
  (`project.external_url`): a URL chip below the header that links out to Jira, GitHub, Linear, etc. Added/edited via
  the `…` menu (triggers `editExternalLink`), removed with a `×` button next to the chip. **Brainstorming** is a
  per-project Markdown scratchpad (`projects.brainstorm` longtext): a read view that renders GitHub-
  flavoured Markdown via `Str::markdown` (`html_input=strip`, `allow_unsafe_links=false` — XSS-safe,
  no new dependency), and an edit view with a small formatting toolbar + auto-growing textarea.
  Notes autosave on Livewire sync (`updatedBrainstorm`); rendered output is styled by `.prose-topo`
  in `app.css`. Empty projects open straight into the editor for fast capture.
- Shared task mutations + the edit sheet live in the **`App\Livewire\Concerns\ManagesTasks`** trait
  (used by both `TaskBoard` and `ProjectPage`); the edit sheet markup is `partials/edit-sheet.blade.php`.
- Project tasks never appear on the main board or in Today (the `onBoard` scope filters them out, and
  `setToday` is a no-op for them).

### Cleanup (built)
- A full-screen swipe-stack triage mode at **`/app/cleanup`** (`route('cleanup')`, linked from the header
  next to "Zeitplan"): sorts the Inbox into To-Dos/Tasks, then passes over every active To-Do/Task to flag
  Today, add a deadline, and mark it important — one Tinder-style card at a time, with a full button
  fallback for every gesture. `App\Livewire\Cleanup` (`#[Layout('layouts.app')]`, `use ManagesTasks`,
  view `livewire.cleanup`) exposes two computed queues (`inboxQueue()`, `reviewQueue()` — the latter is
  *every* active on-board To-Do/Task, not just untagged ones) and two small mutation methods,
  `assignList()` (whitelisted to `todos`/`tasks`) and `markToday()`; `toggleImportant()`/`quickSetDates()`
  are reused as-is from `ManagesTasks`. "Weiter" (skip, no change) has no PHP method at all — it's a pure
  no-op by definition.
- Gesture map — right/left always commit-and-advance, down always defers, up (review only) opens a
  popover without advancing:

  | Phase | Right | Left | Down | Up |
  |---|---|---|---|---|
  | 1 — Inbox | → `list=todos` | → `list=tasks` | "später": requeue, no DB write | unused |
  | 2 — Review | `is_today=true` | "weiter": no change | "später": requeue, no DB write | deadline popover |

  "Wichtig" is a dedicated star button on the card face (`toggleImportant`), not a swipe direction.
- **State split**: the server (`inboxQueue`/`reviewQueue`) is the source of truth for task *content*,
  re-evaluated fresh on every Livewire round trip. Ordering, current phase, and "später" requeueing live
  entirely client-side in `Alpine.store('cleanup')` (`resources/js/app.js`), seeded once from
  `@js($this->inboxQueue->pluck('id'))` / `@js(...reviewQueue...)` via `x-init` on the page root
  (`resources/views/livewire/cleanup.blade.php`). **Gotcha:** Livewire re-morphs the component root on
  *every* action, which re-runs that `x-init` — without a guard this silently wipes the client-tracked
  order (and any pending "später" requeues) after every single swipe. The store's `init()` guards against
  this with a `seeded` flag, and additionally ignores Alpine's own automatic no-argument call to a store's
  `init()` (which fires once, before `x-init` ever runs, as soon as the store is registered) by checking
  that `cfg.inbox` is actually defined before treating a call as the real seed.
- A task committed into Todos/Tasks during phase 1 must reach phase 2's queue in the *same* session even
  though the review queue was conceptually seeded before phase 1 finished — `enqueueReview()` pushes the
  id into the client-side review order synchronously at the exact moment phase 1 commits it, not by
  waiting on a server re-query.
- **`Alpine.data('cleanupSwipeCard', cfg => {...})`** (`resources/js/app.js`) — one instance per card,
  modeled closely on the board's `swipeCard` (same pointer handling, mouse pointers excluded, same
  `threshold = max(64, round(dim*0.38))`/resistance/dead-side-damping math) but generalised from one axis
  to two: the first move past a small deadzone locks to horizontal or vertical, and direction is read from
  whichever of `dx`/`dy` is non-zero (mirroring `swipeCard`'s own simple `dir` getter — the other axis
  stays exactly `0` for the rest of the gesture once locked). Each configured direction resolves via a
  `kind`: `'commit'` (fly off, call the configured `$wire` method if any, remove from the queue for good),
  `'defer'` (später — continue off the same edge, no `$wire` call, just requeue to the back),
  `'popover'` (mirrors `swipeCard`'s existing `intent === 'menu'` case: must reach threshold, then springs
  back and opens the inline date popover without advancing), or `null` (dead side, resists and never
  commits). `trigger(dirName)` is the button-fallback entry point — it synthesises a past-threshold
  gesture and calls the same `resolve()` a real swipe would, so buttons and swipes are always in parity.
  Stack visuals come from `stackIndex` (`store.stackIndexOf(phase, id)`; `-1` means "not queued yet, hide
  it" — guards the moment a freshly-bridged id has no matching DOM node) driving `stackStyle` (index 0 =
  interactive top card; 1–2 = scaled/offset peeking cards, `pointer-events: none`; else hidden). The stack
  container uses the CSS-grid `[grid-area:1/1]` trick so all cards share one cell and the container
  auto-sizes to the tallest one.

### Schedule (Zeitplan) (built)
- **Zeitplan page** — `App\Livewire\Schedule` (`/app/schedule`, `route('schedule')`): a time-scaled vertical
  spine, **mobile = one day, desktop = the current week**, with day/week navigation. Times sit left of the
  spine, name/details right; the spine is tinted in the block's Topografie colour — a category's colour is
  resolved **live** (renaming/recolouring it in Settings repaints every block, past and future), a Termin's
  colour is fixed at creation. Recurring series are materialised on read.
- Every event is exactly one of two kinds: a **Termin** (free-text title + a fixed colour) or a **Kategorie**
  block (references an `EventCategory`; name/colour follow the category live). A category can optionally
  carry a **Pomodoro focus timer** (`pomodoro_enabled`).
- **Header strip** — `partials/schedule-strip.blade.php` in the board: a calm **Zeitstrahl** (filled mark =
  past, hollow = upcoming, partial-fill = active, mark length ∝ duration, red "now" line) when nothing is
  due. When a Pomodoro-enabled category block is active or starts within 5 min (`TaskBoard::focusSession`),
  the strip swaps to a **focus card** with three states, driven by `TaskBoard::focusPhase()` →
  `ScheduleEvent::pomodoroPhaseNow()`:
  - **Bereit** (`$phase === null`, never started) — a Start button. Reaching the block's scheduled time
    never auto-starts it; the *first* session is always a manual tap, unconditionally (the autostart
    setting below only governs what happens *after* this).
  - **Läuft** (`$phase['running']`) — the live countdown ring, fed by `remaining_seconds`/`total_seconds`.
    The ring's wrapper carries a `wire:key` keyed on `(event, phase, cycle)` plus `wire:poll.5s.visible`, so
    a poll that detects a phase change forces Alpine to tear down and reinitialise the ring with the new
    phase's config — a plain re-render wouldn't, since Alpine only evaluates `x-data` once per DOM node.
    While the running phase is a break, an "Überspringen" button (`skipBreak`) sits next to Stop.
  - **Bereit, awaiting a continue** (`$phase['awaiting_next']`) — shown when the previous phase finished and
    `pomodoro_autostart` is off: a static (non-ticking) card naming what's next (`next_phase`), with a
    continue button (`continuePhase`) and, if `next_phase` is a break, an additional skip button
    (`skipBreak`) to bypass it and jump straight to the next work session.
  `startFocusTimer` (ownership- and `pomodoro_enabled`-guarded) always sets `phase=work, cycle=1,
  started_at=now()` — the one unconditionally-manual entry point. `stopFocusTimer` fully resets
  (`phase=null, cycle=1, started_at=null`); ending a session always needs a fresh Start tap to resume.
- **Autostart vs. manual phase transitions** — `users.pomodoro_autostart` (Settings' Pomodoro card, toggle
  next to the rhythm fields, saved together via `saveSchedule()`). The client's local countdown
  (`focusTimer` in `app.js`) calls `TaskBoard::handlePhaseComplete($id)` the instant it reaches zero — the
  server re-checks elapsed time itself before acting (never trusts the client's clock blindly: a premature
  or duplicate call is a no-op). With autostart **on**, this immediately writes the next phase/cycle and
  restarts the clock (`transitionToNextPhase()`); with it **off**, it just clears `pomodoro_started_at`,
  freezing the ring at the just-finished phase until `continuePhase()` (the awaiting-state's button) writes
  the next phase manually. `TaskBoard::continuePhase()`/`skipBreak()` are the two manual-advance actions —
  `skipBreak` resolves "what's the current/upcoming phase" first (reading `next_phase` if frozen, else the
  live `pomodoro_phase`), bails if it isn't a break, then advances **twice** conceptually (break → the work
  cycle *after* it) so the break is bypassed entirely rather than just ended early.
- **`App\Services\PomodoroCycle`** — pure, stateless Pomodoro phase math, no elapsed-time cascading of its
  own (that self-healing loop lives in `ScheduleEvent::pomodoroPhaseNow()`, see above): `durationMinutes
  (phase, rhythm)` looks up one phase's length; `next(phase, cycle, rhythm)` returns what follows — a break
  after work (long when the finishing cycle's number is divisible by `long_every`), or work with `cycle+1`
  after any break. Both `TaskBoard`'s Pomodoro actions and `ScheduleEventController`'s API equivalents
  (`continue-focus`/`skip-focus-break`) call these directly rather than duplicating the phase-order logic.
- **`App\Services\TaskSuggestor`** — "what to work on" for the focus card, tiered by the *effective* current
  Pomodoro cycle number (`TaskBoard::taskSuggestion()` — while frozen awaiting a continue, "effective" means
  `next_phase`/`next_cycle`, not the just-finished ones, so the preview matches what a continue would
  start): cycle 1 nudges to clear the ToDos list (falling through if none are open), any cycle then prefers
  the top active **today** task (board order), and once today's list is empty it falls back to a
  deterministic pick between a project's next task and another todos/tasks-list task — seeded by
  `(event id, cycle)` via `crc32()` so the choice stays stable across the ring's 5s poll instead of
  reshuffling every request. Hidden whenever the effective phase is a break. Rendered by
  `partials/schedule-strip-suggestion.blade.php` in every focus-card state that has a work-phase context; a
  task suggestion opens the existing inline edit sheet (`startEdit`), a project suggestion links to its
  project page.
- **Notifications** — real **Web Push** (VAPID), delivered by the OS/browser even with the app's tab, and
  the whole browser, fully closed. Three independent per-type toggles still gate *which* moments push
  (`notify_event_start`, `notify_pomo_start`, `notify_break_start` on `User`; Settings' Benachrichtigungen
  card), all default `false` — but delivery itself no longer depends on any tab being open, since the
  server decides when to send.
  - **Subscribing** — Settings' Benachrichtigungen card has an Aktivieren/Deaktivieren control
    (`resources/js/app.js`'s `window.subscribeToPush(vapidPublicKey)` requests Notification permission,
    then `navigator.serviceWorker.ready` → `pushManager.subscribe({applicationServerKey: ...})`) that POSTs
    the resulting `{endpoint, p256dh, auth}` to two new Livewire actions on `App\Livewire\Settings` —
    `subscribeToPush()`/`unsubscribeFromPush()` — backed by **`App\Models\PushSubscription`**
    (`push_subscriptions` table: `endpoint`(text) + `endpoint_hash`(sha256, unique — MySQL can't uniquely
    index a raw `text` column) + `p256dh`/`auth_token`, `belongsTo User`, one row per device/browser).
    `PushSubscription::storeFor()` upserts by `endpoint_hash`, so re-subscribing the same browser refreshes
    its row instead of duplicating.
  - **Sending** — **`App\Services\PushNotifier`** wraps a config-driven `Minishlink\WebPush\WebPush`
    singleton (bound in `AppServiceProvider`, VAPID keys from `config/webpush.php`/`.env`): `notify(User,
    payload)` pushes to every subscription the user has and prunes any the push service reports as expired
    (410/404). Delivery is synchronous, no queue worker — this app's volume (a handful of subscriptions per
    user) doesn't warrant one. Any per-subscription failure that isn't a simple expiry (wrong VAPID config,
    a TLS/network failure, the push service rejecting the request) is logged via `Log::warning` — a
    completed report is not the same as a delivered notification, and this class of failure previously went
    completely unnoticed (see *Known Issues*, the Windows CA-bundle gap that caused exactly this).
  - **Debugging** — Settings' Benachrichtigungen card has a "Test-Benachrichtigung senden" control
    (`Settings::sendTestPush()` → `PushNotifier::sendDebug()`) that pushes to every device on the account
    right now, independent of the `notify_*` toggles, and reports success/failure **per device** inline
    (device label + HTTP status + failure reason) — the fastest way to tell "nothing is subscribed", "it's
    subscribed but delivery is failing", and "delivery works, so the gap is in the scheduler/timing" apart.
  - **Pomodoro phase starts** (`notify_pomo_start`/`notify_break_start`) — the transition logic that used to
    be duplicated between `TaskBoard` (Livewire) and `ScheduleEventController` (API) is now consolidated in
    **`App\Services\PomodoroSessionService`** (`start`/`stop`/`transition`/`skipBreak`/`handleTick`, all
    persist via the existing `PomodoroCycle` math and notify through `PushNotifier`, gated on the matching
    per-type flag). Both `TaskBoard` (resolved via `app(PomodoroSessionService::class)` — Livewire action
    methods are called positionally by Livewire's own dispatcher, not container method injection) and
    `ScheduleEventController` (real constructor/method injection) call through it, so Shortcuts-driven
    Pomodoro actions notify identically to tab-driven ones. Critically, a phase used to only ever advance
    because the client's local JS timer called `handlePhaseComplete()` — with no tab open, nothing did that.
    The scheduled command **`app:advance-pomodoro-phases`** (every minute, `bootstrap/app.php`'s
    `withSchedule()`) now ticks every active session server-side via `PomodoroSessionService::handleTick()`,
    which — unlike a single-step advance — **cascades** through however many phases have fully elapsed
    (carrying the real elapsed time forward, mirroring `ScheduleEvent::pomodoroPhaseNow()`'s own read-side
    self-heal loop), so a session left running unattended across a cron gap doesn't have its durations
    silently compressed. With `pomodoro_autostart` off it still just freezes (no notification), same as
    before.
  - **Any schedule event's start time** (`notify_event_start`) — also now fully server-driven: the scheduled
    command **`app:send-event-start-notifications`** (every minute) finds, per opted-in user, today's (in
    *that user's* local day) visible events with `notified_at IS NULL` whose absolute start instant —
    `ScheduleEvent::startInstantUtc(User)`, the inverse of `User::localNow()` (`utc = local − offset`) —
    has passed, sends a push, and stamps `notified_at`. Dedup is "already notified", not a sliding time
    window, so a delayed/missed cron tick still fires on the next run instead of losing the notification.
    `ScheduleEvent::withNotifiedReset(array $updates)` clears `notified_at` whenever `start_time` changes,
    wired into every write path that can move one (`ManagesSchedule::saveEventForm/moveEvent/resizeEvent`,
    `ScheduleEventController::update()`), so a rescheduled event is eligible to notify again at its new time.
  - **`public/sw.js`** has `push` (shows the OS notification) and `notificationclick` (focuses/opens the app)
    listeners alongside its pre-existing offline-caching handlers.
- **Shared mutations** live in **`App\Livewire\Concerns\ManagesSchedule`** (used by `Schedule`): create/edit/
  delete Termine and Kategorie blocks, drag-to-move (keeps duration), drag-to-resize (min-length guard),
  apply-template. The event form sheet (`partials/schedule-event-form.blade.php`) has a Termin/Kategorie
  toggle — Termin keeps the title input + 5-swatch picker, Kategorie shows a chip picker over the user's
  categories; the event card with the pointer gestures is `partials/schedule-event.blade.php`.
- **Quick-create footer** (`partials/schedule-category-footer.blade.php`) — a "Zeichnen:" row of category
  chips plus a "+ Termin" pill, all sharing one gesture: tap arms `$store.draw` (category id+colour, or a
  typed title+colour for a Termin), then a drag on the grid (`scheduleDraw` in `app.js`) draws the block and
  calls `quickCreateCategoryBlock()` or the mirrored `quickCreateTermin()` — no form, 2–3 gestures total.
  `$store.draw.active`/`clear()` cover both modes so arming one cancels the other. A **desktop template row**
  (mirroring the mobile-only one) sits above the grid and applies a template to today's date in one click via
  the existing `applyTemplate()` — no drawing needed, since a template already carries its own time/duration.
  The full "+ Termin" button/modal remains the precision path (exact date, custom colour, recurring series).
- **Gestures** are hand-rolled Alpine pointer components in `resources/js/app.js` (no new deps):
  `scheduleEvent` (move / resize / double-tap edit), `scheduleDraw` (quick-create, above), `focusTimer` (live
  countdown ring — also plays a short synthesised Web Audio chime when a phase's countdown reaches 0, no
  audio file/package, then calls `handlePhaseComplete` — see "Autostart" above), `eventStartNotifier` (see
  "Notifications" above). Desktop uses the hover pencil; mobile uses double-tap. `window.primeFocusAudio()`
  initialises/resumes the shared `AudioContext` on the Start button's `onclick` (a real user gesture), so the
  later automatic chime isn't blocked by autoplay policy.
- **Settings** (`App\Livewire\Settings`) has a Pomodoro section (work / short break / long break /
  sessions-per-long-break / autostart toggle, all via `saveSchedule()`), a Benachrichtigungen section
  (three independent toggles — `toggleNotifyEventStart()`/`toggleNotifyPomoStart()`/`toggleNotifyBreakStart()`,
  each saving immediately like the category Pomodoro toggle below, no separate submit; the card also has a
  client-only permission-request button gated on `Notification.permission`, not a server field), and a
  **Kategorien** card: add/rename/recolour/toggle-Pomodoro/delete, all ownership-scoped (`ManagesSchedule`'s
  and `Settings`' colour validation both read `ScheduleEvent::EVENT_COLORS` — a plain class constant, not a
  trait constant, since PHP forbids accessing a trait's own constant via the trait's name directly).

### API (Apple Shortcuts) (built)
- A token-authenticated JSON API (`routes/api.php`, `auth:sanctum`) covers every mutation the native app
  exposes, so it can be driven from Apple Shortcuts or any other automation — not a 1:1 mirror of every
  Livewire method, but full CRUD + state coverage (e.g. one `PATCH /tasks/{id}` covers toggle-complete,
  toggle-important, set-today, move-list, and assign/release-project, instead of five separate endpoints).
  Controllers live in `App\Http\Controllers\Api`, one per resource: `TaskController`, `ProjectController`,
  `ScheduleEventController` (+ `focus`/`start-focus`/`stop-focus`/`continue-focus`/`skip-focus-break` for the
  Pomodoro timer — the latter two mirror `TaskBoard`'s `continuePhase()`/`skipBreak()` for the same
  manual-advance/skip-a-break behavior over the API), `EventCategoryController`, `EventTemplateController`,
  `MeController` (account info, rhythm/autostart/notification/timezone settings, board counts). Responses
  are shaped by `App\Http\Resources\*Resource` classes.
- **Auth:** Laravel Sanctum personal access tokens, managed from **Settings → Shortcuts & API**
  (`App\Livewire\Settings::createApiToken()`/`revokeApiToken()`) — the plaintext token is shown exactly
  once at creation, never stored/re-displayed; revoke uses the same armed double-click pattern as every
  other destructive action in the app.
- **Docs:** `/docs/api` (`resources/views/docs/api.blade.php`, auth-gated, linked from Settings) — full
  endpoint reference plus a walkthrough for building Apple Shortcuts against it (the "Get Contents of URL"
  action's config, and five worked example Shortcuts).
- **Gotcha:** a controller's `store()` must return `$model->fresh()`, not the just-created in-memory model —
  columns with a DB-level `->default(...)` (e.g. `is_today`, `is_completed`, `is_cancelled`) are absent from
  the in-memory attribute bag until reloaded, so the first JSON response after creation would otherwise show
  `null` instead of the real default. Caught by an end-to-end curl smoke test, not by PHPUnit (the difference
  only shows up in the *first* response after an insert).

---

## 8. Conventions

- **Language:** code, comments, docs, and commit messages in **English**.
- **Branches:** `type/short-description` — e.g. `feature/task-board`, `refactor/...`, `redesign/...`.
- **Commits:** imperative English subject, body explains the *why* when not obvious.
- **PHP:** PSR-12, Laravel conventions. Models singular, tables plural.
- **Authorization:** never trust the frontend — every DB operation is scoped to the authenticated user.

---

## 9. Deployment process (Linux production)

This is a **full rebuild** (old Node.js app → Laravel). The first deploy is a fresh setup, not an update.

### First deploy (one time)
1. `git pull` (the rebuild lives on branch `redesign/from-scratch` until you merge it to `main`).
2. `composer install --no-dev --optimize-autoloader`
3. `cp .env.example .env` and set: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`,
   and the **MySQL** `DB_*` vars (or keep SQLite and create `database/database.sqlite`).
4. `php artisan key:generate`
5. Generate a VAPID key pair for Web Push (once — reuse the same pair on every future deploy, never
   regenerate) and set `VAPID_SUBJECT` (a `mailto:` address or URL), `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`
   in `.env`. Easiest: a throwaway PHP script calling `Minishlink\WebPush\VAPID::createVapidKeys()` (see
   *Known Issues* if `openssl_pkey_new()` fails with a missing-config-file error).
6. `php artisan migrate --force`
7. `npm ci && npm run build`  *(Linux has `pcntl`, so the full `composer run dev` also works there.)*
8. `php artisan config:cache route:cache view:cache`
9. Point the web root at `public/`; ensure `storage/` and `bootstrap/cache/` are writable.
10. **Add a cron entry** (new requirement — see below): `* * * * * cd /path/to/app && php artisan
    schedule:run >> /dev/null 2>&1`.

### Every later deploy
```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
# restart php-fpm / your process manager
```

**Cron is required** as of the Web Push feature: `php artisan schedule:run` must run every minute (a
single crontab line, see step 10 above) — it drives `app:advance-pomodoro-phases` and
`app:send-event-start-notifications`, the two commands that make Pomodoro/event-start push notifications
fire even with no tab open. No separate queue worker is needed (notifications send synchronously inline).

---

## 10. Known Issues & Solutions

### `openssl_pkey_new()` fails generating VAPID keys on the standalone Windows PHP install
**Symptom:** `Minishlink\WebPush\VAPID::createVapidKeys()` (or any raw `openssl_pkey_new(['curve_name' =>
'prime256v1', ...])`) throws `RuntimeException: Unable to create the key`; `openssl_error_string()` reports
`configuration file routines::no such file`.
**Cause:** the standalone PHP-for-Windows install (see the next entry) ships an `openssl.cnf` template
under `extras\ssl\` but never points OpenSSL at it — EC key generation needs a valid config file, and
without one PHP's `openssl` extension silently has no default to fall back to on Windows.
**Fix:** set the `OPENSSL_CONF` environment variable to the shipped config before running the script:
`$env:OPENSSL_CONF = "C:\php\extras\ssl\openssl.cnf"` (PowerShell), then re-run. Only needed for local key
generation — the production Linux box's system OpenSSL already has a working config.

### Push/Pomodoro notifications silently never fire in local dev — nothing runs the scheduler
**Symptom:** subscribing works (a real row lands in `push_subscriptions`), manually starting/continuing a
Pomodoro session notifies fine, but an event's start time passes — or a running phase finishes while the
tab is closed — and no push ever arrives. No error anywhere; `storage/logs/laravel.log` is silent because
there's nothing to log.
**Cause:** `app:advance-pomodoro-phases` and `app:send-event-start-notifications` are registered with
Laravel's scheduler (`bootstrap/app.php`'s `withSchedule()`), but the scheduler itself does *nothing*
unless something invokes `php artisan schedule:run` on a timer. Production gets that from a cron entry
(§9). **Windows has no cron**, and `composer run dev` used to only start `php artisan serve` + `npm run
dev` — so locally, the two scheduled commands never ran at all outside of `php artisan test` and a
manually-triggered `php artisan schedule:run`. Confirmed live: a real Pomodoro test session sat frozen on
an elapsed phase for over an hour (46+ cycles reached only via the client's own open-tab timer calling
`handlePhaseComplete()` directly — never via the scheduler) until `schedule:run` was finally invoked by
hand.
**Fix:** `composer.json`'s `dev` script now also starts `php artisan schedule:work` (Laravel's official
local-dev stand-in for cron — a plain polling loop, no `pcntl` needed, unlike Pail) alongside the server
and Vite. Run `composer run dev` (not `php artisan serve` on its own) whenever testing anything
notification-related locally. To force one tick manually instead: `php artisan schedule:run`.

### Push notifications "send" successfully but never arrive — cURL has no CA bundle on Windows
**Symptom:** the scheduled commands complete with no error, `PushNotifier` throws nothing, no
subscription gets pruned as expired — but no notification ever appears on any device. Diagnosed with
Settings' "Test-Benachrichtigung senden" debug control (see §7): every send reports `success: false`,
`reason: cURL error 60: SSL certificate ... unable to get local issuer certificate`.
**Cause:** the standalone PHP-for-Windows install has no CA bundle configured (`curl.cainfo`/
`openssl.cafile` both empty in `php.ini`), so cURL can't verify the TLS certificate of *any* HTTPS host it
connects to — including Google's/Microsoft's push endpoints — and the request fails before it ever
reaches the server. `MessageSentReport::isSuccess()` correctly reflects this failure, but it isn't a
404/410 "expired" response, so nothing before this fix ever surfaced it: no exception, no pruning, no log
line — a completed report is not the same as a delivered notification.
**Fix:** point PHP at an existing CA bundle rather than downloading one — Git for Windows already ships a
current Mozilla bundle at `C:\Program Files\Git\mingw64\etc\ssl\certs\ca-bundle.crt`. Set both
`curl.cainfo` and `openssl.cafile` in `C:\php\php.ini` to that path, then restart any running PHP
process. Also fixed defensively in code: `PushNotifier` now logs (`Log::warning`) any report that isn't
a success and isn't a simple expiry, so a persistent delivery failure like this one is visible in
`storage/logs/laravel.log` even without manually testing.

### Laravel Pail / `composer run dev` fails on Windows (pcntl)
**Symptom:** `composer run dev` crashes with a RuntimeException; the `concurrently --kill-others` flag
then tears down the whole dev environment.
**Cause:** Laravel Pail requires the `pcntl` PHP extension (process forking), which is **POSIX-only** and
**absent on Windows**.
**Fix:** remove the `pail` (and the queue listener) processes from the `dev` script in `composer.json` on
Windows. Keep only the PHP server + Vite. Pail/queue still work fine on the Linux production box.

### Composer / Laravel installer not on the Bash PATH
**Symptom:** `composer: command not found` in the Bash tool.
**Cause:** the toolchain isn't registered on the Bash PATH at all.
**Fix:** run Composer/Laravel/Artisan via the PowerShell tool. Avoid `2>&1` on native commands in
PowerShell 5.1 — it wraps stderr as an error record even on success.

### Fresh PHP/Node installs aren't on PATH until the session restarts, and a plain PHP zip ships with no php.ini
**Symptom:** `php`/`node`/`npm`/`composer` are "not recognized" even right after installing them; a
freshly installed PHP has almost no extensions loaded (`php -m` is nearly empty) and `php --ini` shows
no loaded configuration file at all.
**Cause:** an installer updates the User/Machine `Path` registry value, but any process already
running — including whatever spawned a shell tool session — keeps its stale copy of `PATH` until
relaunched. Separately, a plain downloaded PHP-for-Windows zip ships with **no active `php.ini`**
(only `php.ini-development`/`php.ini-production` templates) and every extension commented out —
unlike Herd, which preconfigures both.
**Fix:** don't rely on bare `php`/`node`/`npm`/`composer` resolving in a shell tool session — call the
full path (e.g. `C:\php\php.exe`, `C:\Program Files\nodejs\node.exe`) until `Get-Command` confirms it's
on PATH. For a fresh PHP install: `Copy-Item php.ini-development php.ini`, set `extension_dir` to the
install's `ext` folder, and uncomment at least `curl`/`fileinfo`/`gd`/`intl`/`mbstring`/`openssl`/
`pdo_sqlite`/`sqlite3`/`zip` — all required somewhere in this project (`gd` specifically powers
`icons:generate`, see §7 PWA).

### `composer install` fails with "requires php >= 8.4.1" even though *a* working PHP is on PATH
**Symptom:** `composer install` reports a wall of `symfony/* v8.1.0 requires php >=8.4.1 -> your php
version (8.4.0) does not satisfy that requirement` errors.
**Cause:** more than one PHP binary can exist on the machine at once (e.g. herd-lite's own bundled
`php.exe`, stuck at 8.4.0, alongside a separately installed standalone PHP). `composer.lock` is pinned
against whatever PHP version last generated it; an older PHP fails the platform check even though
`php -v` on *some* binary "works" and Composer itself (`composer.phar`) is just a PHP script that can
be run under any interpreter you point at it.
**Fix:** run Composer through the newest available PHP explicitly — e.g.
`& "C:\php\php.exe" "$env:USERPROFILE\.config\herd-lite\bin\composer.phar" install` (standalone PHP
interpreting herd-lite's `composer.phar`). Check every PHP binary's `-v` against `composer.lock`'s
required version before assuming whichever one resolves first is the right one.

### `composer create-project` needs an empty target directory
**Symptom:** it refuses to scaffold into a non-empty directory.
**Fix:** scaffold into a temporary subdirectory, then move the contents up into the repo root (preserving
`.git`).

### Carbon 3 `diffIn*()` methods return a float
**Symptom:** day-bucket logic using `=== 0` / `=== 1` silently never matches (e.g. "heute"/"morgen"); or a
strict `assertSame(int, ...)` test fails with "900.0 is identical to 900" on downstream int arithmetic.
**Fix:** cast to int at the call site: `(int) $today->diffInDays($date)` / `(int) $start->diffInSeconds($now,
false)`. Check overdue separately with `lessThan()`. (See `Task::effectiveDateLabel`,
`ScheduleEvent::pomodoroPhaseNow`.)

### PHP 8.2+ forbids accessing a trait's own constant via the trait's name
**Symptom:** `Cannot access trait constant App\Livewire\Concerns\X::FOO directly` — thrown at the call site,
not where the trait defines it.
**Cause:** a `public const` declared inside a `trait` may only be reached through a **class** that `use`s the
trait (or via `self::`/`static::` from inside the trait itself); `TraitName::FOO` from outside is rejected.
**Fix:** put constants that need to be referenced by name from outside on a real class instead (e.g.
`ScheduleEvent::EVENT_COLORS`, not `ManagesSchedule::EVENT_COLORS`), and have the trait's own methods read it
via the class too. Only use a trait constant if every reader is either the trait itself or a class using it.

### Livewire 4 generates emoji-named single-file components
**Symptom:** `php artisan make:livewire X` creates `resources/views/components/⚡x.blade.php`.
**Fix:** delete it and use a **class-based** component in `app/Livewire/` (ASCII filename, easier to test,
robust across Windows↔Linux git). Reference as `<livewire:task-board />` / route to the class.

### Browser preview tool can't trigger Livewire 4 `wire:` directives
**Symptom:** `preview_click` "succeeds" but no Livewire request fires (no `/livewire/update` POST).
**Cause:** the preview tool's synthetic click events don't reach Livewire 4's delegated listeners; only
`wire:model` (input) and the programmatic API work through it.
**Fix (for verification only):** drive actions through the JS API via `preview_eval` —
`Livewire.all()[0].$wire.call('method', ...args)` and `.$wire.set('prop', value, false)`. In Livewire 4
the component object exposes a `.$wire` proxy (not top-level `.set`/`.call`). Real users are unaffected.

### `php artisan tinker --execute "…"` mangles quotes from PowerShell
**Symptom:** a one-liner passed to `tinker --execute` dies with a PHP parse error (`unexpected '@'`) or
PowerShell splits the string on a `|` inside it.
**Cause:** PowerShell 5.1 reinterprets `|`, `@`, and embedded double-quotes when forwarding an argument to a
native exe, corrupting the PHP snippet.
**Fix:** don't seed/poke the DB with `tinker --execute`. Write a throwaway seeder (`php artisan db:seed
--class=…`) or a small test, or drive state through the running app's Livewire `$wire` API in the browser
preview. Reserve PHP verification for PHPUnit, which has no shell-quoting surface.

### An API controller's `store()` must return `$model->fresh()`
**Symptom:** the JSON returned from a `POST` that creates a row shows `null` for a boolean column
(`is_today`, `is_completed`, `is_cancelled`, …) even though the column has a DB-level `->default(false)` and
every other request shows it correctly as `false`.
**Cause:** the migration's default is enforced by the database, not by Eloquent — a freshly `->create()`d
model only has the attributes you explicitly passed, so a column you didn't set is simply absent from the
in-memory attribute bag until the row is reloaded.
**Fix:** every API `store()` (and any other action that serialises a just-created model) must return
`$model->fresh()`, not `$model` itself. PHPUnit didn't catch this — `assertDatabaseHas`/`fresh()` calls in
the test itself masked it — an end-to-end curl smoke test against a running `php artisan serve` did.

### The same fresh-model gotcha also breaks a strictly-typed `bool` parameter, not just JSON output
**Symptom:** `TypeError: ...pomodoroPhaseNow(): Argument #3 ($autostart) must be of type bool, null given`,
thrown from a Blade view, immediately after a brand-new `User` was created in the same request/test.
**Cause:** the same root cause as the `store()`/`fresh()` issue above (a DB-level `->default(...)` column is
absent from a freshly-`create()`d model's in-memory attributes until reload) — but this time the caller
passed the raw attribute straight into a strictly-typed `bool` parameter instead of into a loosely-typed
JSON response, so PHP throws immediately instead of silently rendering `null`/`false`.
**Fix:** cast at the call site — `(bool) auth()->user()->pomodoro_autostart` — anywhere a boolean
user/model setting with a DB default feeds a typed parameter. `(bool) null` is `false`, matching the
column's actual default, so this is correct even for the one request where the model genuinely hasn't been
reloaded yet. (See `TaskBoard::focusPhase()`.)

### Destructive actions must never use `confirm()` / `wire:confirm`
**Rule:** blocking browser dialogs (`confirm()`, `window.confirm()`, Livewire's `wire:confirm`) are banned
for delete/remove actions — they're jarring, unstyled, and block the main thread. Use the "armed"
double-click pattern instead, exactly as already used across task cards, category rows, project actions,
and the schedule event form:
```html
<button
    type="button"
    x-data="{ armed: false, _t: null }"
    @click="if (armed) { $wire.someDestructiveAction(...args); clearTimeout(_t); armed = false; } else { armed = true; clearTimeout(_t); _t = setTimeout(() => armed = false, 2000); }"
    @click.outside="armed = false; clearTimeout(_t)"
    @keydown.escape.window="armed = false; clearTimeout(_t)"
    :class="armed ? 'bg-signal text-white' : 'text-ink-faint hover:bg-signal-soft hover:text-signal'"
    class="transition ..."
    aria-label="…"
>…</button>
```
First click arms the button (red background, 2s window); a second click within that window fires the
action. Clicking outside or pressing Escape disarms it early. See `partials/task-card.blade.php`,
`settings.blade.php` (category delete), `project-page.blade.php` (project delete, external link/deadline
removal), and `partials/schedule-event-form.blade.php` (event delete) for reference implementations.

### Livewire re-runs a component root's `x-init` on every action, not just on first load
**Symptom:** an Alpine store seeded via `x-init="$store.foo.init({...server data...})"` on a Livewire
component's root element silently resets/loses any client-side-only state the moment *any* `wire:` action
fires anywhere on the page — not just actions related to that store.
**Cause:** Livewire re-morphs the whole component root on every request, and that re-morph re-runs
`x-init` on the root element every time, not just once at first mount (confirmed empirically — traced via
a `new Error().stack` dropped inside the store's `init()`, see git history of `resources/js/app.js` around
the `cleanup` store). Relying on "`x-init` only runs once" (true for a plain Alpine page, false once
Livewire is morphing that element) will quietly wipe any state the client was tracking independently of
the server (manual ordering, a locally-deferred/skipped item, etc.) on the very next unrelated Livewire
round trip. Separately, Alpine also auto-calls a *store's* own `init()` method once with **no arguments**
as soon as the store is registered (before any `x-init` in the DOM has even run) — a naive re-run guard
that just checks "has `init` been called before" will block the real seed call too if it doesn't also
distinguish this argument-less bootstrap call from the real one.
**Fix:** guard the store's `init()` with a `seeded` flag that's set `true` only on the first call carrying
real data, and treat Alpine's own argument-less auto-call as a no-op by checking that the expected argument
is actually defined before seeding:
```js
window.Alpine.store('foo', {
    seeded: false,
    init(cfg = {}) {
        if (this.seeded || cfg.someExpectedKey === undefined) return;
        this.seeded = true;
        // ...apply cfg...
    },
});
```
See the `cleanup` store in `resources/js/app.js` for the reference implementation.

### An un-keyed `x-data` element frozen across a Livewire morph reads stale server data
**Symptom:** on the Zeitplan page, navigating to a different week (or a different day on mobile) and then
drawing a Kategorie/Termin block writes it to the *previously displayed* day/week instead of the one on
screen.
**Cause:** the opposite failure mode from the `x-init` issue above. Livewire's morph tries to preserve an
Alpine component's live state across a re-render by reusing the same DOM node when the node's position/
structure didn't change — so `x-data="scheduleDraw({ date: '{{ $day->toDateString() }}' })"` only ever
evaluates its factory **once**, at first mount. Paging `prevWeek`/`nextWeek`/`prevDay`/`nextDay` re-renders
the blade with a new `$day`/`$focusedDate`, but the day-column `<div x-data="scheduleDraw(...)">` has no
`wire:key`, so Livewire patches its attributes in place rather than replacing the node — Alpine's `date`
stays whatever it was on first page load, and every subsequent drag writes to that stale date.
**Fix:** give the element a `wire:key` derived from the value the factory closes over
(`wire:key="draw-grid-{{ $day->toDateString() }}"` / `wire:key="draw-grid-{{ $focusedDate }}"`). A changed
key makes Livewire's morph treat it as a genuinely new node — destroy the old, mount a fresh one — which
re-runs `x-data` with the current date. Same underlying lesson as the focus ring's `wire:key` (§7 Schedule):
any `x-data`/`x-init` that closes over server-rendered values needs a key tied to those values, or Alpine
will silently keep serving the values from first mount.

---

## 11. Key commands

```powershell
# (run in PowerShell — see §4 for full paths if these aren't resolving on PATH)
composer install            # PHP dependencies
npm install                 # JS dependencies
php artisan migrate         # run migrations
php artisan test            # run the test suite
npm run dev                 # Vite dev server (assets)
php artisan serve           # PHP dev server  (http://127.0.0.1:8000)
```
