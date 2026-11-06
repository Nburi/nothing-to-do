# Conventions

Binding interaction and style rules for every feature of the refactor. They come from reading the
old code as a spec (feature-schmiede taste inventory, 2026-11-06) plus Niels' decisions. A plan that
breaks one must say so and why.

## Layout and modes
1. **Board while idle, focus during a session.** Desktop uses the Werkbank layout, mobile the Stapel
   layout ([docs/REFACTOR_PLAN.md § 1](docs/REFACTOR_PLAN.md#1-decisions-2026-11-06)).
2. **Same features on every device.** Every action is registered once and needs an entry point in
   both layouts and both modes (test A11). Layouts may differ completely, features never.
3. **One suggestion, with a way to shuffle.** Show one suggested thing and an "Another" button, not
   a long list of options (as the old Bastelideen "Mach doch das" hero did). Every suggestion says
   where to start ("Start with: …").
4. **"Do it now" is always the primary button, "Keep it mine" the second.** Button order never
   changes with mood or battery.

## Interaction
5. **Capture is one global, keyboard-first line** (key C on desktop, + / Action Button on mobile).
   One text field; optional fields fold away. Never a page of its own.
6. **Editing reuses the creation form in place.** No separate edit dialogs.
7. **Direct manipulation over dialogs.** Drag and drop on desktop (SortableJS); swipe on mobile with
   1:1 finger tracking, a coloured action surface behind the card, threshold about 40 % of the card
   width. No native `alert()`, `confirm()` or `prompt()`; deletes are undoable instead of confirmed.
8. **Exceptions belong to one occurrence, never the series.** "This week only" edits one date;
   "Every week" edits the template; pause days are per date.

## Look
9. **Semantic colour tokens as CSS variables**, one radius scale, hairlines, tinted shadows, tabular
   figures. Themes change token values (and fonts, corners, outlines), never the token names.
   Topografie is the default theme. Dark mode is an equal, not an afterthought.
10. **The signature moment "The board folds away" is taboo for cleanup.** It is never "made
    consistent" or optimised away. Under reduced motion it becomes an instant, readable change.

## Product tone
11. **Quiet product:** no streaks, badges or points; at most one small celebration a day.
12. **Suggest, never decide.** Free time is not filled automatically; ideas are offered only outside
    pomodoro blocks.
13. **Copy:** German default (informal "du"), English available, both from `lang/` files. Short,
    plain and a little dry. Errors say what happened and what to do; never a stack trace.
14. **Generic core:** nothing in the core knows about a specific hobby, school or job. Categories
    and areas are names the user chooses.
