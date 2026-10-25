<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmploymentPeriod;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmploymentPeriod>
 */
class EmploymentPeriodFactory extends Factory
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
            'started_on' => now()->subYear()->toDateString(),
            'ended_on' => null,
            'start_reason' => 'Initial hire',
            'termination_reason' => null,
            'created_by' => null,
            'terminated_by' => null,
        ];
    }

    /**
     * Indicate that the period is still open.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'ended_on' => null,
            'termination_reason' => null,
            'terminated_by' => null,
        ]);
    }

    /**
     * Indicate that the period ended on the given date.
     */
    public function terminated(?CarbonInterface $endedOn = null): static
    {
        $endedOn ??= now()->subMonth();

        return $this->state(fn (array $attributes): array => [
            'started_on' => $endedOn->copy()->subYear()->toDateString(),
            'ended_on' => $endedOn->toDateString(),
            'termination_reason' => 'Employment ended',
            'terminated_by' => null,
        ]);
    }
}
