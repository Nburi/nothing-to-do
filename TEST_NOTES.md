# Testing notes (Mehrtägiger Test)

Sammelliste für Auffälligkeiten beim Testen. Wird gesammelt, später in einem Rutsch umgesetzt und mit **einem** Merge (erst auf Ansage "merge") integriert. Keine PR pro Punkt.

Branch: `claude/stoic-cerf-iv0fzh`

Status: `[ ]` offen · `[x]` umgesetzt

## Offen

- [ ] **1. Bestätigungs-Meldungen blenden nicht aus.** Meldungen wie "x wurde rückgängig gemacht" bleiben stehen. Sie sollen nach kurzer Zeit von selbst verschwinden.
  - Offen: gilt das für alle Bestätigungen/Toasts in der App oder nur für Undo? Beim Umsetzen alle Stellen suchen, die so etwas rendern (ein gemeinsames Verhalten statt Einzelfixes).
- [ ] **2. Hauptinhalt bekommt zu wenig Platz, vor allem mobil.** Auf manchen Seiten belegt der eigentliche Zweck der Seite weniger als die Hälfte des Screens, Beispiel Zeitplan. Regel: der Hauptinhalt muss auf jeder Seite mindestens etwa 3/4 des Screens einnehmen.
  - Offen: Welche Seiten ausser dem Zeitplan betroffen sind, ist noch nicht erfasst. Beim Umsetzen alle Seiten mobil (375 px) durchmessen und den Anteil des Hauptinhalts pro Seite festhalten, bevor etwas geändert wird.
  - Verdacht Zeitplan mobil: Header, Vorschau-/Deadline-Streifen, Template-Zeile und die "Zeichnen:"-Kategoriezeile unten teilen sich den Platz mit dem Raster.

- [ ] **3. Aufgaben lassen sich nicht bearbeiten (Bug, vermutlich hohe Priorität).**
  - Offen, vom Tester noch zu klären: Auf welcher Seite (Board, Projektseite, Gruppe, Planer)? Mobil oder Desktop? Öffnet sich das Bearbeiten-Sheet gar nicht, oder lässt sich nicht speichern? Gilt es für alle Aufgaben oder nur für bestimmte (Projekt, Hausaufgabe, geteilte Aufgabe)? Gibt es eine Fehlermeldung?
  - Beim Umsetzen zuerst reproduzieren, nicht raten. Relevante Stellen: Doppeltipp auf den Titel und der Stift auf der Karte (`startEdit`), `partials/edit-sheet.blade.php`, `ManagesTasks::saveEdit`.

## Umgesetzt

_(noch leer)_
