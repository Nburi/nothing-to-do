# nothing-to-do — Concept

> Rewritten for the refactor on 2026-11-06, together with Niels. The full plan (designs, risks,
> phases, tests) is in [docs/REFACTOR_PLAN.md](docs/REFACTOR_PLAN.md); the contract the build is
> checked against is [docs/REQUIREMENTS.md](docs/REQUIREMENTS.md).

## The idea
An app for people with **too little time and too many things**. It does not just store tasks. It
starts the evening for you, shows the whole board while nothing runs, and steps back into a calm
focus view while you work.

## The problem it solves
Niels is an apprentice and a competitive orienteerer. Three things go wrong on a normal evening:

1. **Time is tight.** He needs to know exactly when to do what, so everything is done before it is
   too late.
2. **Starting is the hard part.** Coming home tired or moody, overwhelmed by the list, he ends up
   scrolling instead of working. What is missing is a quick reset and a clear "start here".
3. **Ideas get lost.** Ideas (things to do, things to try) arrive at the worst moments, under the
   shower or on the train, and are gone when there would be time for them.

## Target audience
People like Niels: school or work plus a demanding hobby, little spare time, a lot to fit in. The
app must work for **anyone with that time problem**. Nothing in the core may know about
orienteering, school or any specific hobby. Areas and categories are just names the user chooses.

## Core loop
**Arrive → reset → board → session → done → next / free time**

- **Arrive:** the phone joins the home Wi-Fi, an iPhone Shortcut pings the app, the app sends a
  nudge. Fallback: a time guess from the week plan.
- **Reset:** ten minutes to switch off (shower, eat, walk). Skipping is free, no streak.
- **Battery check-in:** one tap (low, okay, good). It sizes the suggestion.
- **Board (idle):** the whole board with **one suggested card** and **where to start**
  ("Start with: exercise 1").
- **Session (focus):** pressing Start folds the board away. Only the card, its first step and a
  25 minute timer stay. Sessions follow pomodoro rules (see below).
- **Done:** the board comes back with the next suggestion. When deadline work is covered and there
  is real free time, an idea from Later is offered, and the user can always keep the time for
  themself.

## The two modes of the screen
| Mode | When | What is on screen |
|---|---|---|
| **Board** | nothing is running | everything: start strip with time and one suggestion, Inbox, To-dos, Tasks, Later |
| **Session** | a pomodoro is running | one card, its first step, the timer, a way back to the board (rail or drawer) |

Desktop uses the **Werkbank** layout (columns, then a rail), mobile uses **Stapel** (piles on a desk,
then a drawer). Features are identical on both devices; only the layout differs.

## Sessions (pomodoro)
- A session is always **25 minutes** of work, followed by a **5 minute** break; every fourth break is
  **15 minutes** (counted per day).
- A task not finished within a session simply **continues in the next session**.
- Finishing a task with **5 minutes or more** left: the next suggested task starts inside the same
  session.
- Finishing with **less than 5 minutes** left: the break starts right away, and the next session
  gets a fresh 25 minutes.

## Time, categories and areas
- The **week plan** has repeating blocks, one-off changes ("this week only") and pause days
  (holidays). This carries over from the old app, which is the part that worked best.
- Every block has a **category** (e.g. School, Training, Deep work). Categories are an **open list
  the user creates and customises**. A category has a **"pomodoro sessions" switch**; only blocks of
  such categories count as work time.
- Tasks have an **area** (e.g. School, Sport, Own projects), also an open, user-defined list.
- Categories and areas are **separate but linked**: each category chooses which areas may be
  suggested inside its blocks (e.g. Deep work suggests School and Own projects, Training suggests
  nothing).
- **Capacity** of a day = the number of 25 minute sessions in its pomodoro blocks. Time outside
  those blocks is free time: **no work is suggested there**, only ideas from Later may be offered, and
  "Keep it mine" applies.

## Ordering without a priority field
Every task has a size and may have a **hard deadline** (real) and a **soft deadline** ("finished by",
self-set). The app works out a start-by date from the size, orders by it, warns early when a day is
overloaded, and suggests letting soft deadlines slip first. In a crunch, the user decides: shrink,
move, drop, or ask for more time.

## Principles
- **Overview while planning, nothing but the one thing while working.**
- **The app suggests, the user decides.** No automatic filling of free time; ideas are only offered.
- **Speed over ceremony:** capture is one gesture, sorting takes about two minutes a day.
- **Quiet:** no streaks, badges or points. At most one small celebration per day.
- **Same features everywhere:** nothing exists on only one device.
- **User data is never lost** (see CLAUDE.md).
- **Generic core:** nothing hobby-specific in the core.

## Out of scope for the first release
Sharing and shared spaces, calendar sync / ICS import, Garmin body battery, learned durations,
natural language week edits, watch complication and widgets, email to inbox. See the "later" list
in [docs/REFACTOR_PLAN.md](docs/REFACTOR_PLAN.md).
