<?php

namespace App\Policies;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    /**
     * Apply the rules that run before every ability check.
     */
    public function before(User $user, string $ability, mixed $role = null): ?bool
    {
        if (in_array($ability, ['update', 'delete'], true) && $this->isProtected($role)) {
            return false;
        }

        return $user->hasRole(RoleEnum::SuperAdmin->value) ? true : null;
    }

    /**
     * Determine whether the user can view the list of roles.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::RolesView->value);
    }

    /**
     * Determine whether the user can create roles.
     */
    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::RolesCreate->value);
    }

    /**
     * Determine whether the user can update the given role.
     */
    public function update(User $user, Role $role): bool
    {
        return $user->can(PermissionEnum::RolesUpdate->value);
    }

    /**
     * Determine whether the user can delete the given role.
     */
    public function delete(User $user, Role $role): bool
    {
        return $user->can(PermissionEnum::RolesDelete->value);
    }

    /**
     * Determine if the role is protected by the application.
     */
    private function isProtected(mixed $role): bool
    {
        return $role instanceof Role && $role->name === RoleEnum::SuperAdmin->value;
    }
}
