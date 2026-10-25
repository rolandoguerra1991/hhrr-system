<?php

namespace App\Concerns;

use App\Models\Position;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait PositionValidationRules
{
    /**
     * Get the validation rules used to validate positions.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string|Closure>>
     */
    protected function positionRules(?Position $position = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail) use ($position): void {
                    $name = mb_strtolower(trim((string) $value));
                    $departmentId = $this->submittedDepartmentId();

                    $duplicate = Position::query()
                        ->whereRaw('LOWER(name) = ?', [$name])
                        ->when(
                            $departmentId === null,
                            fn (Builder $query): Builder => $query->whereNull('department_id'),
                            fn (Builder $query): Builder => $query->where('department_id', $departmentId),
                        )
                        ->when(
                            $position instanceof Position,
                            fn (Builder $query): Builder => $query->whereKeyNot($position->getKey()),
                        )
                        ->exists();

                    if ($duplicate) {
                        $fail(__('A position with this name already exists in the same department or company-wide scope.'));
                    }
                },
            ],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
        ];
    }

    /**
     * Reject department-scope changes after assignments reference a position.
     *
     * @return array<int, callable(Validator): void>
     */
    protected function positionScopeGuards(?Position $position): array
    {
        return [
            function (Validator $validator) use ($position): void {
                if (! $position instanceof Position || $validator->errors()->has('department_id')) {
                    return;
                }

                if (! $position->employmentAssignments()->exists()) {
                    return;
                }

                if ($this->submittedDepartmentId() === $position->department_id) {
                    return;
                }

                $validator->errors()->add(
                    'department_id',
                    __('A position with assignment history cannot be moved to another department.'),
                );
            },
        ];
    }

    /**
     * Get the submitted department id, treating blank values as company-wide.
     */
    private function submittedDepartmentId(): ?int
    {
        $departmentId = $this->input('department_id');

        return $departmentId === null || $departmentId === '' ? null : (int) $departmentId;
    }
}
