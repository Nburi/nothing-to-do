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

**One deliberate exception to "single user":** the **Agenda** (homework & exams) can be shared with a school
class via an **Agenda Space** — several accounts see one list of class homework, while everything else in the
app (tasks, projects, Zeitplan, Bastelideen) stays strictly single-user. The app is still a personal
productivity system, not a team tool; the class agenda is a shared *input*, not shared workspace. See §7
"Agenda — Klassen teilen" for what that does and does not mean.

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
11. **New user-facing feature → draft an announcement.** Whenever a feature ships that a regular user would
    actually notice (a new page, a new gesture, a new setting worth knowing about — not an internal
    refactor or a bugfix), also create a draft `App\Models\FeatureAnnouncement` for it (title + one-sentence
    description, `related_module` set if it maps to an `AppModules::CATALOG` key), or if that's out of scope
    for the current task, at least flag in the summary that one should be written. Leave it unpublished —
    publishing is the user's call. See CLAUDE.md §7, "Feature-Ankündigungen".

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
- **Build:** Vite 8. **Tests:** PHPUnit (868 tests).
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
  `ScheduleEvent::pomodoroPhaseNow()` below. `notify_event_start`/`notify_pomo_start`/`notify_break_start`/
  `notify_event_upcoming` (bools, default `false`) independently gate the four browser-notification triggers
  (Settings' Benachrichtigungen card) — see §7 Schedule "Notifications". Also carries manual timezone settings —
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
  `daily_task_goal`/`notify_daily_reminder`/`daily_reminder_time`/`daily_reminder_sent_on`/
  `notify_streak_risk`/`streak_risk_sent_on` back the Fortschritt feature (see its own section below)
  — `dailyTaskGoal()` and the `STREAK_RISK_DUE_TIME` constant live on `User` alongside the other
  per-user rhythm helpers above.
- **`Project`** — `user_id, name, brainstorm, external_url, sort_order, timestamps`. `hasMany Task`; `activeTasks` is the ordered
  uncompleted working set. `externalServiceName()` detects the service label from the URL (Jira, GitHub, Linear, etc.).
  Scopes: `forUser`, `ordered`.
- **`Task`** — `user_id, title, list, project_id, is_today, is_important, deadline(date), due_date(date),
  notes, is_completed, completed_at, sort_order, timestamps`. See `docs/REQUIREMENTS.md` §2 for field meaning.
  - `list` is a **string** (`inbox|todos|tasks|projects`), not a DB enum. `BOARD_LISTS` are the three
    drag/quick-add columns; a task in the `projects` list also carries a `project_id` and lives on its
    project page (never on the main board — see the `onBoard` scope = `project_id IS NULL`).
  - Scopes: `forUser`, `active`, `inList`, `onBoard`, `boardOrdered` (important → due within 4 days → manual order).
  - Deadline logic lives on the model: `effectiveDate()` = `deadline ?? due_date`, `isUrgent`, `isOverdue`,
    `effectiveDateLabel` (heute/morgen/weekday/d.m./überfällig).
  - Today focus is plain `is_today` — no decoupled planning field.
  - **`notes`** (nullable text) — free-form notes/comments per task, editable from two places: the shared
    edit sheet (`ManagesTasks::editNotes`/`editNotesHtml`) and, for task targets only, the quick-capture
    panel (`QuickCapture::$notes`/`notesHtml` — see §7 Schnellerfassung). Both embed the same
    **`partials/notes-editor.blade.php`** partial (parameterized by `fieldName`/`htmlProperty`/`idPrefix`,
    so both instances can exist in the DOM at once without id collisions) — a small toolbar (Fett/Kursiv/
    Unterstrichen/Liste/Aufgabe) inserts Markdown syntax into a plain textarea (`wire:model`, deferred like
    every other field on both forms); a "Vorschau aktualisieren" button (`wire:click="$refresh"`) forces a
    fresh server render into a `prose-topo` preview box below, since a `.blur`-triggered auto-sync turned
    out to be unreliable in this Livewire 4 setup (see *Known Issues*) — reusing `$refresh` (a core,
    always-fires Livewire action) sidesteps that instead of chasing the blur modifier further. Both call
    **`Task::renderNotesMarkdown(string $text): string`** for the actual rendering — one static helper so
    the safety options (`Str::markdown($text, ['html_input' => 'strip', 'allow_unsafe_links' => false])`,
    same as the project brainstorm field) and the extra extension stay in one place rather than drifting
    across two components. That extra extension is **`App\Support\Markdown\UnderlineExtension`** — a small
    custom CommonMark extension (`app/Support/Markdown/`, modeled on league/commonmark's own bundled
    Strikethrough extension) adding `++underlined++` inline syntax, since neither CommonMark nor GFM has a
    native underline syntax of its own. `Task::notesPreview(int $words = 8)` strips all of that formatting
    back down to plain text (bold/italic/underline markers, list/task-list prefixes) for the one-line
    snippet shown on the task card face (`task-card.blade.php`/`task-card-mobile.blade.php`/
    `project-task-card.blade.php`), truncated with `…` past the word limit.
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

### Schnellerfassung (quick capture) (built)
- **The dashboard has no input fields at all.** It used to carry three — the task quick-add bar, the
  Bastelideen bar, and the "Neues Projekt" field in the Projekte column — which pushed the first task card
  **309 px** down the page (34 % of a 900 px viewport) and, worse, gave the rarely-used Bastelideen bar
  (50 px) exactly the same visual weight as the app's core capture action (52 px): same card, same shadow,
  same green button in the same place. All three are gone; the first card now starts at **159 px**.
- **`App\Livewire\QuickCapture`** replaces them with one panel, included in `layouts/app.blade.php` inside
  `@auth` rather than in `TaskBoard` — being reachable from *every* page is the whole point, and a component
  living in the board could not be. It writes to `tasks`, `projects` or `craft_ideas` depending on the chosen
  target (`QuickCapture::TARGETS` = `inbox|todos|tasks|project|craft|agenda`), always through the owner
  relation.
- **Per-target fields**, revealed progressively: the optional ones appear as soon as the title has content,
  rather than waiting for the "Mehr" toggle (which still overrides in both directions). `wire:model` is
  deferred, so `$wire.title` does *not* change per keystroke — the trigger is a **local Alpine mirror**
  (`typed`, fed by `@input`), reset on `captured` and on `quick-capture-opened`. Tasks get deadline +
  Wunschtermin + Notizen, a project gets a deadline, a Bastelidee gets "Wo anfangen". The Notizen field
  (task targets only) is the same `partials/notes-editor.blade.php` partial the task edit sheet uses
  (`@include(..., ['fieldName' => 'notes', 'htmlProperty' => 'notesHtml', 'idPrefix' => 'qc'])`) — same
  toolbar, same `Task::renderNotesMarkdown()` rendering, same "Vorschau aktualisieren" `$refresh` button —
  so a task can be captured with formatted notes in one step instead of adding them later via the edit sheet.
- **Agenda is the one target whose extras are required**, not optional — type, Fach and date, mirroring
  `Agenda::save()`'s own rules. Those rules are therefore added to `validate()` **only** when `agenda` is the
  chosen target, and its section can't be folded away (the "Mehr" button hides). Fach autocompletes from
  `existingSubjects()` through a native `<datalist>` — the same source as the Agenda page's Alpine combobox,
  without duplicating it. Fach, date and type survive a save the way the target does: several homework items
  for one subject on one day is the normal case, not the exception.
- **The title placeholder follows the target** ("Wie heisst das Projekt?", "Was ist aufgegeben?") — "Was
  steht an?" reads wrong for half of them.
- **Open/closed is Alpine, not Livewire** — ephemeral UI state, same as `draw`/`projectPicker`. The
  `quickCapture` store (`resources/js/app.js`) owns `open`, `show(trigger)` and `hide()`, so opening the
  panel costs no round trip. `show()` also dispatches `quick-capture-opened`, which the component's
  `resetPanel()` listens for — every session starts clean and back at Inbox rather than showing the last
  one's leftovers. Focus is moved imperatively **in the store**, deliberately not via `x-init`/`$watch` in
  the Blade: Livewire re-runs a component root's `x-init` on every action (see *Known Issues*), so a watcher
  registered there would be re-registered after every single capture.
- **Opening it:** the `N` key from anywhere (a plain `document` keydown listener at the bottom of `app.js`,
  guarded against `INPUT`/`TEXTAREA`/`SELECT`/`contenteditable` and against any modifier), or a `+` button.
  `Esc` and click-outside close it, `x-trap.noscroll` traps focus, and `hide()` returns focus to whatever
  opened it.
- **Where the `+` sits** is decided by `$showCaptureFab` in `layouts/app.blade.php` (`routeIs('app')`,
  `routeIs('crafts')` or `routeIs('agenda')`). On touch those pages get a **floating button bottom-right** — the position
  phones have trained everyone to look in, and a header button there is genuinely hard to find. Every other
  page keeps the header button, because their bottom-right corner isn't free: the Zeitplan pins its
  "Zeichnen:" category row to the bottom of a viewport-height grid, which **no amount of page padding can
  scroll clear** (page padding only helps when the thing underneath scrolls). Those pages also have their
  own prominent add buttons, so global capture is a utility action there and the header is the right home
  for it. On touch the header button hides wherever the floating one takes over, so exactly one `+` is ever
  on screen; desktop only ever has the header button. Both pages with the floating button reserve matching
  bottom padding (the board already had `pb-28` for its nav; `craft-ideas.blade.php` got `pb-28 sm:pb-16`;
  `agenda.blade.php` already had it) so the last card can always be scrolled out from under it.
- **`$captureTarget`** (same `@php` block) opens the panel on the chip matching the page's own subject —
  `crafts` → Bastelidee, `agenda` → Agenda, everywhere else the Inbox default. Without it a `+` in the
  bottom-right corner of a page about one kind of thing quietly files something else: on Bastelideen it
  created an Inbox task, and on Agenda it advertised an entry the panel couldn't create at all. That was the
  reason Agenda became a target rather than losing its button.
- **Choosing a target:** chips, or **↑/↓ while typing**. Deliberately *not* number keys, even though the
  design mockup implied them — the digits belong to the title field, and every modifier+digit combination in
  range is already claimed by the browser (Alt/Ctrl+1–9 switch tabs). ↑/↓ do nothing useful in a
  single-line input, so hijacking them costs nothing.
- **After saving, the panel stays open** with the title cleared and the target kept, echoing back what was
  just captured ("„Postenbeschreibung studieren" → Inbox"). Dumping several things in a row is the point, and
  the old inline bars behaved the same way. Closing is always explicit.
- **Keeping other components in sync:** `QuickCapture` is a *separate* component, so its writes don't
  re-render anything else on their own. It dispatches `captured`; `TaskBoard` and `CraftIdeas` listen via
  `#[On('captured')]` with empty method bodies — handling the event *is* the re-render, and every read is a
  computed property that re-evaluates on the way out.
- **In-context capture still exists where it earns its place:** `ProjectPage`, `EmergencyMode` and (new)
  `CraftIdeas` keep their own inline add forms. A page entirely about one kind of thing shouldn't send you
  through a global panel to add one. The Bastelideen page had *no* capture at all before this — the
  dashboard bar was its only entry point.
- **Header diet shipped with it:** Notfall's nav pill only renders while the mode is actually active (or its
  own screen is open) — it's a mode, not a place, and an active emergency already shows a large banner.
  Bastelideen moved into the avatar menu. Five pills became three plus the `+`.

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

### Notfallmodus (built)
- For the "I forgot about this project and it's due very soon" moment: pick one project, sequence its
  tasks in the order you'll actually do them, and the main board temporarily narrows down to just that
  project (plus anything already important) instead of the full inbox/todos/tasks noise.
- **`App\Livewire\EmergencyMode`** (`/app/emergency`, `route('emergency')`) is the picker + arrange
  screen. `mount()` preselects a project from `?project=` (a project-page menu link) or, failing that,
  the currently-active emergency project — so re-opening the screen always lands on the right one.
  `selectProject()` bulk-defaults any of that project's active tasks with `emergency_list IS NULL` to
  `'tasks'` (skips already-categorized ones) so every row starts with a concrete pill state.
  `reorderTasks()`/`setTaskList()`/`addTask()` are all scoped to the currently-selected project — a
  stray id from elsewhere is silently ignored, never trusted. `start()`/`end()` just flip
  `users.emergency_project_id` and redirect to the board; nothing about the tasks themselves changes,
  so ending emergency mode is always non-destructive and instantly reversible.
- **The sequence *is* the project's own `sort_order`** — deliberately reused rather than adding a second
  ordering concept. The project page's own task list was never manually reorderable before this (no
  drag-and-drop existed there), so the arrange screen is incidentally the first place a project's tasks
  get a real manual order at all; that order persists as the project's normal order after emergency mode
  ends. `tasks.emergency_list` (nullable `inbox`/`todos`/`tasks`) is a separate, orthogonal tag saying
  *which board column* a task surfaces under while its project is the active emergency project — it's
  never consulted otherwise, so nothing needs to reset it when emergency mode ends.
- **Dashboard integration** (`TaskBoard`) — `emergencyProject()` (nullable, from
  `users.emergency_project_id`) and `emergencyTasksFor(list)` (that project's active tasks tagged for
  one column, in sequence order) are additive: every existing computed property and query is untouched,
  so nothing changes when emergency mode is off. `partials/emergency-banner.blade.php` (signal-toned,
  mirrors `prepare-prompt.blade.php`'s shell) shows progress + the next step + Verwalten/Beenden, and
  swaps to a congratulatory forest-toned variant once the project's tasks are all done. Each board
  column (`partials/column.blade.php`, and the mobile switch cases via the shared
  `partials/emergency-mobile-section.blade.php`) pins the emergency project's tasks for that list on top
  (numbered via an optional `$orderNumber` badge on `task-card`/`task-card-mobile`, **read-only on the
  dashboard** — reordering only happens on the arrange screen, deliberately, since these cards live
  outside any `boardSortable` zone and dragging them would otherwise fight over the same `sort_order`
  the arrange screen just set up), then independently-important tasks (still just the normal
  onboard-and-important ones — a project's own important tasks don't otherwise surface on the board
  either way), then collapses everything else behind a client-only (`x-data="{ showAll }"`, no
  round-trip) "N weitere · Alle anzeigen" disclosure — rendered but CSS-hidden, not omitted, so revealing
  it is instant. `counts()` includes the emergency tasks pinned into each column, since those are
  genuinely visible there, not just the "real" board tasks.
- **Focus-timer suggestion** — `TaskSuggestor::suggest()` checks `user.emergency_project_id` *before*
  its normal cycle-tiered logic and returns the first incomplete task in the project's sequence
  (`kind: 'emergency'`, rendered in `schedule-strip-suggestion.blade.php` in signal colour) regardless of
  Pomodoro cycle number — the point of emergency mode is that this project outranks everything else.
  Falls through to the normal tiers once the emergency project has nothing active left, rather than
  suggesting nothing just because the user hasn't gotten around to ending emergency mode yet.
- Entry points: a header nav item (`layouts/app.blade.php`, signal-toned whenever emergency mode is
  active, from anywhere in the app), a project's `…` menu ("Notfallmodus starten"/"verwalten", jumps
  straight to the arrange screen via `?project=`), and the dashboard banner's own "Verwalten" link.
  `project-card.blade.php` shows a small "Notfall" badge on whichever project is currently active.

### Vorbereitung für morgen (built)
- A full-screen, three-step end-of-day ritual at **`/app/prepare`** (`route('prepare')`, linked from the
  header as "Vorbereiten") that fully replaced the old two-step "Aufräumen": empty the Inbox, flag what's
  on for tomorrow, then lay out tomorrow's time blocks — one guided flow instead of three separate visits.
  `App\Livewire\PrepareTomorrow` (`#[Layout('layouts.app')]`, `use ManagesTasks, ManagesSchedule`, view
  `livewire.prepare-tomorrow`) exposes `tomorrow()` (`auth()->user()->localToday()->addDay()`), the two
  ported Cleanup computed queues (`inboxQueue()`, `reviewQueue()` — the latter still *every* active
  on-board To-Do/Task, not just untagged ones), `tomorrowFlagged()` (active board tasks with
  `is_today=true` — the reminder tray on step 3) and `tomorrowEvents()` (tomorrow's timeline, recurring
  series materialised in `render()` exactly like `Schedule::render()`), plus the same two triage mutations
  as before — `assignList()` (whitelisted to `todos`/`tasks`) and `markToday()`.
- **Step 3 needed no new mutation surface at all.** `ManagesSchedule` was already fully date-agnostic —
  every write (`quickCreateCategoryBlock`, `quickCreateTermin`, `applyTemplate`, `moveEvent`, `resizeEvent`,
  `saveEventForm`/`openEventForm`, …) already takes its target date as a parameter rather than assuming
  "today". Step 3 is the *same* `ManagesSchedule` trait and the *same* Blade partials
  (`partials/schedule-event.blade.php`, `partials/schedule-category-footer.blade.php`,
  `partials/schedule-event-form.blade.php`) as the standalone Zeitplan page, `@include`d verbatim and just
  pointed at `$this->tomorrow` instead of `localToday()` — draw-to-create, drag-to-move/resize, templates,
  and the "+ Termin" precision form all work identically with zero duplicated gesture code.
- Gesture map — right/left always commit-and-advance, down always defers, up (review only) opens a
  popover without advancing; step 3 has no queue to swipe, it's the open-ended timeline:

  | Step | Right | Left | Down | Up |
  |---|---|---|---|---|
  | 1 — Inbox leeren | → `list=todos` | → `list=tasks` | "später": requeue, no DB write | unused |
  | 2 — Für morgen planen | `is_today=true` | "weiter": no change | "später": requeue, no DB write | deadline popover |
  | 3 — Zeitplan für morgen | — draw / drag / templates / "+ Termin" (see above) — | | | |

  "Wichtig" is a dedicated star button on the card face (`toggleImportant`), not a swipe direction. Step 3
  never auto-advances (scheduling is open-ended, unlike a queue that empties) — "Später planen" and
  "Fertig" both just move on to the done screen (`goDone()`), identical in effect, different only in tone.
- **State split**: the server (`inboxQueue`/`reviewQueue`/`tomorrowFlagged`/`tomorrowEvents`) is the source
  of truth for task/event *content*, re-evaluated fresh on every Livewire round trip. Ordering, current
  step, "später" requeueing, and the two session-only tallies shown on the done screen (`inboxTotal` =
  "sortiert", `flaggedCount` = "für morgen") live entirely client-side in `Alpine.store('prepare')`
  (`resources/js/app.js`), seeded once from `@js($this->inboxQueue->pluck('id'))` /
  `@js(...reviewQueue...)` via `x-init` on the page root (`resources/views/livewire/prepare-tomorrow.blade.php`).
  The done screen's third stat, "geplant", is deliberately *not* a session tally — it's
  `$this->tomorrowEvents->count()` read straight from Blade, live at the last server round trip, since
  "how many blocks does tomorrow have" is more useful than "how many were added this session" and needs no
  extra client-side bookkeeping. **Gotcha:** Livewire re-morphs the component root on *every* action, which
  re-runs that `x-init` — without a guard this silently wipes the client-tracked order (and any pending
  "später" requeues) after every single swipe. The store's `init()` guards against this with a `seeded`
  flag, and additionally ignores Alpine's own automatic no-argument call to a store's `init()` (which fires
  once, before `x-init` ever runs, as soon as the store is registered) by checking that `cfg.inbox` is
  actually defined before treating a call as the real seed. On `init()`, steps 1–2 skip forward when their
  queue is already empty, but step 3 is always visited — it's the floor `phase` never falls past on init,
  since it's open-ended and never auto-skipped (a big "Später planen" button covers "nothing to add").
