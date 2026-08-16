# PLAN — Erinnerung 5 Minuten vor Termin/Kategorie-Start

## Ausgangslage (bestätigt im Code)
- Es gibt bereits `notify_event_start` (User-Flag) + den Minuten-Cronjob `app:send-event-start-notifications`,
  der genau **im Moment des Starts** eine Push-Benachrichtigung schickt. Dedup über `schedule_events.notified_at`
  (nicht ein festes Zeitfenster — ein verpasster/verzögerter Tick holt die Benachrichtigung beim nächsten Lauf
  trotzdem nach, siehe `ScheduleEvent::startInstantUtc()`).
- Zwei weitere, unabhängige Push-Toggles existieren nach demselben Muster: `notify_pomo_start`,
  `notify_break_start` — jeweils eigene Spalte, eigener Schalter in Settings, eigenes Verhalten.
- Alle drei Toggles sind auch über die Shortcuts-API sichtbar/setzbar (`MeController::show/update`).

## Produkt

**Neuer, unabhängiger vierter Toggle** ("5 Minuten vorher") — zusätzlich zum bestehenden "Beginn"-Toggle, nicht
als Ersatz dafür. Grund: gleiches Muster wie die drei bestehenden Toggles — frei kombinierbar (nur bei Start,
nur vorher, oder beides), Default **aus**, wie alle anderen Notify-Toggles.

- Settings → Benachrichtigungen: vierte Zeile in der bestehenden Toggle-Liste.
  - Label: „5 Minuten vor Terminen & Kategorien"
  - Hint: „Ein zusätzlicher Hinweis, kurz bevor ein Zeitplan-Block beginnt."
- Push-Text: „{Titel} beginnt in 5 Minuten" (analog zum bestehenden „{Titel} beginnt jetzt").
- Gilt für Termine **und** Kategorie-Blöcke gleichermaßen (wie der bestehende Start-Toggle auch).

## Umsetzung

- **Migration 1** (`users`): `notify_event_upcoming` (bool, default `false`).
- **Migration 2** (`schedule_events`): `notified_upcoming_at` (timestamp, nullable) — eigene Dedup-Spalte,
  unabhängig von `notified_at`, weil beide Benachrichtigungen unabhängig voneinander an/aus geschaltet werden
  können und ein Event beide auslösen kann.
- **`ScheduleEvent::withNotifiedReset()`**: setzt bei Start-/Datumsänderung künftig auch `notified_upcoming_at`
  zurück (bisher nur `notified_at`).
- **Neuer Command `app:send-event-upcoming-notifications`** (jede Minute, `bootstrap/app.php`), strukturell
  identisch zu `SendEventStartNotifications`, aber:
  - Filter auf `notify_event_upcoming = true` statt `notify_event_start`.
  - Fällig, sobald `startInstantUtc() - 5 Minuten <= now` (statt `startInstantUtc() <= now`).
  - Dedup über `notified_upcoming_at` statt `notified_at`.
- **`Settings::toggleNotifyEventUpcoming()`** + vierte Zeile in `settings.blade.php`s `$notifyRows`.
- **API**: `notify_event_upcoming` in `MeController::show()` (Ausgabe) und `::update()` (Validierung), plus
  Erwähnung in `docs/api.blade.php` — Shortcuts-Nutzer sollen den neuen Toggle genauso setzen können wie die
  drei bestehenden.
- **Tests**: `SendEventUpcomingNotificationsTest.php` (analog zu `SendEventStartNotificationsTest.php`: einmalig
  benachrichtigt, kein Duplikat bei zweitem Lauf, deaktiviertes Flag, zu weit in der Zukunft, Timezone-Offset,
  verpasster Tick, stornierter Termin) + ein Toggle-Test in `ScheduleSettingsTest.php` + API-Test-Ergänzung in
  `MeApiTest.php`.
- **CLAUDE.md**: §7 „Notifications" um den neuen Toggle/Command ergänzen, §9 Deployment-Cron-Hinweis um den
  neuen Command erweitern (kein neuer Cron-Eintrag nötig — läuft über denselben `schedule:run`, aber die
  Aufzählung der Commands, die davon abhängen, muss stimmen).

Branch: `feature/event-upcoming-notification` (von `main`), da mehr als eine Datei/ein Commit betroffen ist.

## Entscheidung

_(aus Schritt 4)_

Bestätigt wie vorgeschlagen: eigener, unabhängiger vierter Toggle „5 Minuten vorher" (nicht Ersatz für den
bestehenden „Beginn"-Toggle).
