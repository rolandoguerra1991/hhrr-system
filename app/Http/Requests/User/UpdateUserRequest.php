<?php

namespace App\Http\Requests\User;

use App\Concerns\DepartmentMembershipRules;
use App\Concerns\ProfileValidationRules;
use App\Concerns\RoleAssignmentRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    use DepartmentMembershipRules, ProfileValidationRules, RoleAssignmentRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $target = $this->route('user');
        $userId = $target instanceof User ? $target->id : null;

        return [
            ...$this->profileRules($userId),
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
            ...$this->roleRules(),
            ...$this->departmentMembershipRules(),
        ];
    }

    /**
     * Get the "after" validation callbacks that apply to the request.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return $this->roleAssignabilityGuards();
    }
}
