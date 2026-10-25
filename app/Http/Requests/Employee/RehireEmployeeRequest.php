<?php

namespace App\Http\Requests\Employee;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RehireEmployeeRequest extends FormRequest
{
    /**
     * Get the validated rehire data with concrete scalar types.
     *
     * @return array{started_on: string, start_reason: string}
     */
    public function rehireData(): array
    {
        return [
            'started_on' => $this->string('started_on')->value(),
            'start_reason' => $this->string('start_reason')->value(),
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
            'started_on' => ['required', 'date', 'before_or_equal:today'],
            'start_reason' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'start_reason' => trim((string) $this->input('start_reason', '')),
        ]);
    }
}
