<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/**
 * Realistic rows for the OLD production schema (what `main` runs today), for the
 * migration rehearsal (TESTBERICHT A12, risk C6). Invented people and content, no
 * real user data.
 *
 * Inserted with the query builder on purpose: the models and factories describe the
 * NEW schema and must not be used while the old one is in place. One user (Niels) has
 * a bit of everything, one (Lena) shares a space with him, one (Neu) owns nothing, so
 * "an empty account survives" is covered as well.
 *
 * Every table that holds user data gets at least one row; the rehearsal test checks
 * that, so a table added to production later cannot silently go untested.
 */
final class OldSchemaFixtures
{
    /** created_at / updated_at of rows that do not say otherwise. */
    private const STAMP = '2026-09-14 09:30:00';

    /**
     * The tasks, keyed by id. `deadline` and `due_date` are the old column names the
     * refactor renames to `hard_deadline` and `soft_deadline`; every combination occurs:
     * both, hard only, soft only, neither. `projects` is the removed list that
     * 2026_10_31_000001 moves into `tasks`.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function taskRows(): array
    {
        return [
            1 => ['user_id' => 1, 'title' => 'Mathe: Kapitel 4 repetieren', 'list' => 'tasks', 'is_today' => 1, 'is_important' => 1, 'deadline' => '2026-11-20', 'due_date' => '2026-11-18', 'sort_order' => 1, 'today_date' => '2026-11-23', 'duration_minutes' => 45, 'notes' => "- [ ] Beispiele 4.1 bis 4.3\n- [ ] Formelsammlung ergänzen"],
            2 => ['user_id' => 1, 'title' => 'Bio-Prüfung lernen', 'list' => 'tasks', 'is_important' => 1, 'deadline' => '2026-11-27', 'due_date' => null, 'sort_order' => 2, 'agenda_entry_id' => 2, 'duration_minutes' => 120],
            3 => ['user_id' => 1, 'title' => 'Zahnarzt anrufen', 'list' => 'todos', 'deadline' => null, 'due_date' => '2026-11-16', 'sort_order' => 1],
            4 => ['user_id' => 1, 'title' => 'Geburtstagsgeschenk für Mama 🎁', 'list' => 'inbox', 'sort_order' => 1],
            5 => ['user_id' => 1, 'title' => 'Kapitel 1 der Facharbeit schreiben', 'list' => 'projects', 'deadline' => '2026-12-18', 'due_date' => '2026-12-01', 'sort_order' => 1, 'project_id' => 1],
            6 => ['user_id' => 1, 'title' => 'Karte für das Trainingslager kaufen', 'list' => 'projects', 'sort_order' => 2, 'project_id' => 2],
            7 => ['user_id' => 1, 'title' => 'Referat Geschichte abgeben', 'list' => 'tasks', 'deadline' => '2026-11-10', 'due_date' => '2026-11-09', 'is_completed' => 1, 'completed_at' => '2026-11-10 16:20:00', 'sort_order' => 3],
            8 => ['user_id' => 1, 'title' => 'Wäsche waschen', 'list' => 'todos', 'due_date' => '2026-11-17', 'is_completed' => 1, 'completed_at' => '2026-11-17 18:00:00', 'repeat_rule' => 'weekly', 'sort_order' => 2],
            9 => ['user_id' => 1, 'title' => 'Wäsche waschen', 'list' => 'todos', 'due_date' => '2026-11-24', 'repeat_rule' => 'weekly', 'repeated_from_id' => 8, 'sort_order' => 3],
            10 => ['user_id' => 1, 'title' => 'Präsentation für Biologie 🧪 (Zellatmung, Größenvergleich)', 'list' => 'tasks', 'deadline' => '2026-12-03', 'due_date' => '2026-11-30', 'sort_order' => 4, 'group_id' => 1, 'duration_minutes' => 60, 'notes' => "Folien auf Teams.\n- [x] Quellen sammeln\n- [ ] Üben vor dem Spiegel"],
            11 => ['user_id' => 1, 'title' => 'Mathe-Nachprüfung vorbereiten', 'list' => 'tasks', 'sort_order' => 5, 'group_id' => 1, 'emergency_list' => 'must'],
            12 => ['user_id' => 1, 'title' => rtrim(str_repeat('Lange Aufgabe ', 17)), 'list' => 'inbox', 'sort_order' => 2],
            13 => ['user_id' => 2, 'title' => 'Gedichtanalyse schreiben', 'list' => 'tasks', 'deadline' => '2026-11-25', 'sort_order' => 1],
            14 => ['user_id' => 2, 'title' => 'Einkaufen', 'list' => 'todos', 'due_date' => '2026-11-23', 'sort_order' => 1],
            15 => ['user_id' => 2, 'title' => 'Chorprobe vorbereiten', 'list' => 'inbox', 'sort_order' => 1],
        ];
    }

    /** Tables that production keeps no rows in on purpose (the device-code grant is switched off). */
    public const EMPTY_BY_DESIGN = ['oauth_device_codes'];

