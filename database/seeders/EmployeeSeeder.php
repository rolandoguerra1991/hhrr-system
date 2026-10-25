<?php

namespace Database\Seeders;

use App\Actions\Employee\AssignEmployee;
use App\Actions\Employee\CreateEmployee;
use App\Actions\Employee\TerminateEmployee;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    /**
     * Seed demo employees, assignments, a termination, and department managers.
     */
    public function run(): void
    {
        $actor = User::query()->orderBy('id')->first();

        if ($actor === null) {
            $this->command->warn('Skipping demo employees because no user exists to record the changes.');

            return;
        }

        $createEmployee = app(CreateEmployee::class);
        $assignEmployee = app(AssignEmployee::class);

        foreach ($this->activeEmployees() as $definition) {
            $employee = $this->ensureEmployee($createEmployee, $definition, $actor);
            $this->ensureAssignment($assignEmployee, $employee, $definition, $actor);
        }

        $formerEmployeeDefinition = $this->formerEmployee();
        $formerEmployee = $this->ensureEmployee($createEmployee, $formerEmployeeDefinition, $actor);
        $this->ensureAssignment($assignEmployee, $formerEmployee, $formerEmployeeDefinition, $actor);
        $this->ensureTermination($formerEmployee, $actor);

        $this->assignDemoManagers();
    }

    /**
     * Get the demo employees that are currently employed.
     *
     * @return list<array{first_name: string, last_name: string, work_email: string, department: string, position: string|null, started_on: string}>
     */
    private function activeEmployees(): array
    {
        return [
            [
                'first_name' => 'Grace',
                'last_name' => 'Hopper',
                'work_email' => 'grace.hopper@example.com',
                'department' => 'Human Resources',
                'position' => 'HR Manager',
                'started_on' => now()->subYears(3)->toDateString(),
            ],
            [
                'first_name' => 'Alan',
                'last_name' => 'Turing',
                'work_email' => 'alan.turing@example.com',
                'department' => 'Operations',
                'position' => 'Operations Manager',
                'started_on' => now()->subYears(2)->toDateString(),
            ],
            [
                'first_name' => 'Katherine',
                'last_name' => 'Johnson',
                'work_email' => 'katherine.johnson@example.com',
                'department' => 'Finance',
                'position' => 'Accountant',
                'started_on' => now()->subYear()->toDateString(),
            ],
            [
                'first_name' => 'Dennis',
                'last_name' => 'Ritchie',
                'work_email' => 'dennis.ritchie@example.com',
                'department' => 'Sales',
                'position' => 'Sales Representative',
                'started_on' => now()->subMonths(8)->toDateString(),
            ],
        ];
    }

    /**
     * Get the demo employee that provides terminated employment history.
     *
     * @return array{first_name: string, last_name: string, work_email: string, department: string, position: string|null, started_on: string}
     */
    private function formerEmployee(): array
    {
        return [
            'first_name' => 'Margaret',
            'last_name' => 'Hamilton',
            'work_email' => 'margaret.hamilton@example.com',
            'department' => 'Operations',
            'position' => 'Warehouse Associate',
            'started_on' => now()->subYears(2)->toDateString(),
        ];
    }

    /**
     * Create the employee unless the demo record already exists.
     *
     * @param  array{first_name: string, last_name: string, work_email: string, department: string, position: string|null, started_on: string}  $definition
     */
    private function ensureEmployee(CreateEmployee $createEmployee, array $definition, User $actor): Employee
    {
        $employee = Employee::query()
            ->where('work_email', $definition['work_email'])
            ->first();

        if ($employee !== null) {
            return $employee;
        }

        return $createEmployee->execute([
            'first_name' => $definition['first_name'],
            'last_name' => $definition['last_name'],
            'work_email' => $definition['work_email'],
            'started_on' => $definition['started_on'],
        ], $actor);
    }

    /**
     * Assign the demo department and position unless the employee already has a current assignment.
     *
     * @param  array{first_name: string, last_name: string, work_email: string, department: string, position: string|null, started_on: string}  $definition
     */
    private function ensureAssignment(AssignEmployee $assignEmployee, Employee $employee, array $definition, User $actor): void
    {
        if ($employee->employmentAssignments()->exists()) {
            return;
        }

        $department = Department::query()
            ->whereNull('parent_id')
            ->where('name', $definition['department'])
            ->firstOrFail();

        $position = $definition['position'] === null
            ? null
            : Position::query()
                ->where('name', $definition['position'])
                ->where('department_id', $department->id)
                ->first();

        $assignEmployee->execute($employee, [
            'department_id' => $department->id,
            'position_id' => $position?->id,
            'effective_from' => $definition['started_on'],
            'reason' => 'Initial assignment',
        ], $actor);
    }

    /**
     * Terminate the demo former employee once, keeping their history.
     */
    private function ensureTermination(Employee $employee, User $actor): void
    {
        if ($employee->currentPeriod()->doesntExist()) {
            return;
        }

        app(TerminateEmployee::class)->execute($employee, [
            'ended_on' => now()->subMonths(3)->toDateString(),
            'termination_reason' => 'End of demo employment',
        ], $actor);
    }

    /**
     * Set demo managers for departments that do not have one yet.
     */
    private function assignDemoManagers(): void
    {
        $managers = [
            'Human Resources' => 'grace.hopper@example.com',
            'Operations' => 'alan.turing@example.com',
        ];

        foreach ($managers as $departmentName => $workEmail) {
            $department = Department::query()
                ->whereNull('parent_id')
                ->where('name', $departmentName)
                ->first();

            if ($department === null || $department->manager_id !== null) {
                continue;
            }

            $manager = Employee::query()
                ->where('work_email', $workEmail)
                ->whereHas('currentPeriod')
                ->first();

            if ($manager === null) {
                continue;
            }

            $department->update(['manager_id' => $manager->id]);
        }
    }
}
