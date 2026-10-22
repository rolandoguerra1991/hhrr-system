<?php

namespace App\Concerns;

use App\Models\Position;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

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
     * Get the submitted department id, treating blank values as company-wide.
     */
    private function submittedDepartmentId(): ?int
    {
        $departmentId = $this->input('department_id');

        return $departmentId === null || $departmentId === '' ? null : (int) $departmentId;
    }
}
