<?php

namespace App\Http\Controllers;

use App\Concerns\BuildsDepartmentTree;
use App\Http\Requests\Department\StoreDepartmentRequest;
use App\Http\Requests\Department\UpdateDepartmentRequest;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    use BuildsDepartmentTree;

    /**
     * Show the department tree.
     */
    public function index(Request $request): Response
    {
        $departments = Department::query()
            ->withCount(['children', 'users', 'positions'])
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
            'positions_count' => $row['department']->positions_count,
            'manager_name' => $row['department']->manager?->name,
            'can' => [
                'update' => $request->user()->can('update', $row['department']),
                'delete' => $request->user()->can('delete', $row['department']),
            ],
        ], $this->departmentTreeRows($departments));

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
        if ($department->positions()->exists()) {
            throw ValidationException::withMessages([
                'department' => __('The :department department cannot be deleted while it has assigned positions.', ['department' => $department->name]),
            ]);
        }

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
