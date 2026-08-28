# Encrypting user data — feasibility, scope, and a deploy plan

> Status: **plan only, nothing implemented.** Written after auditing every text column and every
> place one is used in a SQL clause. Read §5 before touching production — that is the part where
> data actually gets lost.

---

## 1. Verdict up front

**Encrypting the freeform content is easy. Encrypting everything is not worth it.**

Laravel's `encrypted` Eloquent cast does the work transparently — one line per column in
`casts()`, and every read/write in the app keeps working unchanged. The cost is not the encryption,
it's that **an encrypted column can never again be used in a `WHERE`, `ORDER BY`, `GROUP BY`, or
`LIKE`** — Laravel's encryption is non-deterministic (random IV per write), so two rows both
holding `"Mathematik"` are two completely different byte strings in the database.

Across the whole app that costs us exactly **three** query sites (§4). Everything else — every
notes field, every brainstorm, every idea, every note card — is never touched by SQL at all and can
be encrypted for free.

The recommendation is therefore: **encrypt the content, leave the structure.** Dates, `list`,
`is_today`, `is_completed`, `sort_order`, FKs and timestamps stay plaintext. They are what the
board ordering, the Planner's scoring, ProgressStats' streak/heatmap queries and the deadline strip
all run on, and encrypting them would mean pulling entire tables into PHP to filter them by hand.

---

## 2. Threat model — be honest about what this buys

The Agenda can be shared with a class (CLAUDE.md §1). A shared entry has to be readable by ~25
different accounts. That rules out per-user keys derived from a password: there is no key that one
user has and another does not, for a row both must read. So this has to be **application-level
encryption with `APP_KEY`**, and `APP_KEY` lives in `.env` on the same box as the database.

| Threat | Protected after this? |
|---|---|
| A leaked / stolen database dump or backup file | **Yes** — this is the real win |
| The SQLite file or MySQL data directory being read off disk | **Yes** |
| A SQL-injection-shaped read, or a DB user with too much access | **Yes** |
| Someone with shell/file access to the production server | **No** — `.env` is right there |
| A compromised application (RCE, malicious dependency) | **No** — the app decrypts by design |
| Anthropic/Claude, or anyone with repo access | **No** — irrelevant, the data isn't in the repo |

This is not end-to-end encryption and should never be described to a classmate as "only you can
read it". It is backup-and-disk-at-rest protection. That is a genuine, meaningful improvement, and
it is also the *only* kind available while the Agenda stays shared.

> If zero-knowledge encryption ever becomes the actual goal, it is a different project: client-side
> crypto in the browser, a key derived from the password, and the shared Agenda would need per-space
> keys wrapped per member. Everything server-side that reads content — push notification bodies,
> `TaskSuggestor`, the five scheduled reminder commands — would stop working, because the server
> would no longer be able to read a task title. Not recommended.

---

## 3. Field inventory

Audited across `app/`, every `where`/`orderBy`/`groupBy`/`like` against a text column.

### Encrypt — never touched by SQL (free, do all of these)

| Table | Column |
|---|---|
| `tasks` | `notes` |
| `projects` | `brainstorm`, `name`, `external_url` |
| `task_groups` | `name` |
| `group_notes` | `content` |
| `agenda_entries` | `title`, `notes` |
| `agenda_entry_notes` | `notes` |
| `craft_ideas` | `title`, `where_to_begin` |
| `schedule_events` | `title` |
| `schedule_pauses` | `note` |
| `event_categories` | `linked_text` |

### Encrypt — but a query has to be rewritten first (§4)

| Table | Column | Blocked by |
|---|---|---|
| `tasks` | `title` | `where('title', 'like', …)` in `Settings::linkTaskCandidates()` and `ManagesSchedule::eventTaskCandidates()` |
| `agenda_entries` | `subject` | `distinct()->orderBy('subject')` in `Agenda::existingSubjects()` / `QuickCapture`, and `where('subject', $filter)` in `Agenda` |
| `event_categories`, `event_templates`, `agenda_spaces` | `name` | `orderBy('name')` as a secondary sort — cosmetic, easy |

### Leave plaintext — deliberately

- **Every date and flag**: `deadline`, `due_date`, `today_date`, `completed_at`, `date`,
  `start_time`, `end_time`, `is_today`, `is_important`, `is_completed`, `list`, `sort_order`,
  `duration_minutes`. These are the app's entire query surface.
