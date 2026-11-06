# nothing-to-do
Here I'm building a todo list app. It was once just an app for me, because I had some problems with managing my tasks, but today the app is growing and I'm slowly getting more users.

## files
| filename              | description / usage                           |
| --------------------- | ----------------------------------------------|
| `CLAUDE.md`           | this file (the global CLAUDE.md also applies to this project) |
| `CONCEPT.md`          | Capture the app idea, target audience, core requirements, and USP — the "why" behind the project |
| `ERRORS.md`           | Note errors/exceptions here that may recur, and how you fixed them, to prevent recurrence |
| `3-THINGS.md`         | One of the concepts behind the todo list. Made by me to fix my problems with managing my tasks |
| `FUNCTIONS.md`        | Document here every feature the app has and how the feature works |
| `CONVENTIONS.md`      | Binding interaction and style rules (layouts, buttons, capture, tokens, tone). Read before building any UI |
| `TODO.md`             | Open tasks, open questions and known issues between sessions |
| `docs/REQUIREMENTS.md` | The contract the refactor is built and tested against (R1–R10, session and capacity rules) |
| `docs/REFACTOR_PLAN.md` | Decisions, chosen layouts, flows, data migration map, risks and the phases with skills |
| `docs/TESTBERICHT.md` | Test plan (A automatic, B Claude visual, C Niels); fill in the result column during the build |

The most important files of the global CLAUDE.md are also listed here.

## your role
In this project you aren't just an implementer. You should be an actual co-worker. Always check if ...
- the solution I suggest makes sense
- there is a better way to do something
- there's another problem I haven't found yet
- you find something that isn't great yet
- ...

## rules
### git branches and commits
As specified in the global CLAUDE.md, you work here with git and branches. Every time I request a new feature, you create a branch for the feature. After you have finished development and testing, you present the feature to me with a quick guide on what it does and how it works. You don't have to turn off the dev server, because I'll always take a look myself.

Hotfixes for the live app are still branched from `main` (e.g. `fix/<shortname>`) and merged back into `main`.

### create a preview, instead of describing it
Whenever you plan a visual change, instead of describing it, generate a preview, preferably as an artifact. If there are options, add them to the artefact, let me choose from the options (e.g. buttons) and see what they'll do to the app.

### user data must never be lost
The app has real users. Their existing data must never be lost — not during the refactor and not afterwards. Every change to the data model needs a migration path for the existing production data (e.g. a migration that carries the data over, or an import from the old schema). If you're unsure whether a change could lose data, ask first.

# nothing-to-do refactor
Here I tell you everything about the refactor I'm currently working on. Apply these rules when I request something for the refactor, which will be most of the time. I want to refactor the app because, for me, the app is slowly getting more and more messy and I just want to start fresh.

## git
Create every branch for the refactor from the branch `refactor/refactor` and name it `feature/<shortname>`. Merge it back into `refactor/refactor` only after I have taken a look at it and approved it.

## the plan (approved 2026-11-06)
The refactor follows the feature-schmiede plan in `docs/REFACTOR_PLAN.md`; the contract is `docs/REQUIREMENTS.md`. In short:
- **Board while idle, focus during a session.** Desktop = Werkbank layout, mobile = Stapel layout. Same features on both.
- **Signature moment: "The board folds away"** on Start. Never tidy it away in cleanups.
- **Sessions are pomodoros:** 25 min, 5 min break, 15 min after every fourth. Unfinished tasks carry over; done with ≥ 5 min left starts the next task, < 5 min left starts the break early.
- **Categories** (week plan, open list, "pomodoro sessions" switch) define work time and capacity. **Areas** (tasks, open list) stay separate and are linked to categories.
- **Themes:** the user chooses; Topografie is the default.
- **Everyone is switched** to the new app on release; all data is migrated.
- Build phase by phase and respect the gates. Each step that is a feature gets its own `feature/<shortname>` branch.

## visual checks
Try a real browser check once or twice (see the working recipe in your memory: `php artisan serve` + Vite in the background, Playwright MCP). If you can't get a usable screenshot, stop and tell me what you tried; don't silently replace the check with tests. Record the outcome in `docs/TESTBERICHT.md` section B.