    public static function insert(): void
    {
        self::accounts();
        self::projectsAndGroups();
        self::agenda();
        self::weekPlan();
        self::tasks();
        self::taskLinks();
        self::scheduleEvents();
        self::personalData();
        self::accessAndAdmin();
    }

    private static function accounts(): void
    {
        $password = '$2y$12$k3Jt9QvXw1mZp0rLs2Yh5uBn4dGcFa8iVxTqWyRzMlKjHgNfDSeAo';

        self::rows('users', [
            [
                'id' => 1, 'name' => 'Niels Beispiel', 'email' => 'niels@example.test', 'email_verified_at' => '2026-06-20 18:00:00',
                'password' => $password, 'remember_token' => 'rmb-niels-3f9a', 'created_at' => '2026-06-20 17:55:00',
                'task_reset_time' => '04:00', 'pomodoro_work' => 30, 'pomodoro_short_break' => 5, 'pomodoro_long_break' => 20,
                'timezone_auto_dst' => 1, 'timezone_offset' => 1, 'pomodoro_autostart' => 1, 'notify_event_start' => 1,
                'notify_pomo_start' => 1, 'daily_task_goal' => 4, 'notify_daily_reminder' => 1, 'daily_reminder_time' => '19:30',
                'header_badges' => '["streak","deadlines"]', 'planner_enabled' => 1, 'hidden_modules' => '["craft_ideas"]',
                'onboarding_completed_at' => '2026-06-20 18:10:00', 'is_admin' => 1, 'last_login_at' => '2026-11-22 18:41:00',
                'last_seen_at' => '2026-11-22 18:52:00', 'day_start_time' => '06:30', 'day_end_time' => '22:30',
                'weekday_day_bounds' => '{"6":{"start":"08:00","end":"22:00"}}', 'reminder_evening_time' => '19:30',
            ],
            [
                'id' => 2, 'name' => 'Lena Muster', 'email' => 'lena@example.test', 'email_verified_at' => '2026-08-02 12:00:00',
                'password' => $password, 'created_at' => '2026-08-02 11:58:00', 'list_concept' => 'eisenhower',
                'timezone_auto_dst' => 1, 'timezone_offset' => 1, 'last_login_at' => '2026-11-21 20:03:00',
            ],
            ['id' => 3, 'name' => 'Neu Konto', 'email' => 'neu@example.test', 'password' => $password, 'created_at' => '2026-11-22 21:00:00'],
        ]);

        self::rows('password_reset_tokens', [
            ['email' => 'lena@example.test', 'token' => '$2y$12$resetTokenHashForLena0000000000000000000000000000000000000', 'created_at' => '2026-11-20 10:00:00'],
        ], stamps: false);
    }

