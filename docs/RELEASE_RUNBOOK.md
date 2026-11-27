# Release runbook: the switch to the new app (step 5.4)

Prepared on 2026-11-25 on `feature/switch-prep`. This is the sequence for the one night when
`refactor/refactor` is merged into `main`, the production database is migrated and every user lands in
the new app (R8: nobody loses anything). Everything here is derived from the migrations, commands and
config in this repository. What could **not** be checked from the repository is marked
**`UNVERIFIED`**; confirm those on the real server (or on the production copy) before the night.

**Rule for the whole night:** never skip a backup, never run a step you do not understand, and stop at
the first red result. The rollback plan is in section 7.

## 1. Decisions before the switch (Niels)

Nothing below is decided in the code. Each decision has options; the recommendation is only that.

| # | Decision | Options | Recommendation |
|---|---|---|---|
| D1 | **Categories linked to a project, group or Agenda entry** (`event_categories.task_source` = `project`, `group`, `agenda_entry`, `agenda_generic`). The new app shows no link for them; the block falls back to the normal suggestion. `app:switch-preflight` counts them (WARN). Your own prod data: unknown until the copy exists. | (a) **Clear** the link at the switch (`task_source = NULL`, the linked ids stay). (b) **Turn it into a text link** (`task_source = 'text'`, `linked_text` = the project/group/entry name): nothing is lost visibly. (c) **Build** "suggest this project's next steps in this block" (4.3 now has projects and groups again): a new feature, not part of the switch. | (b) at the switch, (c) later if you want it. Either way write down the ids first (section 5, step 6) so it can be undone. |
| D2 | **Users with many „Wichtig“ marks** (`tasks.is_important`, old Eisenhower board users). The new board ranks marked tasks right after the deadline work, so 30 marks flood it. The preflight lists users with more than 10 open marks (`--important-limit`). | (a) Keep every mark as it is. (b) Clear the marks of users above a limit at the switch, after saving which tasks had one. (c) Keep them and say so in the first-run text (`docs/announcement-draft.md`). | Look at the real numbers first (C45). If only a few users are affected, (c). Clearing (b) changes user data, so it needs the saved list and a sentence in the announcement. |
| D3 | **Datenschutz text** (`resources/views/legal/datenschutz.blade.php`). It still describes the class Agenda and Projekte; the new app removes the Agenda and has the evening row (battery), push subscriptions and the arrival/capture tokens. Legal text: yours to write. | (a) Update before the switch. (b) Switch first, update within a fixed number of days and say so. | (a). The facts that changed are listed in `docs/steps/5.4-prep.md`. |
| D4 | **Version number** (global rule 9, format `r.f.h_b`). The app has none yet. | (a) `2.0.0_1` (TODO suggestion): `config/app.php` `version`, shown in Settings, git tag on the merge commit. (b) Git tag only. | (a). Needs a small PR before the switch. |
| D5 | **Landing page.** `/`, `/dashboard` and the redirect after login still go to the old board `/app` (`User::defaultLandingRouteName()`, default `app`). Until that changes nobody lands in `/board`. This is code that does not exist yet. | (a) A last small PR before the merge: everybody lands on `/board`; a chosen default page of a retired screen (`schedule`, `weekplan`) then also ends in the new app (it already does via the redirects). (b) Keep the old landing page and let users find the shell by themselves. | (a). Build it as its own PR so the switch itself stays one merge. |
| D6 | **What happens to the old screens that are left** (`/app` board, planner, progress, crafts, today, settings, onboarding). Listed in `docs/steps/5.4-prep.md`. | (a) Redirect each to its new equivalent where there is one, at the switch. (b) Leave them reachable for one more release and delete after. | (b) for `/app` (the old board is the fallback if something is wrong); (a) for the ones with a clear equivalent. |
| D7 | **Hotfixes that exist only on `main`.** Today: PR #14 (`fix/pause-date-compare`, 2 commits). Merging `refactor/refactor` into `main` conflicts today in `ERRORS.md` and `app/Models/ScheduleEvent.php` (checked 2026-11-25 with `git merge-tree`). | (a) Merge `main` into `refactor/refactor` a few days before and resolve it there (keep the refactor's `scopeVisible()`, step 1.2 fixed the same bug). (b) Resolve at the merge. | (a), so the night has no conflicts. |
| D8 | **Heads-up timing** for the announcement (section 4 of the announcement draft). | A few days before / the evening before / only after the switch. | A few days before, `maintenance` type with the date; the `release` announcement right after the switch. |

