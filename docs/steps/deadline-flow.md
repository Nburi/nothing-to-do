# Deadline flow: „Bis wann?", stage target = soft deadline, reset pauses the pomodoro

Branch `feature/deadline-flow`, based on `refactor/refactor` (57b2311). Three decisions Niels took in the review
artifact (d-bis-wann, d-stage-soft, d-reset-pause). One migration (additive), no data moved.

## 1. „Bis wann?" after marking a task „Wichtig" (d-bis-wann)

The open question of step 4.6. After the tap that marks a task „Wichtig", and only if the task has **no date of its
own**, one quiet card asks „Bis wann?" with **Heute · Morgen · Diese Woche · Ohne Datum**. The answer is the *soft*
deadline (a hard one is a commitment the user chooses on purpose). The mark itself is still one tap and never waits for the card.

| Piece | What |
|---|---|
| `App\Services\Planning\DeadlineOffer` | The rules. `open()` decides and stamps, `choices()` gives the dates, `choose()` writes the soft deadline through `TaskMutator`, `undo()` takes it back. |
| `tasks.deadline_asked_at` (migration `2026_11_26_000002`) | **Additive, nullable, no default.** Stamped the moment the question is shown. This is what makes it "never again for the same task": ignored, answered, unmarked and marked again, other device, all the same. Existing rows stay null (nobody was asked). |
| `Board::$deadlineOffer`, `setImportant()`, `chooseDeadline()`, `dismissDeadline()`, `undoDeadline()` | State is the question (`set = null`) or its confirmation (`set = date`), server-written (`#[Locked]`). |
| `shell/deadline-offer.blade.php` | One card for the whole board, whichever list the flag was tapped in: a bottom sheet under 720 px, a small floating card from there (bottom centre). Also during a session (the flag lives in the peek panel / list sheet there). |

**Who is asked:** open task, not in Later (parked on purpose, R6), no hard deadline, no soft deadline, no stage target
date (a step in a dated stage counts as dated), `deadline_asked_at` still null. Marking by REST or MCP (`is_important`)
never asks: there is no screen to ask on.

**The answers:** today, tomorrow, and the **Sunday** of the user's week. „Diese Woche" is left out when it would be the
same day as „Morgen" (Saturday) or „Heute" (Sunday), so no two buttons ever say the same date. Days follow the user's
clock, not the server's.

**Quiet by construction:** not modal, takes no focus, no backdrop, no sound. It goes away by itself after 14 s (9 s for the
confirmation); hover or focus inside it stops the clock. Escape closes it. A screen reader hears it through an
always-present live region. 44 px targets on the phone.

**Undo:** the confirmation says „Weiche Frist: morgen." with „Rückgängig". Undo clears the date only while it is still the
one the answer wrote; if it was changed since, it is left alone and the notice says so. The question does not come
back after an undo.

**Edge cases handled:** the task got a date while the card was open (not overwritten, notice), the task was deleted
(card closes, calm notice), unmarking closes its own card, an unknown answer is ignored, somebody else's task is a
`ModelNotFoundException`, a double tap shows the card once.

## 2. A stage's target date is the soft deadline of its open steps (d-stage-soft)

`project_stages.target_date` and its field in the stage editor and the API already existed (step 4.3), so **no
migration and no new field**. What changed is that the planning engine uses it:

- `Task::planningSoftDeadline()` = the **earlier** of the step's own soft deadline and its stage's target date.
  `Task::toPlannedTask()` uses it, so the pick (deadline tiers), the start-by / feasibility forecast and the crunch overview all see
  it (`PlanningContext` eager-loads the stage's date, no extra query per task).
- **Derived on every read, never written.** The step keeps its own dates (`soft_deadline` stays null in the database),
  the hard deadline is not touched, moving or clearing the stage date moves the steps with it. Own earlier date wins.
- Steps of a *later* stage carry their stage's date too, so the forecast sees the whole roadmap, not just the current stage.
- **Shown quietly on the stage:** the date was already on the stage line („bis 12. Nov."); its tooltip and the stage form
  now say it counts as a soft deadline for the open steps (`projects.stage_date_hint`).

What it deliberately does **not** do: no soft-slip rule for a stage date (a missed stage date keeps its steps „late"
until the user moves the stage date, because the date lives on the stage and not on the steps); the crunch card's „Soft
date a week later" button exists only for a step's *own* soft date, so for a stage-caused crunch the user moves the
stage date; the Sunday review's „deadlines this week" list reads the tasks' own columns and does not list stage dates.

## 3. A hand-started reset pauses the pomodoro (d-reset-pause)

Before: „Abschalten" during running work started the ten minutes, but the 25-minute timer kept running underneath,
so after the reset the work phase could be over (or a break had run out) without the user having worked.

Now: `EveningReset::begin()` asks `FocusSession::pauseForReset()` to hold running **work**. The pause carries its own
end (`SessionState::pauseEndsAt`, JSON in `pomodoro_sessions.state`, so **no migration**): `SessionEngine::at()` ends it by
itself at that instant, however the reset ended, and counts exactly the paused time. Because the state machine is pure
and the end is stored with the pause, reads never write.

| Situation | Result |
|---|---|
| Reset runs out (time up) | The next read resumes at the reset's end: the user gets all the paused time back, nothing is lost and nothing runs on in between. |
| „Schon fertig" / „Überspringen" | `close()` calls `FocusSession::endReset()`: resumes at that moment. |
| User stops the session during the reset (second device) | `stop()` clears the pause with everything else. Idle stays idle. |
| Page reload / second device during the reset | Same server state, same screen. |
| Page opened hours later | The work phase ended at its correct time (pause counted), so the board shows Ready or the break, never a pause for ever. |
| User had paused by hand before the reset | Stays paused (no `pauseEndsAt`): the reset gives back only what it took. |
| Reset started during a break | The break runs on (it is rest anyway); only running work is held. |
| Reset started on an idle board | No session row is paused; nothing changes. |
| Second device taps Resume during the reset | Resumes, the hold is gone. |

## Decisions Niels should check

1. **„Diese Woche" = Sunday.** Alternative: Friday (end of a work week). Your week plan runs Mon–Sun, so Sunday.
2. **The card goes away on its own after 14 s.** The mark and the card never block each other. Say if it should stay until dismissed.
3. **Asked at most once ever, also after an undo** and also if the user never saw it (page left). The stamp is written when the card is shown, not when it is answered.
4. **No offer for a task in a dated stage**, because it already has a (derived) soft date.
5. **A stage date in the past makes its open steps „late"** in the pick and the crunch until the stage date is moved (no slip rule for stage dates). Alternative: ignore past stage dates.
6. **A break is not held during a reset**, only work.

## Checks

Automatic: TESTBERICHT A140 to A145. Visual: B70 to B72, screens `docs/screens/deadline-flow-*`. Waiting for Niels: C130 to C133.
