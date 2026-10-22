<?php

use App\Enums\Role as RoleEnum;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->seed(RolesAndPermissionsSeeder::class);
});

test('guests cannot view the users index', function () {
    $this->get(route('users.index'))->assertRedirect(route('login'));
});

test('users without the users.view permission are forbidden', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('users.index'))->assertForbidden();
});

test('users with the users.view permission can browse the list', function () {
    $user = User::factory()->create(['name' => 'Zed Actor']);
    $user->givePermissionTo('users.view');

    User::factory()->create(['name' => 'Ada Lovelace'])->assignRole(RoleEnum::SuperAdmin->value);

    $this->actingAs($user)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('users/Index')
            ->has('users.data', 2)
            ->where('users.data.0.name', 'Ada Lovelace')
            ->where('users.data.0.roles', [RoleEnum::SuperAdmin->value])
            ->where('users.data.0.can.update', false)
            ->where('users.data.1.is_self', true)
            ->where('filters.search', '')
        );
});

test('the users index includes each user department', function () {
    $actor = User::factory()->create(['name' => 'Zed Actor']);
    $actor->givePermissionTo('users.view');

    $department = Department::factory()->create(['name' => 'Operations']);

    $ada = User::factory()->create(['name' => 'Ada Lovelace']);
    $ada->department_id = $department->id;
    $ada->save();

    User::factory()->create(['name' => 'Grace Hopper']);

    $this->actingAs($actor)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('users.data', 3)
            ->where('users.data.0.department', 'Operations')
            ->where('users.data.1.department', null)
        );
});

test('the users index can be searched by name and email', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('users.view');

    User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
    User::factory()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);

    $this->actingAs($user)
        ->get(route('users.index', ['search' => 'grace@example.com']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Grace Hopper')
            ->where('filters.search', 'grace@example.com')
        );
});

test('a user can be created with roles', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('users.create');

    $role = Role::findOrCreate('Editor', 'web');

    $this->actingAs($actor)
        ->post(route('users.store'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'roles' => [$role->name],
        ])
        ->assertRedirect(route('users.index'));

    $user = User::where('email', 'ada@example.com')->firstOrFail();

    expect($user->getRoleNames()->all())->toBe(['Editor']);
    expect(Hash::check('password', $user->password))->toBeTrue();
    expect($user->email_verified_at)->not->toBeNull();
});

test('a user can be created with a department', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('users.create');

    $department = Department::factory()->create(['name' => 'Operations']);

    $this->actingAs($actor)
        ->post(route('users.store'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'department_id' => $department->id,
        ])
        ->assertRedirect(route('users.index'));

    expect(User::where('email', 'ada@example.com')->firstOrFail()->department_id)->toBe($department->id);
});

test('an unknown department is rejected when creating a user', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('users.create');

    $this->actingAs($actor)
        ->from(route('users.create'))
        ->post(route('users.store'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'department_id' => 999,
        ])
        ->assertSessionHasErrors('department_id');

    expect(User::where('email', 'ada@example.com')->exists())->toBeFalse();
});

test('a user is created without a department when the field is blank', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('users.create');

    $this->actingAs($actor)
        ->post(route('users.store'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'department_id' => '',
        ])
        ->assertRedirect(route('users.index'));

    expect(User::where('email', 'ada@example.com')->firstOrFail()->department_id)->toBeNull();
});

test('a user can be assigned to a department on update', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('users.update');

    $department = Department::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($actor)
        ->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => [],
            'department_id' => $department->id,
        ])
        ->assertRedirect(route('users.edit', $user));

    expect($user->refresh()->department_id)->toBe($department->id);
});

test('creating a user requires a confirmed password', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('users.create');

    $this->actingAs($actor)
        ->from(route('users.create'))
        ->post(route('users.store'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'different',
        ])
        ->assertSessionHasErrors('password');

    expect(User::where('email', 'ada@example.com')->exists())->toBeFalse();
});

test('user emails must be unique', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('users.create');

    User::factory()->create(['email' => 'ada@example.com']);

    $this->actingAs($actor)
        ->from(route('users.create'))
        ->post(route('users.store'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertSessionHasErrors('email');
});

test('unknown roles are rejected when creating a user', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('users.create');

    $this->actingAs($actor)
        ->from(route('users.create'))
        ->post(route('users.store'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'roles' => ['does.not.exist'],
        ])
        ->assertSessionHasErrors('roles.0');
});

test('a user can be updated without changing the password', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('users.update');

    $user = User::factory()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'original-password',
    ]);

    $this->actingAs($actor)
        ->put(route('users.update', $user), [
            'name' => 'Ada Byron',
            'email' => 'ada@example.com',
            'password' => '',
            'roles' => [],
        ])
        ->assertRedirect(route('users.edit', $user));

    $user->refresh();

    expect($user->name)->toBe('Ada Byron');
    expect(Hash::check('original-password', $user->password))->toBeTrue();
});

test('a user password can be updated', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('users.update');

    $user = User::factory()->create(['password' => 'original-password']);

    $this->actingAs($actor)
        ->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
            'roles' => [],
        ])
        ->assertRedirect(route('users.edit', $user));

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('changing the email resets the verification', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('users.update');

    $user = User::factory()->create(['email' => 'ada@example.com']);

    $this->actingAs($actor)
        ->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => 'ada.byron@example.com',
            'roles' => [],
        ])
        ->assertRedirect(route('users.edit', $user));

    $user->refresh();

    expect($user->email)->toBe('ada.byron@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('a user with users.update can assign roles', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo(['users.update', 'roles.view']);

    $user = User::factory()->create();
    $role = Role::findOrCreate('Editor', 'web');

    $this->actingAs($actor)
        ->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => [$role->name],
        ])
        ->assertRedirect(route('users.edit', $user));

    expect($user->refresh()->getRoleNames()->all())->toBe(['Editor']);
});

