<?php

namespace Database\Factories;

use App\Models\AgendaSpace;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgendaSpace>
 */
class AgendaSpaceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => fake()->randomElement(['Klasse 4b', 'Klasse 3a', 'Bio-Lerngruppe', 'Wahlfach Informatik']),
            'invite_code' => AgendaSpace::generateInviteCode(),
        ];
    }

    /**
     * The owner is a member too — every real space is created this way
     * (AgendaSpaces::createSpace), so a factory space without it would let a
     * test pass against a state the app can't actually produce.
     */
    public function configure(): static
    {
        return $this->afterCreating(
            fn (AgendaSpace $space) => $space->members()->syncWithoutDetaching([$space->owner_id])
        );
    }

    public function withMembers(User ...$users): static
    {
        return $this->afterCreating(function (AgendaSpace $space) use ($users) {
            $space->members()->syncWithoutDetaching(collect($users)->pluck('id')->all());
        });
    }
}
