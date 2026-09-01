# Plan: To-Do-Listen-Konzepte (List Concepts)

Status: **infra + "Simple" + "Eisenhower-Matrix" implemented and merged into `main`; Kanban not yet.**
Plan written on branch
`feature/list-concepts-plan` (branched off `main` at commit `7fe5c7c`, 2026-08-31) — see
"Housekeeping note" at the bottom for why that branches off `main` instead of
`feature/feature-announcements` as originally instructed. The infra session described in §4
"Shared" then landed on **`feature/list-concepts-infra`** (branched off `feature/list-concepts-plan`,
2026-08-31) — `ListConcepts` catalog, `users.list_concept`, the `TaskBoard` `@switch` seam +
`partials/board-three-things.blade.php`, the Settings "Listen-Konzept" card (including the
real-data preview-thumbnail signature moment, §5 option C), and the `QuickCapture` hook — with
the full automated suite green (1146 tests). Two independent sibling sessions then each built one
concept off `feature/list-concepts-infra`, neither depending on the other or containing the
other's commits: the "Simple" concept on **`feature/list-concept-simple`** — `simple` flipped to
`available: true`, `partials/board-simple.blade.php`,
`TaskBoard::simpleTasks()`/`reorderSimple()`/`setTodaySimple()`/`swipeIntentSimple()`, the
`QuickCapture` chip-collapse §4 deferred to it, and a drag-sortable "Heute" zone (added in a later
bugfix pass, replacing an earlier per-card toggle badge) — full automated suite green (1183 tests
at the time of its last bugfix pass); and the "Eisenhower-Matrix" concept on
**`feature/list-concept-eisenhower`** — `eisenhower` flipped to `available: true`,
`partials/board-eisenhower.blade.php` (desktop 2×2 grid + mobile 4-tab layout),
`TaskBoard::eisenhowerQuadrants()`/`reorderEisenhower()`/`setTodayEisenhower()`/
`swipeIntentEisenhower()`, `Task::isUrgencyLocked()`, the `QuickCapture` quadrant tap-to-create
pre-fill hook, and its own signature moment ("der Krisenring") — full automated suite green (1200
tests at the time of its last bugfix pass). Both branches' own QuickCapture chip-collapse got a
matching fix for the sibling concept once compared side by side (see CLAUDE.md's "Bugfix pass"
notes in each concept's own section). All three (infra, `simple`, `eisenhower`) have since been
merged into `main`, in that order, resolving the expected small conflicts by hand per §8's
coordination note. Full detail in CLAUDE.md's "To-Do-Listen-Konzepte", "To-Do-Listen-Konzepte —
'Simple'" and "To-Do-Listen-Konzepte — 'Eisenhower-Matrix'" sections and in `TODO.md`'s matching
follow-up entry, which is also where `kanban` — the one concept still on its own unmerged branch —
is tracked as the next step.

Two small, deliberate deviations from this document, both explained in CLAUDE.md/TODO.md:
- `ListConcepts::CATALOG` ships **all four** concepts now (with an `available` flag), rather than
  starting with only `three_things` and having each concept session add its own array entry from
  scratch — this lets Settings show the other three as "bald verfügbar" today, which is more
  honest/discoverable than them not existing in the UI at all yet. A concept session still only
  ever touches its own entry (flips `available` to `true`), so §8's "small, predictable conflicts"
  coordination note still applies unchanged.
- The `simple`-only `QuickCapture::availableTargets()` chip-collapse described in §4 was left for
  the `simple` session itself rather than stubbed by infra, since `simple` isn't selectable yet —
  building it now would have been unreachable, unverifiable code. `defaultCaptureList()` (the
  other half of the same paragraph) **was** built and wired in, since it's real, testable
  behavior today (`'inbox'` for every currently-available concept).

Goal: let a user pick which mental model their to-do list follows — instead of forcing
everyone through "3 Things" — and switch between them without ever losing data.

---

## 0. Taste inventory (why this plan looks the way it does)

Read from the existing codebase (CLAUDE.md + `app/Services`, `app/Livewire`):

1. **User-configurable feature sets are stateless catalogs**, not DB enums or scattered
   conditionals — `AppModules::CATALOG`, `HeaderBadges::CATALOG`, `FeatureAnnouncement::TYPES`
   all share one shape: a plain PHP array of `key => {label, description, ...}`, read by both
   the settings UI and every consumer. This plan follows the same shape for a new
   `ListConcepts::CATALOG`.
2. **Nothing is ever destructive.** FKs are `nullOnDelete`, settings self-heal
   (`defaultLandingRouteName()` falls back to the board if the stored choice no longer
   resolves), deletions use the armed-double-click pattern. A "concept switch" must be the
   same: instant, reversible, and never a data migration.
3. **Settings is one long scrolling page** with anchored `<section id="...">`s and
   immediate-save controls (`wire:click` toggles/pills), not a wizard with a Save button.
4. **The board has exactly one controller-shaped Livewire component** (`TaskBoard`) — "there is
   no task controller, the component IS the controller." New board behavior extends that class
   and its `ManagesTasks` trait, it doesn't spawn a second competing board component.
5. **"Zero footprint when off/unused."** Every optional feature (homework preview, prepare
   prompt, header badges, emergency banner) renders nothing at all when not applicable, rather
   than an empty/disabled placeholder.
6. **New user-facing features get a highlight mechanism for free** — the
   `?highlight=<selector>` query-param + `.announcement-highlight` CSS flash already built for
   `FeatureAnnouncement`/Settings anchors is reusable for pointing at the new Settings card.
7. **A new user-facing feature drafts a `FeatureAnnouncement`** (unpublished) — CLAUDE.md §3.11.

---

## 1. The concepts

All four concepts below are **pure views over the existing `tasks` table** — none of them adds
a required, concept-specific column. This is deliberate and is what makes switching lossless
"for free": the underlying rows never change shape, only which lens renders them. See §3 for
the mapping table.

### 1. "3 Things" (existing default — unchanged)
To-Do vs. Task vs. Project, sorted by size and shape; Inbox → triage into ToDos/Tasks/Projects;
`is_today`, `is_important`, `deadline`/`due_date`. Everything documented in CLAUDE.md §1/§7
already. Ships as-is; this plan wraps it, doesn't touch its behavior.

### 2. "Simple"
One flat, undivided list. No Inbox/ToDo/Task distinction, no triage step. Every active
on-board task (any `list` value) renders in one `boardOrdered()`-sorted list. Capture always
lands directly as a real task (no inbox holding pen) — see §3.

### 3. "Eisenhower-Matrix"
A 2×2 grid: Wichtig×Dringend, Wichtig×Nicht dringend, Unwichtig×Dringend, Unwichtig×Nicht
dringend. Reuses `Task::is_important` for the Wichtig axis and the existing `Task::isUrgent()`
(deadline/due-date within `URGENCY_DAYS`, already on the model) for the Dringend axis. No new
axis data at all — this concept is a straight re-projection of two flags that already exist and
already drive `boardOrdered()`'s own sort.

### 4. "Kanban"
Three columns: Backlog / In Arbeit / Erledigt. Maps onto existing signals: not
`is_today`+not completed = Backlog; `is_today`+not completed = In Arbeit; `is_completed` (within
the board's existing completed-visibility window) = Erledigt. Dragging a card into "In Arbeit"
sets `is_today` (through the existing `Task::todayDateFor()` helper, exactly like every other
`is_today` write site); dragging into "Erledigt" calls the existing `toggleComplete()`;
dragging back to "Backlog" clears `is_today`. No new column, no new drag primitive — reuses
`window.boardSortable` and `ManagesTasks::toggleComplete()`/`setToday()` as they exist today.

### Considered and deliberately not included in this round

- **Eat-the-Frog / Top-1-Priorität** — genuinely different mental model (pick exactly one task
  to do before anything else), but it's the one concept that can't be built from existing
  columns alone — it needs a small `users.frog_task_id`/`frog_date` pair (mirrors the existing
  `emergency_project_id` pattern, so the shape isn't risky) plus real UI thought about what
  happens once the frog is done. Worth doing, but it's thin if rushed alongside four other
  concepts in the same batch. **Recommendation: a 5th concept in a later round, not this one.**
- **Time-blocking-driven** — explicitly rejected as a *list concept*. The app already has a
  full, heavily-built time-blocking feature (Zeitplan + Wochenplan, §7). Building a second,
  competing "time-blocking to-do concept" would duplicate half of an existing feature area
  instead of reusing it. If a "my list IS my calendar" feel is wanted later, the right lever is
  making Zeitplan a valid **Startseite** choice (already possible via `AppModules`/
  `default_page`) or giving the Zeitplan page a lightweight backlog rail — not a fifth
  `list_concept`.

Net: **4 concepts total** (3 Things, Simple, Eisenhower, Kanban) — matches the "keep scope sane"
instruction: one shared infra session + one session per concept = 5 sessions, not more.

---

## 2. Requirements

| # | Herkunft | Anforderung → Grund → Lösung |
|---|---|---|
| 1 | `PROMPT` | Nutzer können aus mehreren Listen-Konzepten wählen → weil eine Denkweise nicht zu allen passt → `ListConcepts`-Katalog + `users.list_concept` + eine Settings-Karte. |
| 2 | `PROMPT` | Konzeptwechsel darf **nie** Daten verlieren → zentrale Anforderung des Auftrags → alle vier Konzepte sind reine Views über dasselbe Task-Schema (§1), keine konzeptspezifischen Pflichtfelder, kein Datenmigrationsschritt beim Wechsel. |
| 3 | `CODEBASE` | Neue konfigurierbare Feature-Sets folgen dem stateless-Catalog-Pattern → etablierte, für genau diesen Zweck gebaute Konvention (`AppModules`/`HeaderBadges`) → `ListConcepts::CATALOG`, gleiche Form. |
| 4 | `CODEBASE` | Der Wechsel selbst ist ein sofort speicherndes Setting, keine mehrstufige Form → jede vergleichbare Einstellung in dieser App (Modul-Toggle, Startseite-Pill, Vorbereitung-Zeitpunkt) folgt diesem Immediate-Save-Muster → Pill-Auswahl in Settings, `wire:click` pro Option. |
| 5 | `GERATEN` | Der Wechsel muss beliebig oft wiederholbar sein, nicht nur eine Onboarding-Einmalwahl → die App friert grundsätzlich nichts ein (Startseite/Module sind jederzeit änderbar) → Settings-Karte bleibt dauerhaft editierbar. |
| 6 | `GERATEN` | Begleitfeatures (Vorbereitung, Notfallmodus, Planer, Zeitplan, Fortschritt, Header-Badges) bleiben in dieser Runde **konzept-agnostisch**, unverändert → eine vollständige Durchdringung aller ~15 bestehenden Features würde den Scope sprengen, explizit gegen den Auftrag ("Scope sane halten") → nur `TaskBoard` (die Hauptboard-Seite) und `QuickCapture`s Ziel-Routing werden konzeptabhängig; jedes andere Feature liest weiterhin exakt dieselben Task-Spalten wie heute, unabhängig vom aktiven Konzept. |
| 7 | `PROMPT` | Weitere Konzepte müssen sich später sauber ergänzen lassen → explizit gefordert → Katalog-Pattern + "Konzept Nr. 5 hinzufügen"-Anleitung (§6). |
| 8 | `CODEBASE` | Ein spürbares neues Feature bekommt einen unveröffentlichten `FeatureAnnouncement`-Entwurf → globale Regel CLAUDE.md §3.11 → für die Infra-Session vorgemerkt. |

`GERATEN`-Zeilen (5, 6) sind die, die ein Reviewer zuerst gegenlesen sollte — insbesondere 6,
das den Schnitt dieser ganzen Feature-Runde bestimmt.

---

## 3. Data mapping — why nothing is ever lost

No concept owns data another concept can't interpret. Switching is a pure re-render, not a
migration:

| Task field | 3 Things | Simple | Eisenhower | Kanban |
|---|---|---|---|---|
| `list` (inbox/todos/tasks/projects) | drives the 3 board columns + Projekte | ignored for display (all shown together); capture always writes `'tasks'` | ignored for display | ignored for display |
| `is_important` | star, sorts to top | shown as a star, no sort effect | **Wichtig-Achse** | shown as a star |
| `deadline`/`due_date` → `isUrgent()` | drives due-soon sort + badges | shown as a badge | **Dringend-Achse** | shown as a badge |
| `is_today` | Heute-Fokus flag | shown as a badge | shown as a badge | **In-Arbeit-Signal** |
| `is_completed` | strikethrough, ages out of view | strikethrough, ages out of view | strikethrough, ages out of view | **Erledigt-Spalte** |
| `project_id` / `group_id` | Projekte-Spalte / Gruppen-Box | task still belongs to its project/group; Projects/Groups pages stay reachable via nav (unchanged) exactly as before | same as Simple | same as Simple |

Switching from any concept to any other concept **changes nothing in the database** — only
`users.list_concept` is written. A task created while "Kanban" was active shows up correctly
under "3 Things" the moment the user switches back (in whichever board column its `list` value
already puts it), and vice versa. This is the whole answer to "how do we map data across
concepts without loss": there is no mapping step, because there was never a second data shape.

---

## 4. Shared infrastructure vs. per-concept work

### Shared (infra session)
- `users.list_concept` (string, default `'three_things'`, not nullable) — same shape as the
  existing `users.default_page`.
- `App\Services\ListConcepts` — stateless catalog. Starts with **only** the `three_things`
  entry (matching current reality exactly, so the infra session alone changes nothing
  observable). Each concept session adds its own entry.
  ```php
  class ListConcepts
  {
      public const CATALOG = [
          'three_things' => [
              'label' => '3 Things',
              'description' => 'To-Do, Task oder Projekt — sortiert nach Grösse, mit Inbox-Triage.',
          ],
          // 'simple', 'eisenhower', 'kanban' — added by their own sessions.
      ];

      public static function for(User $user): string
      {
          return array_key_exists($user->list_concept, self::CATALOG)
              ? $user->list_concept
              : 'three_things';
      }

      public static function isValid(string $key): bool
      {
          return array_key_exists($key, self::CATALOG);
      }
  }
  ```
  `for()` is the self-healing read every consumer uses — same shape as
  `AppModules::isValidLandingPage()`/`User::defaultLandingRouteName()`, so a `list_concept`
  value left over from a concept that isn't deployed yet (or was ever removed) always falls
  back to `three_things` instead of rendering nothing. `Settings::setListConcept()` and
  `TaskBoard`'s own render path both call through `ListConcepts::for()`/`isValid()`, mirroring
  the dual-consistency lesson already documented for `AppModules` (CLAUDE.md §7).
- **Settings card** ("Listen-Konzept", own `<section id="list-concept">`) — one pill row per
  catalog entry (mirrors the existing Vorbereitung `prepare_time_of_day` pill pattern),
  immediate-save via `Settings::setListConcept(string $key)`, each pill showing the catalog's
  label + one-line description, plus a static reassurance line: *"Deine Aufgaben bleiben
  erhalten — nur die Ansicht wechselt."* Only catalog entries that exist are shown, so the row
  grows automatically as concept sessions land — nothing to wire up per concept beyond adding
  the catalog entry.
- **`TaskBoard` becomes concept-aware at exactly one seam**: the Blade view
  (`resources/views/livewire/task-board.blade.php`) branches once —
  ```blade
  @switch(\App\Services\ListConcepts::for(auth()->user()))
      @case('three_things') @include('livewire.partials.board-three-things') @break
      {{-- future: @case('simple') / 'eisenhower' / 'kanban' --}}
      @default @include('livewire.partials.board-three-things')
  @endswitch
  ```
  The infra session's job is to **extract the current board markup verbatim** into
  `partials/board-three-things.blade.php` behind that switch — a pure refactor, zero behavior
  change, provable with the existing test suite + a manual before/after screenshot. All
  existing computed properties/mutations on `TaskBoard`/`ManagesTasks` are untouched; concept
  sessions only ever *add* new computed properties (`eisenhowerQuadrants()`, `kanbanColumns()`,
  `simpleTasks()`) and a new partial + `@case`, never touch another concept's branch.
- **`QuickCapture` gains one hook**: `ListConcepts::defaultCaptureList(User $user): string`
  (returns `'inbox'` for every concept except `simple`, which returns `'tasks'`) feeding
  `QuickCapture::$target`'s initial value, plus `availableTargets()` (which already filters
  `TARGETS` by hidden modules) gets one more filter step: under `simple`, collapse the three
  `TASK_TARGETS` chips (Inbox/ToDos/Tasks) into a single "Aufgabe" chip. This is the **only**
  concept-driven change to QuickCapture — Eisenhower/Kanban need no capture changes at all,
  since a freshly captured task already carries whatever `is_important`/date/`is_today` flags
  it needs to land in the right quadrant/column the moment it's created.
- **Discoverability nudge in onboarding**, *not* a full interactive rebuild: the existing "3
  Things" onboarding slide (CLAUDE.md §7 Onboarding — the chip-switch `x-data="{ size: 'todo'
  }"` demo) keeps teaching 3 Things as the default exactly as it does today, but gets one
  appended line + a settings deep-link, reusing the **already-built**
  `?highlight=<selector>` mechanism from Feature-Announcements: *"Das ist nicht die einzige
  Ansicht. Probier andere Listen-Konzepte in den Einstellungen aus →"* linking to
  `route('settings') . '?highlight=%23list-concept'`. See §5 for why this plan deliberately
  does **not** turn the onboarding step itself into a live concept-picker.
- **Signature moment** (chosen below, §5) — lives entirely in the Settings card, so it's
  infra-session scope, not any one concept's.
- Draft `FeatureAnnouncement` (unpublished): title "Neu: Listen-Konzepte", one-liner, linked to
  `related_module` → n/a (Settings isn't in `AppModules::CATALOG`; use `highlight_selector`
  `#list-concept` on the `settings` module instead, same pattern the editor already supports).
- Tests: `ListConcepts::for()` fallback behavior, `users.list_concept` default, Settings pill
  writes + rejects invalid keys, `TaskBoard` renders the extracted `three_things` partial with
  byte-identical output to the pre-refactor board (regression proof that infra alone is
  behavior-neutral).
- CLAUDE.md: add a new `§7` subsection "To-Do-Listen-Konzepte" documenting the catalog +
  extension recipe (§6 below), matching the project's own documentation convention.

### Per-concept (one session each, branched off the infra branch)
Each concept session's diff is additive and small by construction:
1. Add its entry to `ListConcepts::CATALOG`.
2. Add its `@case` + `partials/board-<concept>.blade.php` (desktop **and** mobile layout — see
   note below, mobile nav shape is inherently concept-specific).
3. Add whatever new `TaskBoard` computed properties it needs (pure reads derived from existing
   columns, per §3 — no new mutations except where called out below).
4. Any concept-specific mutation wiring:
   - **Simple**: `defaultCaptureList()`/`availableTargets()` override (already stubbed by
     infra, this session fills in the `simple` branch).
   - **Eisenhower**: quadrant tap-to-create pre-fills `is_important`/a near-term date on the
     QuickCapture call (small, optional-params extension to `QuickCapture`, scoped to this
     session, not infra — infra doesn't need to anticipate it).
   - **Kanban**: column drag reuses `ManagesTasks::toggleComplete()`/`setToday()` verbatim
     (via `Task::todayDateFor()`, exactly like every other `is_today` write site — see
     CLAUDE.md's "today_date" audit-lesson entry, this is precisely the kind of new write site
     that entry warns future work to get right the first time) — the "Erledigt" column reuses
     the board's **existing** completed-visibility window, not a new one.
5. Tests + a manual browser pass (drag/tap on both breakpoints).
6. Update the FeatureAnnouncement draft's description to mention the new option (still
   unpublished until the whole batch is ready, publishing is the user's call).

**Mobile layout is explicitly per-concept, not shared.** The current mobile bottom-nav
(Inbox/ToDos/Tasks/Projekte + Heute tabs) is a 3-Things-shaped assumption. Simple needs no tabs
at all (one screen). Eisenhower needs 4 quadrant tabs (or a swipeable 2×2). Kanban needs 3
column tabs. Each concept session owns its own mobile shape; infra does not try to build one
generic tab-bar abstraction that all four squeeze into — that's exactly the kind of premature
abstraction this project's own conventions warn against.

---

## 5. Signature moment (pick one)

The feature is a settings/architecture change by nature — but there's one place a user
genuinely *feels* it: the instant they change their mind about how their list should look,
having already put real tasks into it. Three options, all scoped to the Settings card so they
can't destabilize the live board:

**A — "Konzept-Flug beim Wechsel."** Choosing a new pill in Settings doesn't just save and wait
for the next page load — before it saves, a small live strip inside the Settings card animates
your own top ~6 active tasks flying from their current concept's shape into the new one
(columns dissolving into quadrants, etc.), right there, so switching feels like watching your
own real list reorganize itself, not just clicking a label.

**B — "Board-Morph statt Reload."** The animation happens on the *live board itself*: after
saving, the next time `/app` renders, existing task cards (matched by id, same DOM node) glide
into their new column/quadrant/position via a FLIP-style transition instead of a hard
re-render — e.g. a card visibly slides out of "Tasks" and into "Wichtig & Dringend." Most
visceral, but the riskiest to build: this app has hit several Livewire-morph/`wire:key`
gotchas before (see CLAUDE.md §10) precisely around "preserve DOM identity across a concept
re-render," and this is exactly that problem, applied to *every* card at once.

**C — "Vorschau an echten Daten, vor dem Commit."** Each pill in the Settings card shows a
tiny live thumbnail built from the user's **actual** current tasks (not mock data) — selecting
"Eisenhower" previews a small 2×2 with your 4 most relevant real task titles already sitting in
their real quadrants, so picking a concept is "try it on" rather than a blind label choice, all
without ever touching the live board's own DOM.

**Recommendation: C.** Per the autonomy note, picking now rather than leaving it open: A and B
both eventually want the same "watch your real data reorganize" payoff, but B carries real
implementation risk (this is the *fourth* time this codebase has hit a subtle Livewire-morph
bug building something animated across a re-render — CLAUDE.md's `x-init`/`wire:key`/
`min-h-` entries are all instances of that exact class of bug) for a feature that's explicitly
going to be split across five separate, fresh-context sessions — a bad combination. C delivers
almost the same "oh, that's MY stuff" feeling, is fully self-contained to one Settings
component, and can't break the live board no matter how it's implemented. A later session can
always attempt B once the four concepts are stable and proven, if it still feels worth the
risk. Whoever picks up the infra session should treat this as overridable, not final.

---

## 6. Adding a 5th concept later (the extensibility recipe this was built for)

1. Add one entry to `ListConcepts::CATALOG` (label + description).
2. Add `partials/board-<key>.blade.php` (desktop + mobile) and one `@case` line in
   `task-board.blade.php`.
3. Add any new `TaskBoard` computed properties the view needs — prefer deriving from existing
   columns (per §3) over adding new ones; if a new column is genuinely unavoidable (as with
   Eat-the-Frog's `frog_task_id`), keep it nullable/additive on `users`, never on `tasks`,
   never required, so every other concept keeps working if the new one is never activated.
4. If capture needs concept-specific defaults, extend `ListConcepts::defaultCaptureList()`/
   `QuickCapture::availableTargets()` the same way Simple did.
5. Nothing else in the app needs to change — Vorbereitung, Notfallmodus, Planer, Fortschritt,
   the API, etc. stay concept-agnostic by design (requirement 6).

---

## 7. Explicitly out of scope this round ("später")

- Eat-the-Frog / Top-1-Priorität as a 5th concept (§1) — thin if rushed, needs its own small
  `users.frog_task_id`/`frog_date` pair and real thought about "what happens once the frog is
  done today."
- A time-blocking list concept — rejected in favor of reusing the existing Zeitplan/Wochenplan
  feature (§1).
- Making companion features (Vorbereitung, Notfallmodus, Planer, Zeitplan, Fortschritt, header
  badges) concept-aware. They keep working exactly as documented today, regardless of the
  active concept, for every concept in this batch.
- A fully interactive onboarding concept-picker (growing the existing "3 Things" slide into a
  live switcher). Infra ships a discoverability nudge + deep link only (§4); making the
  tutorial itself set `list_concept` is a reasonable follow-up once concepts are proven, not
  required to ship this.
- User-defined/renameable Kanban columns — ships with the fixed Backlog/In Arbeit/Erledigt set.
- Coupling `list_concept` to `AppModules`' module-visibility (e.g. auto-hiding Projekte/Gruppen
  nav entries under Simple) — deliberately kept as two independent settings a user can combine
  however they like, not one silently mutating the other (matches the explicit-opt-in
  convention already established for `only_for_module_users`, CLAUDE.md §7).
- Signature-moment option B (board-morph/FLIP animation) — noted as a possible later upgrade
  once C has shipped and the four concepts are stable (§5).
- Analytics on which concept is actually used.
- API/Shortcuts awareness of `list_concept` — the API stays concept-agnostic, exactly as it is
  today, operating on raw Task fields regardless of which lens the web UI currently shows.

---

## 8. Session split for the orchestrator

All five branch off `feature/list-concepts-infra` (itself off `main`), **except** infra itself:

1. `feature/list-concepts-infra` (off `main`) — §4 "Shared". Must land and be merged (or at
   least be the common ancestor) before any concept session starts, since every concept session
   needs `ListConcepts`, the `@switch` seam, and the extracted `board-three-things.blade.php`
   partial to exist first.
2. `feature/list-concept-simple` (off infra)
3. `feature/list-concept-eisenhower` (off infra)
4. `feature/list-concept-kanban` (off infra)

Coordination note: sessions 2–4 will each touch `ListConcepts::CATALOG` (adding one array
entry) and `task-board.blade.php` (adding one `@case` line) — expect small, predictable,
easy-to-resolve conflicts there when merging more than one concept branch back-to-back; nothing
structural, just sequence the merges and re-resolve each array/switch by hand.

---

## Housekeeping note

The task described branching this planning session off `feature/feature-announcements`. By the
time this session ran, `feature/feature-announcements`, `feature/module-settings`, and
`feature/onboarding-tutorial` were all already merged into `main` (confirmed via
`git merge-base --is-ancestor`), and `main` had moved further still (category-attributes,
Planer, and more, all merged). Branching off `main` at `7fe5c7c` instead is a strict superset
of the originally-requested base and is the clean starting point for the infra session.
