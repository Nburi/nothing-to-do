# TODO

Open tasks between sessions. The full build order is in [docs/REFACTOR_PLAN.md § 6](docs/REFACTOR_PLAN.md#6-phases).

## Refactor: next up (phase 0)
- [ ] 0.5 Tooling check: `tailwindcss ^3` next to `@tailwindcss/vite ^4` in package.json. Verify `npm run build`, align versions.
- [ ] 0.1 Spike (`feature/arrival-spike`): server side done (`POST /api/arrival`, narrow token via `php artisan app:arrival-token`). **Waiting for Niels:** set up the Shortcut and test five evenings, guide in `docs/spikes/0.1-arrival.md`.
- [ ] 0.2 Spike: slot, session and capacity engine as a pure PHP service with fixture weeks (DST, holiday, one-off move, pomodoro vs non-pomodoro categories).
- [ ] 0.3 Spike: migration dry run on a copy of production (needs a production DB dump from Niels).

## Open questions for Niels
None right now. Answered 2026-11-06: no work suggestions outside pomodoro blocks; long break after every fourth session, counted per day.

## Known issues carried over
- No `lang/` directory yet, so validation messages are English (fixed by G6 in the refactor).
