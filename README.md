# nothing-to-do

A self-hosted, multi-user to-do list with a clean web UI and a token-authenticated REST API. Built to run as a permanent service on a Linux server — no cloud services, no external databases.

**Key features**

- Multiple user accounts, each fully isolated from the others
- Tasks with name, description, tags, and deadline
- Mark done / unmark; completed tasks auto-purge at **00:00 Europe/Zurich**
- REST API compatible with **Apple Shortcuts** and any HTTP client
- SQLite — a single file, trivially backup-able

---

## Prerequisites

| Requirement | Minimum version | Notes |
|---|---|---|
| Node.js | 22.5.0 | On Node 22.x the `--experimental-sqlite` flag is required (see scripts). Node 23.4+ needs no flag. |
| npm | 8+ | |

The app uses only the Node.js built-in SQLite module (`node:sqlite`). No native compilation tools (Python, gcc, Visual Studio) required.

---

## Quick start (development)

```bash
git clone https://github.com/yourname/nothing-to-do
cd nothing-to-do
npm install
cp .env.example .env   # edit SESSION_SECRET at minimum
npm run dev            # starts with --watch for auto-reload
```

Open http://localhost:3000, register an account, and you're good to go.

---

## Production deployment on Linux

### 1. Install Node.js

```bash
# Node 22 LTS via NodeSource
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt-get install -y nodejs
```

### 2. Deploy the app

```bash
sudo mkdir -p /opt/nothing-to-do
sudo chown $USER: /opt/nothing-to-do
git clone https://github.com/yourname/nothing-to-do /opt/nothing-to-do
cd /opt/nothing-to-do
npm install --omit=dev
```

### 3. Configure environment

```bash
cp .env.example .env
# Generate a strong session secret:
node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"
# Paste the output as SESSION_SECRET in .env
nano .env
```

### 4. Install the systemd service

```bash
sudo cp nothing-to-do.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now nothing-to-do
sudo systemctl status nothing-to-do
```

### 5. Set up nginx (reverse proxy + TLS)

```bash
sudo cp nginx.conf.example /etc/nginx/sites-available/nothing-to-do
# Edit the server_name and certificate paths
sudo nano /etc/nginx/sites-available/nothing-to-do
sudo ln -s /etc/nginx/sites-available/nothing-to-do /etc/nginx/sites-enabled/
sudo certbot --nginx -d tasks.example.com
sudo nginx -t && sudo systemctl reload nginx
```

---

## Docker

```bash
# Create a .env with at minimum: SESSION_SECRET=<your-secret>
docker compose up -d
```

The database is stored in a named Docker volume (`ntd-data`). Back it up with:

```bash
docker run --rm -v ntd-data:/data -v $(pwd):/backup alpine \
  tar czf /backup/ntd-backup.tar.gz /data
```

---

## Backup

The entire application state is a single SQLite file:

```bash
# Simple backup (stop-the-world safe)
cp /opt/nothing-to-do/data/tasks.db /backup/tasks-$(date +%Y%m%d).db

# Live backup (safe while running, uses SQLite's online backup)
sqlite3 /opt/nothing-to-do/data/tasks.db ".backup /backup/tasks-$(date +%Y%m%d).db"
```

---

## REST API reference

All API endpoints are under `/api/v1/`. Authentication is via a **Bearer token** in the `Authorization` header.

### Get your API token

1. Log in to the web UI
2. Click the ⚙ settings icon
3. Click **Generate token**
4. Copy the token (it is shown exactly once)

### Endpoints

#### List tasks
```
GET /api/v1/tasks
Authorization: Bearer ntd_<your-token>
```

Returns active tasks and tasks completed today (Zurich time):
```json
{
  "tasks": [
    {
      "id": 1,
      "name": "Buy groceries",
      "description": "Milk, eggs, bread",
      "tags": ["shopping"],
      "deadline": "2024-01-15T18:00",
      "completed_at": null,
      "created_at": 1705312800000
    }
  ]
}
```

