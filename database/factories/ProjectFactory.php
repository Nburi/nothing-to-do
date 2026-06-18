<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        $names = [
            'Maturaarbeit',
            'Trainingslager Tessin',
            'Saisonplanung OL',
            'Zimmer umräumen',
            'Website Verein',
            'Velo aufmöbeln',
            'Leseliste Sommer',
        ];

        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement($names),
            'sort_order' => 0,
        ];
    }
}
