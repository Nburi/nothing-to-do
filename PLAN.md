# PLAN.md — Fortschritt & Motivation

> Nacht-Session, autonom umgesetzt (User schläft). Nicht mergen/pushen — Branch bleibt liegen zur
> morgigen Durchsicht. Schritt 4 ("mit User entscheiden") ist laut Sonderanweisung durch ein
> Subagent-Review ersetzt.

## Anforderungen (Schritt 1)

**Funktionalität**
- Tagesüberblick: wie viele Aufgaben heute erledigt wurden, im Verhältnis zu einem Tagesziel; eine
  "Serie" (Streak) von Tagen mit mindestens einer erledigten Aufgabe; ein Rückblick über mehrere
  Wochen (Heatmap), der sich wie ein Log/Fortschrittsverlauf liest.
- Eine Motivations-Mechanik, die tatsächlich etwas zeigt/tut (Animation), nicht nur einen Text —
  ausgelöst an echten Meilensteinen (Tagesziel erreicht, neuer Bestwert), nicht bei jeder einzelnen
  Aufgabe (sonst nutzt es sich ab).
- Push-Erinnerung, falls abends noch offene "Heute"-Aufgaben liegen, plus eine zweite, falls die
  Serie sonst reissen würde.

**GUI/Design**
- Fügt sich in die bestehende Topografie-Bildsprache ein (Kartenfarben, handgezeichnete SVG-Icons,
  keine Emojis, keine neue visuelle Sprache). Bleibt ruhig — keine ständig sichtbare grosse Kachel
  auf dem Dashboard, die mit den schon existierenden Strips (Zeitplan/Notfall/Vorbereiten/
  Hausaufgaben) konkurriert.
- Ausdrücklich **keine XP/Level-Anzeige**. Die "Spiel"-Analogie kommt aus Streak + Heatmap
  (bekanntes, ruhiges Muster — Duolingo/GitHub-artig), nicht aus Punkten.

**Navigation**
- Eigene Seite `/app/progress`, erreichbar über das bestehende "Mehr"-Menü (wie
  Vorbereiten/Zeitplan/Agenda/Bastelideen/Notfall). Zusätzlich ein kleiner, nur bei Streak ≥ 1
  sichtbarer Flame-Badge im Header — ambient sichtbar ohne Klick, kostet aber keinen Platz, wenn
  noch keine Serie besteht.

**Daten/Persistenz**
- Baut auf dem vorhandenen `tasks.completed_at` auf — keine neue Buchführungstabelle, kein
  Doppel-Tracking. Neue `users`-Spalten nur für Einstellungen (Tagesziel, Erinnerungs-Toggles/-Zeit,
  Dedup-Datumsfelder für die Erinnerungen).

## Produkt (Schritt 2)

### Kernidee
Die App zählt bereits jede erledigte Aufgabe (`tasks.completed_at`). Neu ist nur, das *sichtbar* zu
machen — als Tagesrückblick, als Serie, als Verlauf — und an zwei echten Meilensteinen etwas
passieren zu lassen, das sich nach einem Erfolg anfühlt, ohne ins Kindische zu kippen (keine
Konfetti-Kanone bei jedem Häkchen, kein Punktezähler).

### 1. Header-Badge (immer sichtbar, kein Klick nötig)
Ein kleiner Flame-Chip (handgezeichnetes SVG, kein Emoji) mit der aktuellen Serienlänge, links vom
"Mehr"-Button. Nur sichtbar ab Serie ≥ 1 — bei 0 nimmt er keinen Platz weg (kein trauriger
Nullzustand). Klick → `/app/progress`. Farbintensität steigt mit der Serie (`ink-faint` → `contour` → `forest-soft` → volles `forest`,
gedeckelt bei Grün — `signal` ist in dieser App die Warnfarbe und bleibt für Dringendes reserviert),
rein visuell, keine Zahl-als-Punktesystem.

```
Header:  [Logo]  [🔥6]  [Mehr ▾]  [+]  [Avatar]
                  ^ neu, nur wenn Serie ≥ 1
```
(🔥 hier nur als Platzhalter für die spätere SVG-Flamme — im echten UI kein Emoji.)

### 2. `/app/progress` — die eigentliche Seite
```
┌──────────────────────────────────────────────┐
│ ← Fortschritt                                 │
│                                                │
│  ┌────────────┐ ┌────────────┐ ┌────────────┐ │
│  │  ◔  4 / 5   │ │  ▲  6 Tage │ │  128        │ │
│  │ Heute       │ │ Serie      │ │ Insgesamt   │ │
│  │             │ │ Bestwert 11│ │ erledigt    │ │
│  └────────────┘ └────────────┘ └────────────┘ │
│                                                │
│  Letzte 12 Wochen                             │
│  [ 7×12 Heatmap-Grid, Grün-Intensität ]        │
│                                                │
│  Bestwert: 11 Aufgaben an einem Tag           │
└──────────────────────────────────────────────┘
```
- Drei Statkacheln: Ring "heute vs. Ziel", Serie (aktuell + Bestwert), Gesamtzahl aller Zeiten (klein/
  sekundär gehalten, siehe Entscheidung).
