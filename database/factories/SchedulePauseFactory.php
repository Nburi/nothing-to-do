<?php

namespace Database\Factories;

use App\Models\SchedulePause;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchedulePause>
 */
class SchedulePauseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => fake()->dateTimeBetween('now', '+2 months')->format('Y-m-d'),
            'note' => null,
        ];
    }
}
