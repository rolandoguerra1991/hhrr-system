<?php

namespace App\Http\Requests\Employee;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeAssignmentRequest extends FormRequest
{
    /**
     * Get the validated assignment data with concrete scalar types.
     *
     * @return array{department_id: int, position_id: int|null, effective_from: string, reason: string}
     */
    public function assignmentData(): array
    {
        return [
            'department_id' => $this->integer('department_id'),
            'position_id' => $this->filled('position_id') ? $this->integer('position_id') : null,
            'effective_from' => $this->string('effective_from')->value(),
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
        return [
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'effective_from' => ['required', 'date', 'before_or_equal:today'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'reason' => trim((string) $this->input('reason', '')),
        ]);
    }
}