- Heatmap: 12 Wochen × 7 Tage, CSS-Grid, 5 Deckkraftstufen von `forest`, **relativ zum eingestellten
  Tagesziel** gestuft (0 / <66% / <100% / =Ziel / >Ziel), nicht an festen absoluten Zahlen — sonst
  läuft sie bei niedrigem Ziel immer voll, bei hohem Ziel immer leer. Gleiche Logik wie ein
  GitHub-Contribution-Graph, aber nur diese eine, ruhige Grüntonleiter.
- Kein Bearbeiten des Tagesziels auf dieser Seite — das lebt zentral in Settings, wie jede andere
  Einstellung auch.

### 3. Die Motivations-Mechanik ("etwas passiert")
Ein Overlay (in `layouts/app.blade.php`, einmal global gemountet, wirkt egal auf welcher Seite eine
Aufgabe abgehakt wird) zeigt bei zwei Ereignissen eine kurze (~1,6s), nicht blockierende Animation:
topografische Ringe (konzentrische SVG-Kreise in Kartenfarben) plus ein paar auseinanderfliegende
Strich-Partikel (wie Höhenlinien-Marken), dazu ein kurzes Textlabel. Kein Sound in dieser Version
(Autoplay-Policy-Risiko, schwer im Headless-Browser zu verifizieren — bewusst rausgelassen, siehe
Abschlussbericht).

Ausgelöst nur bei:
- **Tagesziel erreicht** — der Moment, in dem die heutige Erledigt-Zahl exakt das eingestellte Ziel
  erreicht.
- **Neuer Bestwert** — der Moment, in dem die heutige Zahl den bisherigen Allzeit-Tagesrekord
  übertrifft. Wenn beides gleichzeitig zutrifft, gewinnt der Bestwert (das seltenere, grössere
  Ereignis) — kein doppeltes Feiern für dieselbe Aktion.

Automatisch respektiert `prefers-reduced-motion` (bereits global im CSS auf 0 kollabiert).

### 4. Erinnerungen
Neue Settings-Karte "Fortschritt" (eigener Tab, wie Zeitplan/Vorbereitung/Benachrichtigungen):
- Tagesziel-Zahl (1–30, Default 5).
- Toggle "Offene Aufgaben am Abend" + Uhrzeit-Feld (Default 19:00) — Push, falls dann noch offene
  Heute-Aufgaben liegen.
- Toggle "Serie in Gefahr" — Push um fix 21:00, falls die Serie sonst reissen würde (heute noch
  nichts erledigt, aber gestern schon eine laufende Serie).

## Umsetzung (Schritt 3)

- **Stack**: kein neuer Dependency — reines Laravel/Livewire/Alpine/Tailwind/hand-SVG, exakt wie der
  Rest der App. Kein Skill aus der Liste passt hier besser als das bestehende Projektmuster selbst
  (kein Chart-, Konfetti- oder Notification-Skill einschlägig) — es wird keiner verwendet.
- **Datenbank**: eine Migration `users`: `daily_task_goal` (tinyint, default 5), `notify_daily_reminder`
  (bool, default false), `daily_reminder_time` (string, default '19:00'), `daily_reminder_sent_on`
  (date), `notify_streak_risk` (bool, default false), `streak_risk_sent_on` (date). Analog zu den
  bestehenden `prepare_*`-Spalten. `User::$attributes`-Defaults ergänzen (Fresh-Model-Falle,
  CLAUDE.md §10).
- **`App\Services\ProgressStats`** (stateless, wie `PomodoroCycle`/`TaskSuggestor`), alle Tag-Buckets
  über `$user->localToday()` (Kalendertag, nicht `completedWindowStart()`):
  `completedCountsByDay()`, `todayCount()`, `currentStreak()`, `bestStreak()`, `bestDailyCount(array
  $counts, ?string $excluding = null)`, `heatmap()`, `celebrationFor(User $user, int $beforeCount):
  ?array` (vergleicht `beforeCount`/`beforeCount+1` gegen Ziel & Bestwert-ohne-heute; `null` wenn
  keine Schwelle überschritten wurde). Eine Query pro Aufruf-Kontext (Map einmal holen, weiterreichen),
  kein N+1.
- **`App\Livewire\Progress`** (`/app/progress`, `route('progress')`) — reine Read-Seite, nutzt
  `ProgressStats`.
- **Feiern-Hook** in `ManagesTasks::toggleComplete()` **und** `Schedule::toggleDeadlineTaskDone()`
  (beide echten "Aufgabe abgehakt"-Stellen — die API-Controller lösen bewusst nichts aus, dort gibt
  es keinen Browser, der ein Overlay zeigen könnte). Beide erfassen `$before =
  ProgressStats::todayCount($user)` **vor** dem `$task->update(...)`, rufen danach nur bei
  `$done === true` `ProgressStats::celebrationFor($user, $before)` auf.