    private static function projectsAndGroups(): void
    {
        self::rows('projects', [
            ['id' => 1, 'user_id' => 1, 'name' => 'Facharbeit Biologie', 'sort_order' => 1, 'brainstorm' => 'Thema: Zellatmung bei Ausdauersportlern. Interview mit dem Trainer?', 'external_url' => 'https://example.test/facharbeit', 'deadline' => '2026-12-18'],
            ['id' => 2, 'user_id' => 1, 'name' => 'OL-Saison 2027', 'sort_order' => 2],
            ['id' => 3, 'user_id' => 2, 'name' => 'Maturarbeit', 'sort_order' => 1, 'deadline' => '2027-01-15'],
        ]);
        // The emergency project is a circular reference (projects.user_id <-> users.emergency_project_id).
        DB::table('users')->where('id', 1)->update(['emergency_project_id' => 1]);

        self::rows('task_groups', [
            ['id' => 1, 'user_id' => 1, 'name' => 'Schule', 'sort_order' => 1],
            ['id' => 2, 'user_id' => 2, 'name' => 'Haushalt', 'sort_order' => 1],
        ]);
        self::rows('group_notes', [
            ['id' => 1, 'task_group_id' => 1, 'content' => 'Mathelehrer wegen der Nachprüfung fragen', 'sort_order' => 1],
            ['id' => 2, 'task_group_id' => 1, 'content' => 'Biologie: Folien liegen auf Teams', 'sort_order' => 2],
        ]);
    }

    private static function agenda(): void
    {
        self::rows('agenda_spaces', [
            ['id' => 1, 'owner_id' => 1, 'name' => 'Klasse 3b', 'invite_code' => 'k3b-4f9k2m8q'],
        ]);
        self::rows('agenda_space_user', [
            ['id' => 1, 'agenda_space_id' => 1, 'user_id' => 1],
            ['id' => 2, 'agenda_space_id' => 1, 'user_id' => 2],
        ]);
        self::rows('agenda_entries', [
            ['id' => 1, 'user_id' => 1, 'type' => 'homework', 'subject' => 'Mathematik', 'title' => 'Aufgaben 4.2 bis 4.5', 'notes' => 'Seite 88', 'date' => '2026-11-24', 'duration_minutes' => 30],
            ['id' => 2, 'user_id' => 1, 'type' => 'exam', 'subject' => 'Biologie', 'title' => 'Prüfung Zellbiologie', 'date' => '2026-11-27'],
            ['id' => 3, 'user_id' => 2, 'type' => 'homework', 'subject' => 'Deutsch', 'title' => 'Gedichtanalyse', 'date' => '2026-11-25', 'is_done' => 1, 'agenda_space_id' => 1, 'duration_minutes' => 45],
        ]);
        self::rows('agenda_entry_completions', [
            ['id' => 1, 'agenda_entry_id' => 3, 'user_id' => 2],
        ]);
        self::rows('agenda_entry_notes', [
            ['id' => 1, 'agenda_entry_id' => 1, 'user_id' => 1, 'notes' => 'Beispiel 4.3 noch einmal anschauen'],
        ]);
        self::rows('agenda_drafts', [
            ['id' => 1, 'user_id' => 1, 'agenda_space_id' => 1, 'type' => 'homework', 'subject' => 'Englisch'],
        ]);
    }

