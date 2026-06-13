<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        $titles = [
            'Karten für Regio-OL drucken',
            'Intervalltraining planen',
            'Matheaufgaben S. 47–49',
            'Bio-Zusammenfassung Kapitel 4',
            'Aufsatz Deutsch entwerfen',
            'Laufschuhe einlaufen',
            'Wettkampfanmeldung absenden',
            'Vokabeln Französisch Lektion 8',
            'Physik-Praktikum vorbereiten',
            'Dehnen & Mobility',
            'Postenbeschreibung studieren',
            'Lernplan Prüfungswoche',
        ];

        return [
            'user_id' => User::factory(),
            'title' => fake()->randomElement($titles),
            'list' => 'inbox',
            'is_today' => false,
            'is_important' => false,
            'deadline' => null,
            'due_date' => null,
            'is_completed' => false,
            'completed_at' => null,
            'sort_order' => 0,
        ];
    }

    public function inbox(): static
    {
        return $this->state(['list' => 'inbox', 'is_today' => false]);
    }

    public function todos(): static
    {
        return $this->state(['list' => 'todos']);
    }

    public function tasks(): static
    {
        return $this->state(['list' => 'tasks']);
    }

    public function today(): static
    {
        // Today only makes sense outside the inbox.
        return $this->state(fn (array $attrs) => [
            'list' => $attrs['list'] === 'inbox' ? 'todos' : $attrs['list'],
            'is_today' => true,
        ]);
    }

    public function important(): static
    {
        return $this->state(['is_important' => true]);
    }

    public function completed(): static
    {
        return $this->state([
            'is_completed' => true,
            'completed_at' => now(),
        ]);
    }

    public function deadline(string $date): static
    {
        return $this->state(['deadline' => $date]);
    }

    public function dueDate(string $date): static
    {
        return $this->state(['due_date' => $date]);
    }
}
