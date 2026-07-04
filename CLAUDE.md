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
  - Toolchain is provided by **Laravel Herd** (PHP 8.4, Composer, the `laravel` installer, nginx).
    These live on the **PowerShell** PATH (`~/.config/herd/bin`), **not** the Bash PATH.
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
- **Drag & drop:** SortableJS (`window.boardSortable`).
- **Swipe gestures:** hand-rolled Pointer-Events Alpine component (`swipeCard`) — no library.
- **Styling:** Tailwind CSS **v3** (PostCSS). Topografie tokens are CSS custom properties (space-separated
  RGB channels) so one `prefers-color-scheme` media query flips the whole "map" day↔night and Tailwind
  opacity modifiers (`bg-paper/85`) still work. Font: self-hosted **Space Grotesk** (Fontsource).
- **Database:** SQLite (development), MySQL (production-ready).
- **Build:** Vite 8. **Tests:** PHPUnit (131 tests).

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
  Also carries manual timezone settings — `timezone_offset` (a plain UTC-offset integer entered by the
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
  is_cancelled, pomodoro_started_at?, timestamps`. Every event is either a **Termin** (free-text
  `title`/`color`, `category_id` null) or a **Kategorie** block (`category_id` set). `isAppointment()` /
  `isCategory()` branch on that. `displayTitle()`/`colorToken()` prefer the **live** category name/colour,
  falling back to the `title`/`color` snapshot written at creation time if the category was later deleted.
  Times are `HH:MM` strings; `toMinutes/fromMinutes/startMinutes/endMinutes/durationMinutes`, `isActive/isPast/
  progress/secondsRemaining(now)` for the strip + timeline. `pomodoro_started_at` (nullable timestamp) is set
  only by an explicit user tap — reaching the block's scheduled time never starts it automatically.
  `pomodoroPhaseNow(now, rhythm)` delegates to `PomodoroCycle::at()` and returns the current phase plus
  second-precise `remaining_seconds`/`total_seconds` for the live ring, or `null` if not started. Scopes
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
  the strip swaps to a **focus card** with two states: **Bereit** (a Start button — reaching the scheduled
  time never auto-starts the timer) and **Läuft** (the live countdown ring, fed by `TaskBoard::focusPhase`).
  The ring's wrapper carries a `wire:key` keyed on `(event, phase, cycle)` plus `wire:poll.5s.visible`, so a
  poll that detects a phase change (work → break) forces Alpine to tear down and reinitialise the ring with
  the new phase's config — a plain re-render wouldn't, since Alpine only evaluates `x-data` once per DOM
  node. `startFocusTimer`/`stopFocusTimer` on `TaskBoard` set/clear `pomodoro_started_at` (ownership- and
  `pomodoro_enabled`-guarded); a tap before the block's scheduled start begins the cycle immediately.
- **`App\Services\PomodoroCycle`** — a pure, stateless Pomodoro layout: `at(elapsedMinutes, rhythm)` walks
  work→break cycles and returns the phase (`work|short_break|long_break`), cycle number, and minute
  boundaries; a break is long when its preceding work cycle's number is divisible by `long_every`.
  `ScheduleEvent::pomodoroPhaseNow()` wraps it with second-precision remaining/total time for the ring.
- **`App\Services\TaskSuggestor`** — "what to work on" for the focus card, tiered by the run's current
  Pomodoro cycle number (`TaskBoard::taskSuggestion()`, read via `$phase['cycle']`, defaulting to 1 while
  still in **Bereit** so it previews the session about to start): cycle 1 nudges to clear the ToDos list
  (falling through if none are open), any cycle then prefers the top active **today** task (board order),
  and once today's list is empty it falls back to a deterministic pick between a project's next task and
  another todos/tasks-list task — seeded by `(event id, cycle)` via `crc32()` so the choice stays stable
  across the ring's 5s poll instead of reshuffling every request. Hidden during a break. Rendered by
  `partials/schedule-strip-suggestion.blade.php` in both focus-card states; a task suggestion opens the
  existing inline edit sheet (`startEdit`), a project suggestion links to its project page.
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
  audio file/package). Desktop uses the hover pencil; mobile uses double-tap. `window.primeFocusAudio()`
  initialises/resumes the shared `AudioContext` on the Start button's `onclick` (a real user gesture), so the
  later automatic chime isn't blocked by autoplay policy.
- **Settings** (`App\Livewire\Settings`) has a Pomodoro section (work / short break / long break /
  sessions-per-long-break, via `saveSchedule()`) and a **Kategorien** card: add/rename/recolour/toggle-
  Pomodoro/delete, all ownership-scoped (`ManagesSchedule`'s and `Settings`' colour validation both read
  `ScheduleEvent::EVENT_COLORS` — a plain class constant, not a trait constant, since PHP forbids accessing
  a trait's own constant via the trait's name directly).

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
5. `php artisan migrate --force`
6. `npm ci && npm run build`  *(Linux has `pcntl`, so the full `composer run dev` also works there.)*
7. `php artisan config:cache route:cache view:cache`
8. Point the web root at `public/`; ensure `storage/` and `bootstrap/cache/` are writable.

### Every later deploy
```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
# restart php-fpm / your process manager
```

No cron jobs or queue workers are required for the current feature set.

---

## 10. Known Issues & Solutions

### Laravel Pail / `composer run dev` fails on Windows (pcntl)
**Symptom:** `composer run dev` crashes with a RuntimeException; the `concurrently --kill-others` flag
then tears down the whole dev environment.
**Cause:** Laravel Pail requires the `pcntl` PHP extension (process forking), which is **POSIX-only** and
**absent on Windows**.
**Fix:** remove the `pail` (and the queue listener) processes from the `dev` script in `composer.json` on
Windows. Keep only the PHP server + Vite. Pail/queue still work fine on the Linux production box.

### Composer / Laravel installer not on the Bash PATH
**Symptom:** `composer: command not found` in the Bash tool.
**Cause:** the toolchain is installed via **Laravel Herd** and only registered on the **PowerShell** PATH
(`C:\Users\<user>\.config\herd\bin`).
**Fix:** run Composer/Laravel/Artisan via the PowerShell tool. Avoid `2>&1` on native commands in
PowerShell 5.1 — it wraps stderr as an error record even on success.

### `composer create-project` needs an empty target directory
**Symptom:** it refuses to scaffold into a non-empty directory.
**Fix:** scaffold into a temporary subdirectory, then move the contents up into the repo root (preserving
`.git`).

### Only Herd PHP (8.4.13) runs the app — Bash `php` (8.4.0) fails the platform check
**Symptom:** `php artisan …` from the Bash tool dies with "Composer dependencies require PHP >= 8.4.1".
**Cause:** Composer locked deps against Herd's PHP 8.4.13; the separate Bash `php` is 8.4.0.
**Fix:** run **all** PHP/artisan/composer through PowerShell (Herd). Use Bash only for git.

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

---

## 11. Key commands

```powershell
# (run in PowerShell — Herd toolchain)
composer install            # PHP dependencies
npm install                 # JS dependencies
php artisan migrate         # run migrations
php artisan test            # run the test suite
npm run dev                 # Vite dev server (assets)
php artisan serve           # PHP dev server  (http://127.0.0.1:8000)
```
