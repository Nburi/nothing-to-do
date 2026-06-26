<?php

namespace Database\Factories;

use App\Models\EventTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventTemplate>
 */
class EventTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Schule', 'Lauftraining', 'Zahnarzt', 'Krafttraining', 'Nachhilfe']),
            'color' => fake()->randomElement(['contour', 'overprint', 'forest', 'signal']),
            'duration' => fake()->randomElement([30, 45, 60, 90, 120]),
            'default_start' => fake()->randomElement([null, '08:00', '14:00', '17:30']),
            'is_recurring' => false,
            'recurrence' => null,
            'sort_order' => 0,
        ];
    }

    /** A weekly recurring template; pass ISO weekdays, defaults to Mon–Fri. */
    public function recurring(string $days = '1,2,3,4,5'): static
    {
        return $this->state([
            'is_recurring' => true,
            'recurrence' => $days,
            'default_start' => '08:00',
        ]);
    }
}
