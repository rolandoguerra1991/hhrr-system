<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait DepartmentMembershipRules
{
    /**
     * Get the validation rules used to assign a user to a department.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function departmentMembershipRules(): array
    {
        return [
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
        ];
    }
}
