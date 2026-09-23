# TODO

Open tasks and known follow-ups between sessions. Anything already described in `CLAUDE.md` as built is
done; this file is only for what is still outstanding.

## Follow-ups

### Streak rework — a judgment call to revisit

The freeze budget ("Ruhetag") is a **rolling 7-day window**, not a Mon–Sun calendar week — deliberately,
to stop two freezes clustering right at a week boundary. Kept as is (decision 2026-09-19). If a calendar
week ever reads more intuitively, `ProgressStats::freezesUsedInTrailingWeek()` is the one place to change.

### Planer Rasteransicht — rejected, needs rework (Niels will say what was wrong)

`feature/planner-grid-view` (board redesign + Fälligkeits-Geister + Aufteilen-auf-mehrere-Tage +
Kategorie-Filter) was explicitly **not** approved ("grid view is not ok") and is **not merged** — the
branch still exists locally. Don't rework it by guessing; wait for Niels's feedback on what specifically
didn't work.

- **The `TaskSplitSession` cross-feature follow-up is on hold.** Both `Schedule::deadlineItems()` and
  `TaskSuggestor::plannerSuggestion()` are scoped to `TaskDayPlan` only, since `TaskSplitSession` only
  exists on the rejected branch. Once (if) the board redesign with splitting merges, extend both queries
  the same way.

### MCP-Server — live client check does not work yet

Automated tests are green (`tests/Feature/Mcp/`), but a real MCP client connection to `/api/mcp` (Claude
Desktop's custom connector, or `npx @modelcontextprotocol/inspector`, Bearer token from Settings) was tried
and is **not working** (2026-09-19). Needs debugging with the actual client's error output — check the
`initialize`/`tools/list` round trip, the `https://` endpoint URL (see CLAUDE.md §9) and the token abilities.

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
2026-08-29). `schedule_event_task_links` — the *separate* "Zeitplan-Eintrag-Aufgaben-Verknüpfung" feature —
is untouched and still fully live, but its `source` column (`'manual'`/`'auto'`) is now vestigial: nothing
writes `'auto'` to it any more, so every row will only ever read `'manual'`.

Left in place rather than dropped outright — same two-step precedent as `agenda_entries.is_done` above.
Once this has been lived with for a while and nothing depends on distinguishing `source`:

1. New migration dropping `schedule_event_task_links.source`.
2. Remove the docblock note on `ScheduleEvent::linkedTasks()` that points here.
3. Remove this entry.

### Local dev DB still carries a dead `tasks.task_group_id` column

Left over from the deleted first task-groups attempt (2026-07-31). Its migration file is gone with the
branch, so `migrate:rollback` cannot remove it, and SQLite refuses `ALTER TABLE … DROP COLUMN` for a column
that appears in a foreign-key definition — dropping it needs a full table rebuild, which is not worth
risking on a database with real tasks in it. Nothing in the code reads `task_group_id`. **Production never
had any of it.** Clean it up whenever the local database is next rebuilt from scratch.

## Ideas, not committed

- **Presence on the entries themselves**, not just in the member list — "Lena schaut sich das gerade an"
  next to a shared entry. Technically almost free (the heartbeat already exists), but hold it until the
  member-list version has been lived with for a while: on a homework list this could read as surveillance
  rather than help, and the whole product goal is "speed and calm".
- **Drag a task from the board straight onto a Zeitplan block** to link it, instead of only the form's
  search picker. Would need a cross-page gesture (board and Zeitplan are different routes today) — the
  search picker covers the same outcome in a couple of taps, so this stays parked.
- **Watch how often "Tagesziel erreicht" actually fires** once the default goal (5) has been lived with —
  a low goal makes it an almost-daily celebration, which risks the same staleness the per-task version was
  explicitly avoided for, just at a coarser grain. If it starts feeling routine, raising the default (or
  making the celebration itself rarer, e.g. only on a fresh streak-day) would be the fix, not more visual
  intensity.
- **A sound for the milestone celebration** — parked (see "Celebration sound" decision 2026-09-19): the
  existing focus-timer chime pattern (`window.primeFocusAudio()`) could be reused, but audio-autoplay policy
  and headless verification are fragile.
- **Push notifications for a new class entry, a freshly published feature announcement, and a category's
  linked list running dry** — parked on purpose (2026-09-19). The class-entry one needs a rate-limit thought
  first (22 people writing into one class get noisy fast); the other two are worth watching to see whether
  the in-app versions already feel sufficient. (Support-request pushes — admins on a new request, users on an
  answer — are built, see CLAUDE.md "Hilfe-Center & Support".)
- **Extend the API further:** private Agenda notes, group notes, and joining/leaving a class remain app-only.
- **Weg-/Pufferzeit is readable over the API, not writable** (2026-09-21). `ScheduleEventResource`/
  `EventTemplateResource` expose `buffer_before`/`buffer_after`, but no controller or MCP tool accepts
  them, so a block created through Shortcuts always gets 0. Small and mechanical whenever a real
  Shortcut needs it; nothing about the app depends on it.
- **The Tagesrahmen is not mirrored in the Planer or the Tagesueberblick** (2026-09-21). Both show a
  day without showing its length, so a 09:00-14:00 Saturday looks the same there as a full one.
  Worth doing once the frame has been lived with - it is not obvious yet that either page wants it.
- **The desktop week grids cannot scale a single day** (2026-09-21, structural). Seven columns next
  to one hour gutter have to share a scale, so a per-day or per-weekday frame only changes how far
  the dimmed night band reaches there, never the size of that day's blocks. The mobile views of both
  pages do scale per day (see CLAUDE.md). Fixing this would mean a gutter per column, which is a
  different grid, not a tweak.
- **No warning when a Wegzeit overlaps the previous entry** (2026-09-21) - the bands just draw over
  each other, exactly like two overlapping blocks already do. Same underlying gap as the
  long-standing "overlapping blocks are not laid out side by side" limitation; worth solving
  together, not separately.
- **A day frame cannot be set past 23:30** (2026-09-21). `DayWindow::LATEST_END` keeps every stored
  value a plain "HH:MM" with no 24:00 special case; the rendered axis still reaches 24:00 through the
  night margin and the automatic expansion, so this only bites someone who genuinely goes to bed
  after midnight and wants to say so.

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
