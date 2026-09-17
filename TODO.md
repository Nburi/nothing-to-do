# TODO

Open tasks and known follow-ups between sessions. Anything already described in `CLAUDE.md` as built is
done; this file is only for what is still outstanding.

## Follow-ups

### Planer-Umbau (2026-09-15 Nachtsession) — 3 of 4 branches merged; Rasteransicht rejected, needs rework

Built overnight per direct request (brainstorming session decided the direction, mockups approved
before each build): Rollover, Rasteransicht + Fälligkeits-Geister + Aufteilen-auf-mehrere-Tage,
Kategorie-Filter, Zeitplan-Sichtbarkeit, Fokus-Timer-Rückkopplung. See each feature's own section in
CLAUDE.md's "Planer" write-up for the full design (the Rasteransicht section describes what was
*built*, not what's live — see below).

**Reviewed 2026-09-16: Rollover, Zeitplan-Sichtbarkeit, and Fokus-Timer-Rückkopplung are merged into
`main`.** `feature/planner-grid-view` (the board-redesign + Fälligkeits-Geister + Aufteilen-auf-
mehrere-Tage + Kategorie-Filter) was explicitly **not** approved ("grid view is not ok") and is
**not merged** — the branch still exists locally with all of that work, but the board on `main`
today is still the original single-row 14-day strip, not the responsive grid described in CLAUDE.md.
Needs a fresh look with Niels before any of it ships: what specifically didn't work (layout, the
split-across-days flow, the ghost cards, the category filter, or some combination) is still unknown
— ask before reworking rather than guessing at a fix.

- **The `TaskSplitSession` cross-feature follow-up is on hold.** Both `Schedule::deadlineItems()`
  (Zeitplan-Sichtbarkeit) and `TaskSuggestor::plannerSuggestion()` (Fokus-Timer-Rückkopplung) were
  deliberately scoped to `TaskDayPlan` only, since `TaskSplitSession` only exists on the rejected
  `feature/planner-grid-view` branch. Nothing to do here unless/until some future version of the
  board redesign (with splitting) actually merges — then extend both queries the same way, one more
  `->get()->each()`/query each.
- **No manual browser verification** on any of the three merged branches — same "avoid the known
  dev-server-hang trap" discipline as the List-Konzepte sessions; verified via the full automated
  suite (green after every commit and after each merge) plus `npm run build`/`artisan view:cache` to
  catch Blade/Tailwind-purge issues. Niels reviewed and approved these three directly rather than a
  browser walkthrough being done first.
- **No `FeatureAnnouncement` draft was created** for the three merged features — same reasoning as
  every other admin-authored-content gap already documented in this file: the editor needs its own
  admin UI, and this was a fully autonomous overnight session with no safe browser access to use it.
  Worth one once there's a natural pause — Rollover and the Zeitplan-Sichtbarkeit chip are both real,
  regular-user-facing changes to pages some users already have open daily.

### Planer — Standardaufgaben (2026-09-16 Nachtsession) — built on a branch, unmerged

Built overnight per direct request while Niels was asleep, on `feature/planner-standard-tasks` (branched
off `main`, not merged — merging is Niels's call per CLAUDE.md §3.2). See CLAUDE.md's "Planer" section,
"Standardaufgaben (built)" subsection, for the full design: two quick-add templates ("ToDos erledigen"
with an adjustable-per-day length, "Lernen" with Allgemein/Fach/Prüfung modes) next to "Rest automatisch
einplanen", reachable by click (any breakpoint) or — added in a same-branch follow-up, per Niels's own
"I want to drag and drop it on desktop" — by dragging the chip onto a day column on desktop. Full
automated suite green (1418 tests, `tests/Feature/PlannerStandardTasksTest.php` covers both entry paths),
`npm run build`/`artisan view:cache` both clean.

- **No manual browser verification** — same "avoid the known dev-server-hang trap" discipline as the
  other overnight Planer sessions above; the sheet's actual look/feel (both breakpoints, the mode-switch
  chips, the exam picker) *and* the new desktop drag gesture itself have only been exercised through
  Livewire component tests and reading the SortableJS source directly (`toFn`'s group-matching logic,
  confirmed line-by-line in `node_modules/sortablejs/modular/sortable.esm.js` to make sure the drag
  source's own group name doesn't need to be allow-listed on the receiving day columns), never a real
  drag in a real browser. Given how many of this file's own *Known Issues* entries are exactly
  "a Sortable drag looked correct in code and still silently failed live" (the touch/mouse group-matching
  bug, the `invertSwap` gesture, the `onMove` timing trap — all in the Planer/Task-Gruppen sections), this
  one specifically deserves a real click-and-drag test before merging, not just a read of the suite.
- **No `FeatureAnnouncement` draft was created** — same reasoning as every other admin-authored-content
  gap in this file: the editor needs its own admin UI, and this was a fully autonomous session with no
  safe browser access to use it. Worth one once this merges ("Neu im Planer: Standardaufgaben").
- Deliberately out of scope: a duration field for "Lernen" (not asked for — DayPlanner's existing 25-min
  fallback already covers its capacity math), more than two templates, and any provenance marker/badge
  on a Standardaufgabe-created task (it's a plain Task afterward, indistinguishable from a hand-typed one
  by design).

### MCP-Server — built; a FeatureAnnouncement draft and a live client check remain

