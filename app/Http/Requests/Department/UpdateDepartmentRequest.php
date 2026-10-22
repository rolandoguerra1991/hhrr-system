<?php

namespace App\Http\Requests\Department;

use App\Concerns\DepartmentValidationRules;
use App\Models\Department;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateDepartmentRequest extends FormRequest
{
    use DepartmentValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $department = $this->route('department');

        return $this->departmentRules($department instanceof Department ? $department : null);
    }

    /**
     * Get the "after" validation callbacks that apply to the request.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        $department = $this->route('department');

        return $this->departmentParentGuards($department instanceof Department ? $department : null);
    }
}
