<?php

namespace App\Http\Controllers;

use App\Enums\Role as RoleEnum;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Show the list of users.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value();

        $users = User::query()
            ->with('roles')
            ->when($search !== '', fn (Builder $query): Builder => $query->where(
                fn (Builder $query): Builder => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
            ))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames()->all(),
                'is_protected' => $this->isProtected($user),
                'is_self' => $request->user()->is($user),
                'can' => [
                    'update' => $request->user()->can('update', $user),
                    'delete' => $request->user()->can('delete', $user),
                ],
            ]);

        return Inertia::render('users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Show the form for creating a user.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('users/Create', [
            'roles' => $this->roleCatalogue(),
            'can' => [
                'assignSuperAdmin' => $request->user()->can('assignSuperAdmin', User::class),
            ],
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $user = new User([
                'name' => $request->string('name')->trim()->value(),
                'email' => $request->string('email')->trim()->value(),
                'password' => $request->string('password')->value(),
            ]);

            $user->email_verified_at = now();
            $user->save();

            $user->syncRoles($request->collect('roles')->all());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created.')]);

        return to_route('users.index');
    }

    /**
     * Show the form for editing the given user.
     */
    public function edit(Request $request, User $user): Response
    {
        return Inertia::render('users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'roleNames' => $user->getRoleNames()->all(),
            'roles' => $this->roleCatalogue(),
            'can' => [
                'assignSuperAdmin' => $request->user()->can('assignSuperAdmin', User::class),
            ],
        ]);
    }

    /**
     * Update the given user.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user): void {
            $user->name = $request->string('name')->trim()->value();
            $user->email = $request->string('email')->trim()->value();

            if ($request->filled('password')) {
                $user->password = $request->string('password')->value();
            }

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            $user->save();

            $user->syncRoles($request->collect('roles')->all());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return to_route('users.edit', $user);
    }

    /**
     * Delete the given user.
     */
    public function destroy(User $user): RedirectResponse
    {
        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deleted.')]);

        return to_route('users.index');
    }

    /**
     * Determine if the user account is protected by the application.
     */
    private function isProtected(User $user): bool
    {
        return $user->email === config('auth.admin.email');
    }

    /**
     * Get the roles that can be assigned to a user.
     *
     * @return array<int, array{name: string, is_protected: bool}>
     */
    private function roleCatalogue(): array
    {
        return Role::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'name' => $role->name,
                'is_protected' => $role->name === RoleEnum::SuperAdmin->value,
            ])
            ->all();
    }
}
