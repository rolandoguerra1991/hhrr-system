<?php

namespace App\Http\Controllers;

use App\Actions\Employee\AssignEmployee;
use App\Http\Requests\Employee\StoreEmployeeAssignmentRequest;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class EmployeeAssignmentController extends Controller
{
    /**
     * Append a new department assignment for the employee.
     */
    public function store(StoreEmployeeAssignmentRequest $request, Employee $employee, AssignEmployee $assignEmployee): RedirectResponse
    {
        $assignEmployee->execute($employee, $request->assignmentData(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employee assignment updated.')]);

        return to_route('employees.show', $employee);
    }
}