See CLAUDE.md's "MCP-Server — KI-Zugriff" section for the full design. Built on `main` directly (no
branch), full automated suite green (1322 tests). Still outstanding:

- A draft, unpublished `App\Models\FeatureAnnouncement` (CLAUDE.md §3.11) — skipped for the same reason
  every other admin-authored-content gap in this file was: `AnnouncementEditor` needs its own admin UI,
  and this session had no safe browser access to use it. Create one (title e.g. "Neu: MCP-Server für
  KI-Assistenten", unpublished) via the admin panel once ready, linking to `/docs/mcp`.
- **No live MCP client was ever connected** to `/api/mcp` — verification was automated-tests-only
  (`tests/Feature/Mcp/`), deliberately, matching this project's "don't wait forever on a live
  connection" discipline (CLAUDE.md §10). Worth a real check with an actual client (Claude Desktop's
  custom connector config, or `npx @modelcontextprotocol/inspector`) once there's time to babysit it:
  point it at `{{ url }}/api/mcp` with a Bearer token from Settings, confirm `initialize`/`tools/list`/
  `tools/call` round-trip as expected against a real client's own JSON-RPC implementation, not just this
  project's own tests of it.
- Deliberately out of scope for the first version (see CLAUDE.md for the full list): MCP CRUD for
  projects/groups/schedule events/categories/templates, Pomodoro session control via MCP, posting into a
  shared Agenda space, and OAuth-based MCP authorization.

### To-Do-Listen-Konzepte — merged; a manual browser pass and a FeatureAnnouncement draft remain

All four branches (infra, `simple`, `eisenhower`, `kanban`) are merged into `main`, in that order,
resolving the expected small conflicts by hand per the plan's own coordination note (§8) — see
CLAUDE.md's "To-Do-Listen-Konzepte" section and its three per-concept subsections for the full
design, and `PLAN_LIST_CONCEPTS.md` for the plan itself. Full automated suite green after each
merge step; the merge itself found and fixed a handful of test fixtures that used another
concept's key as their own "not yet available" example, now stale since every real catalog key is
available.

Still outstanding:
- **A manual browser pass** (never done on any of the four branches, each verified automated-
  tests-only to avoid a known dev-server-hang trap) — desktop drag on all three concept-specific
  boards, the Eisenhower lock badge and "der Krisenring" visual timing, both Simple's and
  Eisenhower's mobile swipe intents specifically (their board partials pass `rightIntent`/
  `leftIntent`/`wireMethod` overrides into `partials/task-card-mobile.blade.php` that were
  silently dead code until Kanban's own session fixed the shared partial — worth confirming they
  now actually take effect), and the Kanban board's drag/checkbox-driven Erledigt transitions.
- A draft, unpublished `App\Models\FeatureAnnouncement` for this feature (CLAUDE.md §3.11 would
  normally call for one on any user-facing feature) — skipped on every one of the four sessions
  because it's admin-authored content normally created through `AnnouncementEditor`'s own UI, and
  every session's verification was automated-tests-only with no dev-server/browser access.
  Create one (title "Neu: Listen-Konzepte", unpublished) via the admin panel, mentioning the
  Settings card and all three newly-available concepts; publish once ready.

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

## SEO backlog

The public Hilfe-Center mirror (`/hilfe`, `App\Livewire\PublicHelp` — see CLAUDE.md, "Hilfe-Center &
Support") plus its slug URLs, `Article` JSON-LD, and the extended `/sitemap.xml` shipped 2026-08-31. It's a
foundation, not a finished result: with zero published articles it has nothing to actually rank for — the
next real step is Niels writing genuine content in the admin editor (`/app/admin/help`), not more code. The
rest of a broader Google-search-performance pass, roughly in order of expected payoff:

- **Google Search Console / Bing Webmaster Tools verification.** Can't be confirmed or set up from the
  codebase — worth checking whether either is already connected for the production domain, since search
  performance can't be measured (or a sitemap manually resubmitted) without it.
- **Impressum / Datenschutz (privacy policy) page.** Missing entirely. Besides being a Swiss legal
  expectation for a public-facing site, its absence can quietly hurt trust signals, and some
  directories/backlink sources require one before they'll list a site at all.
- **A proper social-preview `og:image`** (1200×630, purpose-made) instead of reusing the 512×512 app icon —
  affects `welcome.blade.php` and the new `layouts/public.blade.php` alike, both currently point at
  `icons/icon-512.png`.
- **Site-wide `WebSite`/`Organization` JSON-LD** plus `BreadcrumbList` structured data for the Hilfe-Center's
  category path — the public mirror only got a per-article `Article` schema (see CLAUDE.md); a full schema
  pass across the marketing page and category hierarchy was left for later.
- **An FAQ section (with FAQ schema) on the landing page** — an easy rich-result win, and it answers real
  long-tail queries the single hero/feature-grid page currently doesn't address.
- **Core Web Vitals audit.** The foundation is solid (Vite build, self-hosted fonts, PWA service worker,
  no render-blocking Google Fonts request) but nobody has actually run Lighthouse/PageSpeed Insights against
  the deployed production URL — worth doing once there's a live URL to point it at, rather than guessing.
- **Backlinks / directory listings** (Product Hunt, Swiss startup directories, etc.) — outside of anything
  code can do, flagged here so it isn't forgotten.
