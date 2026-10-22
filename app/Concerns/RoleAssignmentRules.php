<?php

namespace App\Concerns;

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait RoleAssignmentRules
{
    /**
     * Get the validation rules used to validate assigned roles.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function roleRules(): array
    {
        return [
            'roles' => ['array'],
            'roles.*' => [
                'string',
                Rule::exists('roles', 'name')->where('guard_name', 'web'),
            ],
        ];
    }

    /**
     * Get the callbacks that guard the super admin membership.
     *
     * @return array<int, callable(Validator): void>
     */
    protected function roleAssignabilityGuards(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->user()?->can('assignSuperAdmin', User::class)) {
                    return;
                }

                if ($this->changesSuperAdminMembership()) {
                    $validator->errors()->add('roles', __('Only a Super Admin can change who holds the :role role.', ['role' => RoleEnum::SuperAdmin->value]));
                }
            },
        ];
    }

    /**
     * Determine if the request changes who holds the super admin role.
     */
    private function changesSuperAdminMembership(): bool
    {
        $target = $this->route('user');
        $superAdminRole = RoleEnum::SuperAdmin->value;

        return in_array($superAdminRole, $target instanceof User ? $target->getRoleNames()->all() : [], true)
            !== in_array($superAdminRole, $this->submittedRoles(), true);
    }

    /**
     * Get the submitted role names.
     *
     * @return array<int, string>
     */
    private function submittedRoles(): array
    {
        return array_map('strval', (array) $this->input('roles', []));
    }
}
