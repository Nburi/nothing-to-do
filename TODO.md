# TODO

Open tasks and known follow-ups between sessions. Anything already described in `CLAUDE.md` as built is
done; this file is only for what is still outstanding.

## Follow-ups

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

### Local dev DB still carries a dead `tasks.task_group_id` column

Left over from the deleted first task-groups attempt (2026-07-31). Its migration file is gone with the
branch, so `migrate:rollback` cannot remove it, and SQLite refuses `ALTER TABLE … DROP COLUMN` for a column
that appears in a foreign-key definition — dropping it needs a full table rebuild, which is not worth
risking on a database with real tasks in it. The orphaned `task_groups` table, the `start_hint` column and
both stale `migrations` rows were removed; nothing in the code reads `task_group_id`.

**Production never had any of it** (those branches were never merged or pushed), so there is nothing to
deploy. Clean it up whenever the local database is next rebuilt from scratch.

### Five feature branches still need merging in the right order

`feature/module-settings` (module visibility & default landing page) → `feature/onboarding-tutorial`
(built on top of it, since the onboarding tutorial's module-visibility step needs
`AppModules`/`hidden_modules`/`default_page`) → `feature/feature-announcements` (built on top of
*that* one — reuses nothing code-wise from onboarding, but was branched from it per the same
"branch from the tip of the chain" approach, and its CLAUDE.md edits sit right after the
Onboarding-Tutorial section, so a merge the other way round would conflict) →
`feature/announcement-types` (message types + new-user filtering for the announcement toast, built
on top of *that* one for the same reason — adds a `type` column via its own migration rather than
editing the already-committed one, and its CLAUDE.md edits sit inside the Feature-Ankündigungen
section) → `main`. Merge/rebase in that exact order, or squash-merge each into `main` in turn and
rebase the next one in the chain onto the result each time — don't merge a later branch to `main`
before the ones before it are in, or its dependencies (`AppModules`, `onboarding_completed_at`,
`FeatureAnnouncement`, …) won't exist yet on the target branch.

**`main` has moved on independently since this chain was branched off it** (2026-08-26):
`feature/planner` was fast-forward merged in, plus two more commits landed directly on `main`
(duration estimates in Quick Capture, removing the `password.request` route) — none of that work
is anywhere in this five-branch chain. `main` is currently 3 commits ahead of `origin/main` (not
yet pushed). This means the eventual merge of this chain is **no longer a clean fast-forward**:
expect to rebase the whole chain onto current `main` (or merge `main` into the base of the chain)
before or during the merge, and watch for conflicts in files both sides touched (`routes/web.php`,
`app/Models/User.php`, `CLAUDE.md`, `TODO.md` are the most likely, since both lines of work added
routes/columns/docs independently). Re-verify this note is still accurate before merging — `main`
can keep moving between sessions.

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
