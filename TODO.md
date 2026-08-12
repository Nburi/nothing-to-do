# TODO

Open tasks and known follow-ups between sessions. Anything already described in `CLAUDE.md` as built is
done; this file is only for what is still outstanding.

## Follow-ups

### Drop `agenda_entries.is_done` (blocked on a production deploy)

Shipped in the shared class agenda (branch `feature/agenda-class-spaces`, 2026-08-11): "done" moved from
`agenda_entries.is_done` to the `agenda_entry_completions` pivot, because a class entry is ticked off per
person. The migration backfills the column into the new table and nothing reads it any more — but the
column itself was deliberately left in place as a rollback point (CLAUDE.md §8: data-losing changes ship in
two steps).

**Do this only after the completions migration has run in production and the agenda has been used for a
few days:**

1. New migration dropping `agenda_entries.is_done`.
2. Remove the note from `AgendaEntry`'s `casts()` docblock and the migration comment that points here.
3. Remove this entry.

If anything about per-person completion turns out wrong before then, rolling back is still lossless — the
old column still holds the pre-migration state.

### Local dev DB still carries a dead `tasks.task_group_id` column

Left over from the deleted first task-groups attempt (2026-07-31). Its migration file is gone with the
branch, so `migrate:rollback` cannot remove it, and SQLite refuses `ALTER TABLE … DROP COLUMN` for a column
that appears in a foreign-key definition — dropping it needs a full table rebuild, which is not worth
risking on a database with real tasks in it. The orphaned `task_groups` table, the `start_hint` column and
both stale `migrations` rows were removed; nothing in the code reads `task_group_id`.

**Production never had any of it** (those branches were never merged or pushed), so there is nothing to
deploy. Clean it up whenever the local database is next rebuilt from scratch.

## Ideas, not committed

- **Task groups in the API (Sanctum).** `tasks.group_id` is invisible to Shortcuts: a task cannot be filed
  into a group or read back with its group over the API, and there is no groups endpoint. Worth doing with
  the same care as the Agenda endpoints below rather than bolting on one field.
- **A markdown-notes partial shared by projects and groups.** `partials/group-notes.blade.php` and the
  brainstorm panel in `project-page.blade.php` are now two implementations of the same editor (toolbar,
  autosize, autosave, read/edit toggle). Worth folding into one parameterised partial — but as its own
  refactor, not smuggled into a feature branch.

- **Agenda API endpoints (Sanctum).** The Agenda is the only feature with no REST surface, so Apple
  Shortcuts can't reach homework at all. Shared spaces make this more interesting (a Shortcut that files
  homework for the whole class), but also raise the authorization surface — worth doing deliberately, not
  as an afterthought.
- **Push notification for a new class entry.** "Lena hat eine Hausaufgabe für morgen eingetragen" is the
  obvious next step now that entries can arrive from other people. Needs a rate-limit thought first: 22
  people writing into one class could get noisy fast.
- **Presence on the entries themselves**, not just in the member list — "Lena schaut sich das gerade an"
  next to a shared entry. Technically almost free (the heartbeat already exists), but hold it until the
  member-list version has been lived with for a while: on a homework list this could read as surveillance
  rather than help, and the whole product goal is "speed and calm".
