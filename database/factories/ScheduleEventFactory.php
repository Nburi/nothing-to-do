<?php

namespace Database\Factories;

use App\Models\ScheduleEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleEvent>
 */
class ScheduleEventFactory extends Factory
{
    public function definition(): array
    {
        $startMin = fake()->numberBetween(8, 18) * 60;
        $duration = fake()->randomElement([25, 45, 60, 90]);

        return [
            'user_id' => User::factory(),
            'template_id' => null,
            'category_id' => null,
            'title' => fake()->randomElement(['Schule', 'Lauftraining', 'Zahnarzt', 'Lerngruppe']),
            'color' => 'contour',
            'date' => now()->toDateString(),
            'start_time' => ScheduleEvent::fromMinutes($startMin),
            'end_time' => ScheduleEvent::fromMinutes($startMin + $duration),
            'is_cancelled' => false,
        ];
    }

    public function on(string $date): static
    {
        return $this->state(['date' => $date]);
    }

    public function at(string $start, string $end): static
    {
        return $this->state(['start_time' => $start, 'end_time' => $end]);
    }

    public function cancelled(): static
    {
        return $this->state(['is_cancelled' => true]);
    }
}