## 2. What I could and could not check

| Fact | Status |
|---|---|
| 21 migrations are new compared with `origin/main` (list in section 4); `composer.json`/`composer.lock` are unchanged compared with `main`, `package.json` changes (Tailwind 4, fonts), no new `.env` keys (`.env.example` unchanged), one new config file `config/themes.php`. | verified with `git diff origin/main` on 2026-11-25 |
| `config/app.php` forces `locale = de`; `APP_LOCALE` is not read. The old TODO line "Prod `.env`: `APP_LOCALE=de`" is therefore not needed. | verified (`tests/Feature/GermanLocaleTest.php`) |
| The app dispatches no queued jobs (no `ShouldQueue`, no `app/Jobs`). `QUEUE_CONNECTION=database` in `.env.example` is only a default. No queue worker is needed for the new code. | verified by search |
| Scheduled commands (`bootstrap/app.php`): nine every minute, `app:prune-error-occurrences` daily, `app:run-planning-checks` hourly. None runs `evenInMaintenanceMode()`, so **maintenance mode pauses all of them**. | verified |
| Rollback of all 21 migrations loses no row; the only thing that is not undone is the list move of migration 1 (`projects` → `tasks`). | verified by `MigrationRehearsalTest` (A12, fixtures on SQLite) |
| `php artisan route:cache` works with the current routes (closures and the new redirects). | verified locally |
| Which database runs in production (SQLite or MariaDB/MySQL), the hosting, PHP and Node version, how cron and PHP-FPM are set up, whether `public/build` is built on the server. | **`UNVERIFIED`** (open question in `docs/spikes/0.3-migration-dry-run.md`). Both database variants are given below. |
| The migrations on MariaDB/MySQL (all tests run on SQLite). | **`UNVERIFIED`**: this is what the production-copy dry run (C3) must show. |
| Migrations with a mixed group, a category link and a "Wichtig" flood on real data. | **`UNVERIFIED`**: needs the production copy. |
| Run time of `php artisan migrate` on production size. | **`UNVERIFIED`**. The data migrations loop over groups, projects and notes; the dev database (518 rows) takes well under a second. |

## 3. Before the night (days before)

1. **Production copy dry run (blocks everything else).** Niels makes the dump (spike 0.3), the cycle in
   `docs/spikes/0.3-migration-dry-run.md` runs on the copy with `--env=rehearsal`, and the tests C3, C11,
   C15, C32, C43 and C45 (section 8) are done on the copy. Do it **twice** (plan gate of phase 5).
2. **Decisions D1–D8** (section 1) are made, the small PRs they need (D4 version, D5 landing page) are merged
   into `refactor/refactor`.
3. **`main` into `refactor/refactor`** (D7), test suite green again.
4. **Release candidate review (5.5):** `php artisan test`, `npm run build`, `composer audit`, `npm audit`.
5. **Production `.env` check** (no new keys are needed): `APP_ENV=production`, `APP_DEBUG=false`,
   `APP_URL` is the real address, `VAPID_SUBJECT`, `VAPID_PUBLIC_KEY` and `VAPID_PRIVATE_KEY` are set
   (without them the push commands log errors instead of sending; `config/webpush.php`).
6. **Passport keys exist** on the server: `storage/oauth-private.key` and `storage/oauth-public.key`. Never
   run `php artisan passport:keys --force` on production: it would invalidate every MCP/OAuth token.
7. **Cron** runs `* * * * * cd <APP_DIR> && php artisan schedule:run >> /dev/null 2>&1` (**`UNVERIFIED`** on the server).
8. Publish the heads-up announcement (D8), written from `docs/announcement-draft.md`, in the admin
   announcement editor (`/app/admin/announcements`). Claude does not publish it.

## 4. The migrations, in the order `php artisan migrate` runs them

