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

---

## Solved Issues

### Session cookie never set — `NODE_ENV=production` from `.env` file

**Symptom:** After login or register, `/auth/me` and `/api/v1/tasks` returned 401. No `Set-Cookie` header appeared in the HTTP response. All sessions in `tasks.db` had `userId: undefined` or were saved without `userId` at all.

**What was tried first (did NOT fix it):**

1. **Deleting `data/tasks.db`** — the hypothesis was that stale sessions from earlier development attempts were causing confusion. Deleting the DB didn't help; fresh logins still produced no cookie.

2. **Removing `session.regenerate()`** — the login handler used `await request.session.regenerate()` before setting `request.session.userId = user.id`. The hypothesis was that `regenerate()` was creating a new session instance that lost its connection to the `onSend` hook, so the cookie was never written. Removing `regenerate()` and setting `userId` directly appeared correct (all 44 tests still passed), but the cookie was still absent in real HTTP requests.

3. **Checking `NODE_ENV` from the shell** — running `node -e "console.log(process.env.NODE_ENV)"` returned `undefined`, which pointed away from the `.env` hypothesis. This was misleading because the shell process doesn't call `require('dotenv').config()`.

**Root cause:**

`src/server.js` calls `require('dotenv').config()` at startup, which loads `.env`. That file contains `NODE_ENV=production`. In `src/app.js`, the session cookie was configured as:

```javascript
secure: process.env.NODE_ENV === 'production'  // evaluates to true after dotenv loads
```

With `secure: true`, `@fastify/session`'s `onSend` hook sets `isInsecureConnection = true` for any non-HTTPS request and silently skips writing the cookie. The server responds 200 but never sends `Set-Cookie`.

This affected ALL environments where the server was started via `node src/server.js` (production config, preview tool, direct dev). The only place it worked was in tests — because `test/*.test.js` calls `buildApp()` directly without going through `server.js`, so `dotenv` never loads.

**How it was found:**

Added `console.log` to `src/app.js` right before `fastify.register('@fastify/session', ...)` and saw `NODE_ENV: production` in the server output despite the shell showing it unset. Added a debug log inside `@fastify/session`'s `onSend` handler (in node_modules) and confirmed `cookieOpts.secure: true` and `isInsecure: true`.

**Fix (in `src/app.js`):**

```javascript
// Before (broken):
secure: process.env.NODE_ENV === 'production'

// After (fixed):
secure: 'auto'
```

`'auto'` is a built-in mode in `@fastify/session` / `@fastify/cookie`: it sets `secure: false` when `request.protocol === 'http'` and `secure: true` when `request.protocol === 'https'`. This is correct for all environments without depending on `NODE_ENV`:
- Dev / preview (HTTP) → cookie is set without `Secure` flag ✓
- Production behind nginx/Caddy (HTTPS) → cookie is set with `Secure` flag ✓

**Side effect also fixed:** `session.regenerate()` was removed from the login and register handlers. With `regenerate()` present, the empty session was saved to the store before `userId` was set. The real `userId`-bearing session was saved later in `onSend` — but since `isInsecureConnection` was `true`, `onSend` bailed out early. After the `secure: 'auto'` fix, `regenerate()` would have worked again, but it was left removed since it added complexity without meaningful security benefit in this self-hosted context (session fixation is not a realistic threat model here).

---

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
