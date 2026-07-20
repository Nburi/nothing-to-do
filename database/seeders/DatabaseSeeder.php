<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Niels',
            'email' => 'niels@5033.ch',
            'password' => Hash::make('password'),
        ]);

        // Default schedule categories — Arbeiten drives the dashboard's Pomodoro timer.
        $user->eventCategories()->createMany([
            ['name' => 'Schule', 'color' => 'contour', 'pomodoro_enabled' => false, 'sort_order' => 0],
            ['name' => 'Training', 'color' => 'forest', 'pomodoro_enabled' => false, 'sort_order' => 1],
            ['name' => 'Arbeiten', 'color' => 'overprint', 'pomodoro_enabled' => true, 'sort_order' => 2],
            ['name' => 'Abmachen', 'color' => 'signal', 'pomodoro_enabled' => false, 'sort_order' => 3],
        ]);

        // Inbox — freshly captured, untriaged.
        Task::factory()->for($user)->inbox()->createMany([
            ['title' => 'Wettkampfanmeldung Regio-OL'],
            ['title' => 'Buch zurück in Bibliothek'],
            ['title' => 'Trainingsplan mit Trainer besprechen'],
        ]);

        // To-Dos — small, several per session.
        Task::factory()->for($user)->todos()->today()->important()
            ->create(['title' => 'Matheaufgaben S. 47–49', 'deadline' => now()->addDay()->toDateString()]);
        Task::factory()->for($user)->todos()->today()
            ->create(['title' => 'Vokabeln Französisch Lektion 8']);
        Task::factory()->for($user)->todos()
            ->create(['title' => 'Laufschuhe einlaufen', 'due_date' => now()->addDays(3)->toDateString()]);
        Task::factory()->for($user)->todos()->important()
            ->create(['title' => 'Postenbeschreibung studieren']);
        Task::factory()->for($user)->todos()
            ->create(['title' => 'Dehnen & Mobility']);

        // Tasks — bigger single steps.
        Task::factory()->for($user)->tasks()->today()
            ->create(['title' => 'Bio-Zusammenfassung Kapitel 4', 'deadline' => now()->addDays(2)->toDateString()]);
        Task::factory()->for($user)->tasks()->important()
            ->create(['title' => 'Aufsatz Deutsch entwerfen', 'deadline' => now()->subDay()->toDateString()]);
        Task::factory()->for($user)->tasks()
            ->create(['title' => 'Physik-Praktikum vorbereiten', 'due_date' => now()->addDays(6)->toDateString()]);

        // A completed task (hidden from the board, kept in the DB).
        Task::factory()->for($user)->todos()->completed()
            ->create(['title' => 'Karten für Testlauf gedruckt']);

        // Projects — non-urgent, multi-part work parked for later.
        $matura = $user->projects()->create([
            'name' => 'Maturaarbeit',
            'brainstorm' => <<<'MD'
                ## Mögliche Themen

                - Laktatschwelle im OL-Training
                - Kartenlesen unter Belastung
                - **Favorit:** Pacing-Strategien auf der Langdistanz

                ## Offene Fragen

                - [ ] Betreuer:in fragen, ob das Thema trägt
                - [ ] Reicht ein Halbjahr für die Datenerhebung?
                - [x] Grobthema mit Trainer besprochen

                ## Notiz

                Idee: GPS-Daten von Wettkämpfen mit dem Höhenprofil verknüpfen. Siehe [omap.ch](https://omap.ch).
                MD,
        ]);
        $matura->tasks()->createMany([
            ['user_id' => $user->id, 'list' => 'projects', 'title' => 'Themenwahl finalisieren', 'is_important' => true],
            ['user_id' => $user->id, 'list' => 'projects', 'title' => 'Literatur recherchieren'],
            ['user_id' => $user->id, 'list' => 'projects', 'title' => 'Gliederung entwerfen'],
            ['user_id' => $user->id, 'list' => 'projects', 'title' => 'Betreuer angefragt', 'is_completed' => true, 'completed_at' => now()],
        ]);

        $saison = $user->projects()->create(['name' => 'Saisonplanung OL']);
        $saison->tasks()->createMany([
            ['user_id' => $user->id, 'list' => 'projects', 'title' => 'Wettkampfkalender sichten', 'due_date' => now()->addDays(5)->toDateString()],
            ['user_id' => $user->id, 'list' => 'projects', 'title' => 'Trainingslager Tessin buchen'],
        ]);

        // A fresh, still-empty project.
        $user->projects()->create(['name' => 'Zimmer umräumen']);
    }
}