All 21 are new compared with `main`. Plain schema steps are additive (new table or nullable/defaulted column),
nothing is dropped. The ones that touch existing rows are marked **DATA**.

| # | Migration | What it does | Existing rows | Undo (`down`) |
|---|---|---|---|---|
| 1 | `2026_10_31_000001_move_project_tasks_to_the_tasks_list` | **DATA.** `tasks.list = 'projects'` becomes `'tasks'`. `project_id`, `group_id`, `emergency_list` stay. | changes `tasks.list` | **none** (no-op). Restore with the ids saved in step 6 of section 5, or from the backup. |
| 2 | `2026_11_07_000001_create_areas_tables` | new `areas`, `category_area` | none | drops both |
| 3 | `2026_11_07_000002_add_size_deadlines_and_area_to_tasks_table` | **DATA.** adds `tasks.size`, `hard_deadline`, `soft_deadline`, `area_id`; copies `deadline` → `hard_deadline` and `due_date` → `soft_deadline` | old columns untouched | drops the new columns (the old ones still hold the data) |
| 4 | `2026_11_08_000001_create_pomodoro_sessions_table` | new table | none | drops it |
| 5 | `2026_11_08_000002_create_evenings_table` | new table | none | drops it |
| 6 | `2026_11_08_000003_add_first_step_to_tasks_table` | `tasks.first_step`, nullable | none | drops the column |
| 7 | `2026_11_08_000004_add_soft_slips_and_crunch_alerts` | `tasks.soft_slips` default 0, new `crunch_alerts` | none | drops both |
| 8 | `2026_11_09_000001_add_theme_to_users_table` | `users.theme` default `topografie`, `users.color_mode` default `system` | every user gets the defaults | drops both columns |
| 9 | `2026_11_11_000001_add_reset_to_evenings_table` | three nullable columns on `evenings` | none | drops them |
| 10 | `2026_11_16_000001_add_evening_nudge` | `users.nudge_times` (nullable), `evenings.nudge_sent_at` | none | drops them |
| 11 | `2026_11_16_000002_add_free_time_ideas_to_evenings_table` | five nullable columns on `evenings` | none | drops them |
| 12 | `2026_11_16_000003_create_craft_idea_copies_table` | new table (which craft idea became which task); the copy itself is the command in step 12 | none | drops the table (copied tasks stay) |
| 13 | `2026_11_17_000001_add_deleted_at_to_event_categories_table` | soft delete column on `event_categories` | none | drops the column |
| 14 | `2026_11_17_000001_create_project_stages_table` | new table | none | drops it |
| 15 | `2026_11_17_000002_add_stage_and_waits_on_to_tasks_table` | `tasks.stage_id`, `tasks.waits_on_id`, nullable foreign keys | none | drops both |
| 16 | `2026_11_17_000003_add_project_id_to_task_groups_table` | `task_groups.project_id`, `task_groups.list` | none | drops both |
| 17 | `2026_11_17_000004_turn_mixed_groups_into_projects` | **DATA.** A group whose open tasks sit in two or more lists becomes a project: a new `projects` row (name, the group's notes joined into `brainstorm`), its tasks get `project_id`, the group row and its notes stay. Every other group gets `task_groups.list`. Idempotent. One transaction per group. | adds `projects` rows, sets `tasks.project_id` and `task_groups.project_id/list` | removes only the projects it made and clears the links |
| 18 | `2026_11_17_042001_add_push_to_crunch_alerts` | two nullable columns | none | drops them |
| 19 | `2026_11_18_000001_create_notes_and_tags_tables` | new `notes`, `tags`, `note_tag` | none | drops them |
| 20 | `2026_11_18_000002_copy_old_notes_into_note_cards` | **DATA.** every non-blank `projects.brainstorm` and every group note of a group that is still a group becomes a note card (`notes.legacy_source` makes it idempotent); `#tags` are read from the text. Old columns untouched. | adds `notes`, `tags`, `note_tag` rows | removes only cards with a `legacy_source` and unused tags |
| 21 | `2026_11_23_000001_add_moved_from_id_to_schedule_events_table` | nullable column | none | drops it |

Hotfixes on `main` that are not in this list: none (PR #14 has no migration).

## 5. The night, step by step

Placeholders: `<APP_DIR>` the app folder on the server, `<BACKUP_DIR>` a folder **outside** the web root,
`<STAMP>` e.g. `2026-12-06_2200`. Run everything as the user that owns the app.

**1. Announce and check the state**
```bash
cd <APP_DIR>
git status --short                 # must be empty
git rev-parse HEAD                 # write this down: PREV_RELEASE
php artisan migrate:status | grep -c Pending    # 21 expected (the list in section 4)
```
Stop if something other than the 21 migrations is pending.

**2. Maintenance mode (all scheduled commands pause with it)**
```bash
php artisan down --retry=60 --with-secret
```
It prints a secret; opening `https://<APP_URL>/<secret>` sets a cookie that lets you through while the
rest see the maintenance page. Write the secret down.

**3. Backup, database.** Pick the variant that matches production (**`UNVERIFIED`** which one it is).

SQLite (check the file name in `.env` `DB_DATABASE`; with the app in maintenance mode nothing writes):
```bash
mkdir -p <BACKUP_DIR>/<STAMP>
sqlite3 <DB_FILE> ".backup '<BACKUP_DIR>/<STAMP>/database.sqlite'"
```
MariaDB / MySQL:
```bash
mysqldump --single-transaction --routines --default-character-set=utf8mb4 -u <DB_USER> -p <DB_NAME> > <BACKUP_DIR>/<STAMP>/database.sql
```
**4. Backup, files.** The app stores no uploads (`Storage::` is not used in `app/`), so the files that matter
are the configuration, the OAuth keys and the built assets:
```bash
cp .env <BACKUP_DIR>/<STAMP>/env
cp storage/oauth-private.key storage/oauth-public.key <BACKUP_DIR>/<STAMP>/
cp -r public/build <BACKUP_DIR>/<STAMP>/build     # skip if the folder does not exist
```
**5. Prove the backup restores.** Do not skip this: a backup that was never restored is a hope.

SQLite:
```bash
cp <BACKUP_DIR>/<STAMP>/database.sqlite /tmp/restore-check.sqlite
sqlite3 /tmp/restore-check.sqlite "PRAGMA integrity_check; SELECT COUNT(*) FROM users; SELECT COUNT(*) FROM tasks;"
sqlite3 <DB_FILE> "SELECT COUNT(*) FROM users; SELECT COUNT(*) FROM tasks;"      # same two numbers
rm /tmp/restore-check.sqlite
```
MariaDB / MySQL (into a scratch database, never into production):
```bash
mysql -u <DB_USER> -p -e "CREATE DATABASE restore_check CHARACTER SET utf8mb4"
mysql -u <DB_USER> -p restore_check < <BACKUP_DIR>/<STAMP>/database.sql
mysql -u <DB_USER> -p restore_check -e "SELECT COUNT(*) FROM users; SELECT COUNT(*) FROM tasks;"
mysql -u <DB_USER> -p <DB_NAME> -e "SELECT COUNT(*) FROM users; SELECT COUNT(*) FROM tasks;"   # same two numbers
mysql -u <DB_USER> -p -e "DROP DATABASE restore_check"
```
Stop if the numbers differ.

**6. Save what migration 1 moves (and what D1/D2 would change).** Migration 1 cannot be undone by `down`;
these ids make it exact:
```bash
# SQLite: sqlite3 <DB_FILE> "..."   MySQL: mysql ... -e "..."
SELECT id FROM tasks WHERE list = 'projects';                              -- save to <BACKUP_DIR>/<STAMP>/projects-list-task-ids.txt
SELECT id, task_source FROM event_categories WHERE task_source IN ('project','group','agenda_entry','agenda_generic');   -- D1
SELECT id FROM tasks WHERE is_important = 1 AND is_completed = 0;          -- D2, only if you will clear marks
```
**7. Counts and fingerprint before** (read-only; the fingerprint holds hashes, no readable user data):
```bash
php artisan app:switch-preflight > <BACKUP_DIR>/<STAMP>/preflight-before.txt ; echo "exit $?"
php artisan app:rehearsal-snapshot before --user=<your email>
```
`exit 1` means a BLOCK line: fix the data (or stop) before going on. WARN lines are the decisions of section 1.
It also runs on the old schema, so it is safe before `migrate`. Keep the output next to the backup.

**8. Deploy the code**
```bash
git fetch origin
git checkout main && git merge --ff-only origin/main        # after the PR refactor/refactor -> main is merged on GitHub
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build                                     # UNVERIFIED: skip if the assets are built elsewhere; Node 22 in CI
```
`composer.lock` is the same as on the old release, so `composer install` should report nothing to change.

**9. Look before you migrate**
```bash
php artisan migrate --pretend | head -60          # the schema SQL; the DATA migrations 1, 17, 20 print little (they read rows)
```
**10. Migrate** (all 21 run in one batch; the next `migrate:rollback` undoes exactly this batch)
```bash
php artisan migrate --force
php artisan migrate:status | tail -25               # all Ran, same batch number
```
**11. Counts and fingerprint after migrate, before the craft ideas**
```bash
php artisan app:rehearsal-snapshot after --user=<your email>
php artisan app:rehearsal-compare before after \
  --same=tasks.deadline:tasks.hard_deadline --same=tasks.due_date:tasks.soft_deadline \
  --ignore=tasks.list --ignore=tasks.project_id --ignore=event_categories.task_source --ignore=event_categories.linked_text
```
Expected: "Nothing lost". `tasks.list` changes on purpose (migration 1) and `tasks.project_id` for the tasks of
converted groups (migration 17). If the compare names anything else, stop and go to section 7.
(`--ignore=tasks.project_id` was **not** exercised with a mixed group in the rehearsal fixtures; the production copy shows it.)

**12. Copy the craft ideas into Later (dry run first)**
```bash
php artisan app:copy-craft-ideas --dry-run     # prints users, ideas, would copy: the numbers must match the preflight NOTE
php artisan app:copy-craft-ideas               # idempotent; never changes craft_ideas
```
This must come after `migrate` (it needs `craft_idea_copies` and `tasks.first_step`).

**13. Data decisions** (only if you chose them in section 1): D1 / D2 as SQL, from the ids saved in step 6, in a
transaction, and write down the number of rows changed. Not part of this runbook until decided.

**14. Counts after, and the R8 checklist** (section 6)
```bash
php artisan app:switch-preflight > <BACKUP_DIR>/<STAMP>/preflight-after.txt ; echo "exit $?"
diff <BACKUP_DIR>/<STAMP>/preflight-before.txt <BACKUP_DIR>/<STAMP>/preflight-after.txt
```
**15. Caches and processes**
```bash
php artisan optimize:clear
php artisan optimize                 # config, events, routes and views cached (route:cache works with the current routes)
# restart PHP-FPM / reload OPcache the way your server does it   (UNVERIFIED which)
```
**16. Smoke checks while still in maintenance mode** (open the secret URL from step 2, then):

- `/login` loads; log in with your account; you land on the board (`/app`, or `/board` if D5 is built).
- `/board`, `/board/week`, `/board/week/plan`, `/board/review`, `/board/projects`, `/board/ideas`, `/board/settings` open without an error page.
- `/app/schedule`, `/app/weekplan`, `/app/review` redirect to the three new screens.
- Capture one thing from the capture line; undo it.
- Start and finish a session on the board (pomodoro engine).
- `/profile/export` (data export of your own account) downloads.
- `/app/admin/errors` (error log) shows nothing new except what you caused.
- `php artisan schedule:list` shows the eleven commands; `php artisan schedule:test` is not needed.
- A push test from Settings reaches your phone (VAPID keys are right).

**17. Open it**
```bash
php artisan up
```
**18. Announce** the `release` announcement (admin editor, text from `docs/announcement-draft.md`).

**19. First hours.** The first hourly `app:run-planning-checks` is "release day" (`docs/steps/1.4.md`): every
overdue soft deadline is rolled over once (to tomorrow for old ones), nothing goes to Later on day one. So
`tasks.soft_deadline` (and the mirrored `due_date`) of overdue open tasks changes right after the switch, on
purpose. Take the "after" snapshot (step 11) **before** `php artisan up`, never after this run.
Watch `/app/admin/errors` and `storage/logs/laravel.log` for the first hours.

## 6. Counts before and after (R8)

`app:switch-preflight` (steps 7 and 14) prints the row count of **every** table, so the plain `diff` of the two
files is the full before/after list. Rules for what must and must not change:

| Table | After the switch |
|---|---|
| `users`, `schedule_events`, `schedule_event_attribute_values`, `schedule_pauses`, `schedule_day_bounds`, `event_templates`, `event_categories`, `category_attributes`, `category_task_links`, `task_day_plans`, `reminders`, `push_subscriptions`, `task_sessions`, `help_*`, `feature_announcements`, `support_requests`, `agenda_*`, `streak_day_outcomes`, `module_visits`, `error_occurrences`, `personal_access_tokens`, `oauth_*`, `group_notes`, `task_groups`, `craft_ideas` | **exactly the same** |
| `tasks` | before **+ craft ideas copied in step 12** (`craft_ideas` of users that had no copy); per user: before + that user's craft ideas |
| `projects` | before **+ mixed groups** (the NOTE `groups with open tasks in several lists` of the preflight) |
| `craft_idea_copies` | equals `craft_ideas` after step 12 |
| `notes` | non-blank `projects.brainstorm` after step 10 **+** non-blank `group_notes` of groups that are still groups (see SQL below) |
| `areas`, `category_area`, `evenings`, `pomodoro_sessions`, `crunch_alerts`, `project_stages` | 0 (new, empty). Later they fill by use. |
| `tags`, `note_tag` | filled from `#tags` in the copied notes |
| `migrations` | before + 21 |
| `sessions`, `cache`, `jobs` | not compared (volatile) |

The SQL that backs the rules. Run it before and after (SQLite and MariaDB/MySQL); keep the numbers next to the backup.
```sql
SELECT list, COUNT(*) FROM tasks GROUP BY list ORDER BY list;     -- after: no 'projects'; 'tasks' = before.tasks + before.projects; 'later' = before + copied ideas
SELECT COUNT(*) FROM tasks;                                       -- after = before + copied ideas
SELECT COUNT(*) FROM tasks WHERE is_completed = 1;                -- after = before + copied ideas that are done
SELECT user_id, COUNT(*) FROM tasks GROUP BY user_id ORDER BY user_id;   -- per user, differs only by that user's craft ideas
SELECT COUNT(*) FROM tasks WHERE deadline IS NOT NULL AND (hard_deadline IS NULL OR date(hard_deadline) <> date(deadline));   -- after: 0
SELECT COUNT(*) FROM tasks WHERE due_date IS NOT NULL AND (soft_deadline IS NULL OR date(soft_deadline) <> date(due_date));   -- after: 0
SELECT COUNT(*) FROM tasks WHERE is_important = 1 AND is_completed = 0;     -- same before and after (unless D2)
SELECT COUNT(*) FROM task_groups;                                           -- same
SELECT COUNT(*) FROM task_groups WHERE project_id IS NOT NULL;              -- after = number of mixed groups
SELECT COUNT(*) FROM projects;                                              -- after = before + mixed groups
SELECT COUNT(*) FROM group_notes;                                           -- same
SELECT COUNT(*) FROM craft_ideas;                                           -- same
SELECT COUNT(*) FROM craft_idea_copies;                                     -- after = craft_ideas
SELECT COUNT(*) FROM notes;                                                 -- after: see next query
SELECT (SELECT COUNT(*) FROM projects WHERE brainstorm IS NOT NULL AND TRIM(brainstorm) <> '')
     + (SELECT COUNT(*) FROM group_notes gn JOIN task_groups g ON g.id = gn.task_group_id
         WHERE g.project_id IS NULL AND TRIM(gn.content) <> '') AS expected_notes;   -- equals notes after (a note of only line breaks can differ by a row: PHP trims more than SQL)
SELECT COUNT(*) FROM event_categories WHERE task_source IN ('project','group','agenda_entry','agenda_generic');   -- same unless D1
```
Per user spot check (your own account, id `<ID>`): `SELECT COUNT(*) FROM <table> WHERE user_id = <ID>` for the
tables above, or `php artisan app:rehearsal-snapshot before --user=<your email>` (prints the per-table numbers of
that account).

## 7. Rollback

**Where the point of no return is.** Up to and including step 16 the app is in maintenance mode and nobody has
written anything: going back loses nothing. After `php artisan up` (step 17) people use the new app, and from
the **first new write** (a capture, a session, a changed week plan) going back means losing those writes.
After that, prefer to fix forward.

| When | What | Commands |
|---|---|---|
| Before step 10 (nothing migrated) | Leave maintenance mode. | `php artisan up` (nothing else changed; if the code was already deployed, `git checkout <PREV_RELEASE>` and rebuild, below) |
| Steps 10–16, still in maintenance mode | **Option A (cleanest): restore the backup.** Exact, loses nothing because nobody wrote. | SQLite: `cp <BACKUP_DIR>/<STAMP>/database.sqlite <DB_FILE>`. MySQL: `mysql -u <DB_USER> -p <DB_NAME> < <BACKUP_DIR>/<STAMP>/database.sql` (the dump drops and recreates its tables by default; **`UNVERIFIED`** for your dump options). |
| | **Option B: roll the batch back.** Only if a restore is not possible. | `php artisan migrate:rollback --force` (undoes all 21 if they are one batch: check `migrate:status` first). Then restore what migration 1 moved: `UPDATE tasks SET list = 'projects' WHERE id IN (<ids from projects-list-task-ids.txt>);`. Craft ideas copied in step 12 stay as tasks in `later` (`down` of migration 12 does not delete them): delete them by id from `craft_idea_copies` **before** the rollback if they must go. |
| After `up`, with new writes | **Restore the backup, and accept losing everything written since** (tell the users), or fix forward. Do not roll the migrations back: that drops the tables with the users' new data (areas, evenings, notes, project stages, sessions) and a mixed group's project. | as above |

Code and assets back to the old release:
```bash
git checkout <PREV_RELEASE>
composer install --no-dev --optimize-autoloader --no-interaction
rm -rf public/build && cp -r <BACKUP_DIR>/<STAMP>/build public/build     # or: npm ci && npm run build
cp <BACKUP_DIR>/<STAMP>/env .env                                         # only if .env was touched
php artisan optimize:clear && php artisan optimize
php artisan up
```
Old code on the migrated schema also works (everything is additive and `deadline`/`due_date` are still written),
but then the new app's edits (sizes, areas) are invisible to it, so this is a stop-gap, not a plan.

After any rollback: run `php artisan app:switch-preflight` and compare with `preflight-before.txt`.

## 8. Niels' tests on the production copy (before the night)

All need the production dump (spike 0.3), restored into a separate database or SQLite file with
`.env.rehearsal` (`docs/spikes/0.3-migration-dry-run.md`). Never on production itself. The test report
(`docs/TESTBERICHT.md` section C) has the full wording.

| Test | What you do on the copy |
|---|---|
| **C3** | Run the whole cycle (`app:rehearsal-snapshot before`, `migrate`, `after`, `app:rehearsal-compare`, `migrate:rollback`, `rolled-back`, compare) and open your own account: is anything missing or changed? Also run `php artisan app:switch-preflight` before and after and read the BLOCK/WARN lines. |
| **C11** | Find one thing from last month with real old data. |
| **C15** | `php artisan app:copy-craft-ideas --dry-run`, then without; open „Ideen“: are all your Bastelideen there with their „Wo anfangen“ note? |
| **C32** | Count groups with tasks in more than one list (the preflight NOTE gives the number); after `php artisan migrate` each is a project with all its tasks. |
| **C43** | Your Enderdragon brainstorm and your group notes show up as note cards after `php artisan migrate`. |
| **C45** | Count the open marked tasks per user (the preflight WARN lists users above 10); do the right tasks show as „Wichtig“? Decide D2. |
| **C100–C106** | The new checks of this step (redirects, preflight, runbook rehearsal, announcement): `docs/TESTBERICHT.md` section C. |
