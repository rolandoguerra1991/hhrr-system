<?php

namespace App\Http\Requests\Employee;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TerminateEmployeeRequest extends FormRequest
{
    /**
     * Get the validated termination data with concrete scalar types.
     *
     * @return array{ended_on: string, termination_reason: string}
     */
    public function terminationData(): array
    {
        return [
            'ended_on' => $this->string('ended_on')->value(),
            'termination_reason' => $this->string('termination_reason')->value(),
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
            'ended_on' => ['required', 'date', 'before_or_equal:today'],
            'termination_reason' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'termination_reason' => trim((string) $this->input('termination_reason', '')),
        ]);
    }
}
