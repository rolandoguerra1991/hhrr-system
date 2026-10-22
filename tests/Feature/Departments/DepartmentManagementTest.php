<?php

use App\Enums\Role as RoleEnum;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->withoutVite();

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->seed(RolesAndPermissionsSeeder::class);
});

test('a user without department permissions cannot view or create departments', function () {
    $user = User::factory()->create();
    $department = Department::factory()->create();

    expect($user->can('viewAny', Department::class))->toBeFalse();
    expect($user->can('create', Department::class))->toBeFalse();
    expect($user->can('update', $department))->toBeFalse();
    expect($user->can('delete', $department))->toBeFalse();
});

test('the departments.view permission only grants reading', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('departments.view');

    $department = Department::factory()->create();

    expect($user->can('viewAny', Department::class))->toBeTrue();
    expect($user->can('create', Department::class))->toBeFalse();
    expect($user->can('update', $department))->toBeFalse();
    expect($user->can('delete', $department))->toBeFalse();
});

test('a super admin is granted every department ability', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleEnum::SuperAdmin->value);

    $department = Department::factory()->create();

    expect($user->can('viewAny', Department::class))->toBeTrue();
    expect($user->can('create', Department::class))->toBeTrue();
    expect($user->can('update', $department))->toBeTrue();
    expect($user->can('delete', $department))->toBeTrue();
});

test('guests cannot view the department tree', function () {
    $this->get(route('departments.index'))->assertRedirect(route('login'));
});

test('users without the departments.view permission cannot view the tree', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('departments.index'))->assertForbidden();
});

test('a department can be created as a root and as a child', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('departments.create');

    $this->actingAs($actor)
        ->post(route('departments.store'), ['name' => 'Operations'])
        ->assertRedirect(route('departments.index'));

    $operations = Department::where('name', 'Operations')->firstOrFail();

    $this->actingAs($actor)
        ->post(route('departments.store'), [
            'name' => 'Warehouse',
            'parent_id' => $operations->id,
        ])
        ->assertRedirect(route('departments.index'));

    $warehouse = Department::where('name', 'Warehouse')->firstOrFail();

    expect($operations->parent_id)->toBeNull();
    expect($warehouse->parent_id)->toBe($operations->id);
});

test('sibling department names must be unique regardless of case and whitespace', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('departments.create');

    $parent = Department::factory()->create(['name' => 'Operations']);
    Department::factory()->childOf($parent)->create(['name' => 'Sales']);

    $this->actingAs($actor)
        ->from(route('departments.create'))
        ->post(route('departments.store'), [
            'name' => '  sales  ',
            'parent_id' => $parent->id,
        ])
        ->assertSessionHasErrors('name');

    expect(Department::where('parent_id', $parent->id)->count())->toBe(1);
});

test('root department names must be unique regardless of case and whitespace', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('departments.create');

    Department::factory()->create(['name' => 'Sales']);

    $this->actingAs($actor)
        ->from(route('departments.create'))
        ->post(route('departments.store'), ['name' => ' SALES '])
        ->assertSessionHasErrors('name');

    expect(Department::whereNull('parent_id')->count())->toBe(1);
});

test('departments under different parents may share a name', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('departments.create');

    $operations = Department::factory()->create(['name' => 'Operations']);
    $support = Department::factory()->create(['name' => 'Support']);

    Department::factory()->childOf($operations)->create(['name' => 'Sales']);

    $this->actingAs($actor)
        ->post(route('departments.store'), [
            'name' => 'Sales',
            'parent_id' => $support->id,
        ])
        ->assertRedirect(route('departments.index'));

    expect(Department::where('parent_id', $support->id)->where('name', 'Sales')->exists())->toBeTrue();
});

test('a department can be renamed and re-parented with its children following', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('departments.update');

    $operations = Department::factory()->create(['name' => 'Operations']);
    $support = Department::factory()->create(['name' => 'Support']);
    $warehouse = Department::factory()->childOf($operations)->create(['name' => 'Warehouse']);
    $team = Department::factory()->childOf($warehouse)->create(['name' => 'Night team']);

    $this->actingAs($actor)
        ->put(route('departments.update', $operations), [
            'name' => 'Logistics',
            'parent_id' => $support->id,
        ])
        ->assertRedirect(route('departments.index'));

    expect($operations->refresh()->name)->toBe('Logistics');
    expect($operations->parent_id)->toBe($support->id);
    expect($warehouse->refresh()->parent_id)->toBe($operations->id);
    expect($team->refresh()->parent_id)->toBe($warehouse->id);
});

test('a department cannot become its own ancestor', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('departments.update');

    $operations = Department::factory()->create(['name' => 'Operations']);
    $warehouse = Department::factory()->childOf($operations)->create(['name' => 'Warehouse']);
    $team = Department::factory()->childOf($warehouse)->create(['name' => 'Night team']);

    $this->actingAs($actor)
        ->from(route('departments.edit', $operations))
        ->put(route('departments.update', $operations), [
            'name' => 'Operations',
            'parent_id' => $operations->id,
        ])
        ->assertSessionHasErrors('parent_id');

    $this->actingAs($actor)
        ->from(route('departments.edit', $operations))
        ->put(route('departments.update', $operations), [
            'name' => 'Operations',
            'parent_id' => $team->id,
        ])
        ->assertSessionHasErrors('parent_id');

    expect($operations->refresh()->parent_id)->toBeNull();
    expect($warehouse->refresh()->parent_id)->toBe($operations->id);
    expect($team->refresh()->parent_id)->toBe($warehouse->id);
});

