<?php

namespace App\Policies;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use App\Models\Position;
use App\Models\User;

class PositionPolicy
{
    /**
     * Apply the rules that run before every ability check.
     */
    public function before(User $user, string $ability, mixed $position = null): ?bool
    {
        return $user->hasRole(RoleEnum::SuperAdmin->value) ? true : null;
    }

    /**
     * Determine whether the user can view the position list.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::PositionsView->value);
    }

    /**
     * Determine whether the user can create positions.
     */
    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::PositionsCreate->value);
    }

    /**
     * Determine whether the user can update the given position.
     */
    public function update(User $user, Position $position): bool
    {
        return $user->can(PermissionEnum::PositionsUpdate->value);
    }

    /**
     * Determine whether the user can delete the given position.
     */
    public function delete(User $user, Position $position): bool
    {
        return $user->can(PermissionEnum::PositionsDelete->value);
    }
}
