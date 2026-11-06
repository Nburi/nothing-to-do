# TODO

Open tasks between sessions. The full build order is in [docs/REFACTOR_PLAN.md § 6](docs/REFACTOR_PLAN.md#6-phases).

## Refactor: next up (phase 0)
- [x] 0.5 Tooling check (`feature/tooling-check`): the unused `@tailwindcss/vite` v4 removed, build runs on Tailwind 3.4 via PostCSS; npm and composer audits clean.
- [ ] 0.1 Spike: iOS Shortcut on home Wi-Fi → authenticated URL → Web Push to the installed web app (needs Niels' iPhone).
- [ ] 0.2 Spike: slot, session and capacity engine as a pure PHP service with fixture weeks (DST, holiday, one-off move, pomodoro vs non-pomodoro categories).
- [ ] 0.3 Spike: migration dry run on a copy of production (needs a production DB dump from Niels).

## Open questions for Niels
- Before step 2.1 (themes): stay on Tailwind 3.4 or move to Tailwind 4 (CSS-first `@theme`, fits seven themes well, but every old view has to be checked)? Claude's suggestion: move to v4 at the start of 2.1, since the views get rebuilt anyway.
- The app has no version number yet (global rule 9). Introduce one (e.g. `config/app.php` `version`, shown in settings)? Suggestion: start the refactor at `2.0.0_1`.
- The live app (`main`) has the same vulnerable packages (Laravel critical TokenGuard advisory, Livewire XSS). Hotfix `fix/security-updates` from `main`?
Answered 2026-11-06: no work suggestions outside pomodoro blocks; long break after every fourth session, counted per day.

## Known issues carried over
- No `lang/` directory yet, so validation messages are English (fixed by G6 in the refactor).