- **`users.email` / `password`** — email is the login identifier; password is already hashed.
- **`agenda_spaces.invite_code`** — looked up by `findByInviteCode()`.
- **`agenda_drafts.subject`** — a TTL'd presence signal, deleted within seconds, not durable content.
- **`push_subscriptions`, `personal_access_tokens`, sessions, cache, jobs** — infrastructure, not
  user content. (Tokens are already hashed.)
- **`feature_announcements`** — admin-authored, shown to everyone. Nothing private about it.

---

## 4. The three query sites that have to change

All three are small, and at this app's data volume (one student's tasks; one class's agenda) moving
the filter from SQL into PHP is free.

**a) Task title search** — `Settings::linkTaskCandidates()`, `ManagesSchedule::eventTaskCandidates()`

```php
// before
return $query->where('title', 'like', '%'.$search.'%')->boardOrdered()->get();

// after — decrypt happens in the model, so filter after fetching
return $query->boardOrdered()->get()
    ->filter(fn (Task $t) => str_contains(mb_strtolower($t->title), mb_strtolower($search)))
    ->values();
```
Bonus: this becomes case-insensitive and accent-correct, which the SQLite `LIKE` was not.

**b) Agenda `existingSubjects()`** — the `distinct()->orderBy()->pluck()` has to become a fetch and
a PHP `unique()->sort()`. Note this changes the query from "select one column" to "select the rows",
so keep it `->select(['id','subject'])`-narrow.

**c) `where('subject', $filterSubject)`** — the Fach filter. Same treatment: filter the already-
fetched collection. `Agenda` already loads the visible entries for rendering, so this can reuse
that collection rather than adding a query.

The `orderBy('name')` tie-breakers on categories/templates/spaces just move to a PHP `sortBy()` on
the returned collection — these are lists of a handful of rows.

---

## 5. Not losing data on deploy

This is the part that matters. Three independent ways this can destroy data, and the mitigation for
each.

### 5.1 Silent truncation — the biggest risk

Ciphertext is far longer than plaintext. Measured with Laravel's exact envelope format
(`base64(json{iv, value, mac, tag})`, AES-256-CBC):

| Plaintext | Ciphertext |
|---|---|
| 0 chars | **200** chars |
| 12 chars (`"Hausaufgaben"`) | **200** chars |
| 40 chars | **256** chars |
| 100 chars | **380** chars |
| 255 chars | **632** chars |

Every one of these columns is `VARCHAR(255)` today. **A 40-character task title already overflows
it.** On MySQL outside strict mode that is a silent truncation — the row is written, no error is
raised, and the value is then *permanently undecryptable* because the MAC no longer matches. The
data is gone with no warning at the moment it happens.

**Mitigation:** a separate, earlier migration that widens every column being encrypted to `TEXT`,
deployed and verified **before** any encryption code exists. Widening is pure DDL, touches no
values, and is safe to run on its own.

### 5.2 Double-encryption during the data migration

The backfill migration must **not** go through Eloquent. If the model already declares the
`encrypted` cast, `Model::update()` encrypts a value the migration just encrypted by hand, and the
first read afterwards returns ciphertext-as-plaintext.

**Mitigation:** the data migration uses `DB::table(...)` exclusively, bypassing every cast. And it
is written to be **idempotent** — for each value, try `Crypt::decryptString()` first; if it succeeds,
the row is already encrypted and is skipped. That makes a half-finished run (a timeout, a killed
SSH session) safe to simply re-run, which is the difference between an inconvenience and a disaster.

```php
foreach (DB::table('tasks')->select('id', 'title', 'notes')->orderBy('id')->cursor() as $row) {
    $update = [];
    foreach (['title', 'notes'] as $col) {
        if ($row->$col === null || $row->$col === '') continue;
        try { Crypt::decryptString($row->$col); continue; }   // already encrypted → skip
        catch (DecryptException) { $update[$col] = Crypt::encryptString($row->$col); }
    }
    if ($update) DB::table('tasks')->where('id', $row->id)->update($update);
}
```

`down()` does the mirror image (decrypt, same try/catch guard), so a rollback is non-destructive
too — unlike most data migrations, this one genuinely can be reversed.

### 5.3 `APP_KEY` becomes irreplaceable

Today, losing `APP_KEY` breaks sessions and cookies — annoying, recoverable, nobody notices past
one re-login. **After this ships, losing `APP_KEY` means every task title, every note, and the
entire class agenda is unrecoverable ciphertext forever.** No backup of the database helps; the
backup is encrypted with the same lost key.

