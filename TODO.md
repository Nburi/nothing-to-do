# TODO

Open tasks between sessions. The full build order is in [docs/REFACTOR_PLAN.md § 6](docs/REFACTOR_PLAN.md#6-phases).

## Refactor: next up (phase 0)
- [x] 0.5 Tooling check (`feature/tooling-check`): the unused `@tailwindcss/vite` v4 removed, build runs on Tailwind 3.4 via PostCSS; npm and composer audits clean.
- [ ] 0.1 Spike: iOS Shortcut on home Wi-Fi → authenticated URL → Web Push to the installed web app (needs Niels' iPhone).
- [x] 0.2 Spike (`feature/engine-spike`): engine in `app/Planning`, 63 fixture tests green. Ten decisions E1–E10 in `docs/spikes/0.2-engine.md` wait for Niels.
- [ ] 0.3 Spike: migration dry run on a copy of production (needs a production DB dump from Niels).

## Refactor: phase 1 (started 2026-11-07, stacked on `feature/engine-spike`)
- [x] 1.1 Task model, part one: `size`, `hard_deadline`, `soft_deadline`, `area_id`. Old `deadline`/`due_date` stay and are mirrored by `Task::booted()`. Notes in `docs/steps/1.1-1.6.md`.
- [x] 1.6 Categories and areas, data layer: `areas`, `category_area`, `EventCategory::syncAreas()` / `toPlanningCategory()`. No UI (P1 is "no pixel").
- [ ] 1.1 follow-up (later release): switch the remaining raw-SQL reads (`scopeBoardOrdered`, `DayPlanner`, API/MCP resources) to the new columns, then drop `deadline`/`due_date`.
- [ ] 1.2 Week plan, 1.3 pick engine, 1.4 capacity/crunch, 1.5 capture + API parity, 1.7 session engine, 1.8 review.

## Open questions for Niels
- **E1–E10** in `docs/spikes/0.2-engine.md` (1.3, 1.4 and 1.7 build directly on them).
- **"status" in step 1.1** — the plan lists it, nothing defines it. `list` + `is_completed` already cover open/done/Later. Is it the soft-slip counter (G2, step 1.4) or something else? Not built until answered.
- **`refactor/refactor` is 21 migrations behind `main`** (task_sessions, reminders, repeat rules, OAuth, day bounds, buffers, …). Prod data lives in those tables; the refactor has to land on top of them. Merge `main` into `refactor/refactor` soon, or decide per table what happens to it — the longer this waits, the bigger the 5.4 switch gets.
- Before step 2.1 (themes): stay on Tailwind 3.4 or move to Tailwind 4 (CSS-first `@theme`, fits seven themes well, but every old view has to be checked)? Claude's suggestion: move to v4 at the start of 2.1, since the views get rebuilt anyway.
- The app has no version number yet (global rule 9). Introduce one (e.g. `config/app.php` `version`, shown in settings)? Suggestion: start the refactor at `2.0.0_1`.
- The live app (`main`) has the same vulnerable packages (Laravel critical TokenGuard advisory, Livewire XSS). Hotfix is up as PR #7 against `main`, waiting for Niels to merge and deploy.
- Answered 2026-11-06: no work suggestions outside pomodoro blocks; long break after every fourth session, counted per day.

## Known issues carried over
- No `lang/` directory yet, so validation messages are English (fixed by G6 in the refactor).
