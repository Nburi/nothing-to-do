<?php

namespace Database\Factories;

use App\Models\Reminder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reminder>
 */
class ReminderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->words(3, true),
            'remind_on' => now()->addDay()->toDateString(),
            'notify_evening_before' => true,
            'notify_on_day' => true,
        ];
    }

    public function on(string $date): static
    {
        return $this->state(['remind_on' => $date]);
    }

    public function acknowledged(): static
    {
        return $this->state(['acknowledged_at' => now()]);
    }
}