**Mitigation, all three:**
1. Back up `APP_KEY` somewhere that is *not* the production server and *not* the database backup —
   a password manager entry. Do this **before** running the data migration, not after.
2. Verify the database backup and the key are never stored in the same place. A backup that
   includes `.env` alongside the dump gives an attacker both halves and defeats the entire point.
3. Never regenerate it. `php artisan key:generate` on a configured production box must become a
   never-do. Rotation, if ever needed, goes through `APP_PREVIOUS_KEYS` (already wired up in
   `config/app.php:104`): set the new key, list the old one in `APP_PREVIOUS_KEYS`, then run a
   re-encrypt migration, then drop the old key. Decrypting with a previous key works automatically;
   *encrypting* always uses the current one.

### 5.4 Deploy runbook

Two deploys, deliberately. Do not collapse them into one.

**Deploy 1 — widen columns (no behaviour change, fully reversible)**
1. `php artisan down`
2. Full DB snapshot (`mysqldump` / copy the SQLite file). Keep it.
3. `git pull && composer install --no-dev -o`
4. `php artisan migrate --force` (the widening migration only)
5. `npm ci && npm run build`, caches, `php artisan up`
6. Use the app normally for a day. Nothing should look different at all.

**Deploy 2 — encrypt**
1. **Back up `APP_KEY` off-server first.** Confirm you can read it back.
2. `php artisan down`
3. Full DB snapshot again — *this* is the one you would restore from.
4. `git pull && composer install --no-dev -o`
5. `php artisan migrate --force` (casts ship in the same release as the backfill)
6. Spot-check before opening it up: `php artisan tinker` → `Task::first()->title` reads
   plaintext, and `DB::table('tasks')->first()->title` is ciphertext. Both must be true. If the
   first is ciphertext, the cast is missing; if the second is plaintext, the backfill did not run.
7. `npm ci && npm run build`, caches, `php artisan up`

**Rollback at any point:** restore the snapshot from step 3 and `git checkout` the previous tag.
The widened `TEXT` columns from Deploy 1 are harmless to leave in place.

> One caveat worth knowing: after Deploy 2, an older application version can no longer read the
> database. Rolling back the *code* alone is not enough — the data must be rolled back with it, or
> the migration reversed with `migrate:rollback` (which does decrypt properly, see §5.2).

---

## 6. Work breakdown

| Step | What | Risk |
|---|---|---|
| 1 | Migration: widen ~15 columns to `TEXT` | none |
| 2 | Add `'encrypted'` casts to 10 models | none |
| 3 | Rewrite the 3 query sites in §4 | low |
| 4 | Idempotent backfill migration + its `down()` | **the real one** — see §5.2 |
| 5 | Fix ~62 test assertions | tedious, not risky |
| 6 | CLAUDE.md §9 deployment section + §10 known-issue entry | none |

**Step 5 is the bulk of the mechanical work.** 62 `assertDatabaseHas([... 'title' => 'X'])`-shaped
assertions across the 76 test files can never match ciphertext again. Each becomes a read through
the model instead (`$this->assertSame('X', $task->fresh()->title)`). Worth adding one shared
`assertEncryptedDatabaseHas()` helper rather than hand-editing each — and at least one test should
assert the *raw* column is unreadable, so a future change that quietly drops a cast fails loudly
instead of silently shipping plaintext.

**Estimate: roughly one focused session** for steps 1–5, plus the two staged deploys. There is no
architectural difficulty here — the difficulty is entirely in §5, and it is all avoidable by
following the order above.

---

## 7. What is explicitly not in this plan

- **Encrypting dates or any queryable column.** Would require rewriting board ordering, `WorkPlanner`
  scoring, `ProgressStats` streak/heatmap aggregation and the deadline strip to filter in PHP, and
  would break `whereDate()` equality entirely (see CLAUDE.md §10). Large cost, little gain — a
  leaked date column without its title says very little.
- **End-to-end / zero-knowledge encryption.** Incompatible with the shared class Agenda and with
  every server-side feature that reads content (push notification bodies, the five scheduled
  reminder commands, `TaskSuggestor`). See §2.
- **Searching encrypted fields in SQL.** Possible via a blind index (a deterministic HMAC of a
  normalised value in a sidecar column) if exact-match search on `subject` ever needs to scale.
  Unnecessary at this data volume; the PHP-side filter in §4 is simpler and exact.
- **Encrypting `users.email`.** Needed for login lookups; a deterministic-encryption scheme for it
  is a separate discussion.
