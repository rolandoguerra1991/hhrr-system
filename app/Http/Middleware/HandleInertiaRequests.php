<?php

namespace App\Http\Middleware;

use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'can' => [
                    'roles' => [
                        'view' => $user?->can('viewAny', Role::class) ?? false,
                        'create' => $user?->can('create', Role::class) ?? false,
                    ],
                    'permissions' => [
                        'view' => $user?->can('viewAny', Permission::class) ?? false,
                        'create' => $user?->can('create', Permission::class) ?? false,
                    ],
                    'users' => [
                        'view' => $user?->can('viewAny', User::class) ?? false,
                        'create' => $user?->can('create', User::class) ?? false,
                    ],
                    'departments' => [
                        'view' => $user?->can('viewAny', Department::class) ?? false,
                        'create' => $user?->can('create', Department::class) ?? false,
                    ],
                    'positions' => [
                        'view' => $user?->can('viewAny', Position::class) ?? false,
                        'create' => $user?->can('create', Position::class) ?? false,
                    ],
                ],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
