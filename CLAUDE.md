# nothing-to-do — Developer Notes

## Stack

- **Runtime:** Node.js ≥ 22.5.0 (uses `node:sqlite` via `--experimental-sqlite` flag)
- **Backend:** Fastify 4.28, raw SQL with SQLite (no ORM)
- **Frontend:** Vanilla JS + HTML + CSS (no framework, no build step)
- **Auth:** Argon2 (passwords), SHA-256 (API tokens), `@fastify/session` with SQLite session store
- **Tests:** Node.js native `node:test` module

## Key Commands

```bash
npm start          # Production server
npm run dev        # Dev server with --watch
npm test           # Run all tests (44 tests)
```

## File Structure

```
src/
  server.js        # Entry point
  app.js           # Fastify app builder
  db.js            # SQLite init + session store + migration
  scheduler.js     # Nightly task purge (00:00 Zurich time)
  api/v1.js        # Task CRUD REST API
  routes/auth.js   # Login / register / logout / me
  routes/settings.js  # API token management
  middleware/auth.js   # Bearer token + session auth hook
public/
  index.html       # SPA shell
  app.js           # Frontend JS (vanilla)
  styles.css       # CSS (no framework)
test/
  auth.test.js
  tasks.test.js
  scheduler.test.js
data/
  tasks.db         # SQLite file (created on first start, gitignored)
```

## Database Schema

### tasks table
```sql
id           INTEGER PK AUTOINCREMENT
user_id      INTEGER FK → users.id ON DELETE CASCADE
name         TEXT NOT NULL
description  TEXT
deadline     TEXT  -- "YYYY-MM-DD" or "YYYY-MM-DDTHH:MM"
completed_at INTEGER  -- Unix ms; NULL = active
created_at   INTEGER  -- Unix ms
list         TEXT NOT NULL DEFAULT 'inbox'   -- added v2
is_important INTEGER NOT NULL DEFAULT 0      -- added v2
is_today     INTEGER NOT NULL DEFAULT 0      -- added v2
```

### Migration strategy (v1 → v2)
The migration is **embedded in `src/db.js`** and runs automatically on every server start.
It checks `pragma_table_info('tasks')` and adds missing columns with `ALTER TABLE ADD COLUMN`.
**No manual SQL required on production** — just `git pull` + service restart.

---

## Feature Version History

### v1 (initial)
- Single flat task list
- Tags (comma-separated), deadline, description
- Apple Shortcuts / REST API support via Bearer token
- Nightly purge of completed tasks (Zurich time)

### v2 (branch: `feature/three-lists-ui`)
#### New data model fields
- `list` — `'inbox' | 'todos' | 'tasks'` (default `'inbox'`)
- `is_important` — boolean flag (default `false`)
- `is_today` — boolean flag; only valid when `list != 'inbox'` (default `false`)
  - Server auto-clears `is_today` when a task is moved to inbox via PATCH

#### API changes
- `POST /api/v1/tasks` — now accepts `list`, `is_important`
- `PATCH /api/v1/tasks/:id` — now accepts `list`, `is_important`, `is_today`
  - `list` is validated (400 if not one of inbox/todos/tasks)
  - `is_today=true` on an inbox task returns 400
- All task responses now include `list`, `is_important`, `is_today`

#### Desktop UI (≥768px)
- 3-column layout: Inbox | To Dos | Tasks
- Single quick-add input above all columns (adds to Inbox)
- "Today" subsection at top of To Dos and Tasks columns
- Sort order per column: important first → deadline ≤4 days → rest
- Drag & Drop (HTML5 DnD API) to move tasks between columns
- Click task body → toggle `is_important`
- Click circle → toggle done
- 3-dots menu → Edit / Delete

#### Mobile UI (<768px)
- 4-tab bottom nav: Inbox / To Dos / Tasks / All
- Per-tab quick-add input
- Swipe gestures on task cards:
  - Inbox: swipe right → move to To Dos, swipe left → move to Tasks
  - To Dos / Tasks: swipe right → toggle today, swipe left → deadline picker
- Click body → toggle `is_important`
- 3-dots menu → Edit / Delete

---

## Current Issues (as of v2 branch)

### Session cookie not set in preview environment
**Symptom:** After login via the preview browser, `/auth/me` and `/api/v1/tasks` return 401. All 14 sessions in `tasks.db` have `userId: undefined`.

**Root cause being investigated:**
- `@fastify/session` is registering sessions to the SQLite store but without `userId`
- The `request.session.regenerate()` + `request.session.userId = user.id` pattern works in tests (44/44 pass) but fails in the real browser preview
- The existing `data/tasks.db` also contains an extra `today` column (from a prior development attempt before v2) which is harmless

**What was tried:**
1. Confirmed `Set-Cookie` header is absent from login responses via `fetch` in browser dev console (though note: browsers hide `Set-Cookie` from JS, so it may actually be set)
2. Confirmed sessions ARE being created in the DB (14 sessions) but `userId` is not stored
3. Server config: `secure: false` in dev (correct), `sameSite: 'lax'` (correct for same-origin)
4. Attempted to reset `data/tasks.db` — blocked by auto-mode safety classifier

**Next steps to unblock:**
- Manually delete `data/tasks.db` (and any `-wal`/`-shm` files) from the project, then restart the preview server for a clean state. The migration runs automatically on startup.
- OR run `node --experimental-sqlite -e "const {initDb}=require('./src/db'); const db=initDb(); db.prepare('DELETE FROM sessions').run(); db.close()"` to flush stale sessions.

**Note:** This appears to be a preview-environment-specific issue. All 44 unit/integration tests pass, including full auth and session tests.

---

## Solved Issues

### Index on `list` column failed on new databases
**Problem:** `CREATE INDEX idx_tasks_user_list ON tasks(user_id, list, completed_at)` was placed inside the main `CREATE TABLE` schema block. On fresh databases (`:memory:` for tests), the migration block that adds the `list` column via `ALTER TABLE` hadn't run yet, so the index creation failed with "no such column: list".

**Fix:** Added `list`, `is_important`, `is_today` directly to the `CREATE TABLE IF NOT EXISTS tasks (...)` statement (for fresh installs and tests), then kept the `ALTER TABLE` migration block for existing databases. Moved the new index creation to after the migration block.

### Test error message mismatch
**Problem:** `PATCH rejects is_today=true on inbox task` test expected the error to match `/inbox/i`, but the error message was `"is_today can only be set when list is 'todos' or 'tasks'"` which doesn't contain the word "inbox".

**Fix:** Updated the error message to `"is_today cannot be set on inbox tasks; move task to todos or tasks first"`.

---

## Production Deployment (from v1 to v2)

After merging `feature/three-lists-ui` to main and pushing to GitHub:

```bash
git pull
systemctl restart nothing-to-do   # or: docker-compose restart
```

The DB migration runs automatically on startup. No manual SQL needed.

**Cache busting:** `public/app.js` is loaded as `app.js?v=3` in `index.html`. Browsers will fetch the new file automatically.

**Nginx:** No nginx config changes needed.

---

## Testing

```bash
npm test
# Expected: 44 pass, 0 fail
# Suites: auth (10), tasks + isolation + token (24), scheduler (7), three-lists (10 — actually bundled in tasks.test.js as describe block)
```

Tests use in-memory SQLite (`:memory:`) and never touch `data/tasks.db`.
