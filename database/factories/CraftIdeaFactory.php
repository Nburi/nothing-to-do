<?php

namespace Database\Factories;

use App\Models\CraftIdea;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CraftIdea>
 */
class CraftIdeaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'where_to_begin' => fake()->optional()->sentence(6),
            'is_done' => false,
        ];
    }

    public function done(): static
    {
        return $this->state(['is_done' => true]);
    }
}
