# TODO

Open tasks between sessions. The full build order is in [docs/REFACTOR_PLAN.md § 6](docs/REFACTOR_PLAN.md#6-phases).

## Refactor: next up (phase 0)
- [ ] 0.5 Tooling check: `tailwindcss ^3` next to `@tailwindcss/vite ^4` in package.json. Verify `npm run build`, align versions.
- [ ] 0.1 Spike: iOS Shortcut on home Wi-Fi → authenticated URL → Web Push to the installed web app (needs Niels' iPhone).
- [ ] 0.2 Spike: slot, session and capacity engine as a pure PHP service with fixture weeks (DST, holiday, one-off move, pomodoro vs non-pomodoro categories).
- [ ] 0.3 Spike: migration dry run on a copy of production (needs a production DB dump from Niels).

## Open questions for Niels
- [ ] Outside pomodoro blocks, does the idle board still suggest work? (assumed: yes; capacity counts only pomodoro blocks)
- [ ] Long break after every fourth session, counter reset per day? (assumed: yes)

## Known issues carried over
- No `lang/` directory yet, so validation messages are English (fixed by G6 in the refactor).
