<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Task> */
class TaskFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'owner' => 'user:1',
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'status' => TaskStatus::Open,
        ];
    }

    public function ownedBy(string $owner): static
    {
        return $this->state(['owner' => $owner]);
    }
}
