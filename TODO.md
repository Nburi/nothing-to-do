# TODO

Open tasks between sessions. The full build order is in [docs/REFACTOR_PLAN.md § 6](docs/REFACTOR_PLAN.md#6-phases).

## Refactor: next up (phase 0)
- [x] 0.5 Tooling check (merged, #2): the unused `@tailwindcss/vite` v4 removed, build runs on Tailwind 3.4 via PostCSS; npm and composer audits clean. (Superseded by the move to Tailwind 4 in 2.1.)
- [ ] 0.1 Spike (merged): server side done (`POST /api/arrival`, narrow token via `php artisan app:arrival-token`). **Waiting for Niels:** set up the Shortcut and test five evenings, guide in `docs/spikes/0.1-arrival.md`.
- [x] 0.2 Spike: engine in `app/Planning`, merged. Decisions E1–E10 approved on 2026-11-08.
- [ ] 0.3 Spike: migration dry run on a copy of production (needs a production DB dump from Niels).

## Refactor: phase 1 (started 2026-11-07)
- [x] 1.1 Task model, part one: `size`, `hard_deadline`, `soft_deadline`, `area_id`. Old `deadline`/`due_date` stay and are mirrored by `Task::booted()`. Notes in `docs/steps/1.1-1.6.md`.
- [x] 1.6 Categories and areas, data layer: `areas`, `category_area`, `EventCategory::syncAreas()` / `toPlanningCategory()`. No UI (P1 is "no pixel").
- [ ] 1.1 follow-up (later release): switch the remaining raw-SQL reads (`scopeBoardOrdered`, `DayPlanner`, API/MCP resources) to the new columns, then drop `deadline`/`due_date`.
- [x] 1.7 Session engine wired to storage: `pomodoro_sessions` (one row per user), `App\Services\FocusSession`, `SessionEngine::stop()`. Notes in `docs/steps/1.7.md`.
- [x] 1.3 Pick engine: `PickEngine`, `DeadlineFeasibility::startBy()`, `Suggestions` service, `evenings` (battery + Another log), `tasks.first_step`, first `lang/` files. Also the read side of the week plan (`WeekPlanReader`). Notes in `docs/steps/1.3.md`.
- [x] 1.4 Capacity and crunch: `LoadPlanner`/`Forecast`, `EarlyWarning` (hysteresis, one warning a day, hard only), `SoftSlip` (G2), drop → Later, shrink, hourly `app:run-planning-checks`. Notes in `docs/steps/1.4.md`.
- [ ] 1.4 follow-up (4.2): push the warning, crunch screen.
- [x] 1.2 Week plan: `WeekPlanEditor` (explicit `ChangeScope`, moves, tombstones, pauses), reader from 1.3, property test against the old screens. Notes in `docs/steps/1.2.md`.
- [ ] Hotfix for `main`: `ScheduleEvent::scopeVisible()` compares pause dates as strings, so a paused day still shows an occurrence edited in the form (fixed on the refactor in 1.2, see ERRORS.md).
- [x] 1.5 Capture and API parity: `POST /api/capture` with a narrow token (`app:capture-token`), REST + MCP for suggestion, battery, session, forecast, drop/shrink, areas and the new task fields. Default locale `de`. Notes in `docs/steps/1.5.md`.
- [x] 1.8 Review of phase 1: code review (6 fixes), `EveningFlowTest` (found and fixed a hard-deadline bug in `DeadlineFeasibility`), requirement check, rehearsal on a dev-DB copy: nothing lost. Notes in `docs/steps/1.8-review.md`.
- [ ] Phase 1 gate, open part: the dry run on a **production** copy (0.3) still needs the dump.
- [ ] Prod `.env`: `APP_LOCALE=de` before the switch (5.4).
- [ ] 1.7 follow-up: no HTTP/Livewire entry points yet (that's the session view, 3.1) and no API/MCP endpoints (1.5).

## Refactor: phase 2 (started 2026-11-09)
- [x] 2.1 Token layer and seven themes (`feature/theme-tokens`): Tailwind 4, `config/themes.php`, per-user theme + light/dark, contrast test, theme card in Settings. Notes in `docs/steps/2.1.md`.
- [x] 2.2 App shell (`feature/app-shell`): `/board` with Werkbank and Stapel, board and session mode, action registry, A11. Notes in `docs/steps/2.2.md`.
- [ ] Service worker: the stale page on navigation seen in 1.8 is not reproduced or fixed yet (navigations are network-first in `public/sw.js`); look again when it shows up on the dev server.
- [x] 2.3 Capture, Inbox and the daily sort (`feature/capture-sort`): capture line (C / +), sort card, capture token card, A9. Notes in `docs/steps/2.3.md`.
- [ ] 2.4 Beta 1: Niels uses only capture and sort on `/board` for a week (gate: at least 20 things captured). Day-3 and day-7 check-ins only if Niels asks.
- [x] Phase 2 preview picks confirmed by Niels on 2026-11-09: theme cards, System/Hell/Dunkel, capture line stays open, sort one card at a time, Later asks less, "Woche" opens the old week plan, settings behind the gear.
- [x] Mobile sort as real cards (Niels, 2026-11-09, option A): size on the top card, drag it onto the To-dos / Tasks / Später pile or tap the pile. In `feature/capture-sort` (PR #17).

## Refactor: phase 3 (started 2026-11-11, gate waived by Niels, stacked on PR #17)
- [x] Phase 3 preview picks (2026-11-11): fold A "Falten" at 520 ms (above the plan's 400 ms, on purpose), reset as a calm screen, battery at the end of the reset, nudge at a **fixed time** per weekday, Ideen view as cards. Preview: https://claude.ai/artifact/Y5ieY4W47HaZwjzGFGR48W
- [x] 3.1 "The board folds away" (`feature/board-folds`). Notes in `docs/steps/3.1.md`.
- [ ] 3.1 known issue: in the Stapel layout in a narrow *desktop* window the card jumps 20 px after the fold (a scrollbar shows for ~400 ms). Not on phones. Two fixes failed and were reverted; next idea `scrollbar-gutter: stable`. Waiting for Niels: fix now or leave it?
- [x] 3.2 Battery check-in and reset (`feature/battery-reset`). Notes in `docs/steps/3.2.md`. Offered from 17:00 or via `/board?reset=1` until 3.3 brings the nudge time per weekday.
- [ ] 3.2 open for Niels: should a reset started by hand during a work session pause the pomodoro? (now it keeps running)
- [x] 3.3 Nudge at a fixed time per weekday (`feature/nudge-time`). Notes in `docs/steps/3.3.md`. Time-based only; the reset is offered from the nudge time (17:00 for users who never set one).
- [ ] 3.3 open for Niels: TESTBERICHT C13 (a real push on the iPhone from the dev server; needs VAPID keys and cron `schedule:run`), C12 updated. Check the decisions in `docs/steps/3.3.md`.
- [ ] 3.3 later: the Wi-Fi arrival path (`POST /api/arrival`) once spike 0.1 passes C2; then it should also respect "one nudge per evening" (`evenings.nudge_sent_at`).
- [ ] The older push commands (`app:send-progress-reminders`, event notifications) inject `PushNotifier` and crash every tick on a server without VAPID keys (see ERRORS.md, 2026-11-16). Harmless on prod (keys set); fix when those commands are touched.
- [x] 3.4 Free-time ideas, "Meins behalten", Ideen view (`feature/free-time-ideas`). Notes in `docs/steps/3.4.md`.
- [ ] 3.4 open for Niels: TESTBERICHT C14–C16; check the decisions in `docs/steps/3.4.md` (Jetzt machen = session, soft deadlines also stop ideas, 06:00–24:00 and 30 min, Stapel tab "Später" → Ideen page).
- [ ] 5.4: run `php artisan app:copy-craft-ideas --dry-run`, then without, at the switch (copies the old Bastelideen into Later; idempotent, craft_ideas untouched). Not on prod before that.
- [ ] Shell: editing, moving and deleting single items (also on the Ideen cards) do not exist yet.
- [ ] Stapel board at 390 px: the four piles are only ~56 px wide, titles break mid-word ("Math e-…"); already in the 3.2 screenshots, not from 3.4. Look at `shell/pile.blade.php` / the pile grid.
- [ ] Outside blocks, the plain card says "Der Rest des Abends gehört dir." also in the morning and next to the 3.4 deadline line ("Keine Idee aus Später, solange …"); the copy could say less there.
- [ ] 3.5 Beta 2, 3.6 review rounds.

## Refactor: phase 4, step 4.3 (started 2026-11-17)
- [x] 4.3a Projects and groups with their next two steps (`feature/projects-next-step`): stages, "wartet auf", board cards, Projekte page, mixed old groups become projects. Notes in `docs/steps/4.3.md`.
- [ ] 4.3a open for Niels: TESTBERICHT C29–C32; the decisions in `docs/steps/4.3.md`.
- [ ] 4.3b Notes as cards ("Lose" pile, drag onto a stage, note → step) and shared `#tags` (preview round 2, approved 2026-11-17). The old `brainstorm` text becomes the first note card.
- [ ] Projects/groups: delete, reorder stages, move an existing board task into a project or group (same gap as "editing, moving and deleting single items").
- [ ] Projects: should a stage's target date become the soft deadline of its steps (early warning)? Ask Niels.
- [ ] Projects in the API/MCP of the new model (the old project tools on `main` speak the old model).

## Open questions for Niels
- `main` merged into `refactor/refactor` on 2026-11-16 (`feature/merge-main`): all 21 newer migrations are in; main's features for Projekte, Gruppen, Agenda, Notfallmodus and Vorbereiten were dropped, their tables stay and the data export still reads them. Open from it:
  - The Datenschutz page (`legal/datenschutz.blade.php`) still describes the class Agenda and Projekte. Legal text, so Niels decides what it says after the switch.
  - Prod categories linked to a project, group or Agenda entry (`task_source` project / group / agenda_entry / agenda_generic) now show no link and fall back to the normal suggestion. Decide in 5.4 whether to clear them or turn them into a text link. Since 4.3 projects and groups are back, so a project/group link could become "suggest this project's next steps in this block".
  - `main` (prod) also has `phpseclib/phpseclib` 4.0.1 (CPU-exhaustion advisory, pulled in by Passport). Fixed here (4.0.3); prod needs the same patch update as a hotfix.
- The app has no version number yet (global rule 9). Introduce one (e.g. `config/app.php` `version`, shown in settings)? Suggestion: start the refactor at `2.0.0_1`.
- The security hotfix for the live app (#7, Laravel TokenGuard advisory, Livewire XSS) is merged into `main`; deploy it to prod if not done yet.
- Answered 2026-11-09: move to **Tailwind 4** at the start of 2.1 (Niels: "Yes laravel 4 is fine", meaning Tailwind 4). Done in 2.1 (`feature/theme-tokens`); every old view was migrated by the upgrade tool and still builds and renders.
- Answered 2026-11-08: "status" in step 1.1 is dropped (nobody knew what it meant; `list` + `is_completed` cover it). Instead, step 1.4 adds a soft-deadline slip counter (G2), and the crunch decision "drop" moves a task to Later instead of deleting it.
- Answered 2026-11-06: no work suggestions outside pomodoro blocks; long break after every fourth session, counted per day.

## Known issues carried over
- `lang/` exists since step 1.3 (`pick.php` only). Validation messages are still English until the shell (phase 2) sets the German locale.
