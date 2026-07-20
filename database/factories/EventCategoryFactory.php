<?php

namespace Database\Factories;

use App\Models\EventCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventCategory>
 */
class EventCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Schule', 'Training', 'Arbeiten', 'Abmachen']),
            'color' => fake()->randomElement(['contour', 'overprint', 'forest', 'signal']),
            'pomodoro_enabled' => false,
            'sort_order' => 0,
        ];
    }

    public function pomodoro(): static
    {
        return $this->state(['pomodoro_enabled' => true]);
    }
}
