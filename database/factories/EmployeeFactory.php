<?php

namespace Database\Factories;

use App\Actions\Employee\GenerateEmployeeNumber;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentAssignment;
use App\Models\EmploymentPeriod;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_number' => app(GenerateEmployeeNumber::class)->execute(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'work_email' => fake()->unique()->safeEmail(),
            'user_id' => null,
        ];
    }

    /**
     * Indicate that the employee has no work email.
     */
    public function withoutWorkEmail(): static
    {
        return $this->state(fn (array $attributes): array => [
            'work_email' => null,
        ]);
    }

    /**
     * Indicate that the employee is linked to the given login account.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Indicate that the employee has an open employment period.
     */
    public function active(): static
    {
        return $this->afterCreating(function (Employee $employee): void {
            EmploymentPeriod::factory()
                ->for($employee)
                ->active()
                ->create([
                    'started_on' => now()->subYear()->toDateString(),
                ]);
        });
    }

    /**
     * Indicate that the employee has a closed employment period.
     */
    public function terminated(): static
    {
        return $this->afterCreating(function (Employee $employee): void {
            EmploymentPeriod::factory()
                ->for($employee)
                ->terminated()
                ->create();
        });
    }

    /**
     * Indicate that the employee has an open period and a current assignment.
     */
    public function assigned(?Department $department = null, ?Position $position = null): static
    {
        return $this->active()->afterCreating(function (Employee $employee) use ($department, $position): void {
            $department ??= Department::factory()->create();

            EmploymentAssignment::factory()
                ->for($employee)
                ->for($department)
                ->current()
                ->create([
                    'position_id' => $position?->id,
                    'effective_from' => now()->subMonth()->toDateString(),
                ]);
        });
    }
}
