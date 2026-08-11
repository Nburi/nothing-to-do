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

## Ideas, not committed

- **Agenda API endpoints (Sanctum).** The Agenda is the only feature with no REST surface, so Apple
  Shortcuts can't reach homework at all. Shared spaces make this more interesting (a Shortcut that files
  homework for the whole class), but also raise the authorization surface — worth doing deliberately, not
  as an afterthought.
- **Push notification for a new class entry.** "Lena hat eine Hausaufgabe für morgen eingetragen" is the
  obvious next step now that entries can arrive from other people. Needs a rate-limit thought first: 22
  people writing into one class could get noisy fast.
