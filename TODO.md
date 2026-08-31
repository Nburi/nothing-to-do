# TODO

Open tasks and known follow-ups between sessions. Anything already described in `CLAUDE.md` as built is
done; this file is only for what is still outstanding.

## Follow-ups

### To-Do-Listen-Konzepte — concept sessions can branch off `feature/list-concepts-infra`

Infra shipped (`ListConcepts` catalog, `users.list_concept`, the `TaskBoard` `@switch` seam +
`partials/board-three-things.blade.php`, the Settings "Listen-Konzept" card with real-data
preview thumbnails, the `QuickCapture::defaultCaptureList()` hook) — see CLAUDE.md's
"To-Do-Listen-Konzepte" section and `PLAN_LIST_CONCEPTS.md` for the full design. Branch:
`feature/list-concepts-infra`, off `feature/list-concepts-plan` (itself off `main` at `7fe5c7c`).
Full automated suite green (1146 tests) at the time this landed. **Not merged, not pushed.**

Each remaining concept is its own session, branched off `feature/list-concepts-infra`:

1. ~~`feature/list-concept-simple`~~ — **done.** `simple` flipped to `available: true`,
   `partials/board-simple.blade.php` (desktop + mobile, one flat sortable list),
   `TaskBoard::simpleTasks()`/`reorderSimple()`/`setTodaySimple()`/`swipeIntentSimple()`, the
   `QuickCapture::availableTargets()` chip-collapse and its Settings preview thumbnail — see
   CLAUDE.md's "To-Do-Listen-Konzepte — 'Simple'" section. Branch `feature/list-concept-simple`,
   off `feature/list-concepts-infra`. **Not merged, not pushed.** Verification was
   automated-tests-only — a manual pass is still owed before merge.
