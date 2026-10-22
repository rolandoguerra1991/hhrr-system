<?php

namespace App\Policies;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use App\Models\User;

class UserPolicy
{
    /**
     * Apply the rules that run before every ability check.
     */
    public function before(User $user, string $ability, mixed $target = null): ?bool
    {
        if (in_array($ability, ['update', 'delete'], true) && $this->isProtected($user, $target)) {
            return false;
        }

        return $user->hasRole(RoleEnum::SuperAdmin->value) ? true : null;
    }

    /**
     * Determine whether the user can view the list of users.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::UsersView->value);
    }

    /**
     * Determine whether the user can create users.
     */
    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::UsersCreate->value);
    }

    /**
     * Determine whether the user can update the given user.
     */
    public function update(User $user, User $target): bool
    {
        return $user->can(PermissionEnum::UsersUpdate->value);
    }

    /**
     * Determine whether the user can delete the given user.
     */
    public function delete(User $user, User $target): bool
    {
        return $user->can(PermissionEnum::UsersDelete->value);
    }

    /**
     * Determine whether the user can decide who holds the super admin role.
     *
     * Super admins are granted this ability by `before()`, so every other
     * user is denied by default.
     */
    public function assignSuperAdmin(User $user): bool
    {
        return false;
    }

    /**
     * Determine if the account is off limits for this module.
     *
     * The account configured as the application admin is managed from the
     * environment, and users manage their own profile from the settings.
     */
    private function isProtected(User $user, mixed $target): bool
    {
        if (! $target instanceof User) {
            return false;
        }

        return $user->is($target) || $target->email === config('auth.admin.email');
    }
}
