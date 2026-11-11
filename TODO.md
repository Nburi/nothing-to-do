# TODO

Open tasks between sessions. The full build order is in [docs/REFACTOR_PLAN.md § 6](docs/REFACTOR_PLAN.md#6-phases).

## Refactor: next up (phase 0)
- [x] 0.5 Tooling check (merged, #2): the unused `@tailwindcss/vite` v4 removed, build runs on Tailwind 3.4 via PostCSS; npm and composer audits clean.
- [ ] 0.1 Spike (merged): server side done (`POST /api/arrival`, narrow token via `php artisan app:arrival-token`). **Waiting for Niels:** set up the Shortcut and test five evenings, guide in `docs/spikes/0.1-arrival.md`.
- [x] 0.2 Spike: engine in `app/Planning`, merged. Decisions E1–E10 approved on 2026-11-08.
- [ ] 0.3 Spike: migration dry run on a copy of production (needs a production DB dump from Niels).

## Refactor: phase 1 (started 2026-11-07)
- [x] 1.1 Task model, part one: `size`, `hard_deadline`, `soft_deadline`, `area_id`. Old `deadline`/`due_date` stay and are mirrored by `Task::booted()`. Notes in `docs/steps/1.1-1.6.md`.
- [x] 1.6 Categories and areas, data layer: `areas`, `category_area`, `EventCategory::syncAreas()` / `toPlanningCategory()`. No UI (P1 is "no pixel").
- [ ] 1.1 follow-up (later release): switch the remaining raw-SQL reads (`scopeBoardOrdered`, `DayPlanner`, API/MCP resources) to the new columns, then drop `deadline`/`due_date`.
- [x] 1.7 Session engine wired to storage: `pomodoro_sessions` (one row per user), `App\Services\FocusSession`, `SessionEngine::stop()`. Notes in `docs/steps/1.7.md`.
- [ ] 1.2 Week plan, 1.3 pick engine, 1.4 capacity/crunch, 1.5 capture + API parity, 1.8 review.
- [ ] 1.7 follow-up: no HTTP/Livewire entry points yet (that's the session view, 3.1) and no API/MCP endpoints (1.5).

## Open questions for Niels
- **`refactor/refactor` is 21 migrations behind `main`** (task_sessions, reminders, repeat rules, OAuth, day bounds, buffers, …). Prod data lives in those tables; the refactor has to land on top of them. Merge `main` into `refactor/refactor` soon, or decide per table what happens to it — the longer this waits, the bigger the 5.4 switch gets.
- Before step 2.1 (themes): stay on Tailwind 3.4 or move to Tailwind 4 (CSS-first `@theme`, fits seven themes well, but every old view has to be checked)? Claude's suggestion: move to v4 at the start of 2.1, since the views get rebuilt anyway.
- The app has no version number yet (global rule 9). Introduce one (e.g. `config/app.php` `version`, shown in settings)? Suggestion: start the refactor at `2.0.0_1`.
- The security hotfix for the live app (#7) is merged into `main`; deploy it to prod if not done yet.
- Answered 2026-11-08: "status" in step 1.1 is dropped (nobody knew what it meant; `list` + `is_completed` cover it). Instead, step 1.4 adds a soft-deadline slip counter (G2), and the crunch decision "drop" moves a task to Later instead of deleting it.
- Answered 2026-11-06: no work suggestions outside pomodoro blocks; long break after every fourth session, counted per day.

## Known issues carried over
- No `lang/` directory yet, so validation messages are English (fixed by G6 in the refactor).
