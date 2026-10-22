<?php

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->seed(RolesAndPermissionsSeeder::class);
});

test('guests cannot view the permissions index', function () {
    $this->get(route('permissions.index'))->assertRedirect(route('login'));
});

test('users without the permissions.view permission are forbidden', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('permissions.index'))->assertForbidden();
});

test('users with the permissions.view permission can browse the catalogue', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('permissions.view');

    $this->actingAs($user)
        ->get(route('permissions.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('permissions/Index')
            ->has('permissions')
        );
});

test('a permission can be created', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('permissions.create');

    $this->actingAs($user)
        ->post(route('permissions.store'), ['name' => 'reports.export'])
        ->assertRedirect(route('permissions.index'));

    expect(Permission::findByName('reports.export', 'web')->name)
        ->toBe('reports.export');
});

test('users without the permissions.create permission cannot create a permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('permissions.view');

    $this->actingAs($user)
        ->from(route('permissions.index'))
        ->post(route('permissions.store'), ['name' => 'reports.export'])
        ->assertForbidden();
});

test('permission names must be unique', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('permissions.create');

    $this->actingAs($user)
        ->from(route('permissions.index'))
        ->post(route('permissions.store'), ['name' => 'roles.view'])
        ->assertSessionHasErrors('name');
});

test('permission names must follow the resource.action format', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('permissions.create');

    $this->actingAs($user)
        ->from(route('permissions.index'))
        ->post(route('permissions.store'), ['name' => 'Reports Export'])
        ->assertSessionHasErrors('name');
});

test('a custom permission can be deleted', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('permissions.delete');

    $permission = Permission::findOrCreate('reports.export', 'web');

    $this->actingAs($user)
        ->delete(route('permissions.destroy', $permission))
        ->assertRedirect(route('permissions.index'));

    expect(Permission::where('name', 'reports.export')->exists())->toBeFalse();
});

test('permissions required by the application cannot be deleted', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('permissions.delete');

    $permission = Permission::findByName('roles.view', 'web');

    $this->actingAs($user)
        ->delete(route('permissions.destroy', $permission))
        ->assertForbidden();

    expect(Permission::where('name', 'roles.view')->exists())->toBeTrue();
});

test('even the super admin cannot delete a permission required by the application', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleEnum::SuperAdmin->value);

    $permission = Permission::findByName('roles.view', 'web');

    $this->actingAs($user)
        ->delete(route('permissions.destroy', $permission))
        ->assertForbidden();

    expect(Permission::where('name', 'roles.view')->exists())->toBeTrue();
});
