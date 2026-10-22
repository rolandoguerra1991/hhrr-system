<?php

namespace App\Http\Controllers;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Show the list of roles.
     */
    public function index(Request $request): Response
    {
        $roles = Role::query()
            ->withCount(['permissions', 'users'])
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions_count' => $role->permissions_count,
                'users_count' => $role->users_count,
                'is_protected' => $this->isProtected($role),
                'can' => [
                    'update' => $request->user()->can('update', $role),
                    'delete' => $request->user()->can('delete', $role),
                ],
            ]);

        return Inertia::render('roles/Index', [
            'roles' => $roles,
        ]);
    }

    /**
     * Show the form for creating a role.
     */
    public function create(): Response
    {
        return Inertia::render('roles/Create', [
            'permissions' => $this->permissionCatalogue(),
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(StoreRoleRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $role = Role::create([
                'name' => $request->string('name')->trim()->value(),
            ]);

            $role->syncPermissions($request->collect('permissions')->all());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created.')]);

        return to_route('roles.index');
    }

    /**
     * Show the form for editing the given role.
     */
    public function edit(Role $role): Response
    {
        $role->loadCount('users');

        return Inertia::render('roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'users_count' => $role->users_count,
            ],
            'rolePermissions' => $role->getPermissionNames()->all(),
            'permissions' => $this->permissionCatalogue(),
            'users' => User::query()
                ->whereHas('roles', fn (Builder $query): Builder => $query->whereKey($role->getKey()))
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]),
        ]);
    }

    /**
     * Update the given role.
     */
    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        DB::transaction(function () use ($request, $role): void {
            $role->update([
                'name' => $request->string('name')->trim()->value(),
            ]);

            $role->syncPermissions($request->collect('permissions')->all());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return to_route('roles.edit', $role);
    }

    /**
     * Delete the given role.
     */
    public function destroy(Role $role): RedirectResponse
    {
        if ($role->users()->exists()) {
            throw ValidationException::withMessages([
                'role' => __('The :role role cannot be deleted while it is assigned to users.', ['role' => $role->name]),
            ]);
        }

        $role->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return to_route('roles.index');
    }

    /**
     * Determine if the role is protected by the application.
     */
    private function isProtected(Role $role): bool
    {
        return $role->name === RoleEnum::SuperAdmin->value;
    }

    /**
     * Get the permission catalogue used by the interface.
     *
     * @return array<int, array{name: string, label: string, group: string, group_label: string}>
     */
    private function permissionCatalogue(): array
    {
        return PermissionEnum::catalogue(Permission::query()->pluck('name')->all());
    }
}
