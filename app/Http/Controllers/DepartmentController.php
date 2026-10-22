<?php

namespace App\Http\Controllers;

use App\Http\Requests\Department\StoreDepartmentRequest;
use App\Http\Requests\Department\UpdateDepartmentRequest;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    /**
     * Show the department tree.
     */
    public function index(Request $request): Response
    {
        $departments = Department::query()
            ->withCount(['children', 'users'])
            ->with('manager:id,name')
            ->orderBy('name')
            ->get();

        $rows = array_map(fn (array $row): array => [
            'id' => $row['department']->id,
            'name' => $row['department']->name,
            'parent_id' => $row['department']->parent_id,
            'depth' => $row['depth'],
            'children_count' => $row['department']->children_count,
            'users_count' => $row['department']->users_count,
            'manager_name' => $row['department']->manager?->name,
            'can' => [
                'update' => $request->user()->can('update', $row['department']),
                'delete' => $request->user()->can('delete', $row['department']),
            ],
        ], $this->treeRows($departments));

        return Inertia::render('departments/Index', [
            'departments' => $rows,
        ]);
    }

    /**
     * Show the form for creating a department.
     */
    public function create(): Response
    {
        return Inertia::render('departments/Create', [
            'parentOptions' => $this->departmentOptions(),
            'managers' => $this->managerOptions(),
        ]);
    }

    /**
     * Store a newly created department.
     */
    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        Department::create([
            'name' => $request->string('name')->trim()->value(),
            'parent_id' => $request->filled('parent_id') ? $request->integer('parent_id') : null,
            'manager_id' => $request->filled('manager_id') ? $request->integer('manager_id') : null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Department created.')]);

        return to_route('departments.index');
    }

    /**
     * Show the form for editing the given department.
     */
    public function edit(Department $department): Response
    {
        return Inertia::render('departments/Edit', [
            'department' => [
                'id' => $department->id,
                'name' => $department->name,
                'parent_id' => $department->parent_id,
                'manager_id' => $department->manager_id,
            ],
            'parentOptions' => $this->departmentOptions($department),
            'managers' => $this->managerOptions(),
        ]);
    }

    /**
     * Update the given department.
     */
    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        DB::transaction(function () use ($request, $department): void {
            $department->update([
                'name' => $request->string('name')->trim()->value(),
                'parent_id' => $request->filled('parent_id') ? $request->integer('parent_id') : null,
                'manager_id' => $request->filled('manager_id') ? $request->integer('manager_id') : null,
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Department updated.')]);

        return to_route('departments.index');
    }

    /**
     * Delete the given department.
     */
    public function destroy(Department $department): RedirectResponse
    {
        if ($department->children()->exists()) {
            throw ValidationException::withMessages([
                'department' => __('The :department department cannot be deleted while it has child departments.', ['department' => $department->name]),
            ]);
        }

        if ($department->users()->exists()) {
            throw ValidationException::withMessages([
                'department' => __('The :department department cannot be deleted while it has assigned users.', ['department' => $department->name]),
            ]);
        }

        $department->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Department deleted.')]);

        return to_route('departments.index');
    }

    /**
     * Flatten the given departments into depth-annotated rows in tree order.
     *
     * @param  Collection<int, Department>  $departments
     * @param  array<int, int>  $excludedIds
     * @return array<int, array{department: Department, depth: int}>
     */
    private function treeRows(Collection $departments, array $excludedIds = []): array
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
    private function subtreeIds(Department $department, Collection $departments): array
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
    private function departmentOptions(?Department $exclude = null): array
    {
        $departments = Department::query()->orderBy('name')->get();

        $excludedIds = $exclude instanceof Department
            ? $this->subtreeIds($exclude, $departments)
            : [];

        return array_map(fn (array $row): array => [
            'id' => $row['department']->id,
            'name' => $row['department']->name,
            'depth' => $row['depth'],
        ], $this->treeRows($departments, $excludedIds));
    }

    /**
     * Get the users that can be selected as a department manager.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function managerOptions(): array
    {
        return User::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
            ])
            ->all();
    }
}
