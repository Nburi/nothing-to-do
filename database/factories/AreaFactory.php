<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Area>
 */
class AreaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Schule', 'Sport', 'Eigene Projekte', 'Haushalt']),
            'color' => 'ink',
            'sort_order' => 0,
        ];
    }
}