#### Create a task
```
POST /api/v1/tasks
Authorization: Bearer ntd_<your-token>
Content-Type: application/json

{
  "name": "Buy groceries",
  "description": "Milk, eggs, bread",
  "tags": ["shopping", "weekly"],
  "deadline": "2024-01-15T18:00"
}
```

- `name` — **required**
- `description`, `tags`, `deadline` — optional
- `deadline` format: `YYYY-MM-DD` (date only) or `YYYY-MM-DDTHH:MM` (with time)

Returns `201` with the created task object.

#### Update a task
```
PATCH /api/v1/tasks/:id
Authorization: Bearer ntd_<your-token>
Content-Type: application/json

{
  "name": "New name",
  "deadline": "2024-02-01"
}
```

Send only the fields you want to change. To clear a field, send `null`. To clear tags, send `"tags": []`.

#### Mark as done (toggle)
```
PATCH /api/v1/tasks/:id/done
Authorization: Bearer ntd_<your-token>
```

Toggles the completed state. Returns the updated task.

#### Delete a task
```
DELETE /api/v1/tasks/:id
Authorization: Bearer ntd_<your-token>
```

Permanently deletes the task. Returns `204 No Content`.

---

## curl examples

```bash
TOKEN="ntd_your_token_here"
BASE="https://tasks.example.com"

# List tasks
curl -s -H "Authorization: Bearer $TOKEN" $BASE/api/v1/tasks | jq .

# Create a task
curl -s -X POST $BASE/api/v1/tasks \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Call dentist","tags":["health"],"deadline":"2024-01-20"}'

# Mark task 42 as done
curl -s -X PATCH $BASE/api/v1/tasks/42/done \
  -H "Authorization: Bearer $TOKEN"

# Delete task 42
curl -s -X DELETE $BASE/api/v1/tasks/42 \
  -H "Authorization: Bearer $TOKEN"
```

---

## Apple Shortcuts setup

You can add and manage tasks directly from Shortcuts using the **Get Contents of URL** action.

### Create a task from Shortcuts

1. Add a **Get Contents of URL** action
2. Set the URL to `https://tasks.example.com/api/v1/tasks`
3. Set **Method** to `POST`
4. Add a **Header**: `Authorization` → `Bearer ntd_your_token_here`
5. Set **Request Body** to `JSON`
6. Add JSON fields:
   - `name` → (text input or variable, e.g. from an **Ask for Input** action)
   - `description` → optional
   - `tags` → optional array, e.g. `["shortcuts","auto"]`
   - `deadline` → optional, format `YYYY-MM-DD`

### List tasks from Shortcuts

1. **Get Contents of URL** → `https://tasks.example.com/api/v1/tasks`
2. Method: `GET`
3. Header: `Authorization` → `Bearer ntd_your_token_here`
4. The response is a JSON object with a `tasks` array
5. Use **Get Dictionary Value** → `tasks` to extract the list

### Quick-add task (one-tap Shortcut)

Create a shortcut that:
1. **Ask for Input** — "Task name?"
2. **Get Contents of URL** (POST, with the name from step 1)
3. **Show Notification** — "Task added!"

Add this shortcut to your home screen or as a widget for one-tap task entry.

---

## Running tests

```bash
npm test
```

Tests use Node's built-in `node:test` runner and an in-memory SQLite database — no setup required.

---

## Architecture notes

**Midnight purge:** Implemented as an in-process `node-schedule` cron job (`0 0 * * *`, `Europe/Zurich` timezone). On every startup, the purge also runs immediately to catch up on any days the server was offline. The purge is idempotent — running it multiple times is safe.

**Authentication:** The web UI uses session cookies (`@fastify/session` backed by SQLite). The REST API uses per-user Bearer tokens. Both auth methods work against the same `/api/v1/*` endpoints, so the web UI and external tools share identical data access paths.

**API tokens:** The raw token is returned once at generation time and never stored. Only a SHA-256 hash of the token is persisted in the database.

**Database:** A single SQLite file under `data/tasks.db`. WAL mode is enabled for better read performance. Foreign key constraints are enforced with `PRAGMA foreign_keys = ON`.