    private static function weekPlan(): void
    {
        self::rows('event_categories', [
            ['id' => 1, 'user_id' => 1, 'name' => 'Schule', 'color' => 'contour', 'pomodoro_enabled' => 0, 'sort_order' => 1],
            ['id' => 2, 'user_id' => 1, 'name' => 'Lernen', 'color' => 'forest', 'pomodoro_enabled' => 1, 'sort_order' => 2, 'task_source' => 'tasks'],
            ['id' => 3, 'user_id' => 1, 'name' => 'Training', 'color' => 'signal', 'pomodoro_enabled' => 0, 'sort_order' => 3, 'task_source' => 'text', 'linked_text' => 'Lockerer Dauerlauf, 45 Minuten'],
            ['id' => 4, 'user_id' => 2, 'name' => 'Hausaufgaben', 'color' => 'contour', 'pomodoro_enabled' => 1, 'sort_order' => 1, 'task_source' => 'group', 'linked_group_id' => 2],
            ['id' => 5, 'user_id' => 1, 'name' => 'Facharbeit', 'color' => 'overprint', 'pomodoro_enabled' => 1, 'sort_order' => 4, 'task_source' => 'project', 'linked_project_id' => 1],
            ['id' => 6, 'user_id' => 1, 'name' => 'Prüfungsstoff', 'color' => 'ink', 'pomodoro_enabled' => 1, 'sort_order' => 5, 'task_source' => 'agenda_entry', 'linked_agenda_entry_id' => 2],
        ]);
        self::rows('category_attributes', [
            ['id' => 1, 'event_category_id' => 3, 'name' => 'Distanz', 'type' => 'number', 'unit' => 'km', 'sort_order' => 1],
            ['id' => 2, 'event_category_id' => 3, 'name' => 'Gefühl', 'type' => 'select', 'options' => '[{"label":"locker","color":"forest"},{"label":"hart","color":"signal"}]', 'sort_order' => 2],
            ['id' => 3, 'event_category_id' => 2, 'name' => 'Fertig?', 'type' => 'checkbox', 'sort_order' => 1],
        ]);
        self::rows('event_templates', [
            ['id' => 1, 'user_id' => 1, 'name' => 'Schule', 'color' => 'contour', 'duration' => 480, 'default_start' => '08:00', 'is_recurring' => 1, 'recurrence' => '1,2,3,4,5', 'sort_order' => 1, 'category_id' => 1, 'buffer_before' => 15],
            ['id' => 2, 'user_id' => 1, 'name' => 'Lernblock', 'color' => 'forest', 'duration' => 90, 'default_start' => '19:00', 'is_recurring' => 1, 'recurrence' => '1,3', 'sort_order' => 2, 'category_id' => 2],
            ['id' => 3, 'user_id' => 1, 'name' => 'OL-Training', 'color' => 'signal', 'duration' => 75, 'default_start' => '18:00', 'is_recurring' => 1, 'recurrence' => '2,4', 'sort_order' => 3, 'category_id' => 3, 'buffer_before' => 20, 'buffer_after' => 20],
            ['id' => 4, 'user_id' => 2, 'name' => 'Chor', 'color' => 'overprint', 'duration' => 60, 'default_start' => '17:30', 'is_recurring' => 1, 'recurrence' => '3', 'sort_order' => 1],
        ]);
        self::rows('schedule_pauses', [
            ['id' => 1, 'user_id' => 1, 'date' => '2026-12-24', 'note' => 'Weihnachten'],
            ['id' => 2, 'user_id' => 1, 'date' => '2026-12-25', 'note' => 'Weihnachten'],
            ['id' => 3, 'user_id' => 1, 'date' => '2026-12-26'],
        ]);
        self::rows('schedule_day_bounds', [
            ['id' => 1, 'user_id' => 1, 'date' => '2026-11-28', 'start_time' => '08:00', 'end_time' => '23:30'],
        ]);
    }

    private static function tasks(): void
    {
        foreach (self::taskRows() as $id => $row) {
            DB::table('tasks')->insert(['id' => $id] + $row + ['created_at' => self::STAMP, 'updated_at' => self::STAMP]);
        }
    }

    private static function taskLinks(): void
    {
        self::rows('category_task_links', [
            ['id' => 1, 'category_id' => 2, 'task_id' => 1, 'sort_order' => 1],
            ['id' => 2, 'category_id' => 2, 'task_id' => 10, 'sort_order' => 2],
        ]);
        self::rows('task_day_plans', [
            ['id' => 1, 'task_id' => 1, 'planned_date' => '2026-11-23', 'sort_order' => 1, 'source' => 'manual'],
            ['id' => 2, 'task_id' => 2, 'planned_date' => '2026-11-24', 'sort_order' => 1, 'source' => 'auto', 'promoted_for_date' => '2026-11-23'],
        ]);
        self::rows('task_sessions', [
            ['id' => 1, 'task_id' => 2, 'planned_date' => '2026-11-23', 'minutes' => 50, 'sort_order' => 1],
            ['id' => 2, 'task_id' => 2, 'planned_date' => '2026-11-24', 'minutes' => 70, 'sort_order' => 1],
            ['id' => 3, 'task_id' => 10, 'planned_date' => '2026-11-22', 'minutes' => 30, 'sort_order' => 1, 'done_at' => '2026-11-22 20:05:00'],
        ]);
    }

