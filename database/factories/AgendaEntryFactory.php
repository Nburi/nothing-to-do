<?php

namespace Database\Factories;

use App\Models\AgendaEntry;
use App\Models\AgendaSpace;
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

    /** Shared with a class/group instead of being private to its creator. */
    public function inSpace(AgendaSpace $space): static
    {
        return $this->state(['agenda_space_id' => $space->id]);
    }

    /**
     * Done *for its own creator*. "Done" is per person now, so it can't be a
     * plain column state — it's a completion row, written after the entry (and
     * therefore its user_id) actually exists.
     */
    public function done(): static
    {
        return $this->afterCreating(
            fn (AgendaEntry $entry) => $entry->completedBy()->syncWithoutDetaching([$entry->user_id])
        );
    }

    /** Done for somebody else — a classmate ticking off a shared entry. */
    public function completedByUser(User $user): static
    {
        return $this->afterCreating(
            fn (AgendaEntry $entry) => $entry->completedBy()->syncWithoutDetaching([$user->id])
        );
    }

    public function duration(int $minutes): static
    {
        return $this->state(['duration_minutes' => $minutes]);
    }
}
