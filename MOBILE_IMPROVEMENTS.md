# Mobile improvements — findings & plan

Audit date: 2026-09-18, branch `feature/mobile-polish` (off `main` @ `4fee103`, everything relevant already merged —
`git branch --no-merged main` only lists the rejected `planner-grid-view`, `general-audience-relaunch`, `refactor/refactor`).

## How this was audited

The Browser pane (375 × 812, dpr 2, a fresh throw-away account with seeded tasks/project/agenda/schedule) worked
for navigation and DOM inspection, but **screenshots timed out or came back cropped** (the known flakiness, see
memory). So the audit is DOM-driven instead of visual: a small script (`public/_audit.js`, git-excluded) walked each
page and reported (a) anything wider than the viewport, (b) every interactive element with a smallest side < 44px
(< 32px = "tiny"), (c) inputs under 16px font size (iOS Safari zooms the page when such a field gets focus).

Pages covered: onboarding (step 1–2 only — its step transitions don't advance without a painting tab), Board in
"3 Things" and "Kanban", QuickCapture panel, Agenda, Zeitplan, Wochenplan, Planer, Tagesüberblick, Fortschritt,
Vorbereiten, Bastelideen, Settings. **Not** covered visually: anything that needs a real look (spacing rhythm,
colour/contrast, animation feel), the swipe/long-press gestures, dark vs. light theme.

## Findings

### A — Real bugs (layout breaks)
| # | Where | Finding |
|---|---|---|
| A1 | Settings → Benachrichtigungen | The whole page scrolls sideways (`scrollWidth` 391 vs 375): the "Test-Benachrichtigung senden" button is `flex-none` next to a sentence in a `justify-between` row and pushes 16px past the edge. |
| A2 | Planer | The whole page scrolls sideways (`scrollWidth` 520 vs 375, +145px): the Standardaufgaben/“Rest automatisch einplanen” button group is `flex-none flex-wrap`, so it never wraps and drags the header row wider than the screen. |
| A3 | Every form | All text/number/date inputs are 14–15px. iOS Safari zooms the viewport in on focus for anything < 16px and doesn't zoom back out on its own — Settings (10 fields), QuickCapture title, Bastelideen title, task quick-date/duration popovers. |

### B — Touch targets (recommended ≥ 44px, hard floor ~36px)
Systematic — the same few patterns repeat everywhere:
| # | Pattern | Measured | Where |
|---|---|---|---|
| B1 | Header icon buttons (Tagesüberblick, Schnellerfassung, avatar) | 32×32, 48×40 | every page |
| B2 | Header badge (streak/agenda count) | 43×30 | every page |
| B3 | Task-card mobile action row (drag handle, edit, delete) | 28×28 each, `gap-0.5` | Board (all 4 concepts), Project, Group |
| B4 | Task-card checkbox | 22×22 (15×15 on the homework-preview strip, 18×18 Agenda, 12×12 Zeitplan deadline strip) | Board, Agenda, Zeitplan |
| B5 | Task-card chips ("Termin", date, "Dauer", notes) | ~60×21 | Board |
| B6 | Settings toggle switches | 40×24 (×17) | Settings |
| B7 | Settings sticky section nav + pill choices | 32px high | Settings |
| B8 | Colour swatches (category colours) | 24×24 | Settings |
| B9 | Filter chips / segmented controls | 32–34px | Agenda, Kanban tab bar, QuickCapture target chips (26px!) |
| B10 | Prev/next day + "Heute" on Zeitplan/Wochenplan | 32×32 / 44×34 | Zeitplan, Wochenplan |
| B11 | Bare text links ("Zur Agenda", "Zum Board →", "Öffnen →", "Schliessen", "Deadline ändern", "Automatisch erkennen") | 16–20px high | Board, Fortschritt, Tagesüberblick, Vorbereiten, Planer, Settings |
| B12 | Back-arrow in page headers, Vorbereiten "wichtig" star | 32×32 | Settings, Fortschritt, Planer, Vorbereiten |
| B13 | Bastelideen "Idee hinzufügen" button | 28×28 | Bastelideen |

### C — Looked fine / no change needed
- Bottom nav (five 75×63 tabs), quick-capture FAB (56×56, 84px above the bottom edge — clears the nav), Kanban
  tab bar width, Agenda/Zeitplan/Wochenplan/Tagesüberblick/Fortschritt/Vorbereiten/Bastelideen have **no**
  horizontal overflow, no page overflow on the Board in any concept.

## Plan (prioritised by impact / effort)

**Do now** (all pure CSS/markup, low risk):
1. A1, A2 — stop the two page-level horizontal overflows.
2. A3 — one global rule: inputs are 16px below `sm` (still 14px on desktop).
3. B1/B2/B12 — header + page-header icon buttons 40px on mobile.
4. B3/B4/B5 — task card, Agenda entry, homework strip, deadline strip: bigger targets. Where a bigger *visible* control
   would cost real width (checkbox, tiny chips) use an **invisible extended hit area** (`.hit-area`) so the design
   stays as it is; for the action row use 36px buttons with no gap.
5. B6/B8 — Settings toggles + swatches via the same `.hit-area`.
6. B7/B9/B10/B13 — chips, tab bars, day-nav and pill rows get more vertical padding on mobile only.
7. B11 — text links get `.hit-area` (or padding).

**Deliberately deferred** (need a real visual check, or a product decision):
- Reworking the Zeitplan/Wochenplan grid density and the draw-to-create gesture targets (inherently small, drag based).
- Onboarding steps 3–13 (couldn't advance them without a painting tab) and the Planer day-picker sheet visuals.
- `viewport-fit=cover` + safe-area insets for the fixed bottom nav/FAB in PWA standalone mode (needs a real iPhone to judge).
- Removing the redundant edit button on mobile task cards (double-tap and swipe-left already open the editor) —
  would free ~36px for the title, but it's a discoverability trade-off Niels should decide.
- Landscape phone layouts; dark/light contrast review.

## Status
See the checklist below, updated as items land.

- [x] A1 Settings overflow
- [x] A2 Planer overflow
- [x] A3 16px inputs
- [x] B1/B2/B12 header + page-header icons
- [x] B3/B4/B5 task card & checkboxes
- [x] B6/B8 Settings toggles/swatches
- [x] B7/B9/B10/B13 chips, nav, pills
- [x] B11 text links

## Result (verified at 375px after the build)
- Settings and Planer: `documentElement.scrollWidth` 391 / 520 → **375** (no sideways page scroll).
- Settings category-name input: 26px → 293px wide (it now gets its own row on mobile).
- Inputs under 16px on Settings: 10 → **0**.
- Task-card action buttons 28 → 36px; checkboxes keep their look but a `::before` hit area extends the target
  (measured: a 15px homework-strip checkbox now has a 31px active box, `elementFromPoint` 8px outside it hits the button);
  Kanban tab bar 32 → 40px; header icons 32 → 40px; chips/pills 32 → 40px; day-nav arrows 32 → 40px.
- Full suite: 1504 tests green (5 new in `tests/Feature/MobileLayoutTest.php`).

## Convention introduced
`.hit-area` / `.hit-area-sm` (app.css): below `sm`, adds an invisible ±10px / ±6px tap area to a small control.
Use it instead of enlarging a visible control when width is scarce. The DOM audit script reports the *visible* size,
so controls using it still show up as "small" in `_audit.js` output — that is expected.

## Still open / for Niels
- The remaining "small" controls after this pass are deliberate: task-card date/duration chips are 29px high (the
  row is dense and they sit under the title), text links are 16px visible with a ~36px hit area, and the
  Zeitplan/Wochenplan grids were left alone (see "deferred").
- I could not look at any page (screenshots failed in the Browser pane), so **please eyeball the Board card, the
  Settings category row and the header on a real phone** — the numbers say it's fine, but spacing rhythm is unchecked.
- Onboarding step 3–13 and the sheets (edit sheet, Planer day-picker) were not audited.