    private static function scheduleEvents(): void
    {
        self::rows('schedule_events', [
            ['id' => 1, 'user_id' => 1, 'template_id' => 1, 'color' => 'contour', 'date' => '2026-11-23', 'start_time' => '08:00', 'end_time' => '16:00', 'category_id' => 1, 'buffer_before' => 15],
            ['id' => 2, 'user_id' => 1, 'template_id' => 2, 'color' => 'forest', 'date' => '2026-11-23', 'start_time' => '19:00', 'end_time' => '20:30', 'category_id' => 2, 'pomodoro_started_at' => '2026-11-23 19:02:10', 'pomodoro_phase' => 'work', 'pomodoro_cycle' => 2, 'notified_at' => '2026-11-23 19:00:00', 'notified_upcoming_at' => '2026-11-23 18:45:00', 'pomodoro_linked_notified' => 1],
            ['id' => 3, 'user_id' => 1, 'template_id' => 3, 'color' => 'signal', 'date' => '2026-11-24', 'start_time' => '18:00', 'end_time' => '19:15', 'category_id' => 3, 'is_cancelled' => 1, 'buffer_before' => 20, 'buffer_after' => 20],
            ['id' => 4, 'user_id' => 1, 'title' => 'Zahnarzt', 'color' => 'overprint', 'date' => '2026-11-25', 'start_time' => '14:30', 'end_time' => '15:15'],
            ['id' => 5, 'user_id' => 1, 'title' => 'Facharbeit schreiben', 'color' => 'overprint', 'date' => '2026-11-26', 'start_time' => '19:00', 'end_time' => '21:00', 'category_id' => 5],
            ['id' => 6, 'user_id' => 2, 'template_id' => 4, 'color' => 'overprint', 'date' => '2026-11-25', 'start_time' => '17:30', 'end_time' => '18:30'],
        ]);
        self::rows('schedule_event_attribute_values', [
            ['id' => 1, 'schedule_event_id' => 3, 'category_attribute_id' => 1, 'value' => '12'],
            ['id' => 2, 'schedule_event_id' => 3, 'category_attribute_id' => 2, 'value' => 'locker'],
            ['id' => 3, 'schedule_event_id' => 2, 'category_attribute_id' => 3, 'value' => '1'],
        ]);
        self::rows('schedule_event_task_links', [
            ['id' => 1, 'schedule_event_id' => 2, 'task_id' => 1, 'sort_order' => 1, 'source' => 'manual'],
            ['id' => 2, 'schedule_event_id' => 5, 'task_id' => 5, 'sort_order' => 1, 'source' => 'manual'],
        ]);
    }

    private static function personalData(): void
    {
        self::rows('reminders', [
            ['id' => 1, 'user_id' => 1, 'title' => 'Geburtstag Oma', 'remind_on' => '2026-12-02'],
            ['id' => 2, 'user_id' => 1, 'title' => 'Versicherung kündigen', 'remind_on' => '2026-11-30', 'notify_on_day' => 0, 'acknowledged_at' => '2026-11-22 09:00:00', 'notified_before_at' => '2026-11-29 19:00:00'],
        ]);
        self::rows('craft_ideas', [
            ['id' => 1, 'user_id' => 1, 'title' => 'Holzregal fürs Zimmer', 'where_to_begin' => 'Masse messen, Skizze zeichnen'],
            ['id' => 2, 'user_id' => 1, 'title' => 'Vogelhaus', 'is_done' => 1],
        ]);
        self::rows('streak_day_outcomes', [
            ['id' => 1, 'user_id' => 1, 'date' => '2026-11-20', 'outcome' => 'perfect'],
            ['id' => 2, 'user_id' => 1, 'date' => '2026-11-21', 'outcome' => 'frozen', 'reason' => 'Ferientag'],
            ['id' => 3, 'user_id' => 1, 'date' => '2026-11-22', 'outcome' => 'broken', 'reason' => 'Keine Aufgabe erledigt'],
        ]);
        self::rows('module_visits', [
            ['id' => 1, 'user_id' => 1, 'module_key' => 'planner'],
            ['id' => 2, 'user_id' => 1, 'module_key' => 'agenda'],
            ['id' => 3, 'user_id' => 2, 'module_key' => 'planner'],
        ]);
        self::rows('push_subscriptions', [
            ['id' => 1, 'user_id' => 1, 'endpoint' => 'https://web.push.example.test/send/abc123', 'endpoint_hash' => hash('sha256', 'https://web.push.example.test/send/abc123'), 'p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM', 'auth_token' => 'tBHItJI5svbpez7KI4CCXg', 'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X)'],
        ]);
    }

