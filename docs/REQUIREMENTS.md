# nothing-to-do — Requirements (refactor)

The contract the refactor is built and tested against. Written 2026-11-06 at the feature-schmiede
plan stop and approved by Niels. The "why" is in [CONCEPT.md](../CONCEPT.md), the build order and
risks in [REFACTOR_PLAN.md](REFACTOR_PLAN.md), the test plan in [TESTBERICHT.md](TESTBERICHT.md).

Origin tags: `PROMPT` = Niels' words, `CODEBASE` = carried over from the old app or CLAUDE.md,
`GERATEN → bestätigt` = Claude's guess, confirmed by Niels.

> Replaces the earlier rebuild requirements (3-column board, swipe rules, `is_today`). Parts that
> still hold (swipe gestures, server is the source of truth, quality bar) are kept below.

---

## 1. Product requirements

| ID | Origin | Requirement | Acceptance |
|---|---|---|---|
| R1 | PROMPT | **Board while idle, only the one thing during a session.** | Idle: whole board plus one suggested card with "Start with: …". Session: the board steps back, only card, first step and timer remain. A running session shows on every device of the user. |
| R2 | PROMPT | **A short reset starts the evening and triggers itself.** | iPhone Shortcut on home Wi-Fi calls an authenticated URL, the app sends a Web Push. Fallback: time guess from the week plan. One nudge per evening, none on paused days, none after the reset is done. Skipping is silent. |
| R3 | PROMPT | **Capture is one gesture; typed first, voice later.** | One line reachable from every page on both layouts. Everything lands in the Inbox. A daily sort (about two minutes) moves items to To-do, Task or Later with size and dates. |
| R4 | PROMPT + CODEBASE | **Week plan with one-off changes and holidays.** | Repeating template, "this week only" vs "every week" as an explicit choice, pause days for ranges with single days switchable back on. Same per-date model as the old app. |
| R5 | PROMPT | **Hard and soft deadlines plus size decide the order. No priority field.** | Start-by date from size; hard beats soft; early warning for overloaded days; soft deadlines are suggested to slip first; the user decides in a crunch (shrink, move, drop, ask for more time). |
| R6 | PROMPT | **Ideas come back only in real free time; a slot can be kept "mine".** | Later items are offered only outside pomodoro blocks and when deadline work is covered. "Do it now" is always the primary button, "Keep it mine" the second, on every battery level. Whole free days get a bolder suggestion, still only a suggestion. Bastelideen remains the browse view of Later. |
| R7 | PROMPT | **Every feature on desktop and mobile.** | One action registry; every action has an entry point in both layouts, in board and in session mode. A test fails if one is missing. |
| R8 | CODEBASE | **Existing users lose nothing.** | Two-step migrations, dry run on a production copy, rollback path. Everyone is switched to the new app (no opt-in), with all data carried over. |
| R9 | PROMPT | **Sessions follow pomodoro rules.** | See section 3. |
| R10 | PROMPT | **Categories with a pomodoro switch define work time; areas are linked to categories.** | See section 4. |

## 2. Confirmed guesses

| ID | Decision |
|---|---|
| G1 | Sizes, measured in sessions: **Quick** (several fit into one session), **Short** (one session), **Long** (2–3 sessions), **Block** (4 or more). A missing size counts as Short. |
| G2 | A missed soft deadline rolls over once with "still want this?". A second miss moves the item back to Later. |
| G3 | *Replaced by Niels:* no capacity factor. Capacity comes from blocks of categories with the pomodoro switch (section 4). |
| G4 | The battery is asked once per evening, can be skipped (counts as Okay) and changed later. |
| G5 | The old modes ("Plan my day", "Just a list") merge into one: board while idle, focus during a session. |
| G6 | German is the default language, English is available; both through proper `lang/` files from day one. |
| G7 | No streaks, badges or points. At most one small celebration per day. The old streak flame goes. |
| G8 | *Replaced by Niels:* everyone gets the new app from the start (no opt-in, no old look). |
| G9 | Voice capture comes after typing, through an iPhone Shortcut that dictates and posts to a capture endpoint with a narrow token. |

## 3. Sessions (pomodoro engine)

1. A session is **25 minutes** of work.
2. After a session: **5 minute** break; every **fourth** break is **15 minutes**.
3. A task not finished when the session ends **continues in the next session** (same task, no new pick).
4. Task marked done with **≥ 5 minutes** left: the next suggested task starts **inside the same session**.
5. Task marked done with **< 5 minutes** left: the **break starts immediately**; the next session gets a fresh 25 minutes.
6. Pause and resume are possible; a session is stored on the server, so a reload or the other device shows the running session.
7. Sessions are planned into blocks of pomodoro categories (section 4). Starting a session manually outside such a block is allowed. *(Open: whether the idle board suggests work at all outside pomodoro blocks — current assumption: yes, it suggests, but capacity planning counts only pomodoro blocks.)*

## 4. Categories, areas and capacity

- **Categories** (week plan): an open list the user creates, renames, colours and deletes. Examples: School, Training, Deep work. Each has a **"pomodoro sessions" switch**. Same idea as the old `EventCategory`.
- **Areas** (tasks): an open, user-defined list. Examples: School, Sport, Own projects.
- **Link:** categories and areas stay separate. Each category chooses **which areas may be suggested** inside its blocks (many-to-many). Example: Deep work → School, Own projects; Training → none.
- **Capacity of a day** = number of 25 minute sessions that fit into that day's blocks of pomodoro categories (after one-off changes and pause days).
- **Load of a day** = sum of the planned tasks' sizes in sessions. Load above capacity triggers the early warning (at least two days ahead, at most one warning per day, hysteresis against flicker).
- **Free time** = time outside fixed blocks and outside pomodoro blocks. Only here are Later ideas offered and "Keep it mine" applies.

## 5. Data model changes (summary)

Details and the migration path for every column: [REFACTOR_PLAN.md § Data](REFACTOR_PLAN.md#data-and-migration).

| Old | New |
|---|---|
| `tasks.list` (inbox, todos, tasks, later) | kept, plus `size` (quick, short, long, block) |
| `tasks.deadline` | `hard_deadline` (two-step rename) |
| `tasks.due_date` | `soft_deadline` ("finished by") |
| task groups | projects with one visible next step |
| event templates, schedule events, schedule pauses | kept as the week plan |
| event categories | categories with pomodoro switch and linked areas |
| craft ideas | Later items with a "where to begin" note |
| — | areas, category↔area links, pomodoro sessions (server-side state) |

## 6. Interactions kept from the old app

- Drag and drop on desktop between columns; swipe on mobile with 1:1 finger tracking, a coloured
  action surface behind the card, threshold about 40 % of the card width, spring back below it.
- Editing reuses the creation form in place; no separate edit dialogs.
- Server is the source of truth; every action is validated and authorised server-side.

## 7. Quality bar

- Empty, loading and error states designed, not afterthoughts. No stack traces in the UI.
- Edge cases: empty lists, 400 character titles, umlauts, rapid repeated gestures, conflicting hard/soft deadlines, DST weeks (Europe/Zurich).
- Accessibility: keyboard reachable, contrast ≥ 4.5:1 for text in every theme, reduced motion respected (the signature moment degrades to an instant change).
- Responsive: one codebase; Werkbank on desktop, Stapel on mobile; no broken in-between widths.
- Themes: seven themes, light and dark each; Topografie is the default; the user chooses in settings.
