# Wochenrückblick

`/app/review` (`route('review')`, linked from the Fortschritt page): one local week, Monday to Sunday, current
week by default, paged with ‹ ›. Never pages into the future.

- **One honest sentence** at the top, weighed against the user's *own* previous weeks — never against an
  arbitrary target: "Über deinem Schnitt — sonst waren es etwa 10." / "Ruhiger als sonst — üblich sind etwa 10."
  (±15 % counts as "ziemlich genau dein Schnitt") / "Deine stärkste Woche bisher." / "Deine erste Woche mit
  erledigten Aufgaben.". The comparison averages the `WeekReview::COMPARE_WEEKS` (4) weeks before it.
  **A running week is never judged** — Wednesday's total is not a full week's — it is only reported ("Bisher 4
  erledigt — dein Schnitt der letzten Wochen liegt bei 11."). A quiet week is called quiet, not bad.
- **Bars** per day: pure CSS heights from `ProgressStats::completedCountsByDay()` — the same counts the
  Fortschritt heatmap reads, so the two can never disagree. Today is ringed; `role="img"` + a spoken summary.
- Numbers: erledigt · aktive Tage / 7 · stärkster Tag.
- **Noch offen diese Woche / Liegengeblieben**: active board tasks whose effective date (hard deadline, else
  soft due date) falls inside the week — what slipped (or is still to come). Project tasks are excluded.
- **Geschafft**: the finished titles grouped by day (capped at `TITLE_LIMIT` = 40, with "… und N weitere"; the
  total is always exact).
- All day/week bucketing is by the user's *local* day: `completed_at` (UTC) is shifted by the offset **at that
  instant** (DST-auto users), same rule as `ProgressStats`.
- Belongs to the **Fortschritt** module: hiding that module hides the page (`WeekReviewPage::mount()`
  redirects to the landing page) — no stale link stays reachable.

Not built (ideas): a Sunday-evening push ("Dein Wochenrückblick ist da"), a one-line reflection field saved per
week, carrying open tasks forward with one tap (the Überfällig-Rettung banner already covers soft dates), month
view, a palette entry.

Verification: `tests/Feature/WeekReviewTest.php` (17 tests). No live browser pass (Chrome extension was
unresponsive) — an eyeball check of the bar chart (heights on a narrow phone) is still owed.
