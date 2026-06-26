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
            'suggested_task_id' => null,
            'type' => ScheduleEvent::TYPE_APPOINTMENT,
            'title' => fake()->randomElement(['Schule', 'Lauftraining', 'Zahnarzt', 'Lerngruppe']),
            'color' => 'contour',
            'date' => now()->toDateString(),
            'start_time' => ScheduleEvent::fromMinutes($startMin),
            'end_time' => ScheduleEvent::fromMinutes($startMin + $duration),
            'source' => 'manual',
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

    public function workSession(): static
    {
        return $this->state([
            'type' => ScheduleEvent::TYPE_WORK,
            'title' => null,
            'color' => 'forest',
            'source' => 'brief',
        ]);
    }

    public function todoSession(): static
    {
        return $this->state([
            'type' => ScheduleEvent::TYPE_TODO,
            'title' => null,
            'color' => 'forest',
            'source' => 'brief',
        ]);
    }

    public function break(): static
    {
        return $this->state([
            'type' => ScheduleEvent::TYPE_BREAK,
            'title' => null,
            'color' => 'ink-faint',
            'source' => 'brief',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(['is_cancelled' => true]);
    }
}
