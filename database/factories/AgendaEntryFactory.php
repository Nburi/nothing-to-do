<?php

namespace Database\Factories;

use App\Models\AgendaEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgendaEntry>
 */
class AgendaEntryFactory extends Factory
{
    public function definition(): array
    {
        $subjects = ['Mathematik', 'Deutsch', 'Physik', 'Französisch', 'Chemie', 'Geschichte'];

        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(array_keys(AgendaEntry::TYPES)),
            'subject' => fake()->randomElement($subjects),
            'title' => fake()->sentence(4),
            'date' => fake()->dateTimeBetween('-3 days', '+2 weeks')->format('Y-m-d'),
            'is_done' => false,
        ];
    }

    public function homework(): static
    {
        return $this->state(['type' => 'homework']);
    }

    public function exam(): static
    {
        return $this->state(['type' => 'exam']);
    }

    public function done(): static
    {
        return $this->state(['is_done' => true]);
    }
}
