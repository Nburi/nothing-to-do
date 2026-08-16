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
- **Build:** Vite 8. **Tests:** PHPUnit (372 tests).
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
  start): cycle 1 nudges to clear the ToDos list (falling through if none are open), any cycle then prefers
  the top active **today** task (board order), and once today's list is empty it falls back to a
  deterministic pick between a project's next task and another todos/tasks-list task — seeded by
  `(event id, cycle)` via `crc32()` so the choice stays stable across the ring's 5s poll instead of
  reshuffling every request. Hidden whenever the effective phase is a break. Rendered by
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
  target, again only rendered for someone actually in a class. `openCreateForm()` pre-selects the class the
  list is filtered to and otherwise defaults to private, so nothing is shared by accident. QuickCapture's
  confirmation line names the class (`→ Agenda · Klasse 4b`) — sharing is its one capture with a consequence
  beyond your own list. Fach suggestions now read from every *visible* entry, so a classmate's "Französisch"
  autocompletes for everyone.

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
`app:send-event-start-notifications`, `app:send-event-upcoming-notifications`, and `app:send-prepare-reminders`,
the four commands that make Pomodoro/event-start/event-upcoming/Vorbereitung push notifications fire even with
no tab open. No separate queue worker is needed (notifications send synchronously inline).

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
