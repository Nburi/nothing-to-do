# nothing-to-do — Refactor plan

Approved plan for the refactor, written with the feature-schmiede method (plan stop passed on
2026-11-06). Read with:

- [CONCEPT.md](../CONCEPT.md): the why
- [REQUIREMENTS.md](REQUIREMENTS.md): the contract (R1–R10, confirmed guesses, session and capacity rules)
- [TESTBERICHT.md](TESTBERICHT.md): the test plan, filled in during the build
- [CONVENTIONS.md](../CONVENTIONS.md): binding style and interaction rules

The interactive version used to decide (10 designs, 7 themes, flows, risks, phases) is the artifact
**Refactor Plan**: https://claude.ai/artifact/LFSJXoWz8REyThkGdHa7Gm. It shows the state *before* the
decisions below. Where they differ, this file wins.

---

## 1. Decisions (2026-11-06)

| Topic | Decision |
|---|---|
| Desktop layout | **Werkbank**: idle = start strip (time line + one suggested card with "Start with") above four columns Inbox / To-dos / Tasks / Later. Session = the board folds into a slim rail on the left, only the card, its first step and the timer stay. |
| Mobile layout | **Stapel**: idle = a Tonight tray with the suggested card on top, and piles (Inbox, To-dos, Tasks, Later box) in a 2×2 grid. Session = the piles slide into a drawer at the bottom, the desk holds only the card. |
| Signature moment | **"The board folds away"**: on Start, the columns (desktop) or piles (mobile) fold into the rail or drawer one after another; the card grows into the free space and the timer starts. On Done the board comes back, already showing the next suggestion. Taboo for later cleanup. |
| Themes | Seven themes (Topografie, Nachtzug, Gletscher, Kiosk, Moos, Abendrot, Tinte), each light and dark. The **user chooses**; **Topografie is the default**. |
| Sessions | Pomodoro: 25 min work, 5 min break, every fourth break 15 min. Rules in [REQUIREMENTS § 3](REQUIREMENTS.md#3-sessions-pomodoro-engine). |
| Work time | Open, user-defined list of **categories** (week plan) with a "pomodoro sessions" switch. Only those blocks are work time and capacity. |
| Areas | Open, user-defined list of **areas** (tasks), kept separate from categories but **linked**: each category chooses which areas are suggested in its blocks. |
| Existing users | **Everyone is switched** to the new app on release. No opt-in, no old look. Data is migrated (R8). |
| Language | German default, English available, `lang/` files from day one. |
| Visual checks | Claude tries a real browser check **once or twice**. If no usable screenshot comes out, Claude **stops and tells Niels** instead of silently relying on tests. |

### Design notes for the chosen layouts

**Werkbank (desktop, ≥ 720 px)**
- Top bar: logo, Board / Week / Ideas, capture field (key C), battery chip.
- Start strip: evening time line (reset, planned work, fixed blocks, now marker, "free until") and the suggestion card: area, size, hard deadline chip, title, **Start with: …**, buttons *Start · 35 min* and *Another*.
- Columns: Inbox, To-dos, Tasks, Later. The suggested item is outlined and labelled "Start here".
- Session: left rail (92 px) with counts per column and "Open board"; time ribbon on top; centered session card with timer ring, *Start with*, *Done* / *Pause*, "Then: …".
- Done: board returns; a "done tonight" row with the finished card, the next suggestion in the strip.

**Stapel (mobile, < 720 px)**
- Tonight tray: suggested card on top of a short stack (one edge per remaining pick), "free until" below.
- Piles in a 2×2 grid, each showing its top card, a coloured top edge per list and the count. Tapping a pile opens its list. Inbox fans out for sorting (To-do pile / Task pile / Later box, then size).
- Session: card on the desk, drawer at the bottom (Inbox · To-dos · Tasks · Later, "pull to peek").
- Done: the finished card lands on a "Done tonight" pile.
- Bottom tabs: Desk, Calendar, Later, Inbox. Capture: + on the Inbox pile, or the Action Button / Shortcut.

**Parity:** both layouts expose the same 12 actions (capture, sort, start the pick, swap / shrink, battery, reset, move one event, change the weekly plan, pause days, crunch decisions, browse Later, keep a slot mine) in board and session mode. Enforced by the action registry and test A11.

## 2. Flows

**Evening loop.** Phone joins home Wi-Fi (or time guess) → push "You're home, reset first" → reset (10 min, skippable) → battery (one tap) → pick engine sizes the suggestion → **board** with one suggested start → Start → **session** (board folds away) → after 25 min a break (5 / 15) → task done? next task in the same session (≥ 5 min left) or early break (< 5 min) → more work tonight? next suggestion : free time check → free time and deadlines safe? idea from Later (suggestion) : "the rest is yours".

**Capture and sort.** Idea hits → type in the capture line (any page) or dictate via Shortcut → Inbox (raw text, saved offline too) → daily sort (≈ 2 min) → To-do / Task / Later → size and optional hard / soft deadline → pick engine and load forecast; Later waits for free time.

**Week plan and crunch.** Template (blocks with categories) → change: every week / this week only / pause days → capacity = sessions in pomodoro blocks → load forecast per day → overloaded? early warning ≥ 2 days ahead → user decides (shrink, move, drop, ask for more time; soft deadlines suggested first) → recalculate.

## 3. Data and migration

Real users exist; every change has a path for existing rows. Two-step renames (add new, copy, switch reads, drop old in a later release).

| Old | New | How |
|---|---|---|
| `tasks.list` (inbox, todos, tasks, later) | kept, plus `size` | default `short`; filled during the daily sort |
| `tasks.deadline` | `hard_deadline` | two-step rename |
| `tasks.due_date` | `soft_deadline` ("finished by") | two-step rename |
| `tasks.duration_minutes`, `estimated_sessions` | size is derived; minutes stay as a hint | nothing dropped |
| `task_groups`, `tasks.group_id` | projects with one visible next step | first open task is the next step until set |
| `event_templates`, `schedule_events`, `schedule_pauses` | week plan (same tables) | reused |
| `event_categories` | categories + `pomodoro_enabled` + category↔area links | existing categories kept; pomodoro flag from existing settings where present |
| — | `areas`, `category_area` pivot, `pomodoro_sessions` (server-side session state) | new tables |
| `craft_ideas` (title, where_to_begin, is_done) | Later items with a "where to begin" note | copied |
| `users.*` module settings, onboarding flags | kept; unused modules ignored | no destructive drops in this release |

## 4. Taste inventory

Binding conventions read from the old code are in [CONVENTIONS.md](../CONVENTIONS.md). Deviations:
the old streak flame is removed (conflicts with "quiet product"); non-Topografie themes change token
values, never the token system.

## 5. Risks

L = likelihood, I = impact (1–3). Score = L × I.

| ID | Type | Risk | L | I | Mitigation | Step |
|---|---|---|---|---|---|---|
| F1 | feature | The arrival nudge does not fire reliably on iPhone | 2 | 3 | Spike 0.1 first. Fallback: time-based nudge from the week plan. Wi-Fi join is more reliable than GPS. | 0.1 |
| F2 | feature | The reset habit does not stick, or becomes a chore | 2 | 2 | Ten minutes at most, skipping costs nothing, no streak. Judge it in beta 2 by whether Niels starts without scrolling. | 3.5 |
| F3 | feature | The suggestion feels wrong or bossy | 2 | 3 | A "why" line on every pick, "Another", log swaps. Rule-based first so it is explainable. | 1.3 |
| F4 | feature | Sizes are guessed badly and the load forecast lies | 3 | 2 | Four coarse sizes in sessions. A missing size defaults to Short. Learn real durations later. | 1.3 |
| F5 | feature | Soft deadlines pile up and create guilt | 2 | 2 | Slip rule: roll over once, then back to Later. Soft items are never shown in red. | 1.4 |
| F6 | feature | The session view hides too much and feels restrictive | 2 | 2 | Rail (desktop) and drawer (mobile) keep the board one tap away. Test in beta 2. | 3.5 |
| F7 | feature | The early warning cries wolf and gets ignored | 2 | 2 | Warn on hard deadlines first, with hysteresis. At most one warning per day. | 4.2 |
| F8 | feature | Voice capture is flaky or leaks private text | 2 | 1 | Typing first. Voice is optional and uses a narrow token. Offline queue before shipping it. | 5.3 |
| F9 | feature | The app fills every gap and stops being trusted | 1 | 3 | Ideas only outside pomodoro blocks, "Keep it mine" always available and respected, one idea per slot, no repeat the same day. | 3.4 |
| F10 | feature | Scope: too many concepts in the first release | 3 | 3 | Phases with gates. Capture and sort ship first. Projects and review are optional. | 2.4 |
| F11 | feature | Existing users are surprised by the switch | 2 | 2 | Everyone is switched (decision), so: announcement through the existing announcement module, a short first-run tour, data fully migrated. | 5.4 |
| F12 | feature | Werkbank and Stapel feel like two different apps | 2 | 2 | Same tokens, same card, same session view and the same signature moment in both; only the idle board differs. | 2.2 |
| F13 | feature | The switch between board and focus disorients or feels like a page load | 2 | 2 | The card keeps its place while the rest moves, the switch takes under 400 ms, the board stays one tap away. *2026-11-11: Niels chose 520 ms in the phase 3 preview; built that way on purpose (docs/steps/3.1.md).* | 3.1 |
| F14 | feature | Rigid 25 minute sessions clash with tasks that are much shorter or longer | 2 | 2 | Several quick tasks share one session; long tasks carry over; the < 5 min early-break rule avoids tiny leftovers. Check in beta 2. | 1.7 |
| C1 | code | iOS web push needs an installed web app, permissions and a service worker | 3 | 3 | The web-push library is already installed. Spike 0.1 covers install, permission and delivery. Document the install step in onboarding. | 0.1 |
| C2 | code | Time zones and DST break slot and capacity maths | 2 | 3 | Store local HH:MM with the user timezone. Fixture weeks around the last Sunday in October and March. | 0.2 |
| C3 | code | Templates plus exceptions plus pauses get complicated and drift | 3 | 2 | Keep the old per-date model. One service owns the rules. Property-style tests for overlaps. | 1.2 |
| C4 | code | The capacity algorithm is wrong at the edges (overlaps, split tasks) | 2 | 3 | Pure service, no Livewire inside. Fixtures with overlapping blocks and tasks bigger than any block. | 1.4 |
| C5 | code | Livewire morphing fights timers, animations and SVG | 3 | 2 | Alpine islands for the timer and the signature moment. Keep animated parts out of morphed regions. | 2.2 |
| C6 | code | A migration loses or mangles real user data | 1 | 3 | Two-step migrations, a copy of production, row counts before and after, rollback script, backup before release. | 0.3 |
| C7 | code | Motion is slow on older phones | 2 | 2 | Performance budget. Motion falls back under reduced motion. Test on the oldest available phone. | 5.2 |
| C8 | code | Two layouts drift apart and features exist on one device only | 3 | 3 | Single action registry plus a contract test that every action has both entry points. | 2.2 |
| C9 | code | Mixed Tailwind versions in package.json break the theme layer | 2 | 2 | Step 0.5 checks the build and aligns versions before any theming. | 0.5 |
| C10 | code | A capture token on a Shortcut is a leak waiting to happen | 2 | 2 | Token with a single ability (create inbox item), revocable, rate limited, never in the repository. | 1.5 |
| C11 | code | About 60 feature tests are tied to the old UI and will break | 3 | 1 | Retire them together with the screens they test. Keep API and MCP tests. | 2.2 |
| C12 | code | API and MCP tools still expose removed features | 3 | 1 | Prune and document in step 1.5. | 1.5 |
| C13 | code | Board and session mode double the layouts and the parity tests | 3 | 2 | The session view is one shared component. The action registry covers both modes. | 2.2 |
| C14 | code | A session running on two devices gets out of sync (timer drift, double Done) | 2 | 2 | Server owns start time and state; clients only render; Done is idempotent. | 1.7 |

## 6. Phases

Phases end at a gate, not a date. Size: S ≈ 1 work session, M ≈ 2–3, L ≈ 4–6 (relative, not a promise).
Every feature branch starts from `refactor/refactor` as `feature/<shortname>` and is merged back only after Niels approves it.

### P0 · Prove the risky bits
Three spikes and the tooling check. No product code is committed to a path before these answer.
**Gate:** the nudge fires on Niels' phone or a fallback is chosen; the engine passes its fixtures; a dry-run migration loses zero rows.

| Step | Kind | Size | What | Skills / agents | Serves | Risks |
|---|---|---|---|---|---|---|
| 0.1 | Spike | S | **iOS arrival trigger.** Shortcut on home Wi-Fi calls an authenticated URL; the server sends a Web Push to the installed web app. Success: 5 of 5 evenings without tapping. | `research-agent`, `systematic-debugging`, `verification-before-completion` | R2 | F1, C1 |
| 0.2 | Spike | M | **Slot, session and capacity engine.** Pure PHP service with fixture weeks: DST, a holiday, a one-off move, pomodoro and non-pomodoro categories. No database, no UI. | `test-driven-development`, `writing-plans` | R4, R5, R9, R10 | C2, C4 |
| 0.3 | Spike | M | **Migration dry run.** Restore a copy of production, run the planned migrations, compare counts, spot-check Niels' account. | `test-driven-development`, `code-fixer`, `verification-before-completion` | R8 | C6 |
| 0.4 | Decision | S | **Design, theme, signature moment.** Done on 2026-11-06 (section 1), written to CONVENTIONS.md. | `brainstorming`, `impeccable`, `design-taste-frontend` | R7 | F10, F12 |
| 0.5 | Build | S | **Tooling check.** package.json lists tailwindcss ^3 beside @tailwindcss/vite ^4. Verify and fix the build before the theme layer. | `code-fixer`, `run` | – | C9 |

### P1 · Data and engines
Everything that decides without showing a pixel. Each engine is testable alone.
**Gate:** all engine tests green; migration rehearsed on the copy again.

| Step | Kind | Size | What | Skills / agents | Serves | Risks |
|---|---|---|---|---|---|---|
| 1.1 | Build | M | **Task model:** size, hard and soft deadline, area, status. Two-step migrations; old columns stay until a later release. | `test-driven-development`, `using-git-worktrees`, `writing-plans` | R5, R8 | C6 |
| 1.2 | Build | L | **Week plan:** templates, one-off exceptions, pauses. Port what worked; "this week only" vs "every week" as an explicit choice. | `test-driven-development`, `subagent-driven-development` | R4 | C2, C3 |
| 1.3 | Build | M | **Pick engine:** start-by ordering, battery sizing, warm-up rule, "Another", area filter from the current category. Rule-based and explainable. | `test-driven-development` | R1, R5, R10 | F3, F4 |
| 1.4 | Build | M | **Capacity, load forecast, crunch, soft-slip rule.** Capacity in sessions from pomodoro blocks; early warning with hysteresis. | `test-driven-development` | R5, R10 | F5, F7, C4 |
| 1.5 | Build | S | **Capture endpoint and API parity.** Typed capture plus a scoped token for the Shortcut. Prune API and MCP tools that point at removed features. | `test-driven-development`, `security-review` | R3 | C10, C12 |
| 1.6 | Build | M | **Categories and areas.** Open lists, pomodoro switch per category, category↔area links, migration of existing event categories. | `test-driven-development` | R10 | C3 |
| 1.7 | Build | M | **Session engine.** 25 / 5 / 15 cycle, carry-over, next task inside the session (≥ 5 min), early break (< 5 min), pause/resume, server-owned state for every device. | `test-driven-development` | R1, R9 | F14, C14 |
| 1.8 | Review | S | **Review the engines.** Requirement check and flow tests against fixtures, no UI yet. | `requesting-code-review`, `code-review`, `fs-anforderungs-pruefer` | – | – |

### P2 · Shell, themes, two layouts
Werkbank and Stapel with capture and sort working. The first thing Niels can use.
**Gate:** parity test passes at 390 and 1280 px; Niels captured at least 20 things in the beta.

| Step | Kind | Size | What | Skills / agents | Serves | Risks |
|---|---|---|---|---|---|---|
| 2.1 | Build | M | **Token layer and the seven themes.** Semantic CSS variables, light and dark per theme, theme setting per user (default Topografie), contrast checks in a test. | `design-taste-frontend`, `impeccable`, `emil-design-eng` | R7 | C9 |
| 2.2 | Build | L | **App shell: Werkbank (desktop) and Stapel (mobile).** Board and session mode, one action registry, parity contract test. | `impeccable`, `high-end-visual-design`, `redesign-existing-projects`, `dispatching-parallel-agents`, `test-driven-development` | R7 | C8, C5, C11, C13, F12 |
| 2.3 | Build | M | **Capture, inbox and the daily sort.** One-line capture on every page; sort card with To-do, Task, Later, size and dates. | `feature-schmiede`, `test-driven-development` | R3 | F8 |
| 2.4 | Beta | S | **Beta 1: capture and sort.** Niels uses only capture and sort for a week. A reminder checks in on day 3 and 7. | `schedule`, `handoff` | R3 | F10 |

### P3 · The evening loop
Nudge, reset, battery, suggestion, session, done, next, free time.
**Gate:** two weeks of real evenings; Niels started without scrolling on most of them.

| Step | Kind | Size | What | Skills / agents | Serves | Risks |
|---|---|---|---|---|---|---|
| 3.1 | Build | L | **Board and session views with "The board folds away".** Idle board with one suggested start, session view with only the card, timer and breaks. The signature moment is built first, not last. | `feature-schmiede`, `emil-design-eng`, `test-driven-development` | R1, R9 | F3, F6, F13 |
| 3.2 | Build | M | **Battery check-in and reset.** One-tap battery, ten minute reset, time-based nudge from the week plan. | `feature-schmiede`, `test-driven-development` | R1, R2 | F2 |
| 3.3 | Build | M | **Arrival trigger and Web Push.** Uses the winner of spike 0.1; if the spike failed, ships the time-based nudge only. | `systematic-debugging`, `run`, `verification-before-completion` | R2 | F1, C1 |
| 3.4 | Build | M | **Free-time ideas, "mine", Later view.** Suggestions only outside pomodoro blocks; Bastelideen becomes the browse view of Later. | `feature-schmiede`, `test-driven-development` | R6 | F9 |
| 3.5 | Beta | M | **Beta 2: the tired-evening trial.** Two weeks on real evenings; daily one-question check-in: did you scroll first? | `schedule`, `handoff` | R1, R2, R6, R9 | F2, F6, F14 |
| 3.6 | Review | M | **Review rounds 0 to 4.** Requirement check, flow in three states, fixes, UI lies, simulated user. | `fs-anforderungs-pruefer`, `fs-ablauf-tester`, `fs-fix-verifizierer`, `fs-ui-luegenpruefer`, `fs-nutzer-simulant` | – | – |

### P4 · Planning and foresight
The week plan editor and the early warning, so the evening loop has good data.
**Gate:** a holiday takes under a minute; a crunch shows up two days before it bites.

| Step | Kind | Size | What | Skills / agents | Serves | Risks |
|---|---|---|---|---|---|---|
| 4.1 | Build | L | **Week plan editor.** "This week only", "every week", pause days, categories with pomodoro switch and linked areas, in one editor on both layouts. | `feature-schmiede`, `test-driven-development`, `impeccable` | R4, R7, R10 | C3, C8 |
| 4.2 | Build | M | **Early warning and crunch decisions.** Shrink, move, drop, ask for more time; soft deadlines suggested first. | `feature-schmiede`, `test-driven-development` | R5 | F5, F7 |
| 4.3 | Build | M | **Projects with one visible next step.** | `feature-schmiede`, `test-driven-development` | R6 | F10 |
| 4.4 | Build (optional) | S | **Quiet Sunday review.** One calm screen: what moved, what is next week. | `feature-schmiede` | – | – |
| 4.5 | Review | M | **Review rounds 0 to 4**, focused on planning and crunch. | `fs-anforderungs-pruefer`, `fs-ablauf-tester`, `fs-fix-verifizierer`, `fs-ui-luegenpruefer`, `fs-nutzer-simulant` | – | – |

### P5 · Everyone else, then release
Turn a tool for Niels into a product for people with the same time problem.
**Gate:** a stranger sets it up cold and understands it; migration rehearsed twice.

| Step | Kind | Size | What | Skills / agents | Serves | Risks |
|---|---|---|---|---|---|---|
| 5.1 | Build | M | **Onboarding, empty states, German and English.** First start explains the loop in three taps. | `feature-schmiede`, `impeccable` | R7 | F10 |
| 5.2 | Build | S | **Accessibility, reduced motion, performance.** | `impeccable`, `code-fixer` | R7 | C7 |
| 5.3 | Build (optional) | M | **Voice capture through a Shortcut.** Dictate, post to the capture endpoint, queue offline; Action Button guide. | `systematic-debugging`, `security-review` | R3 | F8, C10 |
| 5.4 | Build | M | **Migration and switch for all users.** Backup, dry run, announcement, first-run tour. Everyone moves to the new app. | `security-review`, `verification-before-completion`, `finishing-a-development-branch` | R8 | C6, F11 |
| 5.5 | Review | S | **Release candidate review.** Full test suite, `composer audit` / `npm audit`, final review round, version bump per global rule 9. | `code-review`, `security-review`, `simplify`, `finishing-a-development-branch` | – | – |

## 7. Not in the first release

Sharing and shared spaces (Agenda spaces were removed on purpose) · calendar sync and ICS import (an
export branch exists) · Garmin body battery pre-filling the battery check-in · learned durations ·
natural language week edits · Apple Watch complication and widgets · email to inbox.

## 8. Answered questions (2026-11-06)

| # | Question | Answer |
|---|---|---|
| Q1 | Outside pomodoro blocks, does the idle board still suggest work? | **No.** Outside pomodoro blocks there is no work suggestion; the strip shows the next block, free time may show a Later idea. Starting a session manually stays possible. |
| Q2 | Is the long break after every fourth session, counted per day? | **Yes**, the counter resets each day. |
