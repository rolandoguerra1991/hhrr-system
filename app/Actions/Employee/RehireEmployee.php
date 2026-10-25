<?php

namespace App\Actions\Employee;

use App\Models\Employee;
use App\Models\EmploymentPeriod;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RehireEmployee
{
    /**
     * Create a new open employment period for a terminated employee.
     *
     * @param  array{started_on: string, start_reason: string}  $data
     */
    public function execute(Employee $employee, array $data, User $actor): EmploymentPeriod
    {
        return DB::transaction(function () use ($employee, $data, $actor): EmploymentPeriod {
            $employee = Employee::query()
                ->whereKey($employee->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($employee->currentPeriod()->exists()) {
                throw ValidationException::withMessages([
                    'employee' => __('This employee is already employed.'),
                ]);
            }

            $previousPeriod = $employee->employmentPeriods()
                ->orderByDesc('started_on')
                ->first();

            $startedOn = CarbonImmutable::parse($data['started_on'])->startOfDay();

            if ($startedOn->isAfter(CarbonImmutable::today())) {
                throw ValidationException::withMessages([
                    'started_on' => __('The rehire date cannot be in the future.'),
                ]);
            }

            if ($previousPeriod?->ended_on !== null && $startedOn->lessThanOrEqualTo($previousPeriod->ended_on)) {
                throw ValidationException::withMessages([
                    'started_on' => __('The rehire date must be after the previous employment end date.'),
                ]);
            }

            return EmploymentPeriod::create([
                'employee_id' => $employee->id,
                'started_on' => $startedOn,
                'ended_on' => null,
                'start_reason' => trim($data['start_reason']),
                'created_by' => $actor->id,
            ]);
        });
    }
}
