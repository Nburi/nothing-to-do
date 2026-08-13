<?php

namespace Database\Factories;

use App\Models\GroupNote;
use App\Models\TaskGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupNote>
 */
class GroupNoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_group_id' => TaskGroup::factory(),
            'content' => fake()->sentence(),
            'sort_order' => 0,
        ];
    }
}
