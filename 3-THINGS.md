# The 3 Things

The concept behind nothing-to-do. Made to fix a real problem: managing tasks by project or category
never worked. Work is sorted by **size and shape** instead.

| Thing | Meaning |
|---|---|
| **To-Dos** | Small tasks. Several can be cleared in one work session. |
| **Tasks** | Larger tasks, but still a single work step. |
| **Later** | Things worth remembering for when there is spare time or boredom. **Not prioritized.** |

## Size (refactor, 2026-11-06)
To-Dos and Tasks additionally get a **size measured in 25 minute sessions**, set during the daily
sort. It feeds the suggestion and the load forecast (see [docs/REQUIREMENTS.md](docs/REQUIREMENTS.md)).

| Size | Meaning |
|---|---|
| **Quick** | Several fit into one session. Usually a To-Do. |
| **Short** | About one session. Default when no size is set. |
| **Long** | 2–3 sessions. |
| **Block** | 4 or more sessions. A candidate for splitting into steps. |

## The Inbox
New items always land in the **Inbox**. The Inbox is not one of the 3 Things — it is the step before them:
capture fast, decide later. Every item is then sorted into To-Dos, Tasks or Later.

## Why "Later" is not prioritized
To-Dos and Tasks compete for your attention: they get deadlines, importance and a place in today's plan.
Later is the opposite. It is a parking lot with no pressure, so ideas don't get lost and don't create guilt.
That is why Later items have no "today" flag and no urgency.

## What happened to Projects
Earlier versions had Projects as a third thing. In practice a project is just a group of Tasks or To-Dos,
so they were removed from the concept. They are planned to come back later as a way of *grouping* items,
not as a separate kind of work.
