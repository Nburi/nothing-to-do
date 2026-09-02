<?php

namespace Database\Factories;

use App\Livewire\Support\FamilyColors;
use App\Models\FamilySpace;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FamilySpace>
 */
class FamilySpaceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => fake()->randomElement(['Familie Meier', 'Familie Keller', 'Familie Huber', 'WG Bahnhofstrasse']),
            'invite_code' => FamilySpace::generateInviteCode(),
        ];
    }

    /**
     * The owner is a member too — every real space is created this way
     * (ManagesFamilySpaces::createFamilySpace), so a factory space without it
     * would let a test pass against a state the app can't actually produce.
     */
    public function configure(): static
    {
        return $this->afterCreating(
            fn (FamilySpace $space) => $space->members()->syncWithoutDetaching([
                $space->owner_id => ['color' => FamilyColors::KEYS[0]],
            ])
        );
    }

    public function withMembers(User ...$users): static
    {
        return $this->afterCreating(function (FamilySpace $space) use ($users) {
            foreach ($users as $user) {
                if (! $space->hasMember($user)) {
                    $space->members()->attach($user->id, ['color' => $space->nextAvailableColor()]);
                }
            }
        });
    }
}
