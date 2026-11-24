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
| A1 | R1 | Battery low returns two quick to-dos, then a shrunk task; mid returns the deadline task first. | `php artisan test --filter=PickEngineTest` | bestanden (Schritt 1.3, 2026-11-08; Verdrahtung in `SuggestionsTest`) |
| A2 | R5 | Hard deadline beats soft; equal dates fall back to the smaller size. | `php artisan test tests/Unit/Planning/StartByOrderTest.php` | bestanden (Engine, Spike 0.2, 2026-11-06; Anbindung an die Modelle in Phase 1) |
| A3 | R4 | A one-off move changes one date only; the template is untouched. | `php artisan test tests/Unit/Planning/WeekExceptionTest.php tests/Feature/Refactor/WeekPlanEditorTest.php` | bestanden (Spike 0.2; Schritt 1.2, 2026-11-08: auf echten Zeilen, inkl. Eigenschaftstest gegen die alten Ansichten) |
| A4 | R4 | A holiday suppresses template events; one day inside can be switched back on. | `php artisan test tests/Unit/Planning/SchedulePauseTest.php tests/Feature/Refactor/WeekPlanEditorTest.php` | bestanden (Spike 0.2; Schritt 1.2, 2026-11-08: auf echten Zeilen, inkl. Eigenschaftstest gegen die alten Ansichten) |
| A5 | R4 | Block and session counts are correct across the October DST change in Europe/Zurich. | `php artisan test tests/Unit/Planning/FreeSlotDstTest.php` | bestanden (Engine, Spike 0.2, 2026-11-06; Anbindung an die Modelle in Phase 1) |
| A6 | R5, R10 | Capacity = 25 min sessions in pomodoro blocks only; load above capacity flags the day. | `php artisan test tests/Unit/Planning/LoadForecastTest.php tests/Unit/Planning/LoadPlannerTest.php tests/Unit/Planning/EarlyWarningTest.php` | bestanden (Spike 0.2; Schritt 1.4, 2026-11-08: Prognose pro Tag, Frühwarnung mit Hysterese, an den Wochenplan angebunden in `CrunchTest`) |
| A7 | R5 | Soft deadline: first miss rolls over, second miss moves the item to Later. | `php artisan test --filter=SoftSlipTest` | bestanden (Schritt 1.4, 2026-11-08: Regel und echte Zeilen) |
| A8 | R3 | Capture saves a 400 character title, rejects an empty one with a German message, refuses a token without the capture ability. | `php artisan test --filter=CaptureTest` | bestanden (Schritt 1.5, 2026-11-08) |
| A9 | R3 | Sorting moves an item to To-do, Task or Later and applies the default size. | `php artisan test --filter=InboxSortTest` | bestanden (Schritt 2.3, 2026-11-09: To-do, Task, Später; fehlende Grösse wird Short, Später ohne Grösse und ohne harten Termin; deutsche Meldungen pro Feld; nur eigene Inbox; Rückgängig; die Sortierkarte mit Überspringen) |
| A19 | R9 | Outside pomodoro blocks the idle board offers no work suggestion; the next block's start time is shown instead; a manual start still works. | `php artisan test --filter=NoSuggestionOutsideBlocksTest` | bestanden (Schritt 1.3, 2026-11-08) |
| A20 | R7 | Every theme, light and dark: all text colours >= 4.5:1 on the backgrounds they sit on; Topografie is the default. | `php artisan test tests/Unit/ThemeContrastTest.php tests/Feature/Refactor/ThemeSettingTest.php` | bestanden (Schritt 2.1, 2026-11-09: 14 Kombinationen; Textfarben der Plan-Paletten minimal angepasst, siehe docs/steps/2.1.md) |
| A10 | R6 | Ideas are offered only in free time outside pomodoro blocks and when deadline work is covered; a "mine" slot offers nothing. | `php artisan test --filter=IdeaSuggestionTest` | offen |
| A11 | R7 | Every action in the registry has a desktop and a mobile entry point, in board and session mode. | `php artisan test --filter=ActionParityTest` | bestanden (Schritt 2.2, 2026-11-09: start, Anderes/kleiner, Akku, Später ansehen in Werkbank und Stapel, Board- und Session-Modus; seit Schritt 2.3 auch Erfassen und Sortieren; der Rest mit seinem Schritt) |
| A12 | R8 | Old-schema fixtures migrate with equal row counts; deadline becomes hard, due date becomes soft; rollback restores. | `php artisan test --filter=MigrationRehearsalTest` | offen |
| A13 | R2 | The nudge is sent once per evening, not after the reset is done, not on paused days. | `php artisan test --filter=NudgeSchedulerTest` | offen |
| A14 | all | No endpoint returns another user's items. | `php artisan test --filter=AuthorizationTest` | bestanden für alle neuen Endpunkte und MCP-Werkzeuge (Schritt 1.5, 2026-11-08); die UI-Seiten folgen mit Phase 2 |
| A15 | R1 | A started session is stored on the server; a reload or the other device shows the focus view; Done returns the board with the next suggestion; Done twice is harmless. | `php artisan test --filter=SessionModeTest` | bestanden (Schritt 1.7 + 1.3, 2026-11-08: Speicherung, zweites Gerät, Done idempotent, Fremd-Tasks abgelehnt, Done liefert die nächste Empfehlung) |
| A16 | R9 | 25 / 5 cycle, 15 min after every fourth session (counter resets daily); an unfinished task carries over to the next session. | `php artisan test tests/Unit/Planning/PomodoroCycleTest.php` | bestanden (Engine, Spike 0.2, 2026-11-06; Anbindung an die Modelle in Phase 1) |
| A17 | R9 | Done with ≥ 5 min left starts the next task in the same session; with < 5 min left the break starts at once and the next session is a fresh 25 min. | `php artisan test tests/Unit/Planning/SessionEarlyFinishTest.php` | bestanden (Engine, Spike 0.2, 2026-11-06; Anbindung an die Modelle in Phase 1) |
| A18 | R10 | Categories and areas are user-defined; a category only suggests its linked areas; deleting an area unlinks it without losing tasks. | `php artisan test --filter=CategoryAreaLinkTest` | bestanden (Datenebene, Schritt 1.6, 2026-11-07; Vorschläge pro Block folgen mit 1.3) |

