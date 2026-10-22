<?php

namespace App\Concerns;

use App\Models\Department;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait DepartmentValidationRules
{
    /**
     * Get the validation rules used to validate departments.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string|Closure>>
     */
    protected function departmentRules(?Department $department = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail) use ($department): void {
                    $name = mb_strtolower(trim((string) $value));
                    $parentId = $this->submittedParentId();

                    $duplicate = Department::query()
                        ->whereRaw('LOWER(name) = ?', [$name])
                        ->when(
                            $parentId === null,
                            fn (Builder $query): Builder => $query->whereNull('parent_id'),
                            fn (Builder $query): Builder => $query->where('parent_id', $parentId),
                        )
                        ->when(
                            $department instanceof Department,
                            fn (Builder $query): Builder => $query->whereKeyNot($department->getKey()),
                        )
                        ->exists();

                    if ($duplicate) {
                        $fail(__('A department with this name already exists under the selected parent.'));
                    }
                },
            ],
            'parent_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'manager_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ];
    }

    /**
     * Get the "after" callbacks that keep the hierarchy free of cycles.
     *
     * @return array<int, callable(Validator): void>
     */
    protected function departmentParentGuards(?Department $department = null): array
    {
        return [
            function (Validator $validator) use ($department): void {
                if (! $department instanceof Department) {
                    return;
                }

                $parentId = $this->submittedParentId();

                if ($parentId !== null && $this->wouldCreateCycle($parentId, $department)) {
                    $validator->errors()->add(
                        'parent_id',
                        __('A department cannot be placed under itself or one of its descendants.'),
                    );
                }
            },
        ];
    }

    /**
     * Determine if the candidate parent is the department or one of its descendants.
     */
    private function wouldCreateCycle(int $parentId, Department $department): bool
    {
        $visited = [];
        $candidate = Department::query()->find($parentId);

        while ($candidate instanceof Department && ! isset($visited[$candidate->id])) {
            if ($candidate->is($department)) {
                return true;
            }

            $visited[$candidate->id] = true;

            $candidate = $candidate->parent_id === null
                ? null
                : Department::query()->find($candidate->parent_id);
        }

        return false;
    }

    /**
     * Get the submitted parent id, treating blank values as a root department.
     */
    private function submittedParentId(): ?int
    {
        $parentId = $this->input('parent_id');

        return $parentId === null || $parentId === '' ? null : (int) $parentId;
    }
}
