# Daten-Export

Profil → **Deine Daten → "Daten herunterladen"** (`GET /profile/export`, `route('profile.export')`) hands back
one JSON file, `nothing-to-do-export-YYYY-MM-DD.json`. The account-deletion card always said "sichere vorher,
was du behalten möchtest" without offering a way to; it now points at this card.

- `App\Services\DataExport::for(User)` builds the array. Every read goes through the owner relation, and each
  section is an **allow-list of fields** (`Arr::only`), not a model dump — a column added next year does not
  start leaking into a download nobody re-audited. `Arr::only` also keeps a section working when a column is
  absent on this schema (features land on independent branches; e.g. `repeat_rule`).
- Sections: account, settings, tasks (incl. completed), projects (incl. brainstorm), task_groups (+ note texts),
  agenda_entries (only ones the user wrote — with done state and their own private note), craft_ideas,
  event_categories (+ custom attributes), event_templates, schedule_events, schedule_pauses. `meta.format_version`
  is bumped when the shape changes in a way an importer would care about.
- **Never included** (tested): password hash, remember token, API/Sanctum tokens, push endpoints/keys, other
  people's rows, other people's entries in a shared class agenda, presence timestamps.
- `Cache-Control: no-store, private`; the route is `throttle:6,1` (building the file reads every table the user
  has rows in). A plain `<a download>` — works without JS, no Livewire round trip.
- Agenda uses `withCompletionState()`/`withPrivateNoteFor()` so a few hundred entries stay a handful of queries.

Not built (ideas): re-importing the file (the format is stable enough to try), CSV per section, an "Export
schedule as .ics" for the calendar side, including who else is in your class spaces.

Verification: `tests/Feature/DataExportTest.php` (8 tests). No live browser pass (Chrome extension was
unresponsive).
