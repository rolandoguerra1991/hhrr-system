<?php

namespace App\Http\Controllers;

use App\Concerns\BuildsDepartmentTree;
use App\Http\Requests\Position\StorePositionRequest;
use App\Http\Requests\Position\UpdatePositionRequest;
use App\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PositionController extends Controller
{
    use BuildsDepartmentTree;

    /**
     * Show the position catalogue.
     */
    public function index(Request $request): Response
    {
        $positions = Position::query()
            ->withCount('employmentAssignments')
            ->with('department:id,name')
            ->orderBy('name')
            ->get();

        return Inertia::render('positions/Index', [
            'positions' => $positions->map(fn (Position $position): array => [
                'id' => $position->id,
                'name' => $position->name,
                'department_id' => $position->department_id,
                'department_name' => $position->department?->name,
                'assignments_count' => $position->employment_assignments_count,
                'is_in_use' => $position->employment_assignments_count > 0,
                'can' => [
                    'update' => $request->user()->can('update', $position),
                    'delete' => $request->user()->can('delete', $position),
                ],
            ])->all(),
        ]);
    }

    /**
     * Show the form for creating a position.
     */
    public function create(): Response
    {
        return Inertia::render('positions/Create', [
            'departmentOptions' => $this->departmentOptions(),
        ]);
    }

    /**
     * Store a newly created position.
     */
    public function store(StorePositionRequest $request): RedirectResponse
    {
        Position::create([
            'name' => $request->string('name')->trim()->value(),
            'department_id' => $request->filled('department_id') ? $request->integer('department_id') : null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Position created.')]);

        return to_route('positions.index');
    }

    /**
     * Show the form for editing the given position.
     */
    public function edit(Position $position): Response
    {
        return Inertia::render('positions/Edit', [
            'position' => [
                'id' => $position->id,
                'name' => $position->name,
                'department_id' => $position->department_id,
                'is_in_use' => $position->isInUse(),
            ],
            'departmentOptions' => $this->departmentOptions(),
        ]);
    }

    /**
     * Update the given position.
     */
    public function update(UpdatePositionRequest $request, Position $position): RedirectResponse
    {
        DB::transaction(function () use ($request, $position): void {
            $position = Position::query()
                ->whereKey($position->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $departmentId = $request->departmentId();

            if ($position->isInUse() && $departmentId !== $position->department_id) {
                throw ValidationException::withMessages([
                    'department_id' => __('A position with assignment history cannot be moved to another department.'),
                ]);
            }

            $position->update([
                'name' => $request->string('name')->trim()->value(),
                'department_id' => $departmentId,
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Position updated.')]);

        return to_route('positions.index');
    }

    /**
     * Delete the given position.
     */
    public function destroy(Position $position): RedirectResponse
    {
        if ($position->isInUse()) {
            throw ValidationException::withMessages([
                'position' => __('The :position position cannot be deleted while it has employee assignment history.', [
                    'position' => $position->name,
                ]),
            ]);
        }

        $position->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Position deleted.')]);

        return to_route('positions.index');
    }
}
