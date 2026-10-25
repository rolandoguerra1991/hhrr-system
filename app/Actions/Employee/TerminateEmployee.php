<?php

namespace App\Actions\Employee;

use App\Models\Employee;
use App\Models\EmploymentPeriod;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TerminateEmployee
{
    /**
     * Close the employee's open employment period and current assignment.
     *
     * @param  array{ended_on: string, termination_reason: string}  $data
     */
    public function execute(Employee $employee, array $data, User $actor): EmploymentPeriod
    {
        return DB::transaction(function () use ($employee, $data, $actor): EmploymentPeriod {
            $employee = Employee::query()
                ->whereKey($employee->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $currentPeriod = $employee->currentPeriod()->first();

            if ($currentPeriod === null) {
                throw ValidationException::withMessages([
                    'employee' => __('This employee is not currently employed.'),
                ]);
            }

            $endedOn = CarbonImmutable::parse($data['ended_on'])->startOfDay();

            if ($endedOn->isAfter(CarbonImmutable::today())) {
                throw ValidationException::withMessages([
                    'ended_on' => __('The termination date cannot be in the future.'),
                ]);
            }

            if ($endedOn->isBefore($currentPeriod->started_on)) {
                throw ValidationException::withMessages([
                    'ended_on' => __('The termination date cannot be before the employment start date.'),
                ]);
            }

            $managedDepartments = $employee->departmentsManaged()
                ->orderBy('name')
                ->pluck('name')
                ->all();

            if ($managedDepartments !== []) {
                throw ValidationException::withMessages([
                    'employee' => __('This employee manages the following departments and cannot be terminated: :departments.', [
                        'departments' => implode(', ', $managedDepartments),
                    ]),
                ]);
            }

            $currentAssignment = $employee->currentAssignment()->first();

            if ($currentAssignment !== null) {
                if ($endedOn->isBefore($currentAssignment->effective_from)) {
                    throw ValidationException::withMessages([
                        'ended_on' => __('The termination date cannot be before the current assignment start date.'),
                    ]);
                }

                $currentAssignment->update([
                    'effective_to' => $endedOn,
                ]);
            }

            $currentPeriod->update([
                'ended_on' => $endedOn,
                'termination_reason' => trim($data['termination_reason']),
                'terminated_by' => $actor->id,
            ]);

            $this->invalidateUserSessions($employee);

            return $currentPeriod;
        });
    }

    /**
     * Clear the linked account's remember token and active database sessions.
     */
    private function invalidateUserSessions(Employee $employee): void
    {
        $user = $employee->user;

        if ($user === null) {
            return;
        }

        if (config('session.driver') === 'database') {
            $table = config('session.table', 'sessions');

            if (Schema::hasTable($table)) {
                DB::table($table)->where('user_id', $user->getKey())->delete();
            }
        }

        $user->forceFill(['remember_token' => null])->save();
    }
}
