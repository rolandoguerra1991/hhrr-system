<?php

namespace App\Actions\Employee;

use App\Models\Employee;
use App\Models\EmployeeChangeLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateEmployee
{
    /**
     * Update the core employee fields and record an audit entry when they change.
     *
     * @param  array{first_name: string, last_name: string, work_email?: string|null, user_id?: int|null, reason: string}  $data
     */
    public function execute(Employee $employee, array $data, User $actor): Employee
    {
        return DB::transaction(function () use ($employee, $data, $actor): Employee {
            $employee = Employee::query()
                ->whereKey($employee->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $reason = trim($data['reason']);

            if ($reason === '') {
                throw ValidationException::withMessages([
                    'reason' => __('A reason is required to change an employee record.'),
                ]);
            }

            $userId = isset($data['user_id']) ? (int) $data['user_id'] : null;

            if ($userId !== null && Employee::query()
                ->where('user_id', $userId)
                ->whereKeyNot($employee->getKey())
                ->exists()) {
                throw ValidationException::withMessages([
                    'user_id' => __('This account is already linked to another employee.'),
                ]);
            }

            $auditedFields = ['first_name', 'last_name', 'work_email', 'user_id'];

            $oldValues = $employee->only($auditedFields);

            $newValues = [
                'first_name' => trim($data['first_name']),
                'last_name' => trim($data['last_name']),
                'work_email' => $this->normalizeWorkEmail($data['work_email'] ?? null),
                'user_id' => $userId,
            ];

            $changes = [];

            foreach ($newValues as $field => $newValue) {
                if ($oldValues[$field] !== $newValue) {
                    $changes[$field] = [
                        'old' => $oldValues[$field],
                        'new' => $newValue,
                    ];
                }
            }

            $employee->update($newValues);

            if ($changes !== []) {
                EmployeeChangeLog::create([
                    'employee_id' => $employee->id,
                    'changed_by' => $actor->id,
                    'reason' => $reason,
                    'changes' => $changes,
                ]);
            }

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
