# nothing-to-do — Concept

> Draft. Written together with Niels, to be corrected.

## The idea
An app to **prioritize and plan your personal work and to-dos**, so you can plan your day and be more
productive. Items are sorted by size and shape (see [3-THINGS.md](3-THINGS.md)), and — if you want — you
pick what fits into today.

## The problem
Niels had trouble managing his tasks. Before the app he did not plan at all. Planning tools tend to fail
for two reasons: planning is an extra step that gets forgotten, and the plan is overloaded and breaks
by the afternoon. The app has to make planning *happen* and keep the plan realistic.

## Target audience
People like the author: busy, with little spare time (e.g. school or work plus competitive sport or
other commitments), who need to use the few hours they have well.

The app should still stay **as open as possible**. Anything specific to one kind of user (homework,
training, ...) must be an optional module, never part of the core. The core has to work for a student,
an apprentice or a freelancer alike.

## Two modes
One data model, two ways of using it. The mode decides which start page and modules are active. Switching
later must never lose data.

| Mode | For whom | What is different |
|---|---|---|
| **Plan my day** | People with little time who want to plan their day | Daily plan with limited capacity: you pull items into today and see what fits |
| **Just a list** | People who only need a to-do list | No daily plan. Prioritizing still works: items are ordered by deadline and importance |

Sorting (To-Dos / Tasks / Later), the Inbox, the important flag and deadlines are the same in both modes.

## Core
Capture → sort (3 Things) → plan the day → get it done.

- **Capture:** fast add, everything lands in the Inbox.
- **Sort:** To-Dos, Tasks, Later.
- **Plan the day** (mode "Plan my day"): choose today's focus with limited capacity, not an endless list.
  Idea: a start-of-day view that goes through what is due today, what is left over from yesterday and
  what is in the Inbox, so planning is what happens when you open the app instead of an extra step.
- **Get it done:** complete items, focus timer.

## Principles
- **Planning is a conscious choice with limited capacity**, not a long list.
- **No auto-filling of free time.** An earlier prototype planned all free time automatically and felt
  oppressive. Automation only happens when the user triggers it.
- **Speed over ceremony:** capture and sort in as few clicks as possible.
- **Quiet:** no streaks, badges or gamification.
- **User data is never lost** — see CLAUDE.md.
- **Core stays small.** Everything else is a module that can be switched on.

## Possible modules (not decided)
Agenda / homework, weekly plan templates, week review, Projects (as groups of tasks, to come back later).
Not part of the rebuild: craft ideas, announcements, hub connection.

## Open questions
- Schedule with focus blocks: part of the core? Suggestion: yes, with a sensible default
  (e.g. 4 hours per day) while no blocks are defined.
- Which existing features come back, which come later, which are dropped?
- How do the existing users' data get migrated to the new schema?
- Name and wording of the two modes (German UI).
- Where does `Later` live in the desktop and mobile layout?
