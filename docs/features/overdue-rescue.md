# Überfällig-Rettung

A calm banner at the top of the board (all four list concepts — it lives in the layout's `<main>`, gated on
`routeIs('app')`) when tasks have outlived their **soft** date: *"3 Aufgaben warten noch auf ein neues
Datum. Ihr Wunschtermin ist vorbei — das ist kein Drama. Neu legen?"* One tap moves all of them: **Auf heute /
Auf morgen / Auf Montag / Datum entfernen**. "Welche?" lists the first eight (title + old date).

- `App\Livewire\OverdueRescue`. Candidates: active board tasks with **no hard deadline** and a `due_date` before
  the user's *local* today (`whereDate` — the `'date'`-cast trap in Known Issues). Project tasks are excluded
  (they have no board card).
- **A hard `deadline` is never moved.** It is somebody else's date; moving it would be the app lying about the
  world. Missed deadlines are only counted in the banner's second line.
- **Every action re-queries at click time** instead of trusting the last render: the banner can be a few
  seconds stale (completing a task doesn't re-render it; `wire:poll.30s.visible` keeps it honest meanwhile), and
  a stale list must never move a task that was finished or edited since.
- **Undo** (9 s strip, "Rückgängig"): `previousDates` (task id → old date) is a `#[Locked]` property, so the
  client can read but never write it — undo can only restore what the component itself recorded, and skips
  tasks completed in the meantime.
- "✕" hides the banner for the rest of the local day (`session('overdue_rescue_dismissed_on')`), it comes back
  tomorrow if the tasks are still there.
- Moves dispatch `captured` to `TaskBoard` (`->to(TaskBoard::class)`, not globally — the component listens to
  `captured` itself for new captures and would otherwise re-render on its own event).
- Tone: `contour-soft` (this app's "time-bound" tone), never `signal` (reserved for danger/urgency).

Not built (ideas): a "smart" spread (distribute the tasks over the coming days by importance/duration), the
same rescue for hard deadlines with an explicit "Deadline wirklich verschieben" step, showing it on the Planer.

Verification: `tests/Feature/OverdueRescueTest.php` (15 tests). No live browser pass (Chrome extension was
unresponsive) — an eyeball check of the banner's spacing above each list concept is still owed.
