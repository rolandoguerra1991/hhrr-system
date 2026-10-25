<?php

namespace App\Http\Requests\Employee;

use App\Concerns\EmployeeValidationRules;
use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    use EmployeeValidationRules;

    /**
     * Get the validated employee data with concrete scalar types.
     *
     * @return array{first_name: string, last_name: string, work_email: string|null, user_id: int|null, reason: string}
     */
    public function employeeData(): array
    {
        return [
            'first_name' => $this->string('first_name')->value(),
            'last_name' => $this->string('last_name')->value(),
            'work_email' => $this->filled('work_email') ? $this->string('work_email')->value() : null,
            'user_id' => $this->filled('user_id') ? $this->integer('user_id') : null,
            'reason' => $this->string('reason')->value(),
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $employee = $this->route('employee');

        return [
            ...$this->employeeCoreRules($employee instanceof Employee ? $employee : null),
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => trim((string) $this->input('first_name', '')),
            'last_name' => trim((string) $this->input('last_name', '')),
            'work_email' => $this->normalizedWorkEmail($this->input('work_email')),
            'reason' => trim((string) $this->input('reason', '')),
        ]);
    }
}
