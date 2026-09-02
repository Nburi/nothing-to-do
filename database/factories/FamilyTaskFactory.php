<?php

namespace Database\Factories;

use App\Models\FamilySpace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\FamilyTask>
 */
class FamilyTaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'family_space_id' => FamilySpace::factory(),
            'title' => fake()->randomElement(['Müll rausbringen', 'Einkaufen', 'Geschirrspüler ausräumen', 'Wäsche aufhängen']),
        ];
    }
}
