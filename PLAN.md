# PLAN — Geteilte Klassen-Agenda (Agenda Spaces)

> Ersetzt den vorherigen Plan (Schnellerfassung, umgesetzt). Der steht weiterhin in der Git-Historie, Commit `5208038`.

> Erstellt: 2026-08-11 · Branch: `feature/agenda-class-spaces` (von `main`)
> Status: **Schritt 5 — Umsetzung** · Layout: **Variante A + Gruppierungs-Umschalter** (entschieden)

---

## Anforderungen (Schritt 1)

| Kategorie | Anforderung |
|---|---|
| **Datenmodell** | `AgendaSpace` (Name, Invite-Code, Besitzer) mit Mitgliedern; `AgendaEntry` bekommt optional eine Raum-Zuordnung — ohne Raum = privat wie bisher |
| | Ein Nutzer kann **mehreren** Räumen angehören (Klasse + z.B. Lerngruppe) |
| **Beitreten** | Jedes Mitglied kann einladen: 6-stelliger Code **und** Link (`/agenda/join/{code}`); Beitreten nur eingeloggt |
| **Sichtbarkeit** | Jedes Mitglied sieht alle Einträge seiner Räume; private Einträge bleiben strikt privat |
| **Erledigt** | **Pro Person.** Zusätzlich am Klassen-Eintrag sichtbar: „5/22 erledigt" |
| **Rechte** | Einträge: **jedes Mitglied** darf bearbeiten/löschen. Raum löschen: nur Besitzer |
| **Erstellen** | Beim Anlegen wählbar: „nur ich" oder ein Raum — auch in QuickCapture |
| **Sicherheit** | Jeder Zugriff läuft über die Sichtbarkeits-Scope, nie über eine Frontend-ID |
| **UX** | Klassen- vs. Privateintrag klar unterscheidbar; Ersteller sichtbar; Leerzustände für „noch kein Raum"; Löschen per armed double-click (nie `confirm()`) |

**Entschieden mit dem User (Schritt 1):** erledigt pro Person **mit** sichtbarem Fortschritt · mehrere Räume · jedes Mitglied darf Einträge bearbeiten.

---

## Produkt (Schritt 2)

### Layout-Varianten (Entscheidung offen)

**Variante A — ein Strom mit Raum-Filter**
Alle Einträge chronologisch in einer Liste, jeder Klassen-Eintrag trägt ein Raum-Badge (`4b`), private
Einträge ein `nur ich`-Badge. Unter der bestehenden Typ-Filterzeile eine zweite, leisere Filterzeile:
`Alle Räume · Nur ich · Klasse 4b · Bio-Lerngruppe` — erscheint nur, wenn der Nutzer mindestens einen
Raum hat (ohne Raum sieht die Seite exakt aus wie heute).

- **Für:** Datum bleibt die einzige Sortierachse — „was ist als Nächstes fällig" ist die eigentliche Frage.
- **Gegen:** zwei Chip-Zeilen übereinander; pro Zeile ein Badge mehr.

**Variante B — getrennte Sektionen pro Raum**
Liste in Abschnitte geteilt (`Klasse 4b · 22 Mitglieder`, dann `Nur ich`), keine zweite Filterzeile,
kein Raum-Badge pro Zeile.

- **Für:** ruhigere Zeilen, Zugehörigkeit ohne Badge lesbar, eine Chip-Zeile weniger.
- **Gegen:** die Fälligkeits-Reihenfolge zerfällt — eine morgen fällige Privataufgabe steht unter einer
  Klassenaufgabe in zwei Wochen.

### Gemeinsam in beiden Varianten

- **Header:** neuer „Klassen"-Button neben `+ Eintrag`.
- **Klassen-Sheet** (gleiche Shell wie `agenda-entry-form`): Liste der Räume mit Mitgliederzahl,
  Invite-Code + „Link kopieren" + „Neu" (Code rotieren), Verlassen/Löschen; darunter „Code eingeben →
  Beitreten" und „Neue Klasse benennen → Erstellen".
- **Eintragsformular:** neue Pill-Reihe „Für: `Nur ich` `Klasse 4b` `Bio-Lerngruppe`" (nur wenn Räume da).
- **Zeile:** Fortschritts-Zähler `5/22` bei Klassen-Einträgen, `von Lena` wenn nicht selbst erstellt.
- **Beitreten-Seite** `/agenda/join/{code}`: zeigt Raumnamen + Mitgliederzahl, ein Button „Beitreten".
  Kein Join per GET — ein Link-Aufruf darf nichts mutieren.