- **Neuer Scheduled Command** `app:send-progress-reminders` (Muster: `SendPrepareReminders`),
  registriert in `bootstrap/app.php` neben den anderen vier.
- **Settings**: neuer Tab „Fortschritt", drei neue Methoden (`saveDailyGoal`, Toggle-Paar analog zu
  `toggleNotifyEventStart`, `saveDailyReminderTime` analog zu `savePrepareReminderTime`).
- **Header**: Flame-Badge-Partial, neuer Menüpunkt „Fortschritt" im „Mehr"-Dropdown.
- **Overlay**: Alpine-Store `celebration` in `app.js`, Keyframes in `app.css`, Markup in
  `layouts/app.blade.php`.
- **Reihenfolge**: Migration → Model → Service (+ Unit-Tests) → Progress-Seite (+ Feature-Test) →
  Feiern-Hook + Overlay (+ Browser-Verifikation) → Settings-Karte (+ Test) → Command (+ Test) →
  Gesamt-Testlauf + Durchklicken im Browser-Preview.

## Entscheidung (Schritt 4)

Nur eine sinnvolle Produktrichtung wurde entwickelt (kein A/B) — stattdessen von einem
Plan-Subagenten als zweite Meinung gegenprüfen lassen (User schläft, Sonderanweisung).

**Verdict: proceed with changes.** Produktrichtung und Architektur-Fit wurden bestätigt (richtige
Hook-Punkte, richtiges Service-Muster, korrekte Wiederverwendung von `tasks.completed_at`,
No-XP-Grenze eingehalten). Vier Punkte wurden vor Schritt 5 eingearbeitet:

1. **"Heute"-Grenze explizit festgelegt.** Diese Codebase hat bereits zwei konkurrierende
   Definitionen: `User::completedWindowStart()` (Board-Sichtbarkeit, `task_reset_time`, Default
   01:00) vs. Kalendertag (`localToday()`, von `prepared_on` & Co. genutzt). Entscheidung: Streak/
   Heatmap/Tagesziel zählen strikt nach **`localToday()`** (Kalendertag), analog zu jedem anderen
   "heute"-Feature ausser dem Board selbst. Randfall (akzeptiert, dokumentiert): eine Aufgabe kurz
   nach Mitternacht, aber vor der 01:00-Reset-Zeit abgehakt, zählt auf dem Board noch als "gestriges"
   Sichtbarkeitsfenster, im Streak aber schon als heutiger Tag — zwei unabhängige Konzepte, das ist
   in Ordnung.
2. **Feiern-Erkennung korrigiert.** Statt Aggregate nach dem Schreiben zu vergleichen (löst bei
   jeder weiteren Aufgabe eines Rekordtages erneut aus / kann "gerade eingeholt" nicht von "schon
   länger vorne" unterscheiden), wird der Vorher-Stand (`todayCount()` vor dem Update) explizit an
   `ProgressStats::celebrationFor(User $user, int $beforeCount): ?array` übergeben; die Methode
   vergleicht `beforeCount`/`beforeCount+1` gegen Ziel und Bestwert-ohne-heute. Beide Aufrufstellen
   (`ManagesTasks::toggleComplete()`, `Schedule::toggleDeadlineTaskDone()`) rufen exakt dieselbe
   Methode auf — die Vergleichslogik selbst wird nicht dupliziert, nur der Toggle-Boilerplate (wie
   im Rest der App üblich).
3. **Farbeskalation korrigiert.** `signal` ist in dieser App fest die Warn-/Dringend-Farbe (armed
   delete, überfällig, aktiver Notfallmodus) — als Ziel einer *positiven* Streak-Eskalation invertiert
   das die etablierte Bedeutung. Die Badge-Eskalation deckelt jetzt bei `forest` (Gewicht/Ton
   innerhalb von Grün variiert, keine Farbe danach).
4. **Scope-Trim.** Der separate "Diese Woche"-Balkenabschnitt entfällt — er zeigt dieselben Daten
   wie die letzte Spalte der Heatmap, nur als zweites Diagramm. Falls die Zeit über Nacht knapp wird,
   ist das die Stelle zum Kürzen, nicht Erinnerungen oder Feiern-Mechanik (beides explizit
   gewünscht). `ProgressStats::weeklyTrend()` entfällt aus der Umsetzung.
5. **Heatmap-Stufen relativ zum Tagesziel**, nicht an festen absoluten Zahlen — sonst läuft die
   Heatmap bei niedrigem Ziel immer "voll" und bei hohem Ziel immer "leer".

Die "Insgesamt erledigt"-Kachel bleibt (bewusst klein/sekundär gehalten) — als reine, unveränderliche
Zähl-Statistik ohne Schwellen/Freischaltungen ist sie kein XP-System, auch wenn sie strukturell die
einzige Zahl ist, die nur wächst.
