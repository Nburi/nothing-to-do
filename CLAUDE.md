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

### use feature schmiede for every feature request
Always use the skill "feature-schmiede" when I request a new feature. In this project, the plan must also always include a preview for any visual changes and the possible skills to use for every implementation step.

### user data must never be lost
The app has real users. Their existing data must never be lost — not during the refactor and not afterwards. Every change to the data model needs a migration path for the existing production data (e.g. a migration that carries the data over, or an import from the old schema). If you're unsure whether a change could lose data, ask first.

# nothing-to-do refactor
Here I tell you everything about the refactor I'm currently working on. Apply these rules when I request something for the refactor, which will be most of the time. I want to refactor the app because, for me, the app is slowly getting more and more messy and I just want to start fresh.

## git
Create every branch for the refactor from the branch `refactor/refactor` and name it `feature/<shortname>`. Merge it back into `refactor/refactor` only after I have taken a look at it and approved it.
