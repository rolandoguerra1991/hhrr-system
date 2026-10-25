<?php

namespace App\Http\Controllers;

use App\Actions\Employee\CreateEmployee;
use App\Actions\Employee\RehireEmployee;
use App\Actions\Employee\TerminateEmployee;
use App\Actions\Employee\UpdateEmployee;
use App\Http\Requests\Employee\RehireEmployeeRequest;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\TerminateEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    /**
     * Show the employee directory.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value();
        $status = $request->string('status')->trim()->value();

        $employees = Employee::query()
            ->with([
                'currentPeriod',
                'currentAssignment.department:id,name,manager_id',
                'currentAssignment.department.manager:id,first_name,last_name',
                'currentAssignment.position:id,name',
            ])
            ->when($search !== '', fn (Builder $query): Builder => $query->where(
                fn (Builder $query): Builder => $query
                    ->where('employee_number', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('work_email', 'like', "%{$search}%")
            ))
            ->when($status === 'active', fn (Builder $query): Builder => $query->whereHas('currentPeriod'))
            ->when($status === 'terminated', fn (Builder $query): Builder => $query->whereDoesntHave('currentPeriod'))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Employee $employee): array => [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'full_name' => $employee->full_name,
                'work_email' => $employee->work_email,
                'status' => $employee->isActive() ? 'active' : 'terminated',
                'current_department' => $employee->currentAssignment?->department?->name,
                'current_position' => $employee->currentAssignment?->position?->name,
                'reporting_manager' => $employee->reporting_manager?->full_name,
                'can' => [
                    'update' => $request->user()->can('update', $employee),
                ],
            ]);

        return Inertia::render('employees/Index', [
            'employees' => $employees,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Show the form for creating an employee.
     */
    public function create(): Response
    {
        return Inertia::render('employees/Create', [
            'users' => $this->userOptions(),
        ]);
    }

    /**
     * Store a newly created employee.
     */
    public function store(StoreEmployeeRequest $request, CreateEmployee $createEmployee): RedirectResponse
    {
        $employee = $createEmployee->execute($request->employeeData(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employee created.')]);

        return to_route('employees.show', $employee);
    }

    /**
     * Show the employee detail with employment, assignment, and audit history.
     */
    public function show(Request $request, Employee $employee): Response
    {
        $employee->load([
            'user:id,name,email',
            'currentPeriod',
            'currentAssignment.department:id,name,manager_id',
            'currentAssignment.department.manager:id,first_name,last_name',
            'currentAssignment.position:id,name',
            'employmentPeriods' => fn ($query) => $query->orderByDesc('started_on'),
            'employmentPeriods.creator:id,name',
            'employmentPeriods.terminator:id,name',
            'employmentAssignments' => fn ($query) => $query->orderByDesc('effective_from'),
            'employmentAssignments.department:id,name',
            'employmentAssignments.position:id,name',
            'employmentAssignments.changedBy:id,name',
            'changeLogs' => fn ($query) => $query->orderByDesc('created_at'),
            'changeLogs.changedBy:id,name',
            'departmentsManaged:id,name',
        ]);

        return Inertia::render('employees/Show', [
            'employee' => [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'full_name' => $employee->full_name,
                'work_email' => $employee->work_email,
                'status' => $employee->isActive() ? 'active' : 'terminated',
                'user' => $employee->user === null ? null : [
                    'id' => $employee->user->id,
                    'name' => $employee->user->name,
                    'email' => $employee->user->email,
                ],
                'current_period' => $employee->currentPeriod === null ? null : [
                    'started_on' => $employee->currentPeriod->started_on->toDateString(),
                    'start_reason' => $employee->currentPeriod->start_reason,
                ],
                'current_assignment' => $employee->currentAssignment === null ? null : [
                    'department_id' => $employee->currentAssignment->department_id,
                    'department_name' => $employee->currentAssignment->department->name,
                    'position_id' => $employee->currentAssignment->position_id,
                    'position_name' => $employee->currentAssignment->position?->name,
                    'effective_from' => $employee->currentAssignment->effective_from->toDateString(),
                ],
                'reporting_manager' => $employee->reporting_manager?->full_name,
                'departments_managed' => $employee->departmentsManaged
                    ->map(fn (Department $department): string => $department->name)
                    ->all(),
                'can' => [
                    'update' => $request->user()->can('update', $employee),
                ],
            ],
            'periods' => $employee->employmentPeriods
                ->map(fn ($period): array => [
                    'id' => $period->id,
                    'started_on' => $period->started_on->toDateString(),
                    'ended_on' => $period->ended_on?->toDateString(),
                    'start_reason' => $period->start_reason,
                    'termination_reason' => $period->termination_reason,
                    'created_by' => $period->creator?->name,
                    'terminated_by' => $period->terminator?->name,
                ])
                ->all(),
            'assignments' => $employee->employmentAssignments
                ->map(fn ($assignment): array => [
                    'id' => $assignment->id,
                    'department_name' => $assignment->department->name,
                    'position_name' => $assignment->position?->name,
                    'effective_from' => $assignment->effective_from->toDateString(),
                    'effective_to' => $assignment->effective_to?->toDateString(),
                    'change_reason' => $assignment->change_reason,
                    'changed_by' => $assignment->changedBy?->name,
                ])
                ->all(),
            'changeLogs' => $employee->changeLogs
                ->map(fn ($log): array => [
                    'id' => $log->id,
                    'reason' => $log->reason,
                    'changes' => $log->changes,
                    'changed_by' => $log->changedBy?->name,
                    'created_at' => $log->created_at->toDateTimeString(),
                ])
                ->all(),
            'departmentOptions' => $this->departmentOptions(),
            'positionOptions' => $this->positionOptions(),
        ]);
    }

    /**
     * Show the form for editing an employee's core details.
     */
    public function edit(Employee $employee): Response
    {
        return Inertia::render('employees/Edit', [
            'employee' => [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'work_email' => $employee->work_email,
                'user_id' => $employee->user_id,
            ],
            'users' => $this->userOptions($employee->user_id),
        ]);
    }

    /**
     * Update the employee's core details.
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee, UpdateEmployee $updateEmployee): RedirectResponse
    {
        $updateEmployee->execute($employee, $request->employeeData(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employee updated.')]);

        return to_route('employees.show', $employee);
    }

    /**
     * Terminate the employee's open employment period.
     */
    public function terminate(TerminateEmployeeRequest $request, Employee $employee, TerminateEmployee $terminateEmployee): RedirectResponse
    {
        $terminateEmployee->execute($employee, $request->terminationData(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employee terminated.')]);

        return to_route('employees.show', $employee);
    }

    /**
     * Rehire a terminated employee.
     */
    public function rehire(RehireEmployeeRequest $request, Employee $employee, RehireEmployee $rehireEmployee): RedirectResponse
    {
        $rehireEmployee->execute($employee, $request->rehireData(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employee rehired.')]);

        return to_route('employees.show', $employee);
    }

    /**
     * Get the login accounts that can be linked to an employee.
     *
     * @return array<int, array{id: int, name: string, email: string}>
     */
    private function userOptions(?int $linkedUserId = null): array
    {
        return User::query()
            ->where(fn (Builder $query): Builder => $query
                ->whereDoesntHave('employee')
                ->when($linkedUserId !== null, fn (Builder $query): Builder => $query->orWhereKey($linkedUserId)))
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->all();
    }

    /**
     * Get the departments available for an assignment.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function departmentOptions(): array
    {
        return Department::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Department $department): array => [
                'id' => $department->id,
                'name' => $department->name,
            ])
            ->all();
    }

    /**
     * Get the positions available for an assignment.
     *
     * @return array<int, array{id: int, name: string, department_id: int|null, department_name: string|null}>
     */
    private function positionOptions(): array
    {
        return Position::query()
            ->with('department:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Position $position): array => [
                'id' => $position->id,
                'name' => $position->name,
                'department_id' => $position->department_id,
                'department_name' => $position->department?->name,
            ])
            ->all();
    }
}
