<?php

namespace App\Policies;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    /**
     * Apply the rules that run before every ability check.
     */
    public function before(User $user, string $ability, mixed $employee = null): ?bool
    {
        return $user->hasRole(RoleEnum::SuperAdmin->value) ? true : null;
    }

    /**
     * Determine whether the user can view the employee directory.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::EmployeesView->value);
    }

    /**
     * Determine whether the user can view the given employee.
     */
    public function view(User $user, Employee $employee): bool
    {
        return $user->can(PermissionEnum::EmployeesView->value);
    }

    /**
     * Determine whether the user can create employees.
     */
    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::EmployeesCreate->value);
    }

    /**
     * Determine whether the user can update the given employee.
     */
    public function update(User $user, Employee $employee): bool
    {
        return $user->can(PermissionEnum::EmployeesUpdate->value);
    }

    /**
     * Determine whether the user can update employees.
     */
    public function updateAny(User $user): bool
    {
        return $user->can(PermissionEnum::EmployeesUpdate->value);
    }
}
