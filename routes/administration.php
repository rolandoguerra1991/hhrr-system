<?php

use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('users', [UserController::class, 'index'])
        ->middleware('can:viewAny,'.User::class)
        ->name('users.index');

    Route::get('users/create', [UserController::class, 'create'])
        ->middleware('can:create,'.User::class)
        ->name('users.create');

    Route::post('users', [UserController::class, 'store'])
        ->middleware('can:create,'.User::class)
        ->name('users.store');

    Route::get('users/{user}/edit', [UserController::class, 'edit'])
        ->middleware('can:update,user')
        ->name('users.edit');

    Route::put('users/{user}', [UserController::class, 'update'])
        ->middleware('can:update,user')
        ->name('users.update');

    Route::delete('users/{user}', [UserController::class, 'destroy'])
        ->middleware('can:delete,user')
        ->name('users.destroy');

    Route::get('roles', [RoleController::class, 'index'])
        ->middleware('can:viewAny,'.Role::class)
        ->name('roles.index');

    Route::get('roles/create', [RoleController::class, 'create'])
        ->middleware('can:create,'.Role::class)
        ->name('roles.create');

    Route::post('roles', [RoleController::class, 'store'])
        ->middleware('can:create,'.Role::class)
        ->name('roles.store');

    Route::get('roles/{role}/edit', [RoleController::class, 'edit'])
        ->middleware('can:update,role')
        ->name('roles.edit');

    Route::put('roles/{role}', [RoleController::class, 'update'])
        ->middleware('can:update,role')
        ->name('roles.update');

    Route::delete('roles/{role}', [RoleController::class, 'destroy'])
        ->middleware('can:delete,role')
        ->name('roles.destroy');

    Route::get('permissions', [PermissionController::class, 'index'])
        ->middleware('can:viewAny,'.Permission::class)
        ->name('permissions.index');

    Route::post('permissions', [PermissionController::class, 'store'])
        ->middleware('can:create,'.Permission::class)
        ->name('permissions.store');

    Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])
        ->middleware('can:delete,permission')
        ->name('permissions.destroy');
});
