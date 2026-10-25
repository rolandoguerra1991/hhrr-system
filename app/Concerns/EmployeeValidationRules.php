<?php

namespace App\Concerns;

use App\Models\Employee;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait EmployeeValidationRules
{
    /**
     * Get the validation rules shared by employee create and update requests.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string|Closure>>
     */
    protected function employeeCoreRules(?Employee $employee = null): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'work_email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail) use ($employee): void {
                    $email = $this->normalizedWorkEmail($value);

                    if ($email === null) {
                        return;
                    }

                    $duplicate = Employee::query()
                        ->whereRaw('LOWER(work_email) = ?', [$email])
                        ->when(
                            $employee instanceof Employee,
                            fn ($query) => $query->whereKeyNot($employee->getKey()),
                        )
                        ->exists();

                    if ($duplicate) {
                        $fail(__('This work email is already used by another employee.'));
                    }
                },
            ],
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
                function (string $attribute, mixed $value, Closure $fail) use ($employee): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $duplicate = Employee::query()
                        ->where('user_id', (int) $value)
                        ->when(
                            $employee instanceof Employee,
                            fn ($query) => $query->whereKeyNot($employee->getKey()),
                        )
                        ->exists();

                    if ($duplicate) {
                        $fail(__('This account is already linked to another employee.'));
                    }
                },
            ],
        ];
    }

    /**
     * Normalize the submitted work email.
     */
    protected function normalizedWorkEmail(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $email = mb_strtolower(trim((string) $value));

        return $email === '' ? null : $email;
    }
}
