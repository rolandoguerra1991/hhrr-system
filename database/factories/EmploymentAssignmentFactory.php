<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentAssignment;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmploymentAssignment>
 */
class EmploymentAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'department_id' => Department::factory(),
            'position_id' => null,
            'effective_from' => now()->subMonth()->toDateString(),
            'effective_to' => null,
            'change_reason' => 'Initial assignment',
            'changed_by' => null,
        ];
    }

    /**
     * Indicate that the assignment is still current.
     */
    public function current(): static
    {
        return $this->state(fn (array $attributes): array => [
            'effective_to' => null,
        ]);
    }

    /**
     * Indicate that the assignment ended on the given date.
     */
    public function endedOn(CarbonInterface $effectiveTo): static
    {
        return $this->state(fn (array $attributes): array => [
            'effective_to' => $effectiveTo->toDateString(),
        ]);
    }
}