test('renaming into a sibling collision is rejected', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('departments.update');

    $operations = Department::factory()->create(['name' => 'Operations']);
    Department::factory()->childOf($operations)->create(['name' => 'Sales']);
    $marketing = Department::factory()->childOf($operations)->create(['name' => 'Marketing']);

    $this->actingAs($actor)
        ->from(route('departments.edit', $marketing))
        ->put(route('departments.update', $marketing), [
            'name' => ' sales ',
            'parent_id' => $operations->id,
        ])
        ->assertSessionHasErrors('name');

    expect($marketing->refresh()->name)->toBe('Marketing');
});

test('the manager can be set and cleared', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('departments.update');

    $department = Department::factory()->create(['name' => 'Operations']);
    $manager = User::factory()->create();

    $this->actingAs($actor)
        ->put(route('departments.update', $department), [
            'name' => 'Operations',
            'manager_id' => $manager->id,
        ])
        ->assertRedirect(route('departments.index'));

    expect($department->refresh()->manager_id)->toBe($manager->id);

    $this->actingAs($actor)
        ->put(route('departments.update', $department), [
            'name' => 'Operations',
            'manager_id' => null,
        ])
        ->assertRedirect(route('departments.index'));

    expect($department->refresh()->manager_id)->toBeNull();
});

test('the edit form excludes the department and its descendants from parent options', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('departments.update');

    $operations = Department::factory()->create(['name' => 'Operations']);
    $warehouse = Department::factory()->childOf($operations)->create(['name' => 'Warehouse']);
    $team = Department::factory()->childOf($warehouse)->create(['name' => 'Night team']);
    $support = Department::factory()->create(['name' => 'Support']);

    $this->actingAs($actor)
        ->get(route('departments.edit', $operations))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('departments/Edit')
            ->where('department.id', $operations->id)
            ->where('department.name', 'Operations')
            ->where('department.parent_id', null)
            ->where('department.manager_id', null)
            ->has('parentOptions', 1)
            ->where('parentOptions.0.id', $support->id)
            ->where('parentOptions.0.name', 'Support')
        );
});

test('a department with children cannot be deleted', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('departments.delete');

    $operations = Department::factory()->create(['name' => 'Operations']);
    Department::factory()->childOf($operations)->create(['name' => 'Warehouse']);

    $this->actingAs($actor)
        ->from(route('departments.index'))
        ->delete(route('departments.destroy', $operations))
        ->assertSessionHasErrors('department');

    expect(Department::whereKey($operations->id)->exists())->toBeTrue();
});

test('a department with assigned users cannot be deleted', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('departments.delete');

    $department = Department::factory()->create(['name' => 'Operations']);
    User::factory()->create(['department_id' => $department->id]);

    $this->actingAs($actor)
        ->from(route('departments.index'))
        ->delete(route('departments.destroy', $department))
        ->assertSessionHasErrors('department');

    expect(Department::whereKey($department->id)->exists())->toBeTrue();
});

test('a department with assigned positions cannot be deleted', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('departments.delete');

    $department = Department::factory()->create(['name' => 'Operations']);
    Position::factory()->inDepartment($department)->create(['name' => 'Engineer']);

    $this->actingAs($actor)
        ->from(route('departments.index'))
        ->delete(route('departments.destroy', $department))
        ->assertSessionHasErrors('department');

    expect(Department::whereKey($department->id)->exists())->toBeTrue();
});

test('a department can be deleted after its last position is deleted', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('departments.delete');

    $department = Department::factory()->create(['name' => 'Operations']);
    $position = Position::factory()->inDepartment($department)->create(['name' => 'Engineer']);

    $position->delete();

    $this->actingAs($actor)
        ->delete(route('departments.destroy', $department))
        ->assertRedirect(route('departments.index'));

    expect(Department::whereKey($department->id)->exists())->toBeFalse();
});

test('an unused department can be deleted', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('departments.delete');

    $department = Department::factory()->create(['name' => 'Temporary']);

    $this->actingAs($actor)
        ->delete(route('departments.destroy', $department))
        ->assertRedirect(route('departments.index'));

    expect(Department::whereKey($department->id)->exists())->toBeFalse();
});

test('the department tree is listed in hierarchy order with depth and counts', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('departments.view');

    $operations = Department::factory()->create(['name' => 'Operations']);
    $warehouse = Department::factory()->childOf($operations)->create(['name' => 'Warehouse']);
    Department::factory()->create(['name' => 'Sales']);

    $manager = User::factory()->create(['name' => 'Ada Lovelace']);
    $warehouse->update(['manager_id' => $manager->id]);
    User::factory()->create(['department_id' => $warehouse->id]);
    Position::factory()->inDepartment($warehouse)->create(['name' => 'Engineer']);

    $this->actingAs($user)
        ->get(route('departments.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('departments/Index')
            ->where('auth.can.departments.view', true)
            ->has('departments', 3)
            ->where('departments.0.name', 'Operations')
            ->where('departments.0.depth', 0)
            ->where('departments.0.children_count', 1)
            ->where('departments.0.users_count', 0)
            ->where('departments.0.positions_count', 0)
            ->where('departments.0.manager_name', null)
            ->where('departments.0.can.update', false)
            ->where('departments.1.name', 'Warehouse')
            ->where('departments.1.depth', 1)
            ->where('departments.1.users_count', 1)
            ->where('departments.1.positions_count', 1)
            ->where('departments.1.manager_name', 'Ada Lovelace')
            ->where('departments.1.can.delete', false)
            ->where('departments.2.name', 'Sales')
            ->where('departments.2.depth', 0)
        );
});
