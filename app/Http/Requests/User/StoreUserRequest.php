<?php

namespace App\Http\Requests\User;

use App\Concerns\DepartmentMembershipRules;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Concerns\RoleAssignmentRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
{
    use DepartmentMembershipRules, PasswordValidationRules, ProfileValidationRules, RoleAssignmentRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
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