- A task committed into Todos/Tasks during step 1 must reach step 2's queue in the *same* session even
  though the review queue was conceptually seeded before step 1 finished — `enqueueReview()` pushes the
  id into the client-side review order synchronously at the exact moment step 1 commits it, not by
  waiting on a server re-query.
- **`Alpine.data('prepareSwipeCard', cfg => {...})`** (`resources/js/app.js`) — one instance per card,
  modeled closely on the board's `swipeCard` (same pointer handling, mouse pointers excluded, same
  `threshold = max(64, round(dim*0.38))`/resistance/dead-side-damping math) but generalised from one axis
  to two: the first move past a small deadzone locks to horizontal or vertical, and direction is read from
  whichever of `dx`/`dy` is non-zero (mirroring `swipeCard`'s own simple `dir` getter — the other axis
  stays exactly `0` for the rest of the gesture once locked). Each configured direction resolves via a
  `kind`: `'commit'` (fly off, call the configured `$wire` method if any, remove from the queue for good;
  a step-2 `right` commit also increments the store's `flaggedCount`), `'defer'` (später — continue off the
  same edge, no `$wire` call, just requeue to the back), `'popover'` (mirrors `swipeCard`'s existing
  `intent === 'menu'` case: must reach threshold, then springs back and opens the inline date popover
  without advancing), or `null` (dead side, resists and never commits). `trigger(dirName)` is the
  button-fallback entry point — it synthesises a past-threshold gesture and calls the same `resolve()` a
  real swipe would, so buttons and swipes are always in parity. **`key(e)`** is a new desktop fast-path with
  no Cleanup equivalent: since `down()` deliberately excludes mouse pointers (swipe is touch-only, exactly
  like the board's `swipeCard`), desktop previously had *only* the button row — a click-to-focus card
  (`tabindex="0"`) now also accepts arrow keys (← → ↓ ↑), routed through the same `trigger()`/`resolve()`
  path so keyboard, buttons, and touch swipes are always in parity. Stack visuals come from `stackIndex`
  (`store.stackIndexOf(phase, id)`; `-1` means "not queued yet, hide it" — guards the moment a
  freshly-bridged id has no matching DOM node) driving `stackStyle` (index 0 = interactive top card; 1–2 =
  scaled/offset peeking cards, `pointer-events: none`; else hidden). The stack container uses the CSS-grid
  `[grid-area:1/1]` trick so all cards share one cell and the container auto-sizes to the tallest one.
- **Morning vs. evening** — `users.prepare_time_of_day` (`'morning'` | `'evening'`, default `'evening'`,
  Settings' Vorbereitung card) decides which day the whole ritual targets: `User::prepareTargetDate()`
  returns `localToday()` in morning mode or `localToday()->addDay()` in evening mode, and
  `PrepareTomorrow::targetDate` is just that value — every date-scoped computed/mutation in the component
  (`targetFlagged`, `targetEvents`, `applyTemplate`, `openEventForm`, `quickCreate*`) reads it instead of a
  hardcoded "tomorrow". `PrepareTomorrow::targetWord` (`'heute'` | `'morgen'`) drives every "für …" label in
  the view and the two swipe-card partials (passed through explicitly as a Blade variable, not relied on via
  implicit `$this` scoping in the `@include`).
- **Completion tracking** — `users.prepared_on` (a plain local-calendar-date column, not a timestamp: every
  read/write compares `toDateString()` against `localToday()->toDateString()`, so no UTC/local conversion is
  needed anywhere) is stamped by `PrepareTomorrow::finish()`, called via `wire:click="finish"` on *both* the
  "Später planen" and "Fertig" buttons (alongside the existing `@click="$store.prepare.goDone()"` — Livewire
  and Alpine listeners on the same element both fire independently, no extra JS needed to run both). Visiting
  `/app/prepare` never stamps it — only actually reaching the done screen does, which is the one signal every
  reminder path below reads via `User::hasPreparedToday()`.
- **Reminders** — `users.prepare_reminder_mode` (`'off'` | `'automatic'` | `'fixed'`, Settings' Erinnerung
  toggle) plus `prepare_reminder_time` (HH:MM, only meaningful in `'fixed'` mode) drive two independent
  nudges, both suppressed the moment `hasPreparedToday()` is true:
  - **In-app banner** (`'automatic'` only) — `TaskBoard::showPreparePrompt` is true during the relevant half
    of the day for the user's morning/evening setting (`User::isWithinPrepareWindow()`: hour `< 12` for
    morning, `>= 12` for evening — a loose half-day window, not a specific time, since "automatic" means
    "whenever you happen to open the app") and not yet dismissed today
    (`users.prepare_prompt_dismissed_on`, another local-date column). Rendered by
    `partials/prepare-prompt.blade.php` — mirrors `schedule-strip.blade.php`'s card shell — `@include`d in
    both the desktop and mobile sections of `task-board.blade.php` with a `spacing` variable (`mb-6`/`mb-4`)
    so the whole margin+content lives inside the partial's own `@if`, and a hidden banner leaves *zero*
    footprint rather than an empty margin div. `TaskBoard::dismissPreparePrompt()` just stamps today's date.
  - **Push fallback** (`'automatic'` and `'fixed'`) — the scheduled command **`app:send-prepare-reminders`**
    (every minute, `bootstrap/app.php`'s `withSchedule()`) sends one push a day once
    `User::prepareReminderDueTime()` has passed and `hasPreparedToday()` is still false. `'fixed'` mode's due
    time is the user's own chosen `prepare_reminder_time`; `'automatic'` mode has no chosen time — its due
    time is a hardcoded fallback (`10:00` morning / `21:00` evening) that only matters if the in-app banner
    was never seen because the app was never opened during the relevant window. Dedup is
    `users.prepare_reminder_sent_on` (today already sent?) rather than an exact-minute match, so a
    delayed/missed cron tick still fires on the next run instead of losing the reminder — same pattern as
    `notified_at` on schedule events (see Notifications below).

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
  start): after Notfallmodus (still the outright top tier) and the running session's own **category task
  link** (see "Kategorie-Aufgaben-Verknüpfung" below — applies on *every* cycle, not just the first, and
  only when it actually has something to offer), cycle 1 nudges to clear the ToDos list (falling through if
  none are open), any cycle then prefers the top active **today** task (board order), and once today's list
  is empty it falls back to a deterministic pick between a project's next task and another todos/tasks-list
  task — seeded by `(event id, cycle)` via `crc32()` so the choice stays stable across the ring's 5s poll
  instead of reshuffling every request. Hidden whenever the effective phase is a break. Rendered by
  `partials/schedule-strip-suggestion.blade.php` in every focus-card state that has a work-phase context; a
  task suggestion opens the existing inline edit sheet (`startEdit`), a project suggestion links to its
  project page.
- **Notifications** — real **Web Push** (VAPID), delivered by the OS/browser even with the app's tab, and
  the whole browser, fully closed. Four independent per-type toggles still gate *which* moments push
  (`notify_event_start`, `notify_pomo_start`, `notify_break_start`, `notify_event_upcoming` on `User`;
  Settings' Benachrichtigungen card), all default `false` — but delivery itself no longer depends on any tab
  being open, since the server decides when to send.
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
  - **A 5-minute heads-up before a schedule event's start time** (`notify_event_upcoming`) — a fourth,
    independent toggle alongside (not a replacement for) `notify_event_start`: a user can opt into either,
    both, or neither. The scheduled command **`app:send-event-upcoming-notifications`** (every minute) mirrors
    `app:send-event-start-notifications` structurally, but is due once `ScheduleEvent::startInstantUtc(User)
    ->subMinutes(5)` has passed, and dedups via its own column, `schedule_events.notified_upcoming_at` — a
    separate flag from `notified_at`, since one event can fire both notifications independently.
    `ScheduleEvent::withNotifiedReset()` clears both columns together whenever `start_time`/`date` changes.
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
  trait constant, since PHP forbids accessing a trait's own constant via the trait's name directly), and a
  **Vorbereitung** card (`setPrepareTimeOfDay()`/`setPrepareReminderMode()` — immediate-save pill toggles,
  like the Termin/Kategorie switch in the schedule event form — plus `savePrepareReminderTime()`, which
  saves on `wire:change` with no separate submit button since it's only ever touched in "fixed" mode) — see
  "Vorbereitung für morgen" below for what these settings actually drive.
- **All-day strip — Task deadlines/Wunschtermine + Agenda homework/exams.** None of these carry a
  time, so they sit in a row above the hour grid rather than on the timeline itself, in both the
  desktop week view and the mobile single-day view. `Schedule::deadlineItems()` (a `#[Computed]`,
  grouped by `Y-m-d`) reads every active `Task` with a `deadline`/`due_date` plus every
  `AgendaEntry::visibleTo($user)->openFor($user)` (done items are excluded entirely, consistent with
  Board/Agenda), and contributes one entry on the item's own date, plus — **only for a hard date**
  (`Task::effectiveIsHard()`, i.e. `deadline`, not `due_date`; every Agenda entry counts as hard) and
  only when the setting below is on — a second `isPreview` entry `deadline_preview_days` earlier. A
  soft Wunschtermin therefore only ever shows on its own day, never as an advance preview — a
  deliberate product decision, since it's self-set and a warning for it would clutter the preview
  zone fast. Colours mirror the rest of the app: hard deadline = `contour`, soft Wunschtermin =
  neutral, Hausaufgabe = `forest`, Prüfung = `overprint` (`partials/schedule-deadline-item.blade.php`).
  A preview chip is dashed and carries a small "in Nd" label (tooltip: "in N Tagen fällig"); more
  than 2 items on one day collapse behind a client-only "+N weitere" Alpine disclosure
  (`partials/schedule-deadline-strip.blade.php`), the same pattern as Notfallmodus/Bastelideen. A
  click on the chip's checkbox ticks the item off directly — `toggleDeadlineTaskDone()` /
  `toggleDeadlineAgendaDone()`, both owner-/visibility-scoped and deliberately duplicating
  `ManagesTasks::toggleComplete()`/`AgendaEntry::toggleDoneFor()` rather than pulling in a whole
  trait for one action — a preview chip is checkable too, since finishing something ahead of its
  date is a normal thing to do. A hover-revealed arrow icon opens the source page (Board or Agenda)
  without deep-linking to the specific item. **Settings** has a matching **"Vorschau auf Termine"**
  card (`users.deadline_preview_enabled` default `true`, `deadline_preview_days` default `2`,
  max `14`) saved together via `saveDeadlinePreview()`, same form pattern as the Pomodoro card.

### Kategorie-Aufgaben-Verknüpfung (built)

A Pomodoro-enabled category can be pointed at something specific to work on, so a "Training" block's
focus sessions suggest *your race-prep project*, not whatever `TaskSuggestor`'s generic cross-app tiers
would otherwise pick. Six mutually-exclusive **`event_categories.task_source`** values (`'tasks'`,
`'project'`, `'group'`, `'agenda_entry'`, `'agenda_generic'`, `'text'`, or `null` = no link):

- **`tasks`** — a handful of individually pinned `Task`s (`category_task_links`: `category_id, task_id,
  sort_order`, unique per pair, both FKs `cascadeOnDelete` — a pin has no meaning outside the category
  that made it, unlike `linked_project_id`/etc. below). `EventCategory::pinnedTasks()` (`belongsToMany`,
  ordered by the pivot's `sort_order`) suggests the next *active* one, skipping completed pins without
  ever un-pinning them (a finished pin just stops being suggested; unpinning is always a separate,
  explicit click). **The picker defaults to tasks due within 2 days or with a Wunschtermin today**
  (`Settings::linkTaskCandidates()`) — the moment you open "bestimmte Aufgaben" you're not staring at a
  blank search box, the things most likely worth pinning are already in front of you. Typing in the
  search box searches every active board task instead, so something outside that window is still
  reachable. **Gotcha hit while building this:** the deadline/due-date window originally used
  `whereBetween`/`orWhere` directly against `deadline`/`due_date` — both plain `'date'` casts, which (per
  the `today_date` entry below) store full datetime precision under the hood, so an exact `orWhere('due_date',
  $today)` silently matched nothing. Fixed with `whereDate()` on both sides, same fix shape as that entry.
- **`project`** / **`group`** — `linked_project_id`/`linked_group_id` (nullable FKs, `nullOnDelete`, same
  safety pattern as `category_id` on `ScheduleEvent`/`EventTemplate` — deleting the target reverts the
  category to having no suggestion, it never breaks). Suggests the linked `Project`/`TaskGroup`'s own
  `activeTasks()->first()`, i.e. its normal next-up task.
- **`agenda_entry`** — `linked_agenda_entry_id` (nullable FK, `nullOnDelete`), a single homework or exam
  entry; suggested until `isDoneFor($user)`, then treated as empty.
- **`agenda_generic`** — no target column at all: counts every open Agenda homework entry
  (`AgendaEntry::visibleTo($user)->ofType('homework')->openFor($user)`) and nudges "Hausaufgaben
  erledigen · N offen", mirroring the existing cycle-1 ToDos nudge's shape — deliberately homework-only,
  not exams, matching the "HAs erledigen" framing this was asked for.
- **`text`** — `linked_text`, a free label with no backing record at all (e.g. "Zimmer aufräumen") for
  the thing that isn't in any list yet. Always suggests the same text; has no "done" state (see below).

**`TaskSuggestor::suggest()`** gained a `?EventCategory $category` parameter and a new tier, sitting right
after Notfallmodus (which still outranks everything) and before the cycle-1 ToDos nudge — so a category
link applies on **every** cycle of a session, not just the first. Each `task_source` branch returns `null`
the moment its target is empty, deleted, or (for `agenda_entry`) already done, and `suggest()` just falls
through to the normal generic tiers below — the existing "never a dead suggestion" contract extends
unchanged to a link that's run dry. New suggestion `kind`s (`category_group`, `category_agenda`,
`agenda_generic`, `category_text`) got their own branches in `schedule-strip-suggestion.blade.php`; `tasks`
and `project` links reuse the *existing* `task`/`project` kinds unchanged, since a pinned task or a linked
project's next task renders identically to what those kinds already showed.

**Settings' Kategorien card** — each Pomodoro-enabled category row grows a small link-status line
(`EventCategory::taskSourceLabel()`, e.g. "Wettkampfvorbereitung" or "3 Aufgaben"; `null` → "Keine
Aufgaben-Verknüpfung") that opens **`partials/category-link-sheet.blade.php`**, a bottom-sheet mirroring
`edit-sheet.blade.php`'s shape (`animate-rise`, no leave-transition) rather than the Alpine-store-driven
`project-picker-sheet.blade.php` pattern — this sheet needs several *server* computeds gated behind which
category is open (`Settings::$linkingCategoryId`, `linkingCategory()` eager-loading every possible target
relation at once), which the client-only-store pattern has no room for. Six chips (Keine / Bestimmte
Aufgaben / Projekt / Gruppe / Agenda / Text) switch which sub-picker shows via a **client-only** `x-data="{
reveal }"` seeded from the category's current `task_source` — chip clicks that can't commit anything by
themselves yet (Projekt/Gruppe/Agenda/Text — picking *which* one is a separate click) only update `reveal`
locally; "Keine" and "Bestimmte Aufgaben" commit immediately on the chip itself (`clearCategoryLink()` /
`setCategoryTasksMode()`), matching the rest of this card's immediate-save convention. Every write
(`linkCategoryToProject/Group/AgendaEntry/AgendaGeneric`, `saveCategoryLinkText`, `togglePinnedTask`) is
ownership-scoped through `auth()->user()->eventCategories()->findOrFail()` and starts by calling
**`EventCategory::clearTaskLink()`** (resets every `linked_*` column and detaches every pinned task) — the
one place the "genau eine Verknüpfungsart" rule is enforced, so switching from "Bestimmte Aufgaben" to
"Projekt" can never leave a stale pin or FK behind.

**Signature moment — the list-just-finished notice.** Completing the last active item in a category's
linked source while its session is running (or frozen awaiting a continue — anything past "Bereit, never
started") shows a quiet, self-dismissing line in the focus card where the suggestion normally sits:
"Wettkampfvorbereitung ist fertig." — then it fades back to normal on its own, no click needed.
`schedule_events.pomodoro_linked_notified` (boolean, reset to `false` by both
`PomodoroSessionService::start()` and `stop()` — a fresh session always gets a fresh chance to notify) is
the guard: `TaskBoard::linkedSourceNotice()` is a **pure, repeatable read** — `TaskSuggestor
::linkedSourceRemainingCount($category, $user)` (the same empty-check the suggestion tier already needs,
exposed as its own method) hits exactly `0` and the flag is still `false` — deliberately *not* an
event-driven push at the moment of completion, since the task that finishes the list can be completed from
any page (`ProjectPage`, `GroupPage`, the Zeitplan's own deadline-strip checkbox…), not only from the
dashboard that shows the focus card. A poll-based read sees it regardless of where the completion happened;
an event dispatched from `ManagesTasks::toggleComplete()` would only reach a browser tab that happened to
already be open on the dashboard at that exact moment. The notice is rendered once
(`x-init="setTimeout(() => $wire.dismissLinkedSourceNotice(), 4000)"` on the `<p>` itself) and that call
stamps the flag — an explicit, client-timed dismiss rather than a server-side "already shown" window,
mirroring `PrepareTomorrow::dismissPreparePrompt()`'s shape more than any polling mechanism elsewhere in
the app. **`text` links never fire this** — `EventCategory::taskSourceFinishedMessage()` returns `null` for
`text` (and for any link whose target no longer resolves), since free text has no "done" state to detect;
`linkedSourceRemainingCount()` likewise returns `null` (not `0`) for it, so the guard never even gets to
the message.

### Zeitplan-Eintrag-Aufgaben-Verknüpfung (built)

A companion to the category link above, one level more specific: an entry can bind **several** tasks to
**one occurrence** specifically — never to its `EventTemplate`, since a recurring Wednesday slot is about
something different every week. Works on either kind of entry (Termin or Kategorie block); only a
Pomodoro-enabled Kategorie block's link feeds the focus timer, but a plain Termin still carries the link
purely for visibility/navigation, since it can never run a Pomodoro session at all under this app's
architecture. Shipped first as a single `linked_task_id` FK, then revised in the same session to several
— **`schedule_event_task_links`** (`schedule_event_id, task_id, sort_order`, cascade both ways) is the
same shape as `category_task_links` for a category's own "Bestimmte Aufgaben" source, and
`ScheduleEvent::linkedTasks()`/`nextLinkedTask()`/`linkedTasksRemainingCount()` mirror
`EventCategory::pinnedTasks()` and friends directly.

- **`TaskSuggestor::suggest()` itself never changed** for this revision — it still takes a single
  `?Task $linkedTask` as its top tier (right after Notfallmodus, before the category-link tier). Only the
  *source* TaskBoard passes changed: `$session->linkedTask` (the old FK) became `$session->nextLinkedTask()`
  — the first still-open bound task in pick order, skipping completed ones without unpinning them, same
  rule as the category's pinned-tasks tier. `TaskBoard::linkedSourceNotice()` mirrors the same precedence
  (event link checked before category) but now waits for the *last* bound task, not the first —
  `linkedTasksRemainingCount() === 0` — firing "Die gebundenen Aufgaben sind fertig." once the whole list
  is cleared, exactly the "list emptied" shape the category's own pinned-tasks notice already had.
- **The picker** (`ManagesSchedule::eventTaskCandidates()`, in `schedule-event-form.blade.php`) stays
  anchored to **the entry's own `eventDate`, not "today"** (a Termin planned for three weeks out needs
  candidates relative to *its* date), and now also excludes whatever's already picked
  (`whereNotIn('id', $pickedIds)`) so a re-opened search never offers a duplicate. **Never offered while
  "Wiederholen" is checked** — a recurring block's template has no single date to anchor a link to.
  `saveEventForm()` re-checks ownership of every id in `eventLinkedTasks` immediately before persisting
  (not just at pick time in `toggleEventLinkedTask()`), then `sync()`s the whole ordered list onto the
  (possibly just-created) event in one call — pick order becomes pivot `sort_order`, i.e. suggestion
  order. The picked set lives in **form state, not the database**, until Save — same as every other field
  on this form (title, date, colour…), and deliberately unlike the category link sheet's immediate-save
  convention, since only this form already has its own Speichern/Abbrechen semantics for everything else.
- **Settings-lesson applied from the start:** the "+ Aufgabe verknüpfen" trigger and each picked chip's
  own remove button carry an explicit `aria-label` (naming the specific task on remove) and real visible
  CTA text — the category-link feature's entry point shipped without one, went undiscovered by a Runde-4
  simulation, and had to be fixed after the fact (see that section above). Verified here by reading the
  same accessibility tree that caught the earlier miss, both for the single-task and the revised
  multi-task version.
- **Signature moment — tap once to peek, tap again to go.** A linked block's icon
  (`schedule-event.blade.php`) sits in the title row next to the (unrelated, purely decorative) Pomodoro
  clock icon. A tap swaps the block's own title for the **next open** linked task's title for **2 seconds**
  (`x-data="{ revealed, _t }"` on the title `<p>`, `x-text` ternary between the two, both strings passed
  through `@js()` — never raw Blade interpolation into a JS expression, since a task title can contain
  quotes) — the same "armed window" shape as this app's destructive double-click confirms, repurposed here
  for a reveal instead of a delete. A small "+N" badge (outside the revealed/not-revealed swap, so it stays
  visible either way) names how many *other* open tasks are also bound, computed from one relation load
  (`$event->linkedTasks`, filtered/counted in PHP) rather than a query per helper. A second tap *within*
  that window calls `ManagesSchedule::navigateToLinkedTask()`, which resolves the event's own
  `nextLinkedTask()` (the same one just revealed, not "whichever was pinned first") and
  `redirectRoute('app', ['task' => $id], navigate: true)`s straight to the board —
  `TaskBoard::mount()` reads `?task=` the same best-effort way `Schedule::mount()` already reads `?event=`
  (silently ignored if stale/foreign/missing, never a broken page load) and opens the task's edit sheet on
  arrival via the existing `ManagesTasks::startEdit()`. No tap ever partially navigates —
  `@pointerdown.stop`/`@click.stop` on the icon keep both the reveal and the eventual redirect fully
  isolated from the card's own drag-move gesture underneath it. Completing the revealed task live-advances
  the icon to the next one and drops the "+N" count on the very next render — verified directly (not just
  by test) by completing a bound task mid-session and watching the block's own accessible name change.

### Planer (built)

An automatic scheduler, on top of the manual linking above: distributes open Tasks/Todos/Agenda
homework into upcoming Pomodoro-enabled work-blocks, so it's visible well before a deadline sneaks
up whether everything will actually get done in time — the motivating scenario was a stuffed
competitive-sport schedule where a day silently assumed free turns out to already be gone. **Default
off** (`users.planner_enabled`, Settings' Planer card, immediate-save toggle same shape as Hausaufgaben-
Vorschau) — an automatic planner is exactly the kind of thing that can feel intrusive to someone who
didn't ask for it. Off = zero footprint everywhere: `WorkPlanner`'s every public method no-ops on the
flag, the `/app/planner` route bounces back to the board (`Planner::mount()`), and the nav pill in the
"Mehr" dropdown simply isn't rendered.

- **`App\Services\WorkPlanner`** — stateless (all static methods, like `TaskSuggestor`/`PomodoroCycle`).
  `HORIZON_DAYS = 14` (fixed, not user-configurable). Only Pomodoro-enabled category block occurrences
  are ever eligible placement targets — never raw free/unscheduled time. A prior, unshipped prototype of
  this whole app auto-filled *all* free time and felt oppressive; restricting to explicitly-designated
  work-blocks is the deliberate fix, carried into this build from the start.
  - **Eligible dated items**: board/project/group Tasks (`list` in `todos|tasks|projects` — never
    `inbox`, untriaged is out of scope) with a `deadline`/`due_date`, plus open `AgendaEntry` homework
    (never exams — there's no "work session" concept for an exam itself). A homework entry already
    promoted into a live Task (via an earlier plan or `TaskBoard::promoteHomeworkToday()`) is represented
    by that Task instead, never listed twice.
  - **Effective deadline** — hard `deadline` minus a 2-day buffer (`DEADLINE_BUFFER_DAYS`), or the soft
    `due_date`/homework date as-is; the earlier of the two when a task has both. The *raw*, unbuffered
    date is carried alongside (`deadlineInfoForTask()` returns both) purely for display — showing a user
    a deadline two days earlier than the one they actually typed in would just be confusing.
  - **Undated backlog** (`fillerItems()`) — lowest priority, only ever used to top up a block's leftover
    space once every dated item has had its chance (`urgency = 0` in the scoring below naturally achieves
    this, no separate pass needed). Mirrors `TaskSuggestor`'s own tiering philosophy so this planner isn't
    a second brain with different opinions than the rest of the app.
  - **Scoring** (`score()`): `urgency × 1.0 + fit × 0.5 + (is_important ? 0.15 : 0)`. `urgency =
    (HORIZON_DAYS − daysUntilDeadline) / HORIZON_DAYS`, measured from **today**, not from whichever block
    is currently being filled — 0 at the edge of the horizon, 1.0 due today, climbs past 1 the more
    overdue something already is. `fit = duration / remainingBlockMinutes` (only eligible if
    `duration ≤ remaining`; never place something into a block on/after its own deadline). Blocks are
    walked chronologically, greedily taking the best-scoring candidate that still fits, until full or
    nothing left fits — this naturally front-loads urgent work into the earliest block that can take it.
  - **Repair pass** (`attemptRescue()`) — bounded, not a general solver: for each still-unplaced dated
    item, first try bumping a filler occupant out of an earlier eligible block (free, nothing to
    relocate), then try bumping a genuinely *less* urgent dated occupant, relocating it to a later block
    it still meets its own deadline in. Cleans up greedy's own local-optimum mistakes (a decent-fit,
    less-urgent item can otherwise win a block's only slot before a more urgent item that needed it is
    even considered); anything still unplaced after this is a genuine, honestly-reported conflict.
  - **Persistence reuses `schedule_event_task_links`** (the Zeitplan-Eintrag-Aufgaben-Verknüpfung pivot
    above) rather than a parallel data model — a `source` column (`'manual' | 'auto'`, default `'manual'`
    for backfill) distinguishes a human pick from an algorithm one. `reconcile()` (passive) rebuilds only
    the `'auto'` layer from scratch every time — auto placements are cheap and *not* sticky by design, so
    there's no diffing old-vs-new, just replace them wholesale around whatever `'manual'` rows already
    anchor a block. `regenerate()` ("Neu planen") is the one action that discards `'manual'` too — a full
    wipe-and-replan, gated behind the app's usual armed double-click since it's the one thing here that
    can throw away a deliberate choice (also literally the answer to "undo an accidental manual move").
  - **Homework promotion** — `schedule_event_task_links.task_id` can only point at a `Task` (a real FK to
    `tasks`), so a homework item is promoted into one (mirroring `promoteHomeworkToday()`'s shape: title
    `"{subject}: {title}"`, `deadline` = the homework's date, `duration_minutes` carried over) at the
    moment it's actually placed, not during scoring — so a plan that gets rearranged during the repair
    pass never creates a task it then immediately discards.
  - **`conflicts(User)`** — every dated item with no link (`'manual'` or `'auto'`) to a block on or before
    its own effective deadline. A manual placement resolves a conflict exactly the same as an auto one,
    even if the algorithm itself would never have chosen that slot — the check is "is it covered", not
    "did the algorithm do it".
- **Estimated duration — `tasks.duration_minutes` / `agenda_entries.duration_minutes`** (nullable,
  minutes). Deliberately never required: Inbox tasks are never asked (out of scope for planning anyway),
  and a missing estimate just falls back to a default *for WorkPlanner's own math only* (never written
  back) — 10 min for `list=todos`, 25 min for `list=tasks`/`projects`/homework (`DEFAULT_DURATION`,
  `DEFAULT_HOMEWORK_DURATION`). The UI affordance is the existing card-face quick-date ghost pattern,
  reused rather than a new mechanism: a "~ Dauer" ghost placeholder shows on `task-card.blade.php`/
  `task-card-mobile.blade.php` (deliberately not `project-task-card.blade.php` — that card has no
  interactive popover mechanism at all yet, so duration entry there goes through the edit sheet only)
  whenever `list ≠ inbox` and no estimate is set; tapping it opens a small popover with quick-pick chips
  (10/15/25/45/60/90 min) calling `ManagesTasks::quickSetDuration()` — same bypass-the-edit-sheet shape as
  `quickSetDates()`. The ghost *is* the "you're missing an estimate" warning; no separate indicator exists
  because none is needed. Also editable from the full edit sheet (`editDuration`) and, for homework only,
  the Agenda entry form (`Agenda::$formDuration`, hidden for exam entries — they're never planner-eligible).
- **`App\Livewire\Planner`** (`/app/planner`, `route('planner')`) — deliberately narrow: only about which
  task/todo/homework happens in which upcoming block, not a general schedule editor (no creating
  Termine/categories, that stays on Zeitplan/Wochenplan). `use ManagesSchedule` directly, purely for
  `moveEvent()`/`resizeEvent()` (repositioning a block, via the *existing* event-edit-sheet — see below,
  not a grid drag) — reads/writes the exact same `ScheduleEvent` data as the real Zeitplan, so a block
  moved here really is moved there too, no shadow schedule to drift out of sync.
  - **`reconcile()` is called exactly once, from `mount()`, not from the `blocks()` computed property.**
    This was a real bug caught by a failing test during development: a `#[Computed]` property re-evaluates
    on every render, and Livewire re-renders the whole view after *every* action on this page — reconciling
    from inside it meant an `unassignTask()` call was immediately undone by the very next render's reconcile
    (the just-removed task was undated-backlog-eligible, so it snapped straight back into the vacated slot).
    Once per full page load is enough to satisfy "never stale when you're actually looking at it" without
    fighting the action the user just took. See `PlannerPageTest::test_unassign_task_removes_its_link`.
  - The page is a **list of day sections and block cards**, not a percentage-positioned time grid like
    Zeitplan/Wochenplan — so block repositioning reuses the *event-edit-sheet* (`startEditEvent()` +
    `@include('livewire.partials.schedule-event-form')`), not the `scheduleEvent` Alpine drag-move
    component (that component's math is grid-height-relative and doesn't apply to a list layout). This is
    a deliberate, disclosed deviation from an earlier assumption that the grid gesture would be reused
    verbatim — precise date/time editing fits a list better than an imprecise drag would have anyway.
  - **`reorderBlock(blockId, taskIds)`** — drag & drop persistence, same "send the destination zone's full
    id order" shape as `TaskBoard::reorder()`. Every id landing here (whether reordered in place or
    dragged in from another block) is stamped `'manual'`, and any *other* block's link for that task is
    dropped first, so a cross-block move can't leave it doubly-linked. `unassignTask()` is the small "×"
    on each chip, a affordance beyond what was explicitly asked for but cheap and consistent with the
    rest of the app's card-face quick-actions.
  - **`window.plannerBlockSortable`** (`resources/js/app.js`) — one Sortable instance per block container,
    all sharing one group name so a chip can move between blocks as well as reorder within one. Modelled
    on `groupDropZone`'s idiom, not a verbatim reuse of it: nothing existing already does "drag between
    several named containers with cross-container reassignment".
  - The conflict banner is **always visible when non-empty** — this, not the drag-and-drop, is the
    feature's actual point, per explicit direction during planning. When empty: a single calm line,
    `"Alles passt bis {horizon end}."` — deliberately not styled `forest`/celebratory (that's reserved for
    Fortschritt's streak system) or accompanied by any animation; the reward here is quiet certainty, not
    a dopamine hit. Each conflict card offers exactly two resolutions — `"Zeitplan öffnen"` (link to
    `route('schedule')`) and `"Deadline ändern"` (`route('app', ['task' => $id])` for a promoted item,
    `route('agenda')` for an unpromoted homework one) — doing neither is itself a valid choice ("accept
    it'll be late"), so there's deliberately no third button and no persisted "dismissed" state.
- **Pomodoro integration** — `PomodoroSessionService::start()`/`transition()`/`skipBreak()` each call
  `WorkPlanner::reconcile()` before their own logic, and `handleTick()` calls it once after a real phase
  advance (outside the row lock, alongside the existing post-transaction notify calls; never for the
  "freeze awaiting a continue" branch, since nothing about which block shows what changes there). This
  keeps the linked-task shown on a live focus session accurate — start a session, and by the time you
  press it the block's plan reflects anything that changed since it was last touched — without hooking
  into every task/schedule mutation site across the app (task creation, random edits elsewhere): all four
  of these are already single, centralized choke points this service owns, covering both the dashboard
  and the API (`ScheduleEventController`) and the per-minute cron cascade (`AdvancePomodoroPhases`) for
  free. Confirmed end-to-end (not just at the service level) in `PlannerPomodoroIntegrationTest`.
- **Later, deliberately not built**: splitting a task across multiple blocks/sessions (a task always
  occupies one contiguous slot in one block), a configurable horizon or scoring weights, auto-planning
  exam entries, push notifications when a new conflict appears, a persisted "snooze this conflict" state,
  drag-to-place directly from the conflict banner into a block (the two text links cover resolution).

### Wochenplan (built)

A dedicated editing surface for the recurring side of the Zeitplan — the part of a week that's the
same every week (Schule, Training) shouldn't have to be replanned inside a real calendar week, and
until this feature there was nowhere to see or edit it as a whole. The materialisation mechanism this
builds on already existed (`EventTemplate` + `ScheduleEvent::materializeRange()`, see Schedule above) —
this feature is a proper front end for it, plus the one thing that genuinely didn't exist: switching
the whole week plan off for specific dates (Ferien, sick days).

- **`App\Livewire\WeekPlan`** (`/app/weekplan`, `route('weekplan')`) is an abstract Mon–Sun canvas — no
  calendar dates, just ISO weekdays 1–7. `templatesByWeekday()` buckets every recurring `EventTemplate`
  by the weekdays in its `recurrence` mask (a template on "1,3,5" appears in three buckets — same
  template id, rendered three times). Its mutation methods (`moveEvent`/`resizeEvent`/
  `quickCreateCategoryBlock`/`quickCreateTermin`/`startEditEvent`/`saveEventForm`/`deleteEvent`) are
  deliberately **named to match `ManagesSchedule`'s own method signatures**, even though they operate on
  `EventTemplate` (weekday-keyed) instead of `ScheduleEvent` (date-keyed) — this lets the existing
  `scheduleEvent`/`scheduleDraw` Alpine components (drag-move, drag-resize, draw-to-create, tap-to-edit)
  be reused **completely unmodified**. Those components only ever forward an opaque id/date string to
  `$wire`; feeding them a weekday number ("1".."7") instead of a date string costs nothing since neither
  component interprets that string itself. No new gesture code exists anywhere in this feature.
- **Editing an existing template propagates to near-future materialised occurrences.** Before this
  feature, a template's shape was fixed at creation (the Zeitplan's own "Wiederholen" checkbox only ever
  *creates* a template, never edits one) — so nothing had to reconcile an edit against rows already
  materialised for the current/next week. `WeekPlan::refreshMaterializedOccurrences()` (called after
  every `saveEventForm`/`moveEvent`/`resizeEvent`) finds that template's still-linked, not-cancelled,
  **strictly future** (`date > today`) occurrences and either updates them to the new title/colour/time,
  or — if the edited weekday mask no longer includes that occurrence's weekday — deletes the row outright
  (not tombstoned; `materializeRange()`'s dedup is presence-based, so a later re-added weekday
  regenerates cleanly with nothing left to work around). **Today's occurrence and every past one are
  deliberately never touched** — a template edit must not rewrite history or disturb something already
  under way. Without this, editing a block on the Wochenplan would silently not apply to a week that had
  already been materialised by opening the Zeitplan.
- **Mobile has no server-side day paging.** Unlike the Zeitplan (which pages through an unbounded range
  of real dates and must round-trip), the Wochenplan only ever has seven days, all loaded up front — so
  the mobile view renders all seven day-columns and shows one at a time with a client-only
  `x-data="{ focused: N }"` wrapper, no Livewire call involved in paging. **The wrapping container uses a
  fixed `h-[calc(100dvh-4rem)]`, not `min-h-`** — copied from the Zeitplan's own mobile view on purpose:
  the blocks inside position themselves with percentage `top`/`height`, which only resolves against a
  *definite* ancestor height. `min-height` lets the container auto-size to its content instead, which
  silently breaks every percentage down the chain (found by comparing computed styles against the
  Zeitplan's identical-looking markup, which works, until the `min-h-`/`h-` difference turned out to be
  the only difference).
- **Pausing** (Ferien, sick days, …) is **`App\Models\SchedulePause`** (`schedule_pauses`: `user_id,
  date, note?`, one row per date, unique per `(user_id, date)`) — deliberately one row per day rather
  than a date range, so a single day inside a longer pause can be switched back on independently (the
  requirement was explicitly "für einzelne Tage", not just whole ranges). `SchedulePause::pauseRange()`
  bulk-inserts (`insertOrIgnore`) one row per date in a Von–Bis span; `collapseToRanges()` re-groups a
  date-ascending list back into consecutive `[start, end]` runs for display (a gap of even one day starts
  a new range), so the "Pausen & Ferien" card can show "14.7.–3.8." instead of fifteen separate rows,
  while every row inside a range still expands into individual day-chips.
- **The pause is enforced in exactly one place: `ScheduleEvent::scopeVisible()`.** It now also excludes
  any template-sourced row (`template_id` not null) whose `date` has a matching `SchedulePause`, via a
  `whereNotExists` correlated subquery — manually placed events (`template_id` null) are always exempt,
  since a pause suspends the *template*, never something typed in by hand for that specific day. Every
  consumer that already reads through `visible()` — the Zeitplan, the dashboard's focus timer
  (`TaskBoard::scheduleToday`), both push-notification commands, `PrepareTomorrow` — is correctly
  pause-aware for free, with no changes to any of that code. **Pausing never touches `ScheduleEvent` rows
  at all** — it only ever inserts/deletes `SchedulePause` markers. This is deliberate: it means pausing
  is trivially reversible (un-pausing shows exactly what was there before, with zero regeneration logic),
  and a date that's paused before ever being viewed/materialised just never gets template rows in the
  first place, without `materializeRange()` needing to know pauses exist. `WeekPlan::unpauseDate()`/
  `unpauseRange()` additionally call `materializeRange()` for the freed date(s) immediately, so the normal
  blocks reappear without waiting for a separate Zeitplan visit.
- **The Zeitplan shows a quiet, non-alarming hint** on a paused day (a small "FERIEN" label under the
  day number, desktop; "· Ferien" appended to the date line, mobile) plus one summary line above the grid
  ("`N` Tage diese Woche pausiert — der Wochenplan füllt sie nicht. Verwalten →") linking back to
  `/app/weekplan` — an empty day should read as intentional, not broken.
- **Two signature moments, different budgets.** The header line ("So sieht dein normaler {Wochentag}
  aus.") is deliberately *not* the protected signature moment — just a cheap, good default that costs
  nothing and needs no special handling in review. The real signature moment is the **ripple**: saving a
  block that spans more than one weekday dispatches a browser event
  (`$this->dispatch('weekplan-ripple', days: [...])`) that a small `Livewire.on('weekplan-ripple', …)`
  listener in `resources/js/app.js` picks up, staggering a `.weekplan-ripple` CSS class (a 650ms
  `box-shadow` wash, `app.css`) across each affected day column roughly 70ms apart — a visible, immediate
  answer to "does this really apply to all of these days now" at the exact moment that becomes true,
  rather than something you'd otherwise only trust an abstract chip list about. A single-weekday save
  never dispatches it — nothing to visually connect.
- Not touched by this feature: the API/Shortcuts, a spontaneous "pause just today" shortcut from the
  Zeitplan itself (management stays centralised on the Wochenplan page), and overlapping-block layout on
  the grid (a pre-existing Zeitplan limitation, not newly solved here).

### Agenda — Hausaufgaben & Prüfungen (built)
- A deliberately standalone page (`/app/agenda`, `route('agenda')`) for school deadlines — homework and
  exams — kept fully isolated from Task/Project/Schedule for now: no FK/relation, and it doesn't surface
  on the board, in Vorbereitung, or in Notfallmodus. A later integration isn't ruled out, just not part of
  this pass.
- **`App\Models\AgendaEntry`** — `user_id, agenda_space_id?, type(homework|exam), subject, title, notes,
  date, timestamps`. `subject` is free text (e.g. "Mathematik") — no separate Subject model yet.
  `dateLabel()`/`isOverdue()` mirror `Task::effectiveDateLabel()`/`Project::deadlineLabel()`
  (heute/morgen/Wochentag/d.m./überfällig), deliberately duplicated rather than shared, the same way those
  two already are with each other. `agenda_space_id` null = private (what every entry was before sharing
  existed); set = visible to that whole class. Scopes `forUser/visibleTo/inSpace/ofType/openFor/doneFor/
  withCompletionState/ordered` — see "Agenda — Klassen teilen" for why "done" is no longer a column.
- **`App\Livewire\Agenda`** (class-based, `#[Layout('layouts.app')]`) — its own component, no shared trait
  with `ManagesTasks`/`ManagesSchedule`. One form (`partials/agenda-entry-form.blade.php`) handles both
  create and edit (`editingId` null vs set), the same bottom-sheet/modal shell as
  `schedule-event-form.blade.php`, with the same kind of Hausaufgabe/Prüfung pill toggle. Every mutation
  resolves through a private `userEntry()` helper (`auth()->user()->agendaEntries()->findOrFail($id)`),
  mirroring `TaskBoard::userTask()` — a foreign id is simply invisible, never trusted.
- **Fach combobox** — the Fach field is free text with suggestions, not a fixed picker: a
  `#[Computed] existingSubjects()` (distinct subjects already used, `forUser`-scoped, sorted) feeds an
  Alpine dropdown under the input. Typing filters the suggestions client-side (no round trip per
  keystroke); picking one calls `$wire.set('formSubject', s)`; typing something that matches nothing just
  shows a "Neues Fach — einfach weitertippen" hint and free text is used as-is — there's no separate
  "neues Fach" step. The filter reads `$wire.formSubject` directly inside an Alpine getter (reactive
  without `.entangle()`, same pattern as `edit-sheet.blade.php`'s `x-show="$wire.editList === ...'"`)
  instead of a local Alpine copy, and the wrapper carries `wire:key="agenda-subject-field-{{
  $this->existingSubjects->count() }}"` — without that key, the `subjects: @js(...)` array baked into
  `x-data` would freeze at first mount (the un-keyed-`x-data`-frozen-across-a-morph trap, see *Known
  Issues*) and a subject added in one save would never appear in the dropdown until a full page reload;
  keying on the count forces Alpine to remount and re-read a fresh array exactly when the list changed.
  **Tab** accepts the top suggestion: a `@keydown.tab` handler on the Fach input (skipped when
  `shift.key` — backward tabbing is untouched) always `preventDefault()`s while the dropdown is open,
  fills `formSubject` with `filtered[0]` only if there's a non-empty query with a match (an empty field
  or a no-match query just advances focus, nothing is force-picked), then focuses `#agenda-form-title`
  directly via `document.getElementById` — `$refs` doesn't reach across the sibling field's separate
  `x-data` scope, so a plain id lookup is simpler than trying to thread a ref through. Always taking
  control of Tab (not just when autocompleting) avoids a race against Alpine's reactive close of the
  dropdown, since a still-visible suggestion button would otherwise sit earlier in native tab order than
  Titel.
- List view (`partials/agenda-entry.blade.php`): sorted by date ascending, a type badge (Hausaufgabe =
  forest, Prüfung = overprint) and a date badge (contour, signal once overdue) per row, completed entries
  collapsed behind a client-only Alpine disclosure ("N erledigt · anzeigen"). Delete uses the armed
  double-click pattern (never `confirm()`), same as everywhere else in the app.
- Nav entry in `layouts/app.blade.php`, same pill/mobile-dropdown treatment as Vorbereiten/Zeitplan/Notfall.
- No push notifications, no API endpoint (Sanctum) yet — purely a standalone Livewire page in this pass.

### Agenda — Klassen teilen (built)

The one multi-user surface in the app (see §1). A class shares **one list of homework and exams**; nothing
else is shared, and a private entry stays private.

- **`App\Models\AgendaSpace`** — `owner_id, name, invite_code, timestamps`. `belongsToMany User` (members,
  pivot `agenda_space_user`), `hasMany AgendaEntry`. Membership is a pivot, not a column on `users`, because
  one person can be in several spaces at once (a class plus a study group). `generateInviteCode()` produces
  6 characters from an alphabet **without `O/0` and `I/1`** — people read these off someone else's screen —
  and re-rolls on the (vanishingly unlikely) collision rather than letting the unique index throw.
  `findByInviteCode()` normalises case and punctuation, so `k7m 4xq` finds `K7M4XQ`.
- **"Done" is per person, not per entry** — `agenda_entry_completions` (`agenda_entry_id, user_id`, unique)
  holds one row per person who ticked something off. A class entry is finished by 22 people independently
  and nobody may clear it for anyone else, so it cannot be a column. **Private entries go through the same
  table** rather than keeping `is_done`: a private entry simply has exactly one person who can complete it,
  and unifying keeps "what's still open for me" a single `whereDoesntHave` instead of a branch over two
  mechanisms. `AgendaEntry::isDoneFor(User)` prefers the `done_for_me` flag that
  `scopeWithCompletionState()` selects alongside the row (`withCount` + `withExists`), so a 40-row list
  stays 3 queries instead of 80.
  > **`agenda_entries.is_done` still exists but is dead.** The completions migration backfills it and then
  > nothing reads it; it is removed from `$fillable` and `casts()` so an accidental write fails loudly.
  > Dropping the column is a separate, later commit (CLAUDE.md §8: two steps) — tracked in `TODO.md`.
- **`AgendaEntry::scopeVisibleTo(User)` is the only gate.** Own private entries **or** entries in a space the
  user belongs to. `Agenda::visibleEntry()` (formerly `userEntry()`) re-resolves every id through it, so a
  stranger's private entry and a class you never joined are equally invisible — and **every member may edit
  or delete what their class posted**, which is the agreed rule, and falls out of the scope for free.
  Writes have their own boundary: `formSpaceId` / `agendaSpaceId` are validated with `Rule::in($spaces)`, so
  an entry can never be filed into a class you're not in.
- **`App\Livewire\Concerns\ManagesAgendaSpaces`** — create/join/leave/delete/regenerate, shared by `Agenda`
  (the whole "Klassen" sheet, `partials/agenda-spaces-sheet.blade.php`) and `JoinAgendaSpace`, so the join
  rules live in one place. **Leaving is never destructive:** entries stay with the class they were written
  for; an owner who leaves hands ownership to the longest-standing remaining member; the last member out
  deletes the space, at which point `nullOnDelete` turns its entries back into private ones rather than
  discarding them. Deleting a space for everyone is the one owner-only action.
- **`App\Livewire\JoinAgendaSpace`** (`/app/agenda/join/{code}`, `route('agenda.join')`) — joins on a
  **button press, never on the GET**: a link pasted into a class chat gets fetched by link previewers long
  before a human clicks it. Unknown code and already-a-member each get their own state.
  > **`RegisteredUserController` redirects to `intended()`** because of this page. "Classmate without an
  > account follows the invite link" is the normal way people join, so *registration* — not just login —
  > has to hand them back to where they were going. Covered by a test.
- **List UI** — one date-sorted stream with a class badge per row, `von <name>` when a classmate wrote it,
  and a quiet class-progress indicator: a `h-1` bar (same visual language as `project-card`'s — `bg-line`
  track, `bg-forest` fill) **stacked over** its `5/22` count rather than beside it. Stacked because this row
  is a single dense line: side by side costs ~38px of the truncating title column, the stack ~10px and no
  extra row height (measured at 375px). Shown at every width — hiding it on mobile would hide it on the
  device this gets used on — and deliberately understated, since it is context, not a leaderboard, and must
  never outweigh the due date. Private entries get none: there is nothing to be "5 of 22" about when exactly
  one person can finish it. A second, quieter chip row filters by
  class, with a toggle between sorting by date (default — "what is due next" is the actual question) and
  grouping into one section per class. **The whole row only renders once the user is in a class**, so a solo
  Agenda looks exactly as it did before this feature.
- **Creating** — a "Für" pill row (`Nur ich` / each class) in both the entry form and QuickCapture's agenda
  target, again only rendered for someone actually in a class. `Agenda::defaultFormSpaceId()` (and
  QuickCapture's equivalent `defaultAgendaSpaceId()`) pick the default: filtered to one specific class, that
  class; filtered to "Nur ich", private; otherwise (including QuickCapture, which has no filter to follow)
  private too, **unless the user belongs to exactly one class**, in which case that one class — "which
  class" has only one possible answer, so there's nothing left to guess wrong. Two or more classes is still
  ambiguous and stays private. This landed after a user-simulation test caught the previous unconditional
  "otherwise private" default silently filing a brand-new class member's first-ever entry as private while
  they were looking at "Alle Räume" — the view joining a class lands you on, and exactly the moment sharing
  is most likely intended. The remaining two-or-more-classes gap is mitigated, not solved: the entry form's
  "Für" row always names the current choice in a caption line, private included (previously only the shared
  state had one), so an accidentally-private pick is never the one that stays quiet. QuickCapture's
  confirmation line names the class (`→ Agenda · Klasse 4b`) — sharing is its one capture with a consequence
  beyond your own list. Fach suggestions now read from every *visible* entry, so a classmate's "Französisch"
  autocompletes for everyone.

### Agenda — private Notizen auf geteilten Einträgen (built)

A shared entry's `notes` field is genuinely shared — any class member can read and edit it (see
"Agenda — Klassen teilen" above). This adds a second, independent note per entry that only the
viewing user ever sees or writes: **`agenda_entry_notes`** (`agenda_entry_id, user_id, notes` text,
unique per pair, `cascadeOnDelete` both FKs) — same reasoning as `agenda_entry_completions`: a
private note is inherently per-viewer, so it lives in its own table rather than a column on the
shared row. Unlike that table this one carries a value, not just presence, so
`AgendaEntry::setPrivateNoteFor()` deletes the row on an empty/cleared note rather than storing a
blank one (mirrors the shared `notes` field's own trim-to-null convention). There is deliberately
**no relation on `AgendaEntry` that returns every user's private notes at once** — the only way to
reach the table is `privateNoteFor(User)`/`setPrivateNoteFor(User, ?string)`, both always scoped by
the given user; rendering a list eager-loads the *current* user's own value via
`scopeWithPrivateNoteFor()`, a correlated subquery (`addSelect`) mirroring
`scopeWithCompletionState()`'s one-query idea rather than an eager-loaded `hasMany`, so there's no
code path that could ever load a classmate's note by accident.

- **Only offered once the entry is actually shared** (`formSpaceId !== null` in `Agenda.php`/the
  form partial) — a "Nur ich" entry already has exactly one viewer, so a second private field on it
  would just duplicate the note above it.
- **Edited inline in the same create/edit sheet** as the shared note
  (`agenda-entry-form.blade.php`), directly below it — not a separate screen. It doesn't exist as a
  visible field until you tap a small "+ Eigene Notiz · nur du" ghost link (the same ghost-reveal
  pattern the board uses for a task's own date); the first keystroke turns the ghost into a real
  textarea, and from then on a small dot-marked disclosure appears next to that entry in the list
  (`agenda-entry.blade.php`) — the one lasting, personal mark this feature leaves behind.
- **The shared Notiz field's own label gets a suffix** ("· sichtbar für die ganze Klasse") whenever
  the entry is shared, right where you're about to type. Added after a Round-4 user-simulation (see
  the feature-building process) showed a real user correctly avoided writing a personal note into
  the shared field — but also never discovered the private one at all, and worked around the goal
  entirely via an unrelated existing feature (promoting the homework into a personal task and using
  *that* task's own notes) instead of ever finding this one. Both labels now name which note is
  which before you write into either — not only after opening the private one.
- Switching a shared entry's "Für" back to "Nur ich" **does not delete** an already-saved private
  note — `saveEntry()` simply skips writing to `agenda_entry_notes` while `formSpaceId` is null, on
  the theory that a "Für" toggle isn't the same action as clearing the field. The note picks back up
  if the entry is ever shared again.

Not touched by this feature: QuickCapture's `agenda` target (it has no notes field for agenda
entries at all, shared or private), Markdown rendering (private notes stay plain text, matching the
shared note), the dashboard homework-preview strip.

### Agenda — Präsenz & Mitgliederverwaltung (built)

Who is in a class, and who is looking at it right now. Both only exist because the agenda is shared; nothing
here applies to the single-user rest of the app.

- **Presence is polled, not pushed.** `users.last_seen_at` + `POST /app/heartbeat`
  (`App\Http\Controllers\PresenceController`, `route('presence.heartbeat')`), beaten once a minute by a small
  block at the bottom of `resources/js/app.js`. Real presence channels would mean **Laravel Reverb plus a
  long-running daemon** on the production box, and this project has deliberately avoided both — there isn't
  even a queue worker (§9: cron is the only background requirement). One tiny POST a minute answers the same
  question for a class of ~25.
  - **Gated on `document.visibilityState`**, and the interval is torn down when the tab goes to the
    background. That's the whole point: "online" has to mean *using the app*, not *left a tab open on
    Tuesday*. A backgrounded tab goes stale by itself.
  - **`User::PRESENCE_TTL_SECONDS` is 150**, not 60 — deliberately longer than the beat interval so one
    missed beat plus latency doesn't flicker someone offline mid-look.
  - **`User::touchPresence()` writes through the query builder** (`->toBase()->update()`), so a beat per
    minute per open tab fires no model events and does **not** bump `updated_at`. Having recently looked at
    the app is not a change to the account.
  - **`isOnline()` compares raw UTC to raw UTC** on purpose. This is elapsed time, not a wall-clock reading,
    so `timezone_offset` must not enter into it — same reasoning as the Pomodoro countdown above.
    `lastSeenLabel()` is hand-rolled German ("gerade eben" / "vor 5 Min" / "vor 3 Std" / "vor 2 Tagen")
    rather than `diffForHumans()`, for the same reason `Task::effectiveDateLabel()` is: the app's locale
    isn't German and these are UI copy.
  - **Opting out stops the recording, not just the display.** `users.show_presence` (Settings → Allgemein,
    rendered only for someone actually in a class — elsewhere it's a switch over nothing);
    `Settings::toggleShowPresence()` also clears any timestamp already held, and `touchPresence()` returns
    early. Someone turning this off is asking not to be tracked, not merely not to be shown. Default is
    **true**: an always-empty presence list reads as a broken feature, and the only people who can see it
    are classmates in a space the user chose to join.
  > **`User` declares `protected $attributes = ['show_presence' => true]`** mirroring the DB default, and it
  > is load-bearing. Without it a freshly created user carries `null` there until reloaded (the fresh-model
  > gotcha in §10) — `null` is falsy, so the first heartbeat after registration was silently skipped, and
  > `toggleShowPresence()` computed `! null === true` and switched the setting *on* when the user asked to
  > turn it off. Caught by tests, but it was a real bug, not a test artifact.
- **Member management is master/detail inside the existing Klassen sheet**, not a second modal stacked on the
  first and not a new route: `$managingSpaceId` null = the list of classes, set = that class's members, with
  a back arrow in the sheet header. `openMembers()` resolves the id through the user's own memberships, so a
  class you're not in never even opens. Deleting or leaving the class being managed drops back to the list,
  so the sheet never sits on a view of a space that's gone.
  - Each class row carries a live **"N online"** count that doubles as the way in. `spaces()` therefore
    eager-loads `members` — the count is a TTL comparison per member, not something SQL can count.
  - The member list **sorts online first, then alphabetically** (in PHP, since "online" isn't a column):
    "who is here right now" is the question the list exists to answer, so it shouldn't need scanning. A
    member who opted out gets **no label at all** rather than "offline" — printing "offline" would leak the
    difference between *away* and *asked not to be tracked*.
  - `wire:poll.30s.visible` keeps it fresh. **The `.visible` is not optional:** the sheet stays in the DOM
    with `display: none` when closed, so without it a shut panel would keep polling forever.
  - **`removeMember()` and `transferOwnership()` are owner-only** — unlike editing entries, which any member
    may. A classmate fixing a typo is routine; one classmate throwing another out of the shared agenda is
    not. Both go through `ownedSpace()` + `$space->members()->findOrFail()`, so an id for a non-member 404s
    like every other id in this app (`abort()`'s `HttpException` does *not* propagate out of a Livewire
    action the way `ModelNotFoundException` does — that's why it isn't `abort_unless`). Removing keeps the
    entries that person wrote with the class, exactly as leaving does; the owner can't remove themselves
    (that's "Verlassen", which hands ownership over properly). `transferOwnership()` exists so that leaving
    isn't the only way to pass on the admin role.

### Dashboard — Hausaufgaben-Vorschau & Tagesfokus-Brücke (built)

A small, deliberately narrow bridge between two systems this app otherwise keeps isolated (see
"Agenda — Hausaufgaben & Prüfungen" above): Agenda homework and the board's Today focus.

- **The preview strip** (`partials/homework-preview-strip.blade.php`, included from `task-board.blade.php`
  on both desktop and mobile, `TaskBoard::homeworkPreview()` → `AgendaEntry::homeworkPreviewFor()`) shows
  open homework due within `AgendaEntry::DASHBOARD_PREVIEW_WEEKDAYS` weekdays as a horizontally-scrolling
  row of compact cards — date badge, subject, title, expandable note, and its own "mark done" checkbox
  (`toggleHomeworkPreviewDone`). Gated by `users.homework_preview_enabled` (default true, Settings toggle);
  zero footprint when off or nothing's due, same pattern as every other optional dashboard card.
- **Promoting a card into Today** — desktop drags a card into a Todos/Tasks column's Heute zone; mobile
  swipes a card **up** (deliberately vertical, not the horizontal swipe used everywhere else for tasks —
  the strip itself scrolls horizontally, so a horizontal swipe-to-act on a card inside it would fight that
  native scroll on every touch; vertical is the one axis nothing else in the strip is already using). Both
  call **`TaskBoard::promoteHomeworkToday(int $agendaEntryId, string $list)`**: reuses an already-active
  linked task if one exists (dragging/swiping the same homework twice re-confirms it, never duplicates —
  keyed on a new nullable **`tasks.agenda_entry_id`** FK, `nullOnDelete` like `group_id`/`project_id`),
  otherwise creates one carrying the homework's `subject: title`, its date as the task's `deadline`, and
  its note (escaped for a leading `-`/`*`/`#`/ordered-list marker only — Agenda notes are plain text, Task
  notes are Markdown source, and an unescaped note starting with one of those would silently render as a
  bullet/heading once living on the task; ordinary prose is untouched). `$list` is `'todos'`/`'tasks'` for
  a real desktop drop zone, or the mobile swipe's `'today'` sentinel, which — like anything else that isn't
  a genuine Today list — falls back to `'tasks'`. An already-promoted card loses its `data-id` (mirroring
  the completed-task-card convention) so a second drag/swipe is impossible rather than silently harmless,
  and shows a quiet forest "Heute" chip instead of its date badge — `TaskBoard::promotedHomeworkEntryIds()`
  (one query, active tasks with a non-null `agenda_entry_id`) drives this without N+1.
  - **Desktop mechanics** (`resources/js/app.js`) — `window.homeworkDragSource(el, wire)` is its own small
    Sortable instance (`group: { name: 'homework-preview', put: false }`, `draggable: '[data-id]'` so an
    already-promoted card can't even be picked up, `sort: false` since the strip has no manual order),
    deliberately separate from `boardSortable` since this list holds AgendaEntry ids, not Task ids, and
    must never feed `wire.reorder()`. `window.boardSortable`'s own `group` option is now conditional —
    only a Today zone (`el.dataset.today === 'true'`) becomes `{ name: 'board', put: ['board',
    'homework-preview'] }`; every other column stays plain `'board'` and therefore can't accept a homework
    drop at all. `homeworkDragSource`'s `onEnd` fires on the *origin* (this instance) with `evt.to` naming
    the destination — the exact same pattern `boardSortable`'s own `onEnd` already relies on for "dropped
    onto a project card" (its `to.dataset.list === undefined` guard). A rejected drop (anywhere without
    `data-today="true"`) just leaves `evt.to === el`, a no-op.
  - **Mobile mechanics** — `homeworkSwipeCard`, a small dedicated Alpine component (not a reuse of the
    board's own `swipeCard`, which is hardcoded to the horizontal axis and to `$wire.swipeIntent`): same
    lock-on-dominant-axis / resist-the-dead-side / spring-back shape, read on `dy` instead of `dx`. A
    downward drag is the dead side (resists, never commits); reaching the threshold upward flies the card
    off and calls `$wire.promoteHomeworkToday(id, 'today')`, mirroring `swipeCard.fire()`'s own
    fly-then-call timing. `touch-pan-x` (not the task cards' `touch-pan-y`) lets the strip's native
    horizontal scroll through on an unlocked gesture.
  - **The same card partial serves both breakpoints** — unlike full task cards (`task-card.blade.php` vs
    `task-card-mobile.blade.php`), a homework preview card is identically shaped at both sizes, so
    `homework-preview-strip.blade.php` takes an `$interaction` param (`'drag'` at the desktop include site,
    `'swipe'` at the mobile one — same pattern `$spacing` already used for a different reason) rather than
    forking into two files: only the `x-init`/`x-data` wiring branches, the card markup is written once.
- **The signature moment — finishing the loop, quietly.** Completing a homework-derived task also completes
  the linked Agenda entry, and un-completing reverses it — no second trip to Agenda, no risk of doing the
  same homework "twice" (once on the board, once forgotten in Agenda). **`Task::syncLinkedAgendaEntry(User
  $user, bool $done)`** is the one place this logic lives (a no-op whenever `agenda_entry_id` is null, i.e.
  for every ordinary task): it's wired into all three places a task's completion can flip —
  `ManagesTasks::toggleComplete()` (board/project/group pages), `Schedule::toggleDeadlineTaskDone()` (which
  already deliberately duplicates `toggleComplete()`, see that section above), and the API's `PATCH
  /tasks/{id}` when `is_completed` is part of the payload (Shortcuts-driven completion behaves identically
  to the UI). The strip's own checkbox (`toggleHomeworkPreviewDone`) is the mirror image: if an already-
  promoted task exists (active or completed — whichever is more recently touched wins the rare case where
  both somehow exist), it routes through `$this->toggleComplete($task->id)` instead of touching the entry a
  second time directly, so the usual completion side effects (celebrations included) fire no matter which
  checkbox you actually tap, and the two records can't drift out of sync.
- **A small provenance icon** (the same document glyph as the strip's own header badge) marks a homework-
  derived task on its card face — `task-card.blade.php`, `task-card-mobile.blade.php`, and
  `project-task-card.blade.php` all check `$task->agenda_entry_id`, since the edit sheet can still move such
  a task into a project later and the icon should follow it there.
- **Dragging a homework-derived task card back onto the strip undoes the promotion** —
  `TaskBoard::removeHomeworkFromToday(int $id)` deletes the task (guarded: a no-op unless
  `agenda_entry_id` is actually set, defense-in-depth against the client-side gate ever being wrong) and
  leaves the Agenda entry itself completely untouched, so it simply becomes open and re-promotable again,
  exactly as if it had never been dragged in — the desktop-only mirror image of `promoteHomeworkToday()`.
  Both directions share **one** Sortable instance (`window.homeworkDragSource`, desktop only — see
  mobile note below): its `group.put` is a **function**, not the `true`/array shorthand used elsewhere,
  checking `dragEl.dataset.homework === 'true'` (set on `task-card.blade.php`'s root only when
  `agenda_entry_id` is non-null) — an ordinary task dropped on the strip bounces back untouched, the same
  as dropping on any other zone it isn't welcome in. The incoming drop is handled by `onAdd` (mirroring
  `projectDropZone`'s exact shape: read `evt.item.dataset.id`, `evt.item.remove()`, call the wire method);
  the *outgoing* promote drop still needs no changes to `window.boardSortable` at all — a card dropped on
  the strip lands in a zone with no `data-list`, so `boardSortable`'s own pre-existing
  `to.dataset.list === undefined` guard (written for "dropped onto a project card") already makes its
  `onEnd` bail out silently, exactly the same free ride `projectDropZone`/`newProjectDropZone` already get.
  **Mobile has no equivalent gesture** — deliberately: mobile's `swipeCard` already spends both directions
  on a Today-flagged task (`right: 'untoday'`, `left: 'edit'`), and the existing armed-double-click delete
  button already reaches the identical end state (task gone, entry untouched, re-promotable), so a mobile
  swipe-to-remove would only duplicate a path that already exists rather than add one.
- Deliberately out of scope for this pass: exam entries (`type=exam`, the strip itself only ever shows
  homework); any promotion entry point on the Agenda page itself, QuickCapture, or the Zeitplan's deadline
  strip (only its *existing* `toggleDeadlineTaskDone` gained the completion echo, no new gesture there).

### Header-Badges (built)

Small ambient shortcuts in the header — an icon plus a short count/snippet that links straight to the
page it's about, fully user-configured (which ones, in what order). Before this, the header only ever
showed one hardcoded indicator (the streak), always in the same spot.

- **`App\Services\HeaderBadges`** — stateless, like `ProgressStats`/`TaskSuggestor`. `CATALOG` is the
  fixed set of six possible badges (`streak`, `agenda`, `today`, `schedule`, `goal`, `emergency`), each
  with a label, target route, and a flat Topografie `tone` (`ink` = neutral border, same look the streak
  badge used at its lowest tier; `emergency` = `signal`, matching that badge's colour everywhere else in
  the app). `DEFAULT_ENABLED = ['streak', 'agenda']` — the two explicitly asked for; the other four ship
  in the catalog but disabled, opt-in via Settings.
  - **`preferenceRowsFor(User)`** — every catalog key, in the user's order, with its `enabled` flag.
    `users.header_badges` (nullable JSON) is `null` for anyone who has never opened the card: that reads
    as "use `DEFAULT_ENABLED`", not an empty header — the point is that the feature is visible on day one
    for existing accounts, not just newly-registered ones. The moment a user saves *any* change, their
    own `{key, enabled}` list is stored and used **verbatim** from then on; a catalog key missing from
    that stored list (either because a future release adds one, or because of the stored/never-customised
    split above) is appended at the end, **disabled** — a new badge type never silently activates itself
    inside a list someone already curated.
  - **`visibleFor(User)`** — the enabled rows, in order, each resolved to real content; a resolver
    returns `null` the moment it has nothing to show and the row is dropped **entirely**, never rendered
    as a "0" or empty pill — the same rule the streak badge already followed before this existed, now
    generalised to all six. `schedule`'s resolver is deliberately scoped to **today only** (current event,
    else the next one still to come today, else hidden) — a header badge answers "what's next", reaching
    into tomorrow is what the Zeitplan page itself is for.
  - `layouts/app.blade.php` computes `HeaderBadges::visibleFor(auth()->user())` once per page load (it's
    plain Blade in the shared layout, not a Livewire component, so it does **not** live-update on an
    in-page Livewire action — same limitation the old hardcoded streak badge already had; a badge's count
    catches up on the next full navigation) and loops over `partials/header-badge.blade.php`, one `<a
    wire:navigate>` per badge. The row sits in a `overflow-x-auto` wrapper (`max-w-[38vw]` on mobile) —
    the safety net for more than a couple of enabled badges on a narrow phone, same pattern as the
    homework preview strip.
- **Settings' "Header-Badges" card** (Allgemein tab) — one draggable list of **every** catalog badge
  (enabled and disabled alike), each row a drag handle + label + the same immediate-save toggle switch
  used elsewhere in Settings. `Settings::toggleHeaderBadge()`/`reorderHeaderBadges()` both round-trip
  through `HeaderBadges::preferenceRowsFor()` so the persisted shape is always the full six-row list, not
  a partial diff. Dragging is `window.headerBadgesSortable` (`resources/js/app.js`), a copy of the
  existing `emergencySortable` pattern (own group name, `onEnd` persists the whole order) — no new gesture
  code, and no `x-init` re-registration guard needed either: that gotcha (§10) is specifically about a
  Livewire **component root**, and this sortable container is a plain nested element, same as
  `emergencySortable`'s own container.
- **Signature moment — the Zeitplan badge proves its destination.** Its link is `?event={id}`, not a bare
  `/app/schedule` — `Schedule::mount()` reads that query param, resolves it ownership-scoped
  (`ScheduleEvent::forUser($user)->visible()->find()`, silently ignored if stale/foreign/missing, never a
  broken page load), and calls the existing `focusDate()` so the event's own day is what's on screen even
  outside the current week. `Schedule::$highlightEventId` flows automatically into
  `partials/schedule-event.blade.php` (a Livewire component's public properties reach every `@include`d
  partial without being passed explicitly) as a `badge-jump-highlight` class — a single-fire, 1.4s
  `box-shadow` wash in `contour` (`app.css`, modelled directly on the existing `weekplan-ripple` keyframe,
  just slower/calmer since it's greeting a page load rather than confirming a save). The other badges
  don't get an equivalent: `agenda`/`today` point at a list, not one specific row, so there's nothing
  singular to prove.
- **The `today` badge's mobile deep link** — its href carries `?tab=today`; `TaskBoard::mount()` (new —
  the component previously had none) reads it and seeds `$mobileTab` directly. Desktop has no separate
  Today view to jump to (Heute-flagged tasks already surface pinned inside their own board column), so the
  param is simply inert there — not worth a bespoke desktop treatment for one query string.
- Deliberately out of scope for this pass: a hover/long-press preview popover for any badge, a live
  in-page header update the instant a badge's underlying count changes (it updates on the next navigation,
  same as the pre-existing streak badge always did), and API/Shortcuts support for reading or writing
  `header_badges`.

### Modul-Sichtbarkeit & Startseite (built)

Lets a user hide any of the app's optional feature pages and choose which page opens by default —
built for the "single user, but the Agenda can be shared with a class" case (§1): a classmate who
only cares about the shared class Agenda can declutter everything else and land straight on it.
Deliberately built as a foundation for two features that don't exist yet — an onboarding tutorial
that should only cover currently-visible modules, and an admin-authored feature-announcement system
— so the catalog is additive/extensible rather than a one-off pair of settings.

- **`App\Services\AppModules`** — a stateless catalog, `CATALOG` mirroring `HeaderBadges::CATALOG`'s
  shape: seven hideable keys (`prepare`, `schedule`, `weekplan`, `agenda`, `crafts`, `emergency`,
  `progress`), each with a label, a one-line description, and the route name its nav entries and
  its landing-page choice point at. **The Board (`app`) and Settings are never in this catalog** —
  they're the app's core surface (QuickCapture's default target, the only place Inbox/Projects/
  Groups live) and the always-safe fallback; hiding either would strand the user with no way back
  in. A feature with its own dedicated on/off toggle (e.g. a default-off `*_enabled` column
  explained inline in Settings) belongs there, not duplicated here — this catalog is only for pages
  that were previously always-on and are only now becoming optional.
  - `isVisible(User, key)` / `hiddenKeys(User)` — **`users.hidden_modules`** (nullable JSON) is
    `null` for anyone who never opens the card, meaning "nothing hidden" — same "untouched means
    default" shape as `header_badges`, just inverted: a hide-list needs no merge-with-catalog logic
    the way an enable-list would, since "not in the list" already means visible. Any key outside the
    catalog (an unknown/future key) is always visible — this only ever hides something explicitly
    listed.
  - `rowsFor(User)` — the catalog in fixed order, each row carrying its current `hidden` flag; what
    Settings' "Module" card renders.
  - `landingPageOptions(User)` — the Board (always first) plus every catalog module that isn't
    currently hidden; what the "Startseite" picker offers.
  - `isValidLandingPage(User, key)` — deliberately **not** just `isVisible()`: that treats an
    unknown key as "always visible" (right for its own job), which would be wrong here — a garbage
    or removed key must never validate as a landing-page choice. Used by both
    `User::defaultLandingRouteName()` and `Settings::setDefaultPage()` so the two can never disagree
    about what's a valid pick.
- **`users.default_page`** (string, default `'app'`) plus **`User::defaultLandingRouteName()`** —
  self-healing: if the stored choice no longer resolves to anything visible (the module was hidden
  after being picked as the landing page), this quietly falls back to the Board instead of routing
  the user to a page the nav can no longer reach. `routes/web.php`'s `/` and `/dashboard` (Breeze's
  post-login target) both redirect through it instead of a hardcoded `route('app')`; a guest hitting
  `/dashboard` directly still falls back to `app`, matching the previous static redirect's behavior.
- **Settings' "Module" card** — one immediate-save toggle per catalog row (same switch style as the
  Benachrichtigungen/Kategorien cards), each row fading to `opacity-45` in place so the effect is
  confirmed without leaving Settings. `Settings::toggleModule()` **resets `default_page` back to
  `'app'` in the same write** whenever the module being hidden is also the user's current landing
  page — the write-side mirror of `defaultLandingRouteName()`'s own read-side fallback, so the two
  can never drift apart even mid-session. A companion **"Startseite"** pill row
  (`Settings::setDefaultPage()`, `landingPageOptions()`) offers only currently-visible pages.
- **Every consumer of a hideable page checks `AppModules::isVisible()`:**
  - `layouts/app.blade.php`'s "Mehr" dropdown and profile-menu Fortschritt entry each wrap their own
    link in an `@if`; the whole "Mehr" button disappears when every one of its entries is hidden,
    rather than opening onto an empty panel. **Notfall is the one exception** — its entry stays
    visible whenever `user->isInEmergencyMode()` is true, regardless of the module toggle, so hiding
    it can never strand the user mid-emergency with no way to see or end it.
  - `HeaderBadges::visibleFor()` drops the `agenda`/`schedule`/`emergency` badges the same way (via
    a small `MODULE_FOR_BADGE` map), with the same Notfall exception — a header shortcut must not
    keep pointing at a page the nav no longer offers.
  - `QuickCapture::availableTargets()` filters `TARGETS` to modules the user hasn't hidden (`craft`
    → `crafts`, `agenda` → `agenda`; every other target is core Board functionality and always
    offered) — hiding a module has to remove its capture entry point too, or "hide everything except
    Agenda" would stay half-done. `setTarget()`, `resetPanel()`, and `save()`'s validation rule all
    check against `availableTargets` instead of the raw `TARGETS` constant.
  - `TaskBoard::homeworkPreview()` also goes empty when the `agenda` module is hidden — the strip is
    a satellite view of Agenda and links straight back to it, so it has to disappear along with it,
    on top of its existing `homework_preview_enabled` gate.
- Deliberately out of scope for this pass: hiding the Board/Settings themselves (see above), a
  per-module hide affecting the API/Shortcuts surface, and the onboarding tutorial / admin
  feature-announcement system this catalog exists to eventually support.

### Onboarding-Tutorial (built)

The second of the two features that catalog was built to eventually support (the first being
itself, above; the third — an admin-authored feature-announcement system shown to *existing*
users — is still unbuilt, tracked in `TODO.md`). A skippable, replayable walkthrough that covers
the "3 Things" framework (see §1) and every feature area of the app, ending on a functional
module-visibility/default-landing-page step.

- **`App\Livewire\Onboarding`** (`/app/onboarding`, `route('onboarding')`, `#[Layout('layouts.app')]`)
  is a single continuous flow, not a wizard with server-tracked position — the 14 slides are
  static content, not data, so step position is **pure client-side Alpine state** (the
  `onboarding` store in `app.js`: `step`/`total`/`next()`/`back()`), the same "ephemeral UI state
  lives in Alpine" convention as `prepare`/`celebration`/`quickCapture`. Unlike `prepare`, it needs
  no seeded-order bookkeeping or re-run guard — `init(total)` only ever sets the slide count (read
  out of the Blade view via `$stepCount` so the two can never drift), which is harmless to
  re-apply on every Livewire re-render the module-visibility step's toggles cause, since it never
  touches `step`.
- **`users.onboarding_completed_at`** (nullable timestamp) — `null` means "never opened it",
  true for a brand-new registration and for any pre-existing account that predates this feature
  (neither is ever retroactively forced through it). `User::needsOnboarding()`/
  `markOnboardingSeen()` are the only two access points. Stamped by **both** `finish()` and
  `skip()` — skipping counts as "seen it" just as much as finishing does, mirroring
  `PrepareTomorrow::finish()`'s own either-button-counts shape — and **re-stamped on every
  replay**, so the same column doubles as "last viewed on" for Settings' own card (below).
- **Auto-redirect on a brand-new registration only** — `RegisteredUserController::store()` builds
  its `redirect()->intended($default)` fallback from `$user->needsOnboarding()` instead of always
  pointing at `route('dashboard')`. Since `intended()` only overrides that fallback when a
  `url.intended` session value is already pending — which is exactly the shape of "a classmate
  followed a class-agenda invite link, got bounced to `/login`, and registered from there" (see
  Agenda — Klassen teilen, "the invite link requires login and returns after it") — that flow is
  completely unaffected: it still lands exactly on the invite it followed, never detoured through
  onboarding first. A plain "just register" has no intended URL pending, so it *does* fall through
  to onboarding. Nothing about a normal *login* (as opposed to registration) ever redirects here.
- **Replay is unconditional** — Settings' new "Tutorial" card (Allgemein tab) always links to
  `route('onboarding')` regardless of `onboarding_completed_at`, with the button label and a
  "Zuletzt angesehen am …" caption both switching on whether it's set. Visiting the route itself
  never stamps anything — only `finish()`/`skip()` do — so a user can open it, look around, and
  leave via the header logo without disturbing the stored "last viewed on" date.
- **`App\Livewire\Concerns\ManagesModuleSettings`** — the module-visibility/default-landing-page
  step (the one place this tutorial is genuinely interactive, not just descriptive) needed the
  exact same `toggleModule()`/`setDefaultPage()` self-healing logic Settings already had, so that
  logic was extracted out of `Settings` into this trait and both components now `use` it — the
  "hiding the current default page resets it to the board" rule now lives in exactly one place
  instead of two copies that could drift. The trait's `mountManagesModuleSettings()` seeds
  `$defaultPage` on mount via Livewire's automatic trait-hook convention (a method named
  `mount<TraitBasename>` is called automatically for every trait a component uses — see
  `SupportLifecycleHooks::callTraitHook()`) — **it must be `public`**, not `protected`: Livewire
  invokes it via `Illuminate\Container\BoundMethod`-style resolution from outside the class, which
  silently fails with a "method does not exist"-shaped error against a non-public method (caught by
  every test that mounts either component, not a subtle runtime-only gap).
- **Signature moment — the "3 Things" step teaches by feel, not by caption.** Three chips
  (To-Do/Task/Project) sit above one sample card; tapping between them doesn't just swap a label —
  the card itself visibly grows (width, padding, weight, colour) at each size, and at "Project" it
  splits apart into three small stacked, slightly rotated cards to make "a container for
  mehrteilige Arbeit" tangible rather than read. Pure Alpine/CSS (`x-transition.scale`), no
  Livewire round trip — the whole slide is local `x-data="{ size: 'todo' }"` nested inside the
  step's `x-show` block.
- Every other feature area gets one slide each (Heute/Wichtig/Termine, Schnellerfassung, das
  Board, Projekte & Gruppen, Vorbereitung, Zeitplan & Fokus, Wochenplan & Ferien, Notfallmodus,
  Agenda, Bastelideen & Fortschritt); Header-Badges and the API/Shortcuts docs get a passing
  mention rather than a dedicated slide (the module step's footer note and the closing slide,
  respectively) since neither needs a decision made on day one.
- Deliberately out of scope for this pass: a live-DOM spotlight tour over the real pages (rejected
  in favour of this dedicated full-screen flow — a spotlight touching a dozen pages, several
  needing real data like an active project or a populated Wochenplan, would be fragile for one
  pass, whereas this app already has a proven "dedicated full-screen ritual" shape in
  `PrepareTomorrow`/`EmergencyMode`), per-step analytics, a deep link to resume one specific slide,
  and the admin feature-announcement system (see "Feature-Ankündigungen" below) this and the
  module catalog were built to eventually support.

### Feature-Ankündigungen (built)

The third and last step of the onboarding/accessibility push `AppModules` and the onboarding
tutorial were built for (see both sections above): a lightweight "here's what's new" toast for
**existing** users, distinct from the new-user tutorial. Two halves — an admin-only editor that
authors announcements, and a per-user toast every regular user sees until they dismiss it.

- **`users.is_admin`** (boolean, default `false`) — set directly in the DB, or via
  `php artisan admin:grant {email}` (`App\Console\Commands\GrantAdmin`, `--revoke` to remove).
  Deliberately no in-app self-service "become admin" flow, and no middleware layer either — every
  other authorization boundary in this app already lives at the component/query level
  (`userTask()`, `visibleEntry()`, …), not in middleware, so `App\Livewire\Admin\AnnouncementEditor`
  follows the same convention: `abort_unless(auth()->user()->is_admin, 403)` in `mount()`. The
  artisan command exists because `php artisan tinker --execute` mangles quotes from PowerShell (see
  *Known Issues*) — flipping one boolean column locally needs a reliable path that isn't that.
- **`App\Models\FeatureAnnouncement`** — `title, description, type, related_module?, created_by?,
  is_published, published_at?`. `related_module` is a key into `AppModules::CATALOG` (or null for
  "no specific page") — deliberately not a foreign key, since the catalog is a stateless PHP
  constant, not a table. `published_at` is stamped **the first time** an announcement is published
  and never moves again on a later unpublish/republish (`AnnouncementEditor::togglePublish()`) — it
  marks when the feature was actually introduced, not the current toggle state, and it's what
  orders the unseen queue (oldest-published-first, see below).
- **Four message types** (`FeatureAnnouncement::TYPES`, a `label`/`tone`/`badge_label` catalog,
  same shape as `AppModules::CATALOG`/`HeaderBadges::CATALOG`): `info` (default, deliberately
  toneless — the same neutral `bg-line`/`text-ink-soft` look the editor's own "Entwurf" badge
  already uses), `maintenance` (planned downtime, `contour` — this app's existing "something
  time-bound" tone from the deadline-strip chips), `warning` (`signal` — the same
  danger/urgency tone as an armed delete or an overdue task, so a warning reads as urgent rather
  than merely informative), and `release` (an official version bump / big change, `forest` — the
  toast's only look before this catalog existed, so a release keeps the original "exciting news"
  identity). `type` is a plain string column (default `'info'`), not a DB enum — same convention
  as `tasks.list`. Picked via a chip row in `AnnouncementEditor`'s form (mirrors
  `AgendaEntry::TYPES`'s Hausaufgabe/Prüfung toggle, just four chips instead of two) and shown as
  a colour-coded pill on each row in the admin list. The toast picks a distinct icon (wrench /
  triangle / star / info-circle) and top-label ("Wartung"/"Warnung"/"Release"/"Neu") per type via a
  `@switch` in the Blade view — **never** a dynamically-built `"bg-{$tone}-soft"` string, since
  Tailwind's content scanner only finds complete literal class tokens in the source (same trap as
  the *Known Issues* entry on JS-only classes silently purging). `FeatureAnnouncement::typeMeta()`
  falls back to `info` for a stale/removed type value, so an old row is never left with nothing to
  render. The "Verstanden" button itself stays the app's one shared forest CTA colour regardless of
  type, deliberately — reusing `signal` there would visually collide with the armed-delete-button
  convention, which is about a destructive click, not "acknowledge a warning".
- **A brand-new account never sees the announcement backlog.** `scopeUnseenBy()` adds
  `->where('published_at', '>=', $user->created_at)` — without it, registering today would
  immediately surface every "what's new" toast ever published, one after another, for features the
  new user has never used any other way. An existing user is unaffected, since their `created_at`
  predates virtually every announcement anyway; the comparison is `>=`, not `>`, so a user who
  registers in the same instant something is published still sees it (a real case — an existing
  user browsing right as an admin publishes — not an edge case worth excluding).
- **"Seen" is per (announcement, person)** — `feature_announcement_dismissals` (`feature_announcement_id,
  user_id`, unique pair, both FKs `cascadeOnDelete`) mirrors `agenda_entry_completions` exactly
  (CLAUDE.md, Agenda — Klassen teilen): the same shape already proven for "done" on a shared Agenda
  entry, reused here for "dismissed" on a shared announcement. `FeatureAnnouncement::dismissFor(User)`
  does a `syncWithoutDetaching()`, so dismissing twice is a no-op, never a second row or an error.
- **`App\Livewire\Admin\AnnouncementEditor`** (`/app/admin/announcements`, `route('admin.announcements')`)
  — one form doubling as create/edit (`editingId` null vs set, same pattern as `Agenda`/`CraftIdeas`),
  a list of every announcement (draft and published, newest first — an admin needs to see drafts
  too), a publish/unpublish button, and delete via the standard armed double-click (never `confirm()`).
  Entry point: a profile-dropdown link (`layouts/app.blade.php`), rendered only for `auth()->user()->is_admin`.
- **`App\Livewire\FeatureAnnouncementToast`** — mounted once in `layouts/app.blade.php` (same
  reasoning as the milestone-celebration overlay: it has to appear no matter which page loads
  first), inside `@auth`. Its `#[Computed] queue()` is every published announcement this user
  hasn't dismissed, oldest-published-first (`FeatureAnnouncement::scopeUnseenBy()`); `current()` is
  its head. Renders **nothing at all** when the queue is empty — the same zero-footprint convention
  as the homework preview strip and the prepare prompt — but the root Blade view still needs a
  permanent outer `<div>` regardless, since Livewire rejects a root component view that can compile
  to literally nothing (`RootTagMissingFromViewException`); only the content *inside* that div is
  conditional. One announcement is shown at a time, never stacked, so a backlog of several unseen
  entries doesn't overwhelm on the next visit — dismissing advances to the next one in the same
  queue, and a `wire:key` tied to the current announcement's id makes each card (and its Alpine
  state) remount fresh rather than mutate in place.
- **If `related_module` is set**, the card also shows an "X ansehen →" link to that catalog route.
  Clicking it both navigates (`wire:navigate`) and dismisses (`wire:click="dismiss(...)"`) — the
  same dual-fire pattern `PrepareTomorrow`'s "Später planen"/"Fertig" buttons already use (a
  Livewire action and a plain link on the same element both fire independently, see *Known Issues*):
  visiting the feature the announcement is about already counts as having seen it.
- **Signature moment — the dismiss button counts itself down.** Rather than a "and 2 more" line
  elsewhere on the card, the primary "Verstanden" button carries a small trailing badge showing how
  many *other* unseen announcements remain; clicking it decrements the badge instantly (a local
  Alpine `remaining`, optimistic — the server's own re-render confirms the true count a beat later
  via the fresh `wire:key`'d card) and it disappears once nothing is left. The whole "there's a
  backlog, but you're processing it one bite at a time" feeling lives at the one spot you're already
  looking at, rather than a separate counter or list.
- Deliberately out of scope for this pass: editing `related_module` to point anywhere outside
  `AppModules::CATALOG` (e.g. a specific settings card or a non-module page), scheduling a future
  publish date, per-announcement analytics (open/click-through), and a push notification for a
  freshly published announcement — the toast only ever appears on the next page load, matching
  "little quick" rather than reaching for a channel that works with the tab closed. Also out of
  scope: a `maintenance` announcement carries no start/end time fields of its own — the window has
  to be written into the free-text description (e.g. "Sonntag 2–4 Uhr") — and there's no
  "resurface a dismissed warning again later" escalation path; both are plain text/one-shot like
  every other type.

### Task-Gruppen (built)

The middle size between a single task and a Project: a bundle of steps that belong together — a
presentation, rearranging a room — where a Project would be too heavy. Before this, every multi-step
thing became a Project, which is exactly what made that column unreadable (see §1).

- **`App\Models\TaskGroup`** — `user_id, name, sort_order, timestamps`. `hasMany Task` (FK
  `tasks.group_id`), `activeTasks` is the ordered working set, scopes `forUser/ordered`. `hasMany GroupNote`
  (`notes()`, ordered) — see below. `DEFAULT_NAME` ("Neue Gruppe") is what a group created by a gesture is
  called until it is named.
- **`App\Models\GroupNote`** (`group_notes` table: `task_group_id, content, sort_order, timestamps`,
  `cascadeOnDelete`) — the Notizen column is a **stack of separate note cards**, not one growing document: a
  group can hold a few unrelated things worth jotting down (a checklist, a quote, a deadline reminder), and
  one blob made finding any single one of them slower as it grew. `contentHtml()` renders through
  `TaskGroup::renderNotes()` (static, shared so the safety options — `html_input=strip`,
  `allow_unsafe_links=false`, same as the project brainstorm field — live in one place). Cascades on group
  delete: unlike a task, a note has no meaning outside the group it was written for, so there is nothing to
  release it back to (contrast with `tasks.group_id`'s `nullOnDelete` just below).
  `GroupPage` keeps only one card editable at a time (`editingNoteId`/`noteDraft`, same single-editor
  pattern as the task edit sheet); switching to another card or leaving one completely empty on "Fertig"
  deletes it rather than leaving a blank tile.
- **`tasks.group_id`** is **orthogonal to `list`**, exactly like `is_today` — a grouped task still lives in
  `inbox`/`todos`/`tasks`, it just surfaces inside its group instead of loose on the board. That is why
  dropping an Inbox card onto a group files it in the *group's* Inbox: nothing about the task's list
  changes, only where it is shown. The FK is `nullOnDelete`, so dissolving a group can never take tasks
  with it. A task belongs to a **project or a group, never both** — every write path enforces it
  (`ManagesTasks::saveEdit`, `TaskBoard::groupTasks/assignTaskToGroup`, `GroupPage::assignToGroup`).
- **`Task::scopeGroupOrdered()`** is `boardOrdered()` **minus the leading `is_important` sort**. Inside a
  group the star is a marker only and must not pull a task to the top — a deliberate product decision, and
  the one place in the app where important does not reorder. Deadlines still do.
- **Board integration** (`TaskBoard`) — `boardTasks()` hides grouped tasks from their column *unless* they
  are `is_important` or `is_today`: both are explicit "this one matters now" signals that outrank the
  bundling, so those show as ordinary cards (and are therefore left out of the box preview, or they'd
  appear twice). `groupBoxesFor(list)` builds the boxes: name, progress and the next two entries, rendered
  by `partials/task-group-box.blade.php` in both `partials/column.blade.php` (desktop) and
  `partials/mobile-task-list.blade.php`. Two rules worth knowing:
  - **The Inbox column never shows a group box.** A group's own inbox is triage that belongs inside the
    group; mixing it into the board's inbox puts two different kinds of "unsorted" in one pile.
  - **A group with no open board work gets one compact box in Tasks** ("3 Aufgaben in der Gruppen-Inbox").
    Without it, a group whose tasks all sit in its own inbox would be invisible on the board — the
    difference between tucked away and lost.
  `counts()` adds the grouped tasks of a column, since they are genuinely visible there.
- **Creating one by drag (desktop)** — each card is split into three vertical bands: the middle 50% is the
  "group" band, the top/bottom 25% are ordinary reorder territory (`groupZone` in `resources/js/app.js`,
  hooked into `boardSortable`'s `onMove`/`onEnd`). This replaced an earlier dwell-time design (hold a card
  over another for 350ms) that was unreachable in practice.
  **The load-bearing part of the fix is `invertSwap: true` + `invertedSwapThreshold: 0.5` on the Sortable
  instances** (`sortableGroupBands()` in `app.js`), not the `onMove` guard. With Sortable's defaults
  (`invertSwap: false`, `swapThreshold: 1`) the swap zone is the *entire* card — `_getSwapDirection` in
  sortablejs tests `mouseOnAxis > targetS1 + targetLength * (1 - swapThreshold) / 2`, which at
  swapThreshold 1 is just "anywhere inside the target" — so the hovered card is reordered out from under
  the cursor the instant the pointer crosses its leading edge. That is why *both* the dwell design and a
  first attempt at a middle-band `onMove` guard failed: by the time the pointer reaches the middle, the
  card that was there has already moved away, so no guard checked at that point can help. `invertSwap`
  switches Sortable to its inverted branch, which swaps only within `invertedSwapThreshold / 2` of each
  end and returns `0` — literally "no swap" — everywhere between, so Sortable itself holds the card still
  in the middle band. Its direction in the outer bands (`mouseOnAxis > mid ? 1 : -1`) also gives the
  intended semantics for free: the top band orders the dragged card *above* the target, the bottom band
  below it. **Arming is driven by `groupZone.track()`, a pointer listener of our own (`dragover` +
  `pointermove`/`touchmove`, attached in `onStart`, removed in `onEnd`), deliberately not by Sortable's
  `onMove`** — `_onDragOver` returns at `if (direction === 0 …)` *before* calling `_onMove`, and the middle
  band is exactly where direction is 0, so `onMove` provably never fires there (see *Known Issues*).
  `groupZone.consider()` stays wired to `onMove` purely as a safety net for the edge cases that do reach it;
  its band fraction is derived from the same `INVERTED_SWAP_THRESHOLD` constant so the cue can never
  disagree with what a release actually does. The armed card gets
  `.group-arm` — an indent plus a forest-coloured bracket on its leading edge (`app.css`), echoing the left
  accent a real group box gets — a secondary cue only, since the dragged card (or the browser's own
  drag-image snapshot) sits directly on top of whatever's under the cursor and would hide it otherwise. The
  reliable indicator is a small "Gruppieren mit «Titel»" label pinned to the cursor itself (`groupArmLabel`
  in `app.js`, styled `.group-arm-label`), which always renders above the drag image. An armed drop calls
  `TaskBoard::groupTasks()` and **returns before `reorder()`** —
  the server moves the task itself, so persisting the destination order too would fight it with a stale
  picture. Dropping onto an *already grouped* card just joins that group (no name prompt). A fresh group
  opens an inline name field on its own box (`namingGroupId`/`groupNameDraft`/`saveGroupName`); leaving it
  empty simply keeps `DEFAULT_NAME`, so the gesture never blocks on a dialog.
- **Dragging a task back out** of a group box — `groupDropZone` (`app.js`) is a full two-way Sortable zone,
  not receive-only: dropping onto a plain board column releases the task (`TaskBoard::ungroupTask()`) and
  persists its new position there via the same `reorder()` call an ordinary cross-column move already
  makes. `sort: false` on this zone, since the box only ever previews up to two tasks — reordering that
  partial view wouldn't mean anything. Hovering the group band of another card on the way out merges
  into *that* card's group instead (takes priority, mirrors `boardSortable`'s own onEnd). Dropping onto a
  project card, another group's box, or the "new project" zone is already fully handled by that zone's own
  `onAdd`, so `groupDropZone`'s `onEnd` only has to act when the destination is a plain column.
- **`TaskGroup::pruneIfTooSmall()`** — dissolves a group the moment it would hold one task or none; a
  bundle of one is not a group, and leaving the user to notice and clean up the leftover shell by hand would
  just be busywork. Returns whether it dissolved. Every write path that can shrink a group's membership
  calls it on the task's *previous* group (captured **before** the update, since `group_id` may already have
  moved on by the time the caller checks): the drag-out gesture above, `groupTasks`/`assignTaskToGroup` when
  the dragged task already belonged to a *different* group, the edit sheet's Gruppe field
  (`ManagesTasks::saveEdit`), deleting a grouped task (`ManagesTasks::deleteTask`), and `GroupPage`'s own
  `removeFromGroup`/`moveTaskToGroup`. `ManagesTasks::afterGroupMayHaveShrunk(?TaskGroup $group)` is the
  shared hook both `saveEdit()` and `deleteTask()` funnel through — its default (used by `TaskBoard`/
  `ProjectPage`) just prunes; **`GroupPage` overrides it** to also redirect to the board (`route('app')`)
  when the group being dissolved is the one *its own page* is showing — otherwise it would try to re-render
  a group that no longer exists. `TaskBoard`'s own group-mutating methods aren't part of the shared trait,
  so they call `pruneIfTooSmall()` directly; they never need the redirect since the board's identity never
  depends on one specific group.
  > Auditing every `group_id` write site for this turned up two pre-existing gaps, fixed alongside it:
  > `TaskBoard::assignTaskToProject()` and `ProjectPage::assignToProject()` (drag onto a project card, or
  > pull one from the inbox picker) never cleared `group_id` — a grouped task dropped there could end up
  > with both a project *and* a group at once, violating the "never both" rule everywhere else in this
  > feature. Both now clear it and prune the vacated group.
- **Three more ways in**, because desktop drag is not enough — phones have no drag, and "file this into the
  group" is wanted from everywhere:
  - **QuickCapture's `group` target** — pick an existing group or name a new one, plus which of the
    group's lists the task lands in. A group is only ever created *together with its first task* (an empty
    group has no reason to exist), and the chosen group survives a save the way the Agenda's Fach does.
  - **The edit sheet's Gruppe field** (`ManagesTasks::$editGroupId`/`editableGroups`) — shown for board
    lists only, `wire:key`ed on the group count so a newly created group actually appears (the frozen
    `x-data` trap, §10).
  - **The mobile long-press sheet** lists groups above projects; inside a group's dashboard the same
    gesture opens the mirror image (`partials/group-task-picker-sheet.blade.php`): release the task, or
    hand it to another group.
- **`App\Livewire\GroupPage`** (`/app/groups/{group}`, `route('group.show')`, `use ManagesTasks`) — the
  group's own dashboard, deliberately the main board's shape so nothing new has to be learned: Kanban on
  desktop, bottom-navigation on mobile, Inbox/To-Dos/Tasks plus a **Notizen** column of note cards where the
  board has its Projekte column. Per-column quick-add (`newTitle` keyed by list), drag-reorder through the same
  `boardSortable`, the same swipe intents, an "Aus der Inbox hinzufügen" picker like the project page.
  **No "Heute" area** — the day's focus is owned by the main board alone; a group task can still be flagged
  for today and then appears in the board's Heute tab. `dissolveGroup()` is non-destructive: the tasks stay
  exactly where they are and simply become loose again (armed double-click all the same — it is an
  irreversible structural change, even if nothing is lost).
- Not touched by this feature: the API/Shortcuts (see `TODO.md`), Notfallmodus, Vorbereitung.

### Bastelideen (built)
- A deliberately low-pressure "what to do when bored" list, kept standalone like Agenda — no FK/relation
  to Task/Project, and it doesn't surface on the board, in Vorbereitung, or in Notfallmodus.
- **`App\Models\CraftIdea`** — `user_id, title, where_to_begin(nullable), is_done, timestamps`. No date
  fields at all (unlike Agenda) — these are "someday" ideas, not deadline-driven. Scopes `forUser/open/done`.
  `User::craftIdeas()` is the standard `hasMany`. `where_to_begin` is deliberately **not** a dedicated
  "first step" field — it's the idea's one free-text note (material, links, a reminder of where to start,
  whatever). Kept under its original column/property name rather than renamed at the DB or Livewire-property
  level (nothing about its shape changed, only the label above it), but the UI calls it "Notiz" and edits it
  through a `<textarea>` rather than a single-line input, since a note can run longer than one line.
- **`App\Livewire\CraftIdeas`** (class-based, `/app/crafts`, `route('crafts')`) is a single-purpose "browse
  and pick one" page, not a form-driven CRUD list like Agenda's:
  - **Hero suggestion ("Mach doch das")** — `$heroId` (persisted on the component, not the DB) names the
    one idea currently pushed to the front. `render()` calls a private `ensureHero()` on every request,
    which self-heals whenever `$heroId` no longer points at a still-open idea belonging to the user (done,
    deleted, or — since it's re-checked through `auth()->user()->craftIdeas()->open()` — foreign) by
    rerolling a fresh one. This is what makes `markDone`/`deleteIdea` on the hero itself "just work" without
    each mutation needing its own explicit reroll call.
  - **`rerollHero(bool $excludeCurrent)`** picks a random open idea into `$heroId`, tracking the previous
    pick in `$lastHeroId`. With `$excludeCurrent` (used by the explicit `shuffle()` action, not by the
    self-heal path), it draws from the open pool minus the current hero so "Andere Idee" never immediately
    repeats — falling back to the full pool if that would leave no candidates (i.e. only one open idea
    exists, so "excluding it" is impossible). An empty pool (no open ideas left) clears both ids and the
    view falls through to its empty state.
  - **The pinboard** (`otherIdeas` — every open idea except the hero, `orderBy('id')`) renders the rest as
    rotated "pinned note" cards (`resources/views/livewire/craft-ideas.blade.php`): a small randomised
    rotation/column-span/pin-colour per card (cycled from fixed arrays keyed on `$loop->index`, purely
    decorative, no persisted layout) with a drawing-pin dot glued to the top edge. Clicking a card's body
    calls `promote($id)`, which swaps it straight into the hero slot (scoped through `->open()`, so a
    done/foreign id 404s the same as every other mutation here) — the deliberate "pick a different one
    myself" alternative to the random "Andere Idee" shuffle.
  - **Done ideas** (`doneIdeas`, `orderByDesc('updated_at')`) are collapsed behind a client-only
    (`x-data="{ show: false }"`, no round-trip) "N erledigt · anzeigen" disclosure at the bottom, each shown
    as a small pill with a restore button (`restoreIdea`) rather than the row-per-entry layout Agenda uses.
- **Ownership scoping** — every single-idea mutation resolves through a private `userIdea(int $id)` helper
  (`auth()->user()->craftIdeas()->findOrFail($id)`), mirroring `TaskBoard::userTask()`/`Agenda::userEntry()`
  — a foreign id 404s (`ModelNotFoundException`), never silently no-ops or leaks another user's idea.
  `promote()` additionally scopes through `->open()` directly (not via `userIdea()`) since a done idea must
  never become the hero. **Delete** (`deleteIdea`) uses the same armed double-click pattern as everywhere
  else in the app (never `confirm()`) — both on the hero card and on each pinboard card (the latter
  hover-revealed on desktop via `group-hover/idea:opacity-100`, always-visible-but-dim on mobile since
  there's no hover there, same convention as the task card's quick-date placeholder). Deleting the idea
  currently open in the edit form (below) cancels the edit too, so the form never keeps editing something
  that no longer exists — mirrors `Agenda::deleteEntry()`.
- **Editing** — the same capture form doubles as the edit form, mirroring `Agenda::saveEntry()`'s single
  create/edit form rather than a separate modal: `$editingId` is null while capturing, set to an idea's id
  while editing it. `startEdit(int $id)` loads the idea into the form and dispatches `edit-idea-opened`,
  which the form's Alpine scope uses to expand the (otherwise collapsed) Notiz field, focus the title input,
  and scroll the form into view — necessary because the trigger can be a pinboard card scrolled well below
  the form. `saveIdea()` (renamed from the old create-only `addIdea()`) branches on `$editingId` to update
  vs. create, then resets the form and dispatches `idea-form-reset` (renamed from `idea-added`, since it now
  fires after an edit too) to collapse the Notiz field back down. `cancelEdit()` does the same reset without
  writing anything. A pencil button opens edit on the hero card (next to delete) and on each pinboard card
  (hover-revealed next to its own delete button, same convention as delete).
- **Capture** happens two ways: the page's own inline form at the top of `/app/crafts` (`saveIdea()` above —
  a page entirely about ideas shouldn't need the global panel, the same reasoning `ProjectPage` and
  `EmergencyMode` follow with their own inline add forms), and the app-wide QuickCapture panel's `craft`
  target from anywhere else (see Schnellerfassung above). Ideas are still only ever browsed/actioned on the
  dedicated `/app/crafts` page.

### Fortschritt & Motivation (built)
- "How did today go", made visible without inventing a second bookkeeping system for most of it —
  volume stats read from **`tasks.completed_at`**, the one field the app already writes. No
  XP/points — the "game" feel comes from a streak counter and a GitHub-style completion heatmap,
  both well-worn, calm patterns, not a scoring system.
- **Two distinct bases, deliberately not unified.** "How much did you do" (today's ring, the
  heatmap, the lifetime total, the goal/record celebrations) is raw **completion count**. "Did you
  succeed" (the streak, perfect-day stats, the perfect-day celebration) is **whether every task
  flagged "today" got done** — a stricter, binary measure the user asked for explicitly after using
  the count-based version for a day: "you completed a day for the streak if you've done every today
  task". The two live side by side in `ProgressStats` rather than one replacing the other.
- **`tasks.today_date`** — a nullable date recording which local day a task was flagged "today"
  *for*, distinct from `is_today` itself (a live flag with no date attached, never auto-reset —
  confirmed by grepping every write site before building this: nothing clears it overnight). Without
  this there was no way to ask "was day X's today-list fully cleared" for any past day, only infer
  from the live board state. Stamped by **`Task::todayDateFor(bool $newIsToday, ?Carbon $targetDate)`**
  — a pure helper reading the task's *current* (not-yet-saved) state: stamps `$targetDate` only when
  actually **entering** today (`is_today` was false); leaves an already-true flag's date **untouched**
  (so reordering within the Today zone, or `PrepareTomorrow` re-touching something already flagged,
  can't silently re-date an old leftover task to "now"); always **clears** to null on exit. Called
  from every one of the (many) places that write `is_today`: `TaskBoard` (`setToday`, `reorder`,
  `swipeIntent`, `assignTaskToProject`), `ProjectPage::assignToProject`, `ManagesTasks::saveEdit`
  (the edit sheet's three ways to move a task off the board's Today lists), `PrepareTomorrow::markToday`
  (using its own `targetDate` — evening mode flags *tomorrow's* tasks tonight, so stamping "now" would
  wrongly attribute them to the still-running today), `GroupPage` (`setToday`, `reorder`, `swipeIntent`
  — added 2026-08-21, see *Known Issues*: these three were missed when `today_date` was first wired up,
  since Task-Gruppen already existed by then and wasn't re-audited), and both API `TaskController`
  endpoints. Purely additive — nothing reads it for board/API display logic, and pre-migration
  `is_today=true` rows get no retroactive value (unreconstructable, so the streak effectively starts a
  clean count from ship day).
- **`App\Services\ProgressStats`** (stateless, like `PomodoroCycle`/`TaskSuggestor`):
  - Volume side: `completedCountsByDay()` (one query, reused by everything below it — never call in a
    loop), `todayCount()`, `bestDailyCount()`, `heatmap()` (12 weeks × 7 days, level 0–4 relative to
    the user's own daily goal, not a fixed absolute count), all keyed by **local calendar day**
    (`User::localToday()`, deliberately *not* `completedWindowStart()`, a separate board-only "how
    long do completed cards stay visible" concept). Each timestamp is shifted using the user's offset
    **at that instant** (`User::utcOffsetMinutes($task->completed_at)`), not "now" — DST-auto users
    have a different offset in July than in January, and this spans their whole history.
  - Streak side: `todayListStatsByDay()` (one query, groups by `today_date` into `{total, done}` —
    a day is simply **absent** as a key if no task was ever flagged today for it, not `{0,0}`),
    `dailySuccessMap()` (derives `total > 0 && done === total` per day), `currentStreak()`/
    `bestStreak()` (consecutive/longest runs of *successful* days — same shape as the old
    count-based version, just fed the success map instead), `perfectDaysCount()`, `perfectDayRate()`
    (null, not 0%, when no today-list has ever existed — "not applicable" reads differently from
    "you always fail"). **Always a live read, never a nightly-frozen snapshot**: finishing an old,
    left-over today-task days later can retroactively turn its original day into a success and heal
    a streak gap — there's no "close the day" ritual to freeze anything against, and it's honestly
    earned. A day with completions but **no** today-list at all does not count (same as the old
    "nothing completed" break) — using the today mechanism is now what the streak measures.
  - `streakTier()` (0–4, drives every streak color escalation, unaffected by the rework).
- **`App\Livewire\Progress`** (`/app/progress`, `route('progress')`) — a read-only page (three stat
  tiles: today-vs-goal ring, streak + best streak — with a second subtitle line for lifetime
  perfect-day count/rate once any today-list has ever existed — and lifetime total; then the
  heatmap; then a best-single-day callout). Reachable from the **profile dropdown** (next to
  Profil/Einstellungen — moved there from the "Mehr" menu: Fortschritt is about the account, not a
  workflow tool like Zeitplan/Agenda/Bastelideen, and the move restores a permanent entry point for a
  streak of 0, which the header badge alone doesn't provide).
- **The header streak badge** (hand-drawn `<x-flame-icon>`, no emoji) sits next to "Mehr" — rendered
  **only once `currentStreak() >= 1`**, so a fresh account's header looks exactly like it always did
  (no sad "0" state). Color escalates with `streakTier()` but is **capped at `forest`**: this app
  reserves `signal` for danger/urgency (armed delete, overdue, active emergency mode), so a positive
  streak never routes through it — a deliberate correction made during this feature's plan review.
- **Milestone celebration** — a non-blocking overlay (concentric topographic rings + flying
  line-mark particles, `resources/js/app.js`'s `celebration` Alpine store, mounted **once** in
  `layouts/app.blade.php` rather than inside any one Livewire component, so it fires no matter which
  page a task gets completed from) triggered by a `celebrate` browser event carrying `{kind, label}`.
  Fires for exactly four milestones, computed by **`ProgressStats::celebrationFor(User $user, Task
  $task, int $beforeCount): ?array`** — called from both real "mark a task done" sites
  (`ManagesTasks::toggleComplete()`, used by the board and `ProjectPage`; and the duplicated
  `Schedule::toggleDeadlineTaskDone()` on the Zeitplan's deadline strip), which each capture
  `$beforeCount = ProgressStats::todayCount($user)` **before** the `$task->update(...)` so goal/record
  crossings can be detected precisely instead of re-comparing aggregates after the fact. Checked in
  priority order, never more than one at once:
  1. **Neue Bestserie** (added 2026-08-21) — same "today just hit zero open today-tasks" trigger as
     Perfekter Tag below, but the resulting `currentStreak()` *also* just moved past `bestStreak()` as it
     stood before today (`unset($successMap[$today])` before calling `bestStreak()`, mirroring
     `bestDailyCount(..., excluding: $today)` for Neuer Bestwert below). Same "broken, never set from
     nothing" guard as Neuer Bestwert — day one of a first-ever streak doesn't celebrate "Bestserie: 1
     Tag". The rarest of the four (a perfect day that *also* beats every streak ever run), so it wins
     over a plain Perfekter Tag on the same completion, and escalates the overlay a size further still
     (24 particles, 2.7s) — see the `celebration` store's tiered `fire()` in `app.js`. Reuses `contour`
     rather than a new color: the four-tone Topografie palette has no fifth tone to spare, and `forest`/
     `overprint` are already Tagesziel/Neuer-Bestwert's own colors.
  2. **Perfekter Tag** — `$task->today_date` is today, and completing it just brought today's open
     today-tasks to zero (checked live post-update via `whereDate('today_date', ...)` — a plain
     `where()` against a *value*, not `whereDate()`, silently matches nothing here: a bare `'date'`
     cast still stores full datetime precision with a zeroed time-of-day, so an exact string
     comparison fails; see §10). Wins over a simultaneous goal/record on the same completion, and gets
     its own warmer/bigger overlay variant (18 particles vs. 12, `contour`-tinted not `forest`/
     `overprint`, 2.2s vs. 1.7s) rather than just a recolor.
  3. **Neuer Bestwert** — today's count just exceeded the all-time daily record. Can only be
     *broken*, never *set from nothing* — the first tasks ever completed don't celebrate "record: 1".
  4. **Tagesziel erreicht** — today's count just reached `daily_task_goal`.
  Deliberately **not** wired into the API controllers — there is no browser there to show anything to.
  No sound in this pass (autoplay-policy risk, hard to verify headless — see `TODO.md`).
- **Settings** has a **Fortschritt** tab: `daily_task_goal` (1–30, default 5, `saveDailyGoal()` —
  form-submit-with-flash like the reset-time card) and two independent immediate-save reminder
  toggles, mirroring the `notify_*` rows and the Vorbereitung reminder-time field.
- **Reminders** — the scheduled command **`app:send-progress-reminders`** (every minute, registered
  in `bootstrap/app.php` alongside the other four — same cron requirement, no new deployment step):
  - **Offene Aufgaben am Abend** (`notify_daily_reminder` / `daily_reminder_time`, default 19:00) —
    once that time has passed, if today still has open "Heute"-flagged board tasks. Dedup:
    `daily_reminder_sent_on`.
  - **Serie in Gefahr** (`notify_streak_risk`, fixed `User::STREAK_RISK_DUE_TIME` = 21:00, **not**
    user-configurable) — once that time has passed, if today isn't already a perfect day but a real
    trailing streak exists (`currentStreak()` counts through yesterday whenever today isn't a success
    yet — see above). The message is built from the same `todayListStatsByDay()` data the streak
    itself uses, so it names exactly how many today-tasks are still open (or nudges to set a list at
    all, if there is none) instead of a generic warning. Dedup: `streak_risk_sent_on`.
  Both dedup columns are "already sent today", not an exact-minute match, matching every other
  reminder command in this app.

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

### Shared class agenda (one time, when that feature first ships)
Nothing beyond the standard `php artisan migrate --force` — no new `.env` variable, no new dependency, no
new cron entry. Two things to be aware of, though:
1. **Registration must be reachable.** Classmates need their own accounts to join a class agenda. If
   `/register` was ever blocked off on the production box, unblock it or invite links will dead-end.
2. **The `is_done` → completions migration backfills data.** It is non-destructive (the old column is left
   intact), but take the usual DB snapshot before running it, and see `TODO.md` for the follow-up commit
   that drops the column later.

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
single crontab line, see step 10 above) — it drives `app:advance-pomodoro-phases`,
`app:send-event-start-notifications`, `app:send-event-upcoming-notifications`, `app:send-prepare-reminders`,
and `app:send-progress-reminders`, the five commands that make Pomodoro/event-start/event-upcoming/
Vorbereitung/Fortschritt push notifications fire even with no tab open. No separate queue worker is
needed (notifications send synchronously inline).

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

### An exact `where()` against a `'date'`-cast column can silently match nothing
**Symptom:** a query like `->where('some_date_column', $carbon->toDateString())` returns zero rows even
though a matching row visibly exists (confirmed via `->fresh()` or the model's own casted accessor) — no
error, no exception, just an empty result. In this app's case: `ProgressStats::celebrationFor()`'s
"is today's today-list fully cleared" check always found `stillOpen === 0`, so it fired the perfect-day
celebration on the very first completion of any day regardless of what else was still open.
**Cause:** a bare `'date'` cast (`'today_date' => 'date'`, not `'date:Y-m-d'`) still stores full datetime
precision — a zeroed time-of-day (`'2026-08-16 00:00:00'`), not a plain `'2026-08-16'` string. Reading the
column back through Eloquent's cast (`$task->today_date->toDateString()`) normalizes it correctly, which
is why the *other* half of the same check (comparing `$task->today_date?->toDateString()` against
`$today->toDateString()`) worked fine and masked the bug — only the *raw SQL* comparison
(`where('today_date', $today->toDateString())`) was broken, silently matching against the wrong stored
format. `json_encode()`/`toArray()` on a fetched model is *also* misleading here — Eloquent's JSON
serialization applies its own ISO-8601 formatting (`'2026-08-16T00:00:00.000000Z'`), which is neither the
raw DB value nor what an exact `where()` compares against; use `DB::table(...)->get()` (bypassing casts
entirely) to see the true stored string when debugging this.
**Fix:** use `whereDate('today_date', $today->toDateString())` instead of `where(...)` for any exact-day
equality check against a `'date'`-cast column — `whereDate()` wraps the comparison in the grammar's own
`DATE()` extraction, so it works regardless of stored precision. (`<=`/`COALESCE` comparisons elsewhere in
this app, e.g. `Task::scopeBoardOrdered()`'s deadline threshold, happen to tolerate the mismatch because a
shorter date string sorts before a longer one that starts with it — only *exact equality* is actually
broken by this, which is why it went unnoticed until the first `where()`-equality check against a date
column was written.) Caught by a dedicated test (`ProgressStatsTest::
test_celebration_does_not_fire_perfect_day_while_other_today_tasks_are_still_open`) before this shipped.

### A new invariant added after a feature already exists needs an audit pass over that feature too
**Symptom:** the header streak badge (and other Fortschritt numbers) had gone quiet for real accounts with
real usage, with no error anywhere — `ProgressStats::currentStreak()` just kept returning 0.
**Cause:** `Task::todayDateFor()` and the "every `is_today` write must also write `today_date`" invariant
were introduced by the `today_date` migration (2026-08-18, commit `30201ac`) — but **Task-Gruppen already
existed on main by then** (commit `115ad56`, earlier), and `GroupPage::reorder()`/`swipeIntent()`/
`setToday()` were never updated to the new contract. They kept writing `is_today` alone, exactly as every
site correctly did before `today_date` existed. The result was silent, two-directional data corruption:
flagging a task "today" from inside a group page left `today_date` null (invisible to `ProgressStats`,
so a day with only group-flagged tasks could show zero total); unflagging it (or dragging it back to the
Inbox) left a stale `today_date` stamped (marooning that day as permanently "imperfect", since nothing
ever re-clears it). `currentStreak()` requires today-or-yesterday to be a fully perfect day, so either
corruption anywhere in the last day or two silently zeroed the streak — with no exception, no log line,
nothing to grep for, because both writes "succeeded" from the database's point of view. Confirmed live in
the dev DB: 6 real rows across 2 real users showed exactly this shape before the fix.
**Fix:** wired all three `GroupPage` sites to call `Task::todayDateFor()`, mirroring `TaskBoard`'s own
`reorder()`/`swipeIntent()`/`setToday()` exactly (see the `todayDateFor` list above, now updated to
include `GroupPage`). Existing corrupted rows were repaired by a one-off data migration
(`2026_08_21_000001_repair_today_date_missed_by_group_page.php`) — safe on a fresh install (no matching
rows), and idempotent — plus regression tests in `TaskGroupsTest` for all three sites and an end-to-end
test asserting `ProgressStats::currentStreak()` directly. **The general lesson:** when a new invariant is
added to an existing column/flag, grep every write site across the *whole* app before considering the
feature done, not just the sites touched by the commit that's adding the invariant — a site that predates
the invariant is exactly the one most likely to be missed, because nothing about editing it that day would
naturally draw your attention there. The Task-Gruppen section above documents the same lesson learned once
already (`group_id` vs. `assignTaskToProject`/`assignToProject`); this is the same mistake shape recurring
across a different pair of features.

### PHP 8.2+ forbids accessing a trait's own constant via the trait's name
**Symptom:** `Cannot access trait constant App\Livewire\Concerns\X::FOO directly` — thrown at the call site,
not where the trait defines it.
**Cause:** a `public const` declared inside a `trait` may only be reached through a **class** that `use`s the
trait (or via `self::`/`static::` from inside the trait itself); `TraitName::FOO` from outside is rejected.
**Fix:** put constants that need to be referenced by name from outside on a real class instead (e.g.
`ScheduleEvent::EVENT_COLORS`, not `ManagesSchedule::EVENT_COLORS`), and have the trait's own methods read it
via the class too. Only use a trait constant if every reader is either the trait itself or a class using it.

### A trait's `mount<TraitName>()` lifecycle hook must be `public`, not `protected`
**Symptom:** every test that mounts a component using the trait fails with
`Method App\Livewire\X::mountTraitName does not exist.` — even though the method is right there in the
trait, correctly named.
**Cause:** Livewire automatically calls a `mount<TraitBasename>()`/`boot<TraitBasename>()`/
`booted<TraitBasename>()` method for every trait a component uses (`SupportLifecycleHooks::callTraitHook()`),
without the component needing to call it itself. It invokes that method via
`Illuminate\Container\BoundMethod`-style resolution from *outside* the class, the same mechanism used to call
`mount()`/`render()` themselves — and that resolution path cannot see a `protected`/`private` method, so it
fails, but with a generic "does not exist"-shaped message rather than a visibility error, which reads like a
typo or a missing method rather than what it actually is.
**Fix:** declare any trait's `mount<TraitBasename>()` (and its `boot`/`booted` siblings, if used) `public`.
(See `App\Livewire\Concerns\ManagesModuleSettings::mountManagesModuleSettings()`.)

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
the `prepare` store). Relying on "`x-init` only runs once" (true for a plain Alpine page, false once
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
See the `prepare` store in `resources/js/app.js` for the reference implementation.

### `wire:model.blur` did not reliably fire a request in this Livewire 4 setup
**Symptom:** a textarea bound with `wire:model.blur="foo"` — intended to sync only when the field loses
focus, to get a "live-ish" server-rendered preview without per-keystroke chatter — never triggered a
`POST /livewire/update` on blur, whether the blur was caused by a genuine user click on another field,
a real click via the browser automation tool, or a programmatic `el.blur()` call. Confirmed via
`read_network_requests`: the request count never changed across several different blur-triggering attempts,
even though the field's value itself was updating correctly in the DOM the whole time (ruling out an
event-dispatch problem on the input side).
**Cause:** not root-caused — Alpine's own model-binding code (`vendor/livewire/livewire/dist/livewire.js`,
search `hasBlurModifier`) does register a plain `blur` listener that should fire regardless of how the blur
happened, so this may be specific to a textarea living inside a `wire:submit` form, a version-specific
Livewire 4 quirk, or something about this app's Alpine/Livewire wiring — not conclusively diagnosed, since
CLAUDE.md's "two failures = stop" rule applied once multiple different blur-triggering approaches all
produced the same non-result.
**Fix:** don't rely on `.blur` (or presumably `.change`/`.lazy`) for a "sync on demand, not every keystroke"
field. Use a plain deferred `wire:model="foo"` (identical to every other edit-sheet field, e.g. `editTitle`)
plus an explicit `wire:click="$refresh"` button the user can press to force a fresh server render — `$refresh`
is a core Livewire action, unconditionally sends a request, and (like any Livewire action) flushes every
pending deferred model along with it. See the Notizen field's "Vorschau aktualisieren" button in
`partials/edit-sheet.blade.php` — confirmed working via the same `read_network_requests` check that exposed
the `.blur` gap. If a future session needs the same "preview my formatted text" pattern, prefer this over
reaching for `.blur` again.

### Alpine silently ignores `@click` on an element that isn't inside an Alpine component
**Symptom:** a button with `@click="$store.something.doThing()"` does nothing at all — no error, no console
warning, no network request. Clicking it in the browser, and dispatching a real bubbling `MouseEvent` at it
from the console, both do nothing.
**Cause:** Alpine only walks and binds directives on elements **inside an `x-data` scope**. A `@click` on an
element with no `x-data` ancestor is never registered as a listener — the attribute just sits in the HTML.
This is easy to hit in `layouts/app.blade.php` specifically, because the layout chrome (header actions,
anything appended near `@livewireScripts`) usually sits outside every `x-data` on the page — unlike partials
inside a Livewire component, which are almost always nested in one already. Referencing a *store* rather than
local state makes it look like no component should be needed, which is exactly the trap.
**Fix:** put a bare `x-data` on the element itself. It costs nothing and makes it an Alpine component so its
own directives are processed:
```html
<button type="button" x-data @click="$store.quickCapture.show($event.currentTarget)">…</button>
```
Both quick-capture triggers in `layouts/app.blade.php` carry this, with a comment saying why — it reads like
a stray attribute otherwise and is an obvious thing for a later cleanup to "tidy away".

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

### `min-h-` instead of `h-` on a flex ancestor silently breaks percentage-based positioning inside it
**Symptom:** on the Wochenplan's mobile view, every block rendered as a squat, auto-sized pill pinned near
the top of its column instead of a properly time-scaled rectangle — `x-bind:style="top:${top}%;
height:${height}%"` (the same `scheduleEvent` component the Zeitplan already uses successfully) was
producing valid, sane percentage values, but they visibly weren't being applied.
**Cause:** the mobile wrapper used `class="flex min-h-[calc(100dvh-4rem)] flex-col …"` where the Zeitplan's
own (working) mobile view uses `h-[calc(100dvh-4rem)]`. `min-height` lets the element auto-size to its
content instead of committing to a definite height — and a CSS percentage `height` several levels down
only resolves against an ancestor with a *definite* height. One copy-paste letter (`min-h-` vs `h-`) was
the only difference from the Zeitplan's identical-looking, working markup; `getBoundingClientRect()` on
the intermediate `.flex.h-full` wrapper read `0` even though its own parent measured a correct ~600px,
which is what gave it away — a definite-looking parent height doesn't help if *it* isn't definite either.
**Fix:** use a fixed `h-[…]`, not `min-h-[…]`, on any flex ancestor that a percentage-positioned descendant
(anything using `top:%`/`height:%` for layout, not just padding) needs to resolve against. If more content
has to go below a fixed-height block like this, put it in a separate sibling section after the fixed-height
one closes, rather than loosening the fixed height to fit it in.

### PHPUnit's default 128M `memory_limit` (`php.ini`) becomes too small as the suite grows
**Symptom:** `php artisan test` (no filter, the full suite) dies with `Fatal error: Allowed memory size of
134217728 bytes exhausted`, deep inside Livewire's test JSON encode/decode — but the same test passes
instantly and cleanly when run alone or filtered to its own file. Bisecting (`tests/Unit` alone,
`tests/Feature` alone) showed both halves passing individually; only running the *whole* suite in one
PHP process (no `--process-isolation`) crossed the ceiling.
**Cause:** PHPUnit runs every test in a single long-lived PHP process by default, so nothing from earlier
tests is freed before later ones run — the 128M limit is a `php.ini` default shared with `php artisan
serve`, sized for one request, not hundreds of accumulated test cases. This was latent, not something any
one test caused; it surfaces as "whichever test happens to be running when the total finally tips over."
**Fix:** raise `memory_limit` for test runs only, via `<ini name="memory_limit" value="512M"/>` in
`phpunit.xml`'s `<php>` block — scoped to PHPUnit, leaves the shared `php.ini` (and therefore `php artisan
serve`/production) untouched. A CLI `-d memory_limit=…` flag on the `php artisan test` invocation does
**not** reliably propagate to the process that actually runs PHPUnit; the `phpunit.xml` `<ini>` directive
does, since PHPUnit applies it directly via `ini_set()` before running.

### The local dev SQLite file can silently drift out of sync with the migration history
**Symptom:** `php artisan migrate` for a brand-new migration fails with `no such table: agenda_entries` (a
table created by a migration from weeks earlier) — but re-running the *full* migration history from
scratch then fails the opposite way, `table "tasks" already exists`. `php artisan migrate:status` shows
almost the entire history as "Pending" despite the tables visibly existing.
**Cause:** `database/database.sqlite` is gitignored and PHPUnit's `RefreshDatabase` tests run against
their own isolated database, never this file — so nothing in the normal edit/test/commit loop ever
touches it, and it can quietly fall behind (or, as found once, turn out to hold an entirely different,
much older schema — a `tasks` table with a `legacy_id` column, a `users` table with `list_labels`/
`onboarded_at` instead of any of this app's actual columns, `migrations` rows referencing files that don't
exist in `database/migrations/` at all — evidently a leftover from an earlier prototype of the app,
sitting at the same path this project's `.env` points at). The `migrations` tracking table and the actual
schema can end up telling two different stories, and diagnosing that mismatch is exactly what surfaced it.
**Fix:** back up the suspect file (rename, don't delete, in case it's someone's real data — this is a
"stop and ask" situation, not a silent auto-fix) and run a clean `php artisan migrate` to rebuild a
schema that actually matches the current migration history. This has no bearing on the real test suite
(which was green throughout) — it only affects manual/browser verification against the dev server.

### CSS classes applied only from JavaScript are silently purged out of the build
**Symptom:** a rule written in `resources/css/app.css` simply does not exist at runtime — no typo, no
specificity fight, no cascade problem. `getComputedStyle` returns the untouched defaults, and searching
`document.styleSheets` for the class name finds nothing. Drag & drop was the worst case: **every** piece of
its visual feedback (`.board-ghost`, `.board-chosen`, `.group-arm`, `.group-arm-label`) was missing, so
dragging a card gave no indication of anything at all and the task-group gesture looked completely dead
even though its logic was running correctly.
**Cause:** those rules live in app.css's `@layer components`, and **Tailwind v3 tree-shakes
`@layer components` / `@layer utilities` rules whose class name never appears in a file matched by
`content` in `tailwind.config.js`** (unlike `@layer base`, which is always emitted). The content globs
listed Blade and PHP only. These particular classes are applied exclusively from `resources/js/app.js` —
either by hand (`card.classList.add('group-arm')`) or as SortableJS options (`ghostClass: 'board-ghost'`) —
so Tailwind never saw them and dropped all four. Verify with
`grep -c board-ghost public/build/assets/app-*.css` → `0`.
**Fix:** `./resources/js/**/*.js` is in the `content` array — keep it there. Any new class that is only
ever added from JS needs its name to appear in a scanned file; putting the rule in `@layer base` instead
also works, but the glob is the honest fix. **After changing `tailwind.config.js`, restart the Vite dev
server** — a running `npm run dev` does not reliably pick up a config change, so the old purged CSS keeps
being served and it looks like the fix did nothing.

### SortableJS never calls `onMove` for the one region a drop-onto-card gesture needs
**Symptom:** with `invertSwap` correctly configured (previous entry), the middle band genuinely stops
reordering — cards hold still exactly as intended — but the gesture built on it still does nothing: no
highlight, no label, and releasing creates nothing. Instrumenting shows `_onDragOver` firing while the
pointer crosses the card, yet the `onMove` callback is never invoked and the DOM correctly never changes.
**Cause:** `_onDragOver` computes `direction` and then bails early —
`if (direction === 0 || sibling === target) { return completed(false); }` — **before** it reaches
`_onMove(...)`. `invertSwap` makes the middle band return `direction === 0` by design. So the single region
where a "drop onto this card" gesture must arm is precisely the region where `onMove` is guaranteed never
to fire. Any arming logic living in `onMove` is unreachable there, no matter how correct its band math is.
**Fix:** don't hang the gesture off `onMove`. Track the pointer independently for the duration of the drag
and resolve the hovered card yourself:
```js
onStart: (evt) => groupZone.startTracking(evt.item),   // adds document listeners
onEnd:   (evt) => { const id = groupZone.disarm(); groupZone.stopTracking(); /* … */ },
```
listening to `dragover` (desktop's native HTML5 drag), plus `pointermove`/`touchmove` (touch and
`forceFallback`), and finding the target with `document.elementFromPoint(x, y).closest('[data-id]')`
— skipping the dragged element itself, which stays in the DOM as the placeholder. Keep the `onMove`
predicate as a safety net for the edge cases that *do* reach `_onMove` (cross-list drops, `differentLevel`),
but never as the place arming happens. See `groupZone.track()` in `resources/js/app.js`.
> Also worth knowing when debugging Sortable: it binds **per-instance** copies of its private methods in
> the constructor (`this[fn] = this[fn].bind(this)`), so wrapping `Sortable.prototype._onDragOver` after
> instances exist observes nothing — wrap the methods on the instance. And `forceFallback` cannot be
> toggled via `.option()` after construction: `nativeDraggable` is computed once in the constructor.

### A "drop one card onto another" gesture is impossible until you set `invertSwap` on the Sortable
**Symptom:** any gesture that needs the pointer to rest *on* another card — dwell-to-group, or a
middle-of-the-card drop band — never triggers. The target card slides out from under the cursor before you
can release on it, and with a reorder-on-approach it can oscillate: you chase the card up and down and can
never land on it. `onMove` guards that check "am I in the middle of the target?" appear correct but never
help.
**Cause:** SortableJS's default swap zone is the **entire** target card. `_getSwapDirection`
(`node_modules/sortablejs/modular/sortable.esm.js`) takes the regular branch when `invertSwap` is false and
tests `mouseOnAxis > targetS1 + targetLength * (1 - swapThreshold) / 2 && mouseOnAxis < targetS2 - …`; with
the default `swapThreshold: 1` both margins are 0, so the test is simply "anywhere inside the target" and a
swap is triggered the moment the pointer crosses the card's leading edge. The pointer therefore *cannot*
reach the card's middle while that card is still there — it has already been reordered away, and what sits
under the cursor is the dragged element's own placeholder (which Sortable then ignores). Checking anything
in `onMove` at middle-of-card time is checking a state that can no longer occur.
**Fix:** change *where Sortable swaps*, don't try to veto it after the fact. Set `invertSwap: true` plus an
explicit `invertedSwapThreshold` (0.5 gives 25% / 50% / 25% bands). That routes Sortable to its inverted
branch, which swaps only within `invertedSwapThreshold / 2` of each end and `return 0`s — no swap at all —
everywhere in between, so the card genuinely holds still in the middle band and can be dropped onto. Bonus:
the inverted branch's direction (`mouseOnAxis > targetS1 + targetLength / 2 ? 1 : -1`) means the top band
inserts the dragged item *before* the target and the bottom band after it, which is usually exactly the
intended reorder semantics. Keep an `onMove` guard returning `false` for the same middle band as belt and
braces / to drive the visual cue, and derive its band size from the same threshold constant so the two can
never disagree. See `sortableGroupBands()` and `groupZone` in `resources/js/app.js` (Task-Gruppen §7); an
earlier 350ms-dwell design and a first `onMove`-only attempt both failed to this exact cause.

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