- **Fach-Vorschläge** kommen ab jetzt aus allen sichtbaren Einträgen (eigene + Räume), nicht nur eigenen.

---

## Umsetzung (Schritt 3)

**Kein neuer Stack, keine neue Dependency.** Alles mit Laravel + Livewire 4 + Alpine wie bisher.
Keine externen Skills nötig — die App hat für jedes Element hier schon ein bestehendes Muster
(Sheet = `schedule-event-form`, Löschen = armed double-click, Pill-Toggle = Termin/Kategorie-Schalter).

### Migrationen (4, alle nicht-destruktiv)

1. `create_agenda_spaces_table` — `id, owner_id→users, name, invite_code(unique), timestamps`
2. `create_agenda_space_user_table` — Pivot, `unique(agenda_space_id, user_id)`
3. `add_agenda_space_id_to_agenda_entries_table` — nullable FK, `nullOnDelete`, Index `(agenda_space_id, date)`
4. `create_agenda_entry_completions_table` — Pivot `(agenda_entry_id, user_id)`, **+ Backfill**:
   bestehende `is_done = true`-Einträge bekommen eine Completion-Zeile ihres Besitzers

> **`agenda_entries.is_done` bleibt vorerst stehen** (Rollback-Punkt, CLAUDE.md §8: zweistufig).
> Nach bestätigtem Produktionsdeploy in einem eigenen Commit entfernen → Eintrag in `TODO.md`.

### Modelle

- **`AgendaSpace`** — `owner()`, `members()` (BelongsToMany User), `entries()`, `hasMember()`,
  `static generateInviteCode()` (6 Zeichen, Alphabet ohne `O/0/I/1`), Scope `forMember`.
- **`AgendaEntry`** — `+ agenda_space_id`; `space()`, `completedBy()` (BelongsToMany User über
  `agenda_entry_completions`), `isShared()`, `isDoneFor(User)`;
  Scopes `visibleTo(User)` (eigene private **oder** in einem meiner Räume), `openFor(User)`,
  `doneFor(User)`. Listen laden mit `withCount('completedBy')` +
  `withExists(['completedBy as done_for_me' => …])` — keine N+1.
- **`User`** — `agendaSpaces()`, `ownedAgendaSpaces()`.

### Komponenten

- **`Agenda`** — `userEntry()` → `visibleEntry()` (über `visibleTo`, nie über `agendaEntries()`);
  `$filterSpace`; `$formSpaceId`; Sheet-Actions `createSpace/joinSpace/leaveSpace/deleteSpace/regenerateCode`.
  Besitzer verlässt Raum → Besitz geht an das längste verbliebene Mitglied; letztes Mitglied raus → Raum weg
  (Einträge fallen per `nullOnDelete` auf privat zurück, nichts geht verloren).
- **`JoinAgendaSpace`** — neue Seite `/agenda/join/{code}`, `auth`-middleware.
- **`QuickCapture`** — Raum-Auswahl für das `agenda`-Target.

### Reihenfolge (ein Commit pro Schritt)

1. Migrationen + Modelle + Factories + Modell-Tests
2. Klassen-Sheet: erstellen / beitreten / verlassen / Code rotieren + Join-Seite
3. Geteilte Einträge: „Für"-Auswahl, Sichtbarkeit, Erledigt pro Person, Fortschritt
4. QuickCapture-Raumauswahl + Fach-Vorschläge aus allen Räumen
5. Doku: `CLAUDE.md` §1/§7, `CHANGELOG.md`, `TODO.md`, Deployment-Checkliste

### Tests (`tests/Feature/AgendaSpacesTest.php` + Erweiterung `AgendaTest.php`)

Nicht-Mitglied sieht nichts · Beitritt per Code/Link · falscher Code · doppelter Beitritt ·
Erledigt ist pro Person · Fortschrittszähler stimmt · jedes Mitglied darf bearbeiten ·
Raum löschen nur als Besitzer · Verlassen macht Einträge nicht kaputt · privater Eintrag bleibt privat.

### Deployment

Nur `php artisan migrate --force` zusätzlich zum Standardablauf (§9) — keine neue `.env`-Variable,
kein neuer Cron, keine neue Dependency.
