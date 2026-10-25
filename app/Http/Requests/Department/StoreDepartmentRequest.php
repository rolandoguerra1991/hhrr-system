<?php

namespace App\Http\Requests\Department;

use App\Concerns\DepartmentValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDepartmentRequest extends FormRequest
{
    use DepartmentValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->departmentRules();
    }

    /**
     * Get the validation callbacks that require an active department manager.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return $this->departmentManagerGuards();
    }
}