2. ~~`feature/list-concept-eisenhower`~~ — **done.** `eisenhower` flipped to `available: true`,
   `partials/board-eisenhower.blade.php` (desktop 2×2 grid + mobile 4-tab layout),
   `TaskBoard::eisenhowerQuadrants()`/`reorderEisenhower()`/`setTodayEisenhower()`/
   `swipeIntentEisenhower()`, `Task::isUrgencyLocked()`, the `QuickCapture` quadrant tap-to-create
   pre-fill hook (`$important`/`$dueDate` on `resetPanel()`), and its Settings preview thumbnail —
   see CLAUDE.md's "To-Do-Listen-Konzepte — 'Eisenhower-Matrix'" section for the full detail,
   including the "der Krisenring" signature moment and the deliberate two-pronged answer to "how
   does a task move between quadrants" (existing card controls first, drag as a desktop-only
   shortcut on top, locked against a hard deadline). Branch `feature/list-concept-eisenhower`, off
   `feature/list-concepts-infra` — a **sibling** of `feature/list-concept-simple`, built
   independently, neither depends on the other. Full automated suite green (1191 tests) at the
   time this landed. **Not merged, not pushed.** Verification was automated-tests-only (explicit
   instruction, avoiding a known dev-server-hang trap) — no browser click-through was done
   (desktop drag gesture, the lock badge, the Krisenring's actual visual timing, both mobile
   tabs); worth a manual pass before merging, same as `simple`.
   **Revised in a later bugfix pass on the same branch:** added the `QuickCapture` chip-collapse
   the original session had left out (`defaultCaptureList()` staying `'inbox'` only covered the
   *default target*, not the fact that three chips still advertised a list-distinction this
   concept's board ignores) — `availableTargets()` now drops To-Dos/Tasks, keeping `'inbox'`
   (labelled "Aufgabe") as the sole task chip. See CLAUDE.md's "Bugfix pass" bullet at the end of
   that section. Still automated-tests-only for the same dev-server-hang reason.
3. `feature/list-concept-kanban` — the one concept left. Same shape, 3-column board reusing
   `toggleComplete()`/`setToday()`/`Task::todayDateFor()` exactly as they exist today. Branch off
   `feature/list-concepts-infra`, same as its two now-done siblings.

Expect small, predictable conflicts merging more than one concept branch back-to-back (each adds
one `ListConcepts::CATALOG` entry + one `@case` in `task-board.blade.php` + — as of `simple` and
`eisenhower` both independently building the same "Heute badge has no spatial zone" answer — one
duplicate `.today-toggle`/`.today-pulse-ring`/`@keyframes today-pulse` CSS rule to de-duplicate)
— sequence the merges and re-resolve each array/switch/rule by hand, per the plan's own
coordination note (§8).

**Also deferred from infra, flagged but not done — still applies after both `simple` and
`eisenhower` too:**
- A draft, unpublished `App\Models\FeatureAnnouncement` for this feature (CLAUDE.md §3.11 would
  normally call for one on any user-facing feature) — skipped because it's admin-authored content
  normally created through `AnnouncementEditor`'s own UI, and none of infra/`simple`/`eisenhower`
  had dev-server/browser access to use that UI safely. Create one (title "Neu:
  Listen-Konzepte", unpublished) via the admin panel once merged, mentioning at least the
  Settings card and that "Simple" and "Eisenhower-Matrix" are both available; update its
  description as `kanban` lands, publish once the whole batch (or a decided subset) is ready.

### Drop `agenda_entries.is_done` (blocked on a production deploy)

Shipped in the shared class agenda (branch `feature/agenda-class-spaces`, 2026-08-11): "done" moved from
`agenda_entries.is_done` to the `agenda_entry_completions` pivot, because a class entry is ticked off per
person. The migration backfills the column into the new table and nothing reads it any more — but the
column itself was deliberately left in place as a rollback point (CLAUDE.md §8: data-losing changes ship in
two steps).

**Do this only after the completions migration has run in production and the agenda has been used for a
few days:**

1. New migration dropping `agenda_entries.is_done`.
2. Remove the note from `AgendaEntry`'s `casts()` docblock and the migration comment that points here.
3. Remove this entry.

If anything about per-person completion turns out wrong before then, rolling back is still lossless — the
old column still holds the pre-migration state.

### Drop `schedule_event_task_links.source` (blocked on living with the day-planner rework for a while)

The Planer moved from block-granularity to day-granularity placement (branch `feature/day-planner`,
2026-08-29): tasks are now planned onto a day (`task_day_plans`, `App\Services\DayPlanner`), not linked to
one specific calendar block instance any more. `schedule_event_task_links` — the *separate*
"Zeitplan-Eintrag-Aufgaben-Verknüpfung" feature that binds specific tasks to one occurrence for the
Pomodoro focus-timer suggestion — is untouched and still fully live, but its `source` column
(`'manual'`/`'auto'`) is now vestigial: nothing writes `'auto'` to it any more (that was always the old
block-filling planner's own doing), so every row here will only ever read `'manual'` from now on.

Left in place rather than dropped outright — same two-step precedent as `agenda_entries.is_done` above
(CLAUDE.md §8: data-losing changes ship in two steps). Once this has been lived with for a while and it's
clear nothing still depends on distinguishing `source` here:

1. New migration dropping `schedule_event_task_links.source`.
2. Remove the docblock note on `ScheduleEvent::linkedTasks()` that points here.
3. Remove this entry.

### Local dev DB still carries a dead `tasks.task_group_id` column

Left over from the deleted first task-groups attempt (2026-07-31). Its migration file is gone with the
branch, so `migrate:rollback` cannot remove it, and SQLite refuses `ALTER TABLE … DROP COLUMN` for a column
that appears in a foreign-key definition — dropping it needs a full table rebuild, which is not worth
risking on a database with real tasks in it. The orphaned `task_groups` table, the `start_hint` column and
both stale `migrations` rows were removed; nothing in the code reads `task_group_id`.

**Production never had any of it** (those branches were never merged or pushed), so there is nothing to
deploy. Clean it up whenever the local database is next rebuilt from scratch.

### `main` is merged locally but not yet pushed — needs a deploy checklist run

The module-settings → onboarding-tutorial → feature-announcements → announcement-types chain (four
branches) was merged into `main` on 2026-08-26 (`b761db0`, one merge commit — `main` had
independently moved on in the meantime via `feature/planner` plus two more direct commits, so this
was a real three-way merge, not a fast-forward; conflicts in `routes/web.php`, `app/Models/User.php`,
and `layouts/app.blade.php` were resolved by hand and verified with the full test suite). `main` is
now **10 commits ahead of `origin/main`, not pushed** — pushing is deliberately left to Niels
(CLAUDE.md §3.1: "Never push — the user does that").

**Before/when pushing and deploying to production, run the full checklist in CLAUDE.md §9** — this
merge added six new migrations (`hidden_modules`/`default_page`/`onboarding_completed_at`/`is_admin`
columns, `feature_announcements` + `feature_announcement_dismissals` tables, and the announcement
`type` column) on top of the four already-pending from the `feature/planner` merge (`duration_minutes`
on tasks/agenda_entries, `planner_enabled`, `schedule_event_task_links.source`) — none of which have
ever been deployed. `php artisan migrate --force` on the production box picks up all ten in one run;
no new `.env` variable or dependency was introduced by either line of work.

**One pre-existing, unrelated test failure surfaced while verifying this merge:**
`Tests\Feature\Auth\PasswordResetTest::test_reset_password_link_screen_can_be_rendered` now fails
(expects 200, gets 405) — caused by commit `0215365` ("removed route 'password.request' because mail
servers aren't set up yet"), which commented out the `GET forgot-password` route but left the test
asserting the old behavior. Not caused by the merge above; flagged here since it was found in the
same test run. Needs a decision: update the test to match the intentional route removal, or restore
the route once mail is actually configured.

## Ideas, not committed

- **Push notification for a freshly published feature announcement.** Right now the "here's what's
  new" toast (see CLAUDE.md, Feature-Ankündigungen) only ever appears on the next page load — fine
  for "little quick", but someone who doesn't open the app for a while won't hear about a feature
  until they do. Mirrors the same "worth watching whether the in-app version already feels
  sufficient" caution already noted below for the category-link-empty notice.
- **Task groups in the API (Sanctum).** `tasks.group_id` is invisible to Shortcuts: a task cannot be filed
  into a group or read back with its group over the API, and there is no groups endpoint. Worth doing with
  the same care as the Agenda endpoints below rather than bolting on one field.
- **A markdown-notes partial shared by projects and groups.** `partials/group-notes.blade.php` and the
  brainstorm panel in `project-page.blade.php` are now two implementations of the same editor (toolbar,
  autosize, autosave, read/edit toggle). Worth folding into one parameterised partial — but as its own
  refactor, not smuggled into a feature branch.

- **Agenda API endpoints (Sanctum).** The Agenda is the only feature with no REST surface, so Apple
  Shortcuts can't reach homework at all. Shared spaces make this more interesting (a Shortcut that files
  homework for the whole class), but also raise the authorization surface — worth doing deliberately, not
  as an afterthought.
- **Push notification for a new class entry.** "Lena hat eine Hausaufgabe für morgen eingetragen" is the
  obvious next step now that entries can arrive from other people. Needs a rate-limit thought first: 22
  people writing into one class could get noisy fast.
- **Presence on the entries themselves**, not just in the member list — "Lena schaut sich das gerade an"
  next to a shared entry. Technically almost free (the heartbeat already exists), but hold it until the
  member-list version has been lived with for a while: on a homework list this could read as surveillance
  rather than help, and the whole product goal is "speed and calm".
- **The Zeitplan's deadline/homework/exam strip (`Schedule::deadlineItems()`) could extend into the
  "Vorbereitung für morgen" step 3** (`PrepareTomorrow`), which has its own, smaller one-day timeline. Not
  done in the branch that shipped the strip — `PrepareTomorrow` would need its own wiring of the same
  computed logic (it doesn't share a base class with `Schedule`), which is meaningfully more scope than the
  original request covered.
- **A sound for the milestone celebration** (Fortschritt & Motivation) was deliberately left out of the
  first pass — the existing focus-timer chime pattern (`window.primeFocusAudio()`, primed on a real click
  before the async round trip) could be reused, but audio-autoplay policy and headless-browser
  verification are both fragile enough that it felt safer to ship the visual-only version first and see
  whether it's missed before adding the complexity.
- **Watch how often "Tagesziel erreicht" actually fires** once the default goal (5) has been lived with —
  a low goal makes it an almost-daily celebration, which risks the same staleness the per-task version was
  explicitly avoided for, just at a coarser grain. If it starts feeling routine, raising the default (or
  making the celebration itself rarer, e.g. only on a fresh streak-day) would be the fix, not more visual
  intensity.
- **Category task links (Kategorie-Aufgaben-Verknüpfung) in the API (Sanctum).** Shortcuts can toggle a
  Pomodoro timer but can't see or change what a category is linked to — same gap as task groups above, and
  worth doing together rather than as two separate passes over `EventCategoryController`.
- **Manual reordering of pinned tasks** (`task_source = 'tasks'`) — the picker only supports add/remove
  today, in the pivot's insertion order. A small drag list (or up/down buttons) inside the sheet would let
  someone sequence which pinned task comes first, mirroring the emergency-mode arrange screen, but felt
  like more UI than the first pass needed.
- **Push notification when a category's linked list runs dry** — right now the "list just finished" moment
  is a quiet in-app notice (see CLAUDE.md, Kategorie-Aufgaben-Verknüpfung) that only shows if the dashboard
  happens to be open. A push would reach a session running with the tab closed, but risks being noisy for
  something this minor — worth watching whether the in-app version already feels sufficient before adding it.
- **Schedule-entry task links (Zeitplan-Eintrag-Aufgaben-Verknüpfung) in the API (Sanctum).** Same gap as
  the category link and task groups above — `ScheduleEventController` has no way to read or set an
  entry's bound tasks (`schedule_event_task_links`). Worth doing together with those rather than as three
  separate passes.
- **Drag a task from the board straight onto a Zeitplan block** to link it, instead of only the form's
  search picker. Would need a cross-page gesture (board and Zeitplan are different routes today), which
  felt like more scope than the first pass needed — the search picker covers the same outcome in a
  couple of taps.
- **Manually reordering an entry's bound tasks.** Same limitation as the category's own pinned-tasks
  picker — order is currently just pick order (each new one goes to the end), with add/remove but no
  drag. A small reorder control would let someone fix "I picked these in the wrong order" without
  unpinning and re-picking.
- **A hint in the event form when its category already has its own task link.** A Runde-4 simulation for
  the per-event task link (Zeitplan-Eintrag-Aufgaben-Verknüpfung) never discovered it — not because it was
  hard to find, but because the coarser category-level link already satisfied the whole scenario, so the
  simulated user never had a reason to open one specific block's edit form looking for an override. A
  small note there ("Diese Kategorie ist bereits mit X verknüpft — hier nur für diesen Termin etwas
  anderes wählen") could make the override's existence visible at the moment someone might actually want
  it, without waiting for them to stumble onto the form section on their own. Not built now — it's a
  cross-reference between two already-shipped features, not a fix for either.
- **A category whose linked project/group/Agenda entry gets deleted keeps `task_source` set even though the
  target is gone** (the FK itself correctly goes `null` via `nullOnDelete` — see CLAUDE.md). The category
  row's own label already self-heals ("Keine Aufgaben-Verknüpfung", since it reads the resolved relation,
  not the raw `task_source` string), but the link sheet's chip row would still show e.g. "Projekt" as the
  active chip with nothing selected underneath, until someone picks a new target or explicitly clears it.
  Fixing this for real means deciding *where* a deletion should reach back and clear `task_source` too (a
  model observer on `Project`/`TaskGroup`/`AgendaEntry`, most likely) — an architectural add, not a quick
  patch, and the externally-visible behavior (the row label) is already honest in the meantime.
