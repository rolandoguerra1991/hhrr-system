<?php

namespace App\Policies;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use App\Models\User;
use Spatie\Permission\Models\Permission;

class PermissionPolicy
{
    /**
     * Apply the rules that run before every ability check.
     */
    public function before(User $user, string $ability, mixed $permission = null): ?bool
    {
        if ($ability === 'delete' && $this->isProtected($permission)) {
            return false;
        }

        return $user->hasRole(RoleEnum::SuperAdmin->value) ? true : null;
    }

    /**
     * Determine whether the user can view the permission catalogue.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::PermissionsView->value);
    }

    /**
     * Determine whether the user can create permissions.
     */
    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::PermissionsCreate->value);
    }

    /**
     * Determine whether the user can delete the given permission.
     */
    public function delete(User $user, Permission $permission): bool
    {
        return $user->can(PermissionEnum::PermissionsDelete->value);
    }

    /**
     * Determine if the permission is protected by the application.
     */
    private function isProtected(mixed $permission): bool
    {
        return $permission instanceof Permission
            && PermissionEnum::tryFrom($permission->name) !== null;
    }
}
