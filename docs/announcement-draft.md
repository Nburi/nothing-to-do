# Announcement draft: the switch to the new app (step 5.4)

Draft, 2026-11-25. **Nothing here is published.** It is text to copy into the announcement editor
(`/app/admin/announcements`) by hand when the date is fixed. Tone as in `CONVENTIONS.md` 11–13: informal
"du", short, plain, a little dry, no hype, no streaks and no "amazing".

Placeholders in `<angle brackets>` are filled in by Niels. Lines marked **[confirm]** state something about
the product that depends on a decision in `docs/RELEASE_RUNBOOK.md` section 1.

## How the announcement module works (checked in `FeatureAnnouncement`)

| Field | Use here |
|---|---|
| `title` | the headline |
| `description` | the text; keep it to a few sentences, it appears as a toast/card |
| `type` | `maintenance` for the heads-up, `release` for the switch |
| `related_module`, `only_for_module_users` | leave empty: everybody is switched |
| `external_url`, `external_link_label` | optional link, e.g. to the help article |
| `is_published` | stays off until the date is fixed |

Two facts to plan around: an announcement is shown **only to users registered before it was published**, and it
has one text per row (no language switch), so the German text is the announcement and the English text below is
for a second row if you want one.

## 1. Heads-up, a few days before (type `maintenance`)

**Deutsch**

> **Titel:** Am `<Datum>` wird die App umgebaut
>
> **Text:** Am `<Datum>` ab `<Uhrzeit>` ist nothing-to-do für etwa `<Dauer>` nicht erreichbar. Danach sieht die App anders aus: ein ruhigeres Board, eine Aufgabe zum Starten, ein Wochenplan für deine Arbeitszeit. Alle deine Aufgaben, Termine, Projekte, Notizen und Ideen bleiben, vor dem Umbau wird alles gesichert. Du musst nichts tun.

**English**

> **Title:** The app changes on `<date>`
>
> **Text:** On `<date>` from `<time>`, nothing-to-do will be offline for about `<duration>`. Afterwards the app looks different: a calmer board, one task to start with, a week plan for your work time. All your tasks, appointments, projects, notes and ideas stay; everything is backed up before the change. You do not have to do anything.

## 2. After the switch (type `release`)

**Deutsch**

> **Titel:** Das neue nothing-to-do ist da
>
> **Text:** Das Board ist ruhiger geworden. Es schlägt dir eine Aufgabe vor, mit einem ersten Schritt, und zwar nur in deiner Arbeitszeit. Mit „Start“ klappt das Board weg und du siehst nur noch diese Aufgabe, den Timer und die Pausen. Neu sind auch der Wochenplan für deine Arbeitszeit, eine Erfassungszeile für schnelle Gedanken, „Ideen“ für freie Zeit und ein stiller Wochenrückblick am Sonntag. Deine alten Daten sind alle da: Aufgaben, Termine, Projekte, Notizen, Bastelideen (jetzt unter „Ideen“). Wenn dir etwas fehlt, schreib uns über „Hilfe“.
>
> **Link:** „Was ist neu?“

**English**

> **Title:** The new nothing-to-do is here
>
> **Text:** The board is calmer. It suggests one task with a first step, and only during your work time. Tap "Start" and the board folds away: you see only that task, the timer and the breaks. New too: a week plan for your work time, a capture line for quick thoughts, "Ideas" for free time and a quiet weekly review on Sunday. All your old data is there: tasks, appointments, projects, notes, craft ideas (now under "Ideas"). If something is missing, write to us through "Help".
>
> **Link:** "What is new?"

## 3. First-run explanation (short, on the first visit to the new board)

Three cards, one tap each, skippable, shown once per account. Not built yet (the onboarding of 5.1 is for new
accounts; users who are switched need this shorter one): the text is ready, the place for it is a follow-up
build. `[confirm]` whether it is built or the announcement link is enough.

**Deutsch**

> **1 · Eine Aufgabe zum Starten.** Das Board zeigt dir eine Aufgabe mit „Starte mit …“. Passt sie nicht, tippst du auf „Anderes“ oder „Kleiner machen“. Ausserhalb deiner Arbeitszeit schlägt es nichts vor.
>
> **2 · Ein Start, dann Ruhe.** „Start“ faltet das Board weg. Du siehst die Aufgabe, den Timer (25 Minuten, 5 Minuten Pause) und sonst nichts. Das Board ist einen Tipp entfernt.
>
> **3 · Erst erfassen, abends sortieren.** Über die Zeile unten (oder die Taste C) hältst du einen Gedanken fest. Sortiert wird einmal am Tag: To-do, Task oder Später.
>
> *Deine Arbeitszeit stellst du im Wochenplan ein: Bereiche, Blöcke und Pausentage. Alles andere findest du unter „Hilfe“.*

**English**

> **1 · One task to start with.** The board shows you one task with "Start with …". If it does not fit, tap "Another" or "Make it smaller". Outside your work time it suggests nothing.
>
> **2 · One start, then quiet.** "Start" folds the board away. You see the task, the timer (25 minutes, 5 minutes break) and nothing else. The board is one tap away.
>
> **3 · Capture first, sort in the evening.** The line at the bottom (or the C key) holds a thought. You sort once a day: To-do, Task or Later.
>
> *You set your work time in the week plan: areas, blocks and days off. Everything else is under "Help".*

## 4. Sentences that depend on a decision

Use only the ones that apply; they are not in the texts above.

| Decision | Sentence (DE / EN) |
|---|---|
| D1, category links turned into a text link | „Bei Kategorien, die mit einem Projekt, einer Gruppe oder einem Agenda-Eintrag verknüpft waren, steht jetzt der Name als Text.“ / "Categories that were linked to a project, a group or an Agenda entry now show its name as text." **[confirm]** |
| D1, links cleared | „Die Verknüpfung von Kategorien mit Projekten, Gruppen und Agenda-Einträgen gibt es nicht mehr; der Block schlägt jetzt selbst eine Aufgabe vor.“ / "Linking categories to projects, groups and Agenda entries is gone; the block now suggests a task by itself." **[confirm]** |
| D2, "Wichtig" marks kept | „Aufgaben, die du als „Wichtig“ markiert hast, stehen jetzt direkt nach den Aufgaben mit Frist. Wenn du viele markiert hast, räum kurz auf.“ / "Tasks you marked „Wichtig“ now come right after the ones with a deadline. If you marked many, take a minute to tidy up." **[confirm]** |
| D2, marks cleared | „Die „Wichtig“-Markierungen wurden zurückgesetzt, weil sie auf dem neuen Board nur wenige Aufgaben meinen sollen.“ / "The „Wichtig“ marks were reset, because on the new board they are meant for a few tasks only." **[confirm]** |
| Agenda, Notfallmodus, Vorbereiten | The old Agenda (classes and entries), the emergency mode and "Vorbereiten" are not part of the new app; their data stays in the database and in the data export (`/profile/export`). **[confirm]** this is what you want to say, and how. |
| Datenschutz (D3) | one sentence linking the updated privacy page, if it changed |

## 5. Before copy and paste

1. Fill the placeholders, read each text once aloud: short, plain, no exclamation marks.
2. Check every promise against the app on the day: nothing here says "faster", "smarter" or "better", only what
   you can see.
3. The `maintenance` text goes out a few days before (D8), the `release` text right after `php artisan up`
   (runbook step 18). Create both as drafts first (`is_published` off).
