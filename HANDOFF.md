# Handoff: v2 Three-Lists Feature

**Branch:** `feature/three-lists-ui`  
**Status:** Code complete, tests pass — one preview environment blocker to clear before final verification

---

## Was bereits fertig ist

- ✅ `src/db.js` — 3 neue Spalten (`list`, `is_important`, `is_today`) in Schema + idempotente Startup-Migration
- ✅ `src/api/v1.js` — neue Felder in allen Task-Responses, POST/PATCH akzeptieren neue Felder, Validierung implementiert
- ✅ `test/tasks.test.js` — bestehende Tests aktualisiert + 10 neue Tests für v2-Logik
- ✅ `public/index.html` — komplett neue HTML-Struktur (3-Spalten Desktop + 4-Tab Mobile)
- ✅ `public/styles.css` — neues Layout (Desktop Grid, Mobile Nav, Task-Card-States)
- ✅ `public/app.js` — neue Render-Logik, Drag & Drop, Swipe-Gesten, Dropdown-Menü, Deadline-Picker
- ✅ **44/44 Tests grün** (`npm test`)

---

## Einziger offener Blocker

### `data/tasks.db` löschen und Preview testen

Die lokale `data/tasks.db` enthält alten Zustand aus früheren Entwicklungsversuchen. Das verhindert, dass Sessions im Preview-Browser korrekt gesetzt werden (alle gespeicherten Sessions haben `userId: undefined`).

**Schritt 1 — DB-Dateien löschen:**
```powershell
Remove-Item data\tasks.db, data\tasks.db-wal, data\tasks.db-shm -ErrorAction SilentlyContinue
```
Die Migration läuft beim nächsten Server-Start automatisch. Kein manuelles SQL nötig.

**Schritt 2 — Server starten:**
```bash
npm run dev
```
Oder via Preview-Tool mit dem Config-Eintrag `"nothing-to-do"` in `.claude/launch.json`.

**Schritt 3 — Manuell testen** (Browser auf `http://localhost:3000`):

**Desktop (≥768px):**
- [ ] 3 Spalten sichtbar: Inbox | To Dos | Tasks
- [ ] Quick-Add oben fügt Task zur Inbox hinzu
- [ ] Task per Drag & Drop zwischen Spalten verschieben → bleibt nach Reload in der Zielspalte
- [ ] Auf Task-Body klicken → gelber Rand (is_important)
- [ ] Wichtige Tasks erscheinen als erste in der Spalte
- [ ] 3-Punkte → Bearbeiten → Modal öffnet sich, Speichern funktioniert
- [ ] 3-Punkte → Löschen → Task verschwindet
- [ ] Kreis klicken → Task durchgestrichen, landet in "Done today"-Sektion
- [ ] Task in To Dos/Tasks → "Today"-Abschnitt erscheint oben in der Spalte wenn is_today gesetzt

**Mobile (<768px, DevTools oder schmales Fenster):**
- [ ] 4 Tabs in unterer Navigationsleiste (Inbox / To Dos / Tasks / Alle)
- [ ] Jede Tab hat eigene Input-Box oben
- [ ] Inbox: Task nach rechts swipen → Task wechselt zu To Dos
- [ ] Inbox: Task nach links swipen → Task wechselt zu Tasks
- [ ] To Dos: Task nach rechts swipen → erscheint im "Today"-Bereich
- [ ] To Dos: Task nach links swipen → Deadline-Picker erscheint, Datum setzen funktioniert
- [ ] Auf Task-Body tippen → is_important toggled
- [ ] 3-Punkte → Bearbeiten / Löschen funktionieren
- [ ] "Alle"-Tab zeigt alle Tasks aller Listen; Quick-Add dort → geht in Inbox

---

## Danach: Commit & PR

```bash
git add -A
git commit -m "feat: three-lists UI with drag-and-drop and mobile swipe gestures"
```

Dann PR von `feature/three-lists-ui` → `main` erstellen.

---

## Deployment auf dem externen Server

Nach dem Merge und `git pull` auf dem Server:
```bash
systemctl restart nothing-to-do   # oder: docker-compose restart
```

Die DB-Migration (`list`, `is_important`, `is_today` Spalten) läuft automatisch beim Start.  
Kein manuelles SQL, kein Downtime-Risiko.  
Frontend-Cache: `app.js?v=3` busted automatisch beim nächsten Browser-Reload.

---

## Tests ausführen

```bash
npm test
# Erwartet: 44 pass, 0 fail
```

Tests laufen gegen eine In-Memory-DB (`:memory:`) und berühren `data/tasks.db` nicht.

---

## Relevante Dateien

| Datei | Was geändert wurde |
|---|---|
| `src/db.js` | Schema + Migration für `list`, `is_important`, `is_today` |
| `src/api/v1.js` | Neue Felder in serialize/POST/PATCH + `VALID_LISTS` Validierung |
| `public/index.html` | Kompletter Umbau: 3-Spalten-Grid + Mobile-Nav |
| `public/styles.css` | Kompletter Umbau: responsive Layout, neue Komponenten |
| `public/app.js` | Kompletter Umbau: neue State-Logik, DnD, Swipe, Dropdown |
| `test/tasks.test.js` | Assertions angepasst + `describe('Three-lists feature')` ergänzt |
| `CLAUDE.md` | Technische Doku, bekannte Issues, gelöste Bugs |
