# PLAN.md — Agenda: Fach-Filter

## Anforderungen (Schritt 1)

**Funktionalität**
- Einträge in der Agenda (`/app/agenda`) zusätzlich nach Fach filterbar, kombinierbar mit dem
  bestehenden Typ- (Hausaufgabe/Prüfung) und Klassen-Filter (UND-Verknüpfung).
- Fach-Liste im Filter kommt aus den tatsächlich vorhandenen, sichtbaren Fächern
  (`existingSubjects()` — bereits vorhanden für die Fach-Autovervollständigung im Formular).

**GUI/Design** (per Rückfrage mit User geklärt)
- Dropdown statt Chip-Reihe — bei potenziell 10+ Fächern (Mathe, Deutsch, Englisch, Französisch,
  Physik, Chemie, Bio, Geschichte, Geografie, Sport, ...) bleibt ein Dropdown übersichtlich, eine
  Chip-Reihe würde über mehrere Zeilen umbrechen.
- Einfachauswahl (ein Fach oder "Alle"), kein Mehrfach-Filter — konsistent mit Typ-/Klassen-Filter.
- Kein natives `<select>` (die App verwendet nirgends eines) — stattdessen ein handgebautes
  Alpine-Dropdown im gleichen Stil wie die Fach-Combobox im Formular
  (`partials/agenda-entry-form.blade.php`): Button mit aktuellem Wert + Chevron, öffnet eine Liste,
  `@click.outside`/Escape schliessen sie.

**Navigation/Platzierung**
- Reiht sich in die bestehende Filterzeile ein: Typ-Pills → Fach-Dropdown → Klassen-Chips (nur
  sichtbar, wenn der User in einer Klasse ist) → Gruppierungs-Toggle.
- Nur sichtbar, wenn `existingSubjects()` nicht leer ist (gleiches Prinzip wie die Klassen-Zeile) —
  eine Agenda ohne einen einzigen Eintrag zeigt keinen leeren Fach-Filter.

**Daten/Persistenz**
- Kein neues Datenbankfeld — `subject` existiert bereits auf `AgendaEntry`. Reiner Query-Filter,
  analog zu `filterType`/`filterSpace`.

## Produkt (Schritt 2)

```
┌ Alle │ Hausaufgaben │ Prüfungen ┐   ← bestehende Typ-Pills
[ Fach: Mathematik ▾ ]                ← NEU, Dropdown-Button
Alle Räume · Nur ich · 4b      nach Klasse   ← bestehende Klassen-Zeile (falls vorhanden)
```
Dropdown-Inhalt bei Klick: „Alle Fächer" oben, darunter alle `existingSubjects()` alphabetisch,
aktives Fach hervorgehoben (gleiche Selected-Optik wie die Fach-Combobox-Liste im Formular).
Leerer Zustand des Filters wird nicht extra behandelt — er existiert nur, wenn es Fächer gibt.

## Umsetzung (Schritt 3)

Kein neuer Skill/Dependency nötig — reines Livewire/Alpine/Tailwind, exakt bestehendes Muster.

- **`App\Livewire\Agenda`**: neue Property `public string $filterSubject = 'all';`, Methode
  `setSubjectFilter(string $subject)` (validiert gegen `'all'` oder eine vorhandene
  `existingSubjects()`-Zeichenkette, sonst Fallback `'all'` — exakt das Muster von
  `setSpaceFilter()`). `baseQuery()` bekommt einen dritten Zweig: `if ($this->filterSubject !== 'all')
  $query->where('subject', $this->filterSubject);`.
- **View** (`resources/views/livewire/agenda.blade.php`): neuer Alpine-Dropdown-Block zwischen der
  Typ-Pill-Zeile und der Klassen-Zeile, nur gerendert wenn `$this->existingSubjects->isNotEmpty()`.
  Gleiches Optik-Muster wie der Fach-Combobox-Dropdown im Formular (open/close, Liste, Selected-
  Zustand), aber deutlich simpler (keine Freitext-Eingabe, keine Tab-Vervollständigung — reine
  Auswahlliste).
- **Empty-State-Text** in derselben Blade-Datei: die Bedingung `$filterSpace !== 'all' || $filterType
  !== 'all'` für "Hier ist gerade nichts offen." wird um `|| $filterSubject !== 'all'` ergänzt.
- **Reihenfolge**: Livewire-Component-Änderung → View → Feature-Test (Filterkombination) → Browser-
  Verifikation (Dropdown öffnen/schliessen, Filter kombinieren, leerer Zustand).

## Entscheidung (Schritt 4)

Nur eine Umsetzungsoption entwickelt (kleines, klar umrissenes Feature nach bestehendem Muster) —
UI-Stil (Dropdown, Einfachauswahl) bereits per Rückfrage mit dem User festgelegt. Direkt mit Schritt 5
fortgefahren.
