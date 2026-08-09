# PLAN.md — Bastelideen (craft ideas)

## Produkt

Ein Ort, um "Bastelideen" abzulegen, die man aufhebt, bis einem langweilig ist. Erfassung passiert beiläufig auf
dem Dashboard; die eigentliche Seite ist zum Stöbern/Auswählen da, nicht zum Verwalten einer Liste.

- **Erfassen**: eine eigene, kompakte, einklappbare Schnellerfassungsleiste direkt auf dem Board (Desktop +
  Mobile) — Titel + optionales "Wo anfangen"-Feld. Kein neuer Bottom-Nav-Tab, nur ein Header-Nav-Link
  "Bastelideen" (wie Agenda/Notfall/Zeitplan).
- **Seite `/app/crafts`**: kein geordnete Liste. Oben ein direkter Vorschlag ("Mach doch das") — Titel + (falls
  vorhanden) "Wo anfangen" + Buttons "Erledigt" / "Andere Idee". Darunter alle übrigen offenen Ideen als
  Pinnwand: unterschiedlich grosse, leicht schief stehende Karten, die per Klick zum neuen Vorschlag werden.
  Erledigte Ideen landen ausgeblendet hinter "N erledigt · anzeigen" (reversibel, wie bei der Agenda).
- Design visuell entschieden per interaktivem HTML-Mockup (zwei Optionen: Pinnwand vs. Kartenstapel) —
  User hat sich für **Pinnwand** entschieden.

Entscheidungen aus Rückfragen:
- Erledigte Ideen werden **markiert & ausgeblendet**, nicht gelöscht (reversibel).
- Der Vorschlag ist **zufällig, meidet aber die zuletzt gezeigte Idee** (fühlt sich weniger festgefahren an).
- Erfassung sitzt in einer **eigenen kleinen Leiste direkt auf dem Board**, nicht hinter einem Popover-Button.

## Umsetzung

- Stack: Laravel/Livewire, folgt exakt dem Agenda-Feature als Vorbild (eigenständiges, standalone Modell ohne
  FK zu Task/Project — kein Bezug zu den anderen drei Listen).
- Kein bestehender Skill einschlägig; Umsetzung folgt den etablierten Projekt-Konventionen (siehe CLAUDE.md §7
  Agenda-Abschnitt als engste Vorlage).
- Reihenfolge:
  1. Migration `craft_ideas` (user_id, title, where_to_begin nullable, is_done, timestamps) + Model + Factory +
     `User::craftIdeas()`.
  2. `App\Livewire\CraftIdeas` (Route `/app/crafts`, Name `crafts`) + Pinnwand-View + Header-Nav-Eintrag.
  3. Dashboard-Erfassungsleiste in `TaskBoard` (`addCraftIdea()` + Partial, Desktop & Mobile eingebunden).
  4. Tests (Erfassung, Ownership-Scoping, erledigt/wiederherstellen/löschen, Vorschlags-Rotation).
  5. Testsuite grün, danach Browser-QA (Desktop + Mobile).

## Entscheidung

User hat sich für Option **A · Pinnwand** entschieden (siehe interaktives Mockup). Alle drei Rückfragen wurden
mit der jeweils empfohlenen Option beantwortet (erledigt=markieren, Vorschlag=zufällig-meidet-letzte,
Erfassung=eigene Leiste auf dem Board).
