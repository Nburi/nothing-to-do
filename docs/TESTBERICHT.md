# Testbericht — refactor

Test plan from the feature-schmiede method, written 2026-11-06 before the build. Every row maps to a
requirement in [REQUIREMENTS.md](REQUIREMENTS.md) or to the signature moment. The **Ergebnis** column
stays `offen` until the build; then it becomes `bestanden`, `fehlgeschlagen` or `nicht geprüft` (with
a reason) based on real command output or a real look at the app.

## A. Automatisch

Runs with the existing PHPUnit setup: `php artisan test` (or `composer test`). Nothing new to install.
The engine tests live in `tests/Unit/Planning`; their commands use the file path because the old UI
also has a `PomodoroCycleTest` and a `SchedulePauseTest` (retired with the old screens, risk C11).

| Nr | Anforderung | Prüfung | Befehl | Ergebnis |
|---|---|---|---|---|
| A1 | R1 | Battery low returns two quick to-dos, then a shrunk task; mid returns the deadline task first. | `php artisan test --filter=PickEngineTest` | offen |
| A2 | R5 | Hard deadline beats soft; equal dates fall back to the smaller size. | `php artisan test tests/Unit/Planning/StartByOrderTest.php` | bestanden (Engine, Spike 0.2, 2026-11-06; Anbindung an die Modelle in Phase 1) |
| A3 | R4 | A one-off move changes one date only; the template is untouched. | `php artisan test tests/Unit/Planning/WeekExceptionTest.php` | bestanden (Engine, Spike 0.2, 2026-11-06; Anbindung an die Modelle in Phase 1) |
| A4 | R4 | A holiday suppresses template events; one day inside can be switched back on. | `php artisan test tests/Unit/Planning/SchedulePauseTest.php` | bestanden (Engine, Spike 0.2, 2026-11-06; Anbindung an die Modelle in Phase 1) |
| A5 | R4 | Block and session counts are correct across the October DST change in Europe/Zurich. | `php artisan test tests/Unit/Planning/FreeSlotDstTest.php` | bestanden (Engine, Spike 0.2, 2026-11-06; Anbindung an die Modelle in Phase 1) |
| A6 | R5, R10 | Capacity = 25 min sessions in pomodoro blocks only; load above capacity flags the day. | `php artisan test tests/Unit/Planning/LoadForecastTest.php` | bestanden (Engine, Spike 0.2, 2026-11-06; Anbindung an die Modelle in Phase 1) |
| A7 | R5 | Soft deadline: first miss rolls over, second miss moves the item to Later. | `php artisan test --filter=SoftSlipTest` | offen |
| A8 | R3 | Capture saves a 400 character title, rejects an empty one with a German message, refuses a token without the capture ability. | `php artisan test --filter=CaptureTest` | offen |
| A9 | R3 | Sorting moves an item to To-do, Task or Later and applies the default size. | `php artisan test --filter=InboxSortTest` | offen |
| A19 | R9 | Outside pomodoro blocks the idle board offers no work suggestion; the next block's start time is shown instead; a manual start still works. | `php artisan test --filter=NoSuggestionOutsideBlocksTest` | offen |
| A10 | R6 | Ideas are offered only in free time outside pomodoro blocks and when deadline work is covered; a "mine" slot offers nothing. | `php artisan test --filter=IdeaSuggestionTest` | offen |
| A11 | R7 | Every action in the registry has a desktop and a mobile entry point, in board and session mode. | `php artisan test --filter=ActionParityTest` | offen |
| A12 | R8 | Old-schema fixtures migrate with equal row counts; deadline becomes hard, due date becomes soft; rollback restores. | `php artisan test --filter=MigrationRehearsalTest` | offen |
| A13 | R2 | The nudge is sent once per evening, not after the reset is done, not on paused days. | `php artisan test --filter=NudgeSchedulerTest` | offen |
| A14 | all | No endpoint returns another user's items. | `php artisan test --filter=AuthorizationTest` | offen |
| A15 | R1 | A started session is stored on the server; a reload or the other device shows the focus view; Done returns the board with the next suggestion; Done twice is harmless. | `php artisan test --filter=SessionModeTest` | offen |
| A16 | R9 | 25 / 5 cycle, 15 min after every fourth session (counter resets daily); an unfinished task carries over to the next session. | `php artisan test tests/Unit/Planning/PomodoroCycleTest.php` | bestanden (Engine, Spike 0.2, 2026-11-06; Anbindung an die Modelle in Phase 1) |
| A17 | R9 | Done with ≥ 5 min left starts the next task in the same session; with < 5 min left the break starts at once and the next session is a fresh 25 min. | `php artisan test tests/Unit/Planning/SessionEarlyFinishTest.php` | bestanden (Engine, Spike 0.2, 2026-11-06; Anbindung an die Modelle in Phase 1) |
| A18 | R10 | Categories and areas are user-defined; a category only suggests its linked areas; deleting an area unlinks it without losing tasks. | `php artisan test --filter=CategoryAreaLinkTest` | bestanden (Datenebene, Schritt 1.6, 2026-11-07; Vorschläge pro Block folgen mit 1.3) |

