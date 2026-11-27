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
  - Bestätigt: Desktop, Board. Andere Seiten und Mobil sind ungeprüft.
  - Offen, vom Tester noch zu klären: Öffnet sich das Bearbeiten-Sheet gar nicht, oder lässt sich nicht speichern? Gilt es für alle Aufgaben oder nur für bestimmte (Projekt, Hausaufgabe, geteilte Aufgabe)? Gibt es eine Fehlermeldung?
  - Beim Umsetzen zuerst reproduzieren, nicht raten. Relevante Stellen: Doppeltipp auf den Titel und der Stift auf der Karte (`startEdit`), `partials/edit-sheet.blade.php`, `ManagesTasks::saveEdit`.

- [ ] **4. Mobil: Swipe in der Inbox (Wunsch / evtl. Bug).** Der Tester will das Swipen mobil "wieder einführen", um eine einzelne Inbox-Aufgabe zu sortieren. Eine Richtung soll "Bearbeiten" sein, die andere ist noch offen.
  - Befund im Code: Swipe gibt es noch. `task-card-mobile.blade.php` setzt in der Inbox rechts = To-Dos, links = Tasks. Ausserhalb der Inbox ist rechts = Heute/aus Heute, links = Bearbeiten. Das Ziel "wieder einführen" stimmt also nur, wenn das Swipen in der Praxis nicht funktioniert oder in der gewählten Ansicht nicht angeboten wird.
  - Offen: In welchem Listen-Konzept wurde getestet (3 Dinge hat eine Inbox, Simple/Kanban nicht)? Was passiert beim Swipen genau (nichts, falsche Aktion)?
  - Designkonflikt: Sortieren in der Inbox ist eine Zweier-Entscheidung (To-Do oder Task) und braucht beide Richtungen. Wird eine Richtung "Bearbeiten", bleibt nur eine zum Sortieren. Bearbeiten ist schon per Doppeltipp auf den Titel erreichbar. Zu entscheiden: Richtung 1 = To-Do, Richtung 2 = Task (jetziger Stand), oder Richtung 1 = Bearbeiten, Richtung 2 = ein Sortierziel (der andere über das Bearbeiten-Sheet).

## Umgesetzt

_(noch leer)_
