<?php

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->seed(RolesAndPermissionsSeeder::class);
});

test('guests cannot view the roles index', function () {
    $this->get(route('roles.index'))->assertRedirect(route('login'));
});

test('users without the roles.view permission are forbidden', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('roles.index'))->assertForbidden();
});

test('users with the roles.view permission can browse the roles index', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('roles.view');

    $this->actingAs($user)
        ->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('roles/Index')
            ->has('roles', 1)
            ->where('auth.can.roles.view', true)
            ->where('auth.can.roles.create', false)
        );
});

test('a role can be created with permissions', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('roles.create');

    $this->actingAs($user)
        ->post(route('roles.store'), [
            'name' => 'Editor',
            'permissions' => ['roles.view', 'permissions.view'],
        ])
        ->assertRedirect(route('roles.index'));

    $role = Role::findByName('Editor', 'web');

    expect($role->getPermissionNames()->sort()->values()->all())
        ->toBe(['permissions.view', 'roles.view']);
});

test('users without the roles.create permission cannot create a role', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('roles.view');

    $this->actingAs($user)
        ->from(route('roles.index'))
        ->post(route('roles.store'), ['name' => 'Editor'])
        ->assertForbidden();
});

test('role names must be unique', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('roles.create');

    $this->actingAs($user)
        ->from(route('roles.create'))
        ->post(route('roles.store'), ['name' => RoleEnum::SuperAdmin->value])
        ->assertSessionHasErrors('name');
});

test('unknown permissions are rejected when creating a role', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('roles.create');

    $this->actingAs($user)
        ->from(route('roles.create'))
        ->post(route('roles.store'), [
            'name' => 'Editor',
            'permissions' => ['does.not.exist'],
        ])
        ->assertSessionHasErrors('permissions.0');
});

test('a role can be updated and its permissions synced', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('roles.update');

    $role = Role::findOrCreate('Editor', 'web');
    $role->syncPermissions(['roles.view']);

    $this->actingAs($user)
        ->put(route('roles.update', $role), [
            'name' => 'Publisher',
            'permissions' => ['permissions.view'],
        ])
        ->assertRedirect(route('roles.edit', $role));

    $role->refresh();

    expect($role->name)->toBe('Publisher');
    expect($role->getPermissionNames()->all())->toBe(['permissions.view']);
});

test('the reserved role cannot be edited or renamed', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('roles.update');

    $role = Role::findByName(RoleEnum::SuperAdmin->value, 'web');

    $this->actingAs($user)->get(route('roles.edit', $role))->assertForbidden();

    $this->actingAs($user)
        ->put(route('roles.update', $role), [
            'name' => 'Root',
            'permissions' => [],
        ])
        ->assertForbidden();

    expect($role->refresh()->name)->toBe(RoleEnum::SuperAdmin->value);
});

test('the edit form lists the users assigned to the role', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('roles.update');

    $role = Role::findOrCreate('Editor', 'web');

    User::factory()->create(['name' => 'Ada Lovelace'])->assignRole($role);
    User::factory()->create(['name' => 'Grace Hopper']);

    $this->actingAs($user)
        ->get(route('roles.edit', $role))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('roles/Edit')
            ->has('users', 1)
            ->where('users.0.name', 'Ada Lovelace')
            ->where('role.users_count', 1)
        );
});

test('an unused role can be deleted', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('roles.delete');

    $role = Role::findOrCreate('Temporary', 'web');

    $this->actingAs($user)
        ->delete(route('roles.destroy', $role))
        ->assertRedirect(route('roles.index'));

    expect(Role::where('name', 'Temporary')->exists())->toBeFalse();
});

test('a role assigned to users cannot be deleted', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('roles.delete');

    $role = Role::findOrCreate('Editor', 'web');
    User::factory()->create()->assignRole($role);

    $this->actingAs($user)
        ->from(route('roles.index'))
        ->delete(route('roles.destroy', $role))
        ->assertSessionHasErrors('role');

    expect(Role::where('name', 'Editor')->exists())->toBeTrue();
});

test('the reserved role cannot be deleted', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('roles.delete');

    $role = Role::findByName(RoleEnum::SuperAdmin->value, 'web');

    $this->actingAs($user)
        ->delete(route('roles.destroy', $role))
        ->assertForbidden();

    expect(Role::where('name', RoleEnum::SuperAdmin->value)->exists())->toBeTrue();
});

test('the super admin is granted access without permissions stored on the role', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleEnum::SuperAdmin->value);

    expect(Role::findByName(RoleEnum::SuperAdmin->value, 'web')->permissions)->toBeEmpty();
    expect($user->getAllPermissions())->toBeEmpty();
    expect($user->can('viewAny', Role::class))->toBeTrue();
    expect($user->can('create', Permission::class))->toBeTrue();

    $this->actingAs($user)->get(route('roles.index'))->assertOk();
    $this->actingAs($user)->get(route('permissions.index'))->assertOk();
});

test('even the super admin cannot change the reserved role', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleEnum::SuperAdmin->value);

    $role = Role::findByName(RoleEnum::SuperAdmin->value, 'web');

    $this->actingAs($user)
        ->put(route('roles.update', $role), [
            'name' => 'Root',
            'permissions' => [],
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('roles.destroy', $role))
        ->assertForbidden();

    expect($role->refresh()->name)->toBe(RoleEnum::SuperAdmin->value);
});
