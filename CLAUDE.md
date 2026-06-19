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
2. **Git branches** — always work on a feature branch, never directly on `main`/`master`.
   **Never merge** — the user does that.
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
- **Build:** Vite 8. **Tests:** PHPUnit (51 tests).

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
- **`User`** (Breeze) `hasMany` **`Task`** and `hasMany` **`Project`**.
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

### Carbon 3 `diffInDays()` returns a float
**Symptom:** day-bucket logic using `=== 0` / `=== 1` silently never matches (e.g. "heute"/"morgen").
**Fix:** cast to int: `(int) $today->diffInDays($date)`, and check overdue separately with `lessThan()`.
(See `Task::effectiveDateLabel`.)

### Livewire 4 generates emoji-named single-file components
**Symptom:** `php artisan make:livewire X` creates `resources/views/components/⚡x.blade.php`.
**Fix:** delete it and use a **class-based** component in `app/Livewire/` (ASCII filename, easier to test,
robust across Windows↔Linux git). Reference as `<livewire:task-board />` / route to the class.

### Browser preview tool can't trigger Livewire 4 `wire:` directives
**Symptom:** `preview_click` "succeeds" but no Livewire request fires (no `/livewire/update` POST).
**Cause:** the preview tool's synthetic click events don't reach Livewire 4's delegated listeners; only
`wire:model` (input) and the programmatic API work through it.
**Fix (for verification only):** drive actions with a real DOM event via `preview_eval`
(`el.click()`) or the API (`$wire.method()` / `Livewire.all()[0].$refresh()`). Real users are unaffected.

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
