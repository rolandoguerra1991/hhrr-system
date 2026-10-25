<?php

use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeAssignmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
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

    Route::get('departments', [DepartmentController::class, 'index'])
        ->middleware('can:viewAny,'.Department::class)
        ->name('departments.index');

    Route::get('departments/create', [DepartmentController::class, 'create'])
        ->middleware('can:create,'.Department::class)
        ->name('departments.create');

    Route::post('departments', [DepartmentController::class, 'store'])
        ->middleware('can:create,'.Department::class)
        ->name('departments.store');

    Route::get('departments/{department}/edit', [DepartmentController::class, 'edit'])
        ->middleware('can:update,department')
        ->name('departments.edit');

    Route::put('departments/{department}', [DepartmentController::class, 'update'])
        ->middleware('can:update,department')
        ->name('departments.update');

    Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])
        ->middleware('can:delete,department')
        ->name('departments.destroy');

    Route::get('positions', [PositionController::class, 'index'])
        ->middleware('can:viewAny,'.Position::class)
        ->name('positions.index');

    Route::get('positions/create', [PositionController::class, 'create'])
        ->middleware('can:create,'.Position::class)
        ->name('positions.create');

    Route::post('positions', [PositionController::class, 'store'])
        ->middleware('can:create,'.Position::class)
        ->name('positions.store');

    Route::get('positions/{position}/edit', [PositionController::class, 'edit'])
        ->middleware('can:update,position')
        ->name('positions.edit');

    Route::put('positions/{position}', [PositionController::class, 'update'])
        ->middleware('can:update,position')
        ->name('positions.update');

    Route::delete('positions/{position}', [PositionController::class, 'destroy'])
        ->middleware('can:delete,position')
        ->name('positions.destroy');

    Route::get('employees', [EmployeeController::class, 'index'])
        ->middleware('can:viewAny,'.Employee::class)
        ->name('employees.index');

    Route::get('employees/create', [EmployeeController::class, 'create'])
        ->middleware('can:create,'.Employee::class)
        ->name('employees.create');

    Route::post('employees', [EmployeeController::class, 'store'])
        ->middleware('can:create,'.Employee::class)
        ->name('employees.store');

    Route::get('employees/{employee}', [EmployeeController::class, 'show'])
        ->middleware('can:view,employee')
        ->name('employees.show');

    Route::get('employees/{employee}/edit', [EmployeeController::class, 'edit'])
        ->middleware('can:update,employee')
        ->name('employees.edit');

    Route::put('employees/{employee}', [EmployeeController::class, 'update'])
        ->middleware('can:update,employee')
        ->name('employees.update');

    Route::post('employees/{employee}/assignments', [EmployeeAssignmentController::class, 'store'])
        ->middleware('can:update,employee')
        ->name('employees.assignments.store');

    Route::post('employees/{employee}/terminate', [EmployeeController::class, 'terminate'])
        ->middleware('can:update,employee')
        ->name('employees.terminate');

    Route::post('employees/{employee}/rehire', [EmployeeController::class, 'rehire'])
        ->middleware('can:update,employee')
        ->name('employees.rehire');
});
