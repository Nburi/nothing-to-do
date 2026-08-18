<?php

namespace Database\Factories;

use App\Models\TaskGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskGroup>
 */
class TaskGroupFactory extends Factory
{
    public function definition(): array
    {
        $names = [
            'Vortrag Klimawandel',
            'Zimmer umstellen',
            'Anmeldung Trainingslager',
            'Velo-Service',
            'Bewerbung Ferienjob',
        ];

        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement($names),
            'sort_order' => 0,
        ];
    }
}