## B. Claude prüft visuell

Rule (Niels, 2026-11-06): Claude tries a real browser check **once or twice**. If no usable
screenshot comes out, Claude **stops and tells Niels**, and the affected rows move to section C.

| Nr | Anforderung | Was gesehen werden muss | Wie | Ergebnis |
|---|---|---|---|---|
| B1 | R1–R6 | Empty states of board, week, inbox and Later look finished, not blank. | Screenshot with a fresh account | offen |
| B2 | R3 | A 400 character title and umlauts do not clip in the suggestion card, inbox row, pile card and week block. | Screenshots at 390 and 1280 px | teilweise (Schritt 2.2, 2026-11-09): ein 150-Zeichen-Titel mit Umlauten bricht in Inbox-Zeile (1280, 760) und Liste (390) sauber um, der Stapel kürzt ihn auf zwei Zeilen mit Auslassung; bei 760 px brachen Wörter mitten im Wort, behoben (2 Spalten bis 1024 px). Wochenblock folgt mit 4.1. |
| B3 | R7 | No horizontal scroll at 390 px; every registry action is reachable in Werkbank and Stapel. | Walk the parity table | teilweise (Schritt 2.2, 2026-11-09): kein seitliches Scrollen bei 390 px; im Browser durchgespielt: Start (Karte und Liste), Anderes, Akku, Stapel antippen, Session mit Timer, Peek über Schiene und Schublade, Fertig → nächste Aufgabe in derselben Session, Session beenden → Board mit „1 erledigt heute“. Schritt 2.3: Erfassen mit C (1280) und + (390, Blatt über der Tastatur), Rückgängig, Sortierkarte mit 1/2/3 und Enter (1280) und als Blatt (390), Kurzbefehl-Token in den Einstellungen (390), kein seitliches Scrollen. Screens `docs/screens/2.2-*`. Sortier-Stapel auf dem Handy (Option A, 390 px): Karte folgt dem Finger 1:1, schrumpft über einem Stapel, landet nach dem Loslassen mit der gewählten Grösse (Tasks · Long), springt ohne Ziel zurück, Antippen von Später sortiert ebenfalls. Screens `docs/screens/2.3-deck-*`. |
| B4 | Themes | Each theme is readable in light and dark; Topografie is the default for a new account. | Screenshots | teilweise (Schritt 2.1, 2026-11-09): Themen-Karte in den Einstellungen bei 1280 und 390 px, Wechsel auf Kiosk dunkel ohne Reload färbt die ganze (alte) Seite um, Topografie hell ist Standard; alte Board- und Zeitplan-Ansicht unter Tailwind 4 unverändert. Screens `docs/screens/2.1-*`. Alle sieben im neuen Shell folgen mit 2.2. |
| B5 | Signature | "The board folds away" plays on Start in both layouts; under reduced motion it becomes an instant, readable change. | Live check + emulated reduced motion | bestanden mit einer offenen Stelle (Schritt 3.1, 2026-11-11, Playwright): Werkbank 1280: Spalten falten nacheinander weg, die Karte bleibt stehen und fliegt in die Mitte, Session steht nach ~520 ms, Position danach stabil; „Session beenden“ faltet das Board wieder auf, mit dem nächsten Vorschlag. Stapel 390: Stapel rutschen weg, Karte wächst von 234 auf 430 px. Reduzierte Bewegung: kein Kartenflug, Spalten sofort weg. **Offen:** im Stapel-Layout in einem schmalen *Desktop*-Fenster springt die Karte nach dem Falten um 20 px (Scrollbalken für ~400 ms), auf Handys nicht möglich; zwei Fixversuche zurückgenommen, siehe docs/steps/3.1.md. Screens `docs/screens/3.1-*`. |
| B6 | R4 | A holiday week shows paused blocks as ghosts that can be restored. | Screenshot of the week | offen |
| B7 | R5 | The crunch screen with six items fits at 390 px with all four decisions visible. | Screenshot | offen |
| B8 | R3 | Capture on a slow connection shows a saving state and a calm retry message. | Throttled network | bestanden (Schritt 2.3, 2026-11-09): Anfrage im Browser 1,5 s verzögert und dann abgebrochen: „Speichere …“ im Feld, danach „Gerade keine Verbindung. Dein Text bleibt im Feld.“ mit „Nochmal“, der Text bleibt stehen. Screens `docs/screens/2.3-capture-saving-1280.png`, `2.3-capture-offline-1280.png`. |
| B9 | R9 | Timer, break screen and "next task inside this session" render correctly on both layouts. | Live check with a shortened test cycle | offen |
| B10 | R2, G4 | The reset is a calm full screen (board hidden), its countdown survives a reload and hands over to the battery question; the board comes back with the chosen battery. | Live check at 1280 and 390 px, reduced motion | bestanden (Schritt 3.2, 2026-11-11, Playwright): ruhiger Bildschirm deckt das Board ganz ab, Los fokussiert; Countdown läuft und übersteht einen Reload (09:46); bei 0 wechselt er von selbst zur Akku-Frage; Später → Board mit „Akku: Okay“, Leer → „Akku: Leer“; „Abschalten“ neben dem Akku-Chip startet sofort. 390 px ohne seitliches Scrollen. Gefunden und behoben: der Timer rutschte bei Los 16 px nach unten. Reduzierte Bewegung: kein Einblenden, Linie springt. Screens `docs/screens/3.2-*`. |

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
| C12 | MUSS | R2 | For a week, let the reset open when you come home (or tap „Abschalten“). Does it feel like ten minutes for you, or like a chore before work? Is 17:00 a sensible „from“ time until the 3.3 nudge exists? | offen (Schritt 3.2) |
