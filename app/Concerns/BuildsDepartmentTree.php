<?php

namespace App\Concerns;

use App\Models\Department;
use Illuminate\Support\Collection;

trait BuildsDepartmentTree
{
    /**
     * Flatten the given departments into depth-annotated rows in tree order.
     *
     * @param  Collection<int, Department>  $departments
     * @param  array<int, int>  $excludedIds
     * @return array<int, array{department: Department, depth: int}>
     */
    protected function departmentTreeRows(Collection $departments, array $excludedIds = []): array
    {
        $departments = $departments
            ->reject(fn (Department $department): bool => in_array($department->id, $excludedIds, true))
            ->sortBy(fn (Department $department): string => mb_strtolower($department->name));

        $byParent = $departments->groupBy(fn (Department $department): int => $department->parent_id ?? 0);

        $rows = [];
        $visited = [];

        $walk = function (int $parentId, int $depth) use (&$walk, &$rows, &$visited, $byParent): void {
            foreach ($byParent->get($parentId, collect()) as $department) {
                if (isset($visited[$department->id])) {
                    continue;
                }

                $visited[$department->id] = true;
                $rows[] = ['department' => $department, 'depth' => $depth];

                $walk($department->id, $depth + 1);
            }
        };

        $walk(0, 0);

        return $rows;
    }

    /**
     * Get the ids of a department and all of its descendants.
     *
     * @param  Collection<int, Department>  $departments
     * @return array<int, int>
     */
    protected function departmentSubtreeIds(Department $department, Collection $departments): array
    {
        $byParent = $departments->groupBy(fn (Department $candidate): int => $candidate->parent_id ?? 0);

        $ids = [$department->id];
        $queue = [$department->id];

        while ($queue !== []) {
            $parentId = array_shift($queue);

            foreach ($byParent->get($parentId, collect()) as $child) {
                if (in_array($child->id, $ids, true)) {
                    continue;
                }

                $ids[] = $child->id;
                $queue[] = $child->id;
            }
        }

        return $ids;
    }

    /**
     * Get the department options used by the forms.
     *
     * @return array<int, array{id: int, name: string, depth: int}>
     */
    protected function departmentOptions(?Department $exclude = null): array
    {
        $departments = Department::query()->orderBy('name')->get();

        $excludedIds = $exclude instanceof Department
            ? $this->departmentSubtreeIds($exclude, $departments)
            : [];

        return array_map(fn (array $row): array => [
            'id' => $row['department']->id,
            'name' => $row['department']->name,
            'depth' => $row['depth'],
        ], $this->departmentTreeRows($departments, $excludedIds));
    }
}
