<?php

namespace App\Policies;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    /**
     * Apply the rules that run before every ability check.
     */
    public function before(User $user, string $ability, mixed $department = null): ?bool
    {
        return $user->hasRole(RoleEnum::SuperAdmin->value) ? true : null;
    }

    /**
     * Determine whether the user can view the department tree.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::DepartmentsView->value);
    }

    /**
     * Determine whether the user can create departments.
     */
    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::DepartmentsCreate->value);
    }

    /**
     * Determine whether the user can update the given department.
     */
    public function update(User $user, Department $department): bool
    {
        return $user->can(PermissionEnum::DepartmentsUpdate->value);
    }

    /**
     * Determine whether the user can delete the given department.
     */
    public function delete(User $user, Department $department): bool
    {
        return $user->can(PermissionEnum::DepartmentsDelete->value);
    }
}
