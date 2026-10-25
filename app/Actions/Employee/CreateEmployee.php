<?php

namespace App\Actions\Employee;

use App\Models\Employee;
use App\Models\EmploymentPeriod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateEmployee
{
    public function __construct(private GenerateEmployeeNumber $generateEmployeeNumber) {}

    /**
     * Create an employee and their first open employment period.
     *
     * @param  array{first_name: string, last_name: string, work_email?: string|null, user_id?: int|null, started_on: string}  $data
     */
    public function execute(array $data, User $actor): Employee
    {
        return DB::transaction(function () use ($data, $actor): Employee {
            $userId = isset($data['user_id']) ? (int) $data['user_id'] : null;

            if ($userId !== null && Employee::query()->where('user_id', $userId)->exists()) {
                throw ValidationException::withMessages([
                    'user_id' => __('This account is already linked to another employee.'),
                ]);
            }

            $employee = Employee::create([
                'employee_number' => $this->generateEmployeeNumber->execute(),
                'first_name' => trim($data['first_name']),
                'last_name' => trim($data['last_name']),
                'work_email' => $this->normalizeWorkEmail($data['work_email'] ?? null),
                'user_id' => $userId,
            ]);

            EmploymentPeriod::create([
                'employee_id' => $employee->id,
                'started_on' => $data['started_on'],
                'ended_on' => null,
                'start_reason' => 'Initial hire',
                'created_by' => $actor->id,
            ]);

            return $employee;
        });
    }

    /**
     * Normalize an optional work email for storage and comparison.
     */
    private function normalizeWorkEmail(?string $workEmail): ?string
    {
        if ($workEmail === null || trim($workEmail) === '') {
            return null;
        }

        return mb_strtolower(trim($workEmail));
    }
}