    private static function accessAndAdmin(): void
    {
        self::rows('personal_access_tokens', [
            ['id' => 1, 'tokenable_type' => 'App\\Models\\User', 'tokenable_id' => 1, 'name' => 'iPhone: Kurzbefehl', 'token' => hash('sha256', 'plain-token-niels'), 'abilities' => '["*"]', 'last_used_at' => '2026-11-22 18:40:00'],
        ]);
        self::rows('oauth_clients', [
            ['id' => '9c1f3b2e-0000-4a5e-9d1a-000000000001', 'name' => 'Claude', 'redirect_uris' => '["https://claude.ai/api/mcp/auth_callback"]', 'grant_types' => '["authorization_code","refresh_token"]', 'revoked' => 0],
        ]);
        self::rows('oauth_access_tokens', [
            ['id' => str_repeat('a1', 40), 'user_id' => 1, 'client_id' => '9c1f3b2e-0000-4a5e-9d1a-000000000001', 'scopes' => '["mcp:read"]', 'revoked' => 0, 'expires_at' => '2026-11-23 12:00:00'],
        ]);
        self::rows('oauth_refresh_tokens', [
            ['id' => str_repeat('b2', 40), 'access_token_id' => str_repeat('a1', 40), 'revoked' => 0, 'expires_at' => '2027-05-22 12:00:00'],
        ], stamps: false);
        self::rows('oauth_auth_codes', [
            ['id' => str_repeat('c3', 40), 'user_id' => 1, 'client_id' => '9c1f3b2e-0000-4a5e-9d1a-000000000001', 'scopes' => '["mcp:read"]', 'revoked' => 1, 'expires_at' => '2026-11-22 10:10:00'],
        ], stamps: false);
        self::rows('support_requests', [
            ['id' => 1, 'user_id' => 2, 'type' => 'feedback', 'subject' => 'Dunkler Modus', 'message' => 'Gibt es einen dunklen Modus? Abends blendet mich der Bildschirm.', 'status' => 'in_progress', 'response' => 'Danke, das kommt mit einem der nächsten Themes.', 'responded_by' => 1],
        ]);
        self::rows('feature_announcements', [
            ['id' => 1, 'title' => 'Neu: der Wochenplan', 'description' => 'Plane deine Woche mit Blöcken und Pausen.', 'related_module' => 'planner', 'created_by' => 1, 'is_published' => 1, 'published_at' => '2026-09-01 08:00:00', 'type' => 'info'],
        ]);
        self::rows('feature_announcement_dismissals', [
            ['id' => 1, 'feature_announcement_id' => 1, 'user_id' => 2],
        ]);
        self::rows('help_categories', [
            ['id' => 1, 'name' => 'Erste Schritte', 'sort_order' => 1],
        ]);
        self::rows('help_articles', [
            ['id' => 1, 'title' => 'So legst du eine Aufgabe an', 'content' => 'Tippe auf das Plus und schreibe, was dir einfällt.', 'help_category_id' => 1, 'created_by' => 1, 'is_published' => 1, 'published_at' => '2026-09-02 08:00:00', 'sort_order' => 1, 'slug' => 'aufgabe-anlegen'],
        ]);
        self::rows('error_occurrences', [
            ['id' => 1, 'status_code' => 500, 'exception_class' => 'ErrorException', 'message' => 'Undefined array key "x"', 'path' => '/app', 'method' => 'GET', 'user_id' => 2, 'user_agent' => 'Mozilla/5.0'],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  bool  $stamps  false for the few tables without created_at / updated_at
     */
    private static function rows(string $table, array $rows, bool $stamps = true): void
    {
        foreach ($rows as $row) {
            DB::table($table)->insert($stamps ? $row + ['created_at' => self::STAMP, 'updated_at' => self::STAMP] : $row);
        }
    }
}
