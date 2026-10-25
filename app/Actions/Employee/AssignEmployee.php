<?php

namespace App\Actions\Employee;

use App\Models\Employee;
use App\Models\EmploymentAssignment;
use App\Models\Position;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignEmployee
{
    /**
     * Append a department assignment for an active employee.
     *
     * @param  array{department_id: int, position_id?: int|null, effective_from: string, reason: string}  $data
     */
    public function execute(Employee $employee, array $data, User $actor): EmploymentAssignment
    {
        return DB::transaction(function () use ($employee, $data, $actor): EmploymentAssignment {
            $employee = Employee::query()
                ->whereKey($employee->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $currentPeriod = $employee->currentPeriod()->first();

            if ($currentPeriod === null) {
                throw ValidationException::withMessages([
                    'employee' => __('A terminated employee cannot receive a new assignment.'),
                ]);
            }

            $effectiveFrom = CarbonImmutable::parse($data['effective_from'])->startOfDay();

            if ($effectiveFrom->isAfter(CarbonImmutable::today())) {
                throw ValidationException::withMessages([
                    'effective_from' => __('The effective date cannot be in the future.'),
                ]);
            }

            if ($effectiveFrom->isBefore($currentPeriod->started_on)) {
                throw ValidationException::withMessages([
                    'effective_from' => __('The effective date cannot be before the employment start date.'),
                ]);
            }

            $departmentId = (int) $data['department_id'];
            $positionId = $this->positionId($data);

            if ($positionId !== null) {
                $position = Position::query()->find($positionId);

                if ($position === null) {
                    throw ValidationException::withMessages([
                        'position_id' => __('The selected position does not exist.'),
                    ]);
                }

                if ($position->department_id !== null && (int) $position->department_id !== $departmentId) {
                    throw ValidationException::withMessages([
                        'position_id' => __('The selected position belongs to another department.'),
                    ]);
                }
            }

            $currentAssignment = $employee->currentAssignment()->first();

            if ($currentAssignment !== null) {
                if ($effectiveFrom->lessThanOrEqualTo($currentAssignment->effective_from)) {
                    throw ValidationException::withMessages([
                        'effective_from' => __('The effective date must be later than the current assignment start date.'),
                    ]);
                }

                $currentAssignment->update([
                    'effective_to' => $effectiveFrom->subDay(),
                ]);
            }

            return EmploymentAssignment::create([
                'employee_id' => $employee->id,
                'department_id' => $departmentId,
                'position_id' => $positionId,
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'change_reason' => trim($data['reason']),
                'changed_by' => $actor->id,
            ]);
        });
    }

    /**
     * Get the optional position identifier from the submitted data.
     *
     * @param  array{position_id?: int|null}  $data
     */
    private function positionId(array $data): ?int
    {
        return $data['position_id'] ?? null;
    }
}
