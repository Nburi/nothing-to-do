# TODO

Open tasks between sessions. The full build order is in [docs/REFACTOR_PLAN.md § 6](docs/REFACTOR_PLAN.md#6-phases).

## Refactor: next up (phase 0)
- [ ] 0.5 Tooling check: `tailwindcss ^3` next to `@tailwindcss/vite ^4` in package.json. Verify `npm run build`, align versions.
- [ ] 0.1 Spike: iOS Shortcut on home Wi-Fi → authenticated URL → Web Push to the installed web app (needs Niels' iPhone).
- [ ] 0.2 Spike: slot, session and capacity engine as a pure PHP service with fixture weeks (DST, holiday, one-off move, pomodoro vs non-pomodoro categories).
- [ ] 0.3 Spike (`feature/migration-rehearsal`): snapshot/compare tooling done and tried on a copy of the local dev DB. **Waiting for Niels:** production DB engine, and a production copy (or Niels runs the cycle), guide in `docs/spikes/0.3-migration-dry-run.md`.

## Open questions for Niels
None right now. Answered 2026-11-06: no work suggestions outside pomodoro blocks; long break after every fourth session, counted per day.

## Known issues carried over
- No `lang/` directory yet, so validation messages are English (fixed by G6 in the refactor).