## B. Claude prüft visuell

Rule (Niels, 2026-11-06): Claude tries a real browser check **once or twice**. If no usable
screenshot comes out, Claude **stops and tells Niels**, and the affected rows move to section C.

| Nr | Anforderung | Was gesehen werden muss | Wie | Ergebnis |
|---|---|---|---|---|
| B1 | R1–R6 | Empty states of board, week, inbox and Later look finished, not blank. | Screenshot with a fresh account | offen |
| B2 | R3 | A 400 character title and umlauts do not clip in the suggestion card, inbox row, pile card and week block. | Screenshots at 390 and 1280 px | offen |
| B3 | R7 | No horizontal scroll at 390 px; every registry action is reachable in Werkbank and Stapel. | Walk the parity table | offen |
| B4 | Themes | Each theme is readable in light and dark; Topografie is the default for a new account. | Screenshots | offen |
| B5 | Signature | "The board folds away" plays on Start in both layouts; under reduced motion it becomes an instant, readable change. | Live check + emulated reduced motion | offen |
| B6 | R4 | A holiday week shows paused blocks as ghosts that can be restored. | Screenshot of the week | offen |
| B7 | R5 | The crunch screen with six items fits at 390 px with all four decisions visible. | Screenshot | offen |
| B8 | R3 | Capture on a slow connection shows a saving state and a calm retry message. | Throttled network | offen |
| B9 | R9 | Timer, break screen and "next task inside this session" render correctly on both layouts. | Live check with a shortened test cycle | offen |

## C. Niels prüft

`MUSS` = Claude cannot judge it, or a mistake would be expensive, or it is a feeling. `SOLLTE` = good to know, blocks nothing.

| Nr | Klasse | Anforderung | Was du tust | Ergebnis |
|---|---|---|---|---|
| C1 | MUSS | Signature | Start a session on a tired evening and watch the first second. Does the board folding away feel like relief, or like a jump? | offen |
| C2 | MUSS | R2 | On your iPhone leave and rejoin the home Wi-Fi five times. Does the nudge arrive before you pick up the phone? Setup and evening table: `docs/spikes/0.1-arrival.md`. | offen (Server bereit, Spike 0.1) |
| C3 | MUSS | R8 | Open your own account on a copy of production. Is anything missing or changed? | offen |
| C4 | MUSS | R1 | Use it for two weeks of evenings. Count the evenings you started without scrolling first. | offen |
| C5 | MUSS | R1 | Open it on a low battery evening. Do the two quick to-dos help you get going, or annoy you? | offen |
| C6 | MUSS | R4 | Pause next week's school and work in under a minute. Does the plan look right? | offen |
| C7 | MUSS | R9 | Do three sessions in a row with a mix of short and long tasks. Do the 25 / 5 rules and the < 5 min early break feel right? | offen |
| C8 | MUSS | R10 | Set up your own categories (with and without pomodoro) and link areas. Do the suggestions inside each block make sense? | offen |
| C9 | SOLLTE | R3 | Capture three ideas by voice on the train with one hand. | offen |
| C10 | SOLLTE | R7 | Hand it cold to a friend with a different life. Did they understand it without help? | offen |
| C11 | SOLLTE | R8 | With real old data, find one thing from last month. | offen |