test('only a super admin can grant the super admin role', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('users.create');

    $this->actingAs($actor)
        ->from(route('users.create'))
        ->post(route('users.store'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'roles' => [RoleEnum::SuperAdmin->value],
        ])
        ->assertSessionHasErrors('roles');

    expect(User::where('email', 'ada@example.com')->exists())->toBeFalse();
});

test('a super admin can grant the super admin role', function () {
    $actor = User::factory()->create();
    $actor->assignRole(RoleEnum::SuperAdmin->value);

    $this->actingAs($actor)
        ->post(route('users.store'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'roles' => [RoleEnum::SuperAdmin->value],
        ])
        ->assertRedirect(route('users.index'));

    expect(User::where('email', 'ada@example.com')->firstOrFail()->hasRole(RoleEnum::SuperAdmin->value))->toBeTrue();
});

test('a user with users.update cannot revoke the super admin role', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('users.update');

    $user = User::factory()->create();
    $user->assignRole(RoleEnum::SuperAdmin->value);

    $this->actingAs($actor)
        ->from(route('users.edit', $user))
        ->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => [],
        ])
        ->assertSessionHasErrors('roles');

    expect($user->refresh()->hasRole(RoleEnum::SuperAdmin->value))->toBeTrue();
});

test('a user with users.update can edit a super admin without touching its roles', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('users.update');

    $user = User::factory()->create(['name' => 'Ada Lovelace']);
    $user->assignRole(RoleEnum::SuperAdmin->value);

    $this->actingAs($actor)
        ->put(route('users.update', $user), [
            'name' => 'Ada Byron',
            'email' => $user->email,
            'roles' => [RoleEnum::SuperAdmin->value],
        ])
        ->assertRedirect(route('users.edit', $user));

    $user->refresh();

    expect($user->name)->toBe('Ada Byron');
    expect($user->hasRole(RoleEnum::SuperAdmin->value))->toBeTrue();
});

test('the application admin account cannot be updated or deleted', function () {
    $actor = User::factory()->create();
    $actor->assignRole(RoleEnum::SuperAdmin->value);

    $admin = User::factory()->create(['email' => config('auth.admin.email')]);

    $this->actingAs($actor)
        ->put(route('users.update', $admin), [
            'name' => 'Hacked',
            'email' => $admin->email,
            'roles' => [],
        ])
        ->assertForbidden();

    $this->actingAs($actor)
        ->delete(route('users.destroy', $admin))
        ->assertForbidden();

    expect($admin->refresh()->name)->not->toBe('Hacked');
});

test('the admin account is flagged as protected in the list', function () {
    $actor = User::factory()->create();
    $actor->assignRole(RoleEnum::SuperAdmin->value);

    User::factory()->create(['email' => config('auth.admin.email')]);

    $this->actingAs($actor)
        ->get(route('users.index', ['search' => config('auth.admin.email')]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.is_protected', true)
            ->where('users.data.0.is_self', false)
            ->where('users.data.0.can.update', false)
            ->where('users.data.0.can.delete', false)
        );
});

test('users cannot delete their own account from the users module', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('users.delete');

    $this->actingAs($actor)
        ->delete(route('users.destroy', $actor))
        ->assertForbidden();

    expect(User::whereKey($actor->id)->exists())->toBeTrue();
});

test('a user can be deleted by someone with the users.delete permission', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('users.delete');

    $user = User::factory()->create();

    $this->actingAs($actor)
        ->delete(route('users.destroy', $user))
        ->assertRedirect(route('users.index'));

    expect(User::whereKey($user->id)->exists())->toBeFalse();
});

test('users without the users.update permission cannot edit a user', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('users.view');

    $user = User::factory()->create();

    $this->actingAs($actor)->get(route('users.edit', $user))->assertForbidden();
});

test('only a super admin is offered the super admin role in the forms', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('users.create');

    Department::factory()->create(['name' => 'Operations']);

    $this->actingAs($actor)
        ->get(route('users.create'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('users/Create')
            ->where('can.assignSuperAdmin', false)
            ->where('roles.0.is_protected', true)
            ->has('departments', 1)
            ->where('departments.0.name', 'Operations')
        );

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(RoleEnum::SuperAdmin->value);

    $this->actingAs($superAdmin)
        ->get(route('users.create'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('can.assignSuperAdmin', true)
        );
});

test('the edit form is loaded with the assigned roles', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('users.update');

    $department = Department::factory()->create(['name' => 'Operations']);

    $user = User::factory()->create(['name' => 'Ada Lovelace']);
    $user->department_id = $department->id;
    $user->save();
    $user->assignRole(Role::findOrCreate('Editor', 'web'));

    $this->actingAs($actor)
        ->get(route('users.edit', $user))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('users/Edit')
            ->where('user.name', 'Ada Lovelace')
            ->where('user.department_id', $department->id)
            ->where('roleNames', ['Editor'])
            ->has('departments', 1)
            ->where('departments.0.name', 'Operations')
        );
});
