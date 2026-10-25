<?php

use App\Enums\Role as RoleEnum;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentAssignment;
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

test('a user without position permissions cannot view or change positions', function () {
    $user = User::factory()->create();
    $position = Position::factory()->create();

    expect($user->can('viewAny', Position::class))->toBeFalse();
    expect($user->can('create', Position::class))->toBeFalse();
    expect($user->can('update', $position))->toBeFalse();
    expect($user->can('delete', $position))->toBeFalse();
});

test('the positions.view permission only grants reading', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('positions.view');

    $position = Position::factory()->create();

    expect($user->can('viewAny', Position::class))->toBeTrue();
    expect($user->can('create', Position::class))->toBeFalse();
    expect($user->can('update', $position))->toBeFalse();
    expect($user->can('delete', $position))->toBeFalse();
});

test('a super admin is granted every position ability', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleEnum::SuperAdmin->value);

    $position = Position::factory()->create();

    expect($user->can('viewAny', Position::class))->toBeTrue();
    expect($user->can('create', Position::class))->toBeTrue();
    expect($user->can('update', $position))->toBeTrue();
    expect($user->can('delete', $position))->toBeTrue();
});

test('guests cannot view the position list', function () {
    $this->get(route('positions.index'))->assertRedirect(route('login'));
});

test('users without the positions.view permission cannot view the list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('positions.index'))->assertForbidden();
});

test('the positions index lists positions ordered by name with their department', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('positions.view');

    $department = Department::factory()->create(['name' => 'Operations']);

    Position::factory()->inDepartment($department)->create(['name' => 'Engineer']);
    Position::factory()->create(['name' => 'General Manager']);

    $this->actingAs($actor)
        ->get(route('positions.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('positions/Index')
            ->where('auth.can.positions.view', true)
            ->where('auth.can.positions.create', false)
            ->has('positions', 2)
            ->where('positions.0.name', 'Engineer')
            ->where('positions.0.department_name', 'Operations')
            ->where('positions.0.assignments_count', 0)
            ->where('positions.0.is_in_use', false)
            ->where('positions.0.can.update', false)
            ->where('positions.1.name', 'General Manager')
            ->where('positions.1.department_name', null)
        );
});

test('the positions index marks positions with assignment history as in use', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo(['positions.view', 'positions.delete']);

    $position = Position::factory()->create(['name' => 'Engineer']);
    $currentEmployee = Employee::factory()->active()->create();
    $formerEmployee = Employee::factory()->terminated()->create();

    EmploymentAssignment::factory()
        ->for($currentEmployee)
        ->for($position)
        ->current()
        ->create();
    EmploymentAssignment::factory()
        ->for($formerEmployee)
        ->for($position)
        ->endedOn(now()->subDay())
        ->create();

    $this->actingAs($actor)
        ->get(route('positions.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('positions/Index')
            ->where('positions.0.id', $position->id)
            ->where('positions.0.assignments_count', 2)
            ->where('positions.0.is_in_use', true)
            ->where('positions.0.can.delete', true)
        );
});

test('a position can be created company-wide and within a department', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('positions.create');

    $department = Department::factory()->create();

    $this->actingAs($actor)
        ->post(route('positions.store'), ['name' => '  General Manager  '])
        ->assertRedirect(route('positions.index'));

    $this->actingAs($actor)
        ->post(route('positions.store'), [
            'name' => 'Engineer',
            'department_id' => $department->id,
        ])
        ->assertRedirect(route('positions.index'));

    expect(Position::where('name', 'General Manager')->firstOrFail()->department_id)->toBeNull();
    expect(Position::where('name', 'Engineer')->firstOrFail()->department_id)->toBe($department->id);
});

test('position names are unique within the same department', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('positions.create');

    $department = Department::factory()->create();
    Position::factory()->inDepartment($department)->create(['name' => 'Engineer']);

    $this->actingAs($actor)
        ->post(route('positions.store'), [
            'name' => ' engineer ',
            'department_id' => $department->id,
        ])
        ->assertSessionHasErrors('name');

    expect(Position::query()->count())->toBe(1);
});

test('position names are unique among company-wide positions', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('positions.create');

    Position::factory()->create(['name' => 'Engineer']);

    $this->actingAs($actor)
        ->post(route('positions.store'), ['name' => 'ENGINEER'])
        ->assertSessionHasErrors('name');

    expect(Position::query()->count())->toBe(1);
});

test('the same position name can be used in different departments', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('positions.create');

    $operations = Department::factory()->create(['name' => 'Operations']);
    $warehouse = Department::factory()->create(['name' => 'Warehouse']);

    Position::factory()->inDepartment($operations)->create(['name' => 'Engineer']);

    $this->actingAs($actor)
        ->post(route('positions.store'), [
            'name' => 'Engineer',
            'department_id' => $warehouse->id,
        ])
        ->assertRedirect(route('positions.index'));

    expect(Position::where('department_id', $warehouse->id)->firstOrFail()->name)->toBe('Engineer');
});

test('a position can be renamed and moved between departments', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('positions.update');

    $operations = Department::factory()->create();
    $warehouse = Department::factory()->create();
    $position = Position::factory()->inDepartment($operations)->create(['name' => 'Engineer']);

    $this->actingAs($actor)
        ->put(route('positions.update', $position), [
            'name' => 'Senior Engineer',
            'department_id' => $warehouse->id,
        ])
        ->assertRedirect(route('positions.index'));

    $position->refresh();

    expect($position->name)->toBe('Senior Engineer');
    expect($position->department_id)->toBe($warehouse->id);
});

test('a position can be moved back to company-wide', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('positions.update');

    $department = Department::factory()->create();
    $position = Position::factory()->inDepartment($department)->create(['name' => 'Engineer']);

    $this->actingAs($actor)
        ->put(route('positions.update', $position), [
            'name' => 'Engineer',
            'department_id' => '',
        ])
        ->assertRedirect(route('positions.index'));

    expect($position->refresh()->department_id)->toBeNull();
});

test('a position with assignment history cannot move to another department', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('positions.update');

    $operations = Department::factory()->create();
    $warehouse = Department::factory()->create();
    $position = Position::factory()->inDepartment($operations)->create(['name' => 'Engineer']);
    $employee = Employee::factory()->active()->create();
    EmploymentAssignment::factory()
        ->for($employee)
        ->for($position)
        ->current()
        ->create();

    $this->actingAs($actor)
        ->from(route('positions.edit', $position))
        ->put(route('positions.update', $position), [
            'name' => 'Engineer',
            'department_id' => $warehouse->id,
        ])
        ->assertSessionHasErrors([
            'department_id' => 'A position with assignment history cannot be moved to another department.',
        ]);

    expect($position->refresh()->department_id)->toBe($operations->id);
});

test('a position with assignment history cannot move to company-wide', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('positions.update');

    $department = Department::factory()->create();
    $position = Position::factory()->inDepartment($department)->create(['name' => 'Engineer']);
    $employee = Employee::factory()->active()->create();
    EmploymentAssignment::factory()
        ->for($employee)
        ->for($position)
        ->current()
        ->create();

    $this->actingAs($actor)
        ->from(route('positions.edit', $position))
        ->put(route('positions.update', $position), [
            'name' => 'Engineer',
            'department_id' => '',
        ])
        ->assertSessionHasErrors([
            'department_id' => 'A position with assignment history cannot be moved to another department.',
        ]);

    expect($position->refresh()->department_id)->toBe($department->id);
});

test('a position with assignment history can be renamed without changing its scope', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('positions.update');

    $department = Department::factory()->create();
    $position = Position::factory()->inDepartment($department)->create(['name' => 'Engineer']);
    $employee = Employee::factory()->active()->create();
    EmploymentAssignment::factory()
        ->for($employee)
        ->for($position)
        ->current()
        ->create();

    $this->actingAs($actor)
        ->put(route('positions.update', $position), [
            'name' => 'Senior Engineer',
            'department_id' => $department->id,
        ])
        ->assertRedirect(route('positions.index'));

    expect($position->refresh()->name)->toBe('Senior Engineer');
    expect($position->department_id)->toBe($department->id);
});

test('a position cannot be renamed into a name used in its scope', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('positions.update');

    $department = Department::factory()->create();
    Position::factory()->inDepartment($department)->create(['name' => 'Engineer']);
    $designer = Position::factory()->inDepartment($department)->create(['name' => 'Designer']);

    $this->actingAs($actor)
        ->put(route('positions.update', $designer), [
            'name' => 'engineer',
            'department_id' => $department->id,
        ])
        ->assertSessionHasErrors('name');

    expect($designer->refresh()->name)->toBe('Designer');
});

test('the create form is loaded with department options', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('positions.create');

    Department::factory()->create(['name' => 'Operations']);

    $this->actingAs($actor)
        ->get(route('positions.create'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('positions/Create')
            ->has('departmentOptions', 1)
            ->where('departmentOptions.0.name', 'Operations')
        );
});

test('the edit form is loaded with the position and department options', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('positions.update');

    $department = Department::factory()->create(['name' => 'Operations']);
    $position = Position::factory()->inDepartment($department)->create(['name' => 'Engineer']);

    $this->actingAs($actor)
        ->get(route('positions.edit', $position))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('positions/Edit')
            ->where('position.id', $position->id)
            ->where('position.name', 'Engineer')
            ->where('position.department_id', $department->id)
            ->where('position.is_in_use', false)
            ->where('departmentOptions.0.name', 'Operations')
        );
});

test('the edit form marks a position with assignment history as in use', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('positions.update');

    $department = Department::factory()->create(['name' => 'Operations']);
    $position = Position::factory()->inDepartment($department)->create(['name' => 'Engineer']);
    $employee = Employee::factory()->active()->create();
    EmploymentAssignment::factory()
        ->for($employee)
        ->for($position)
        ->current()
        ->create();

    $this->actingAs($actor)
        ->get(route('positions.edit', $position))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('positions/Edit')
            ->where('position.id', $position->id)
            ->where('position.is_in_use', true)
            ->where('position.department_id', $department->id)
        );
});

test('an unused position can be deleted without changing its department', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('positions.delete');

    $department = Department::factory()->create();
    $position = Position::factory()->inDepartment($department)->create(['name' => 'Engineer']);
    $other = Position::factory()->inDepartment($department)->create(['name' => 'Designer']);

    $this->actingAs($actor)
        ->delete(route('positions.destroy', $position))
        ->assertRedirect(route('positions.index'));

    $this->assertDatabaseMissing('positions', ['id' => $position->id]);

    expect($department->fresh())->not->toBeNull();
    expect($other->refresh()->department_id)->toBe($department->id);
});

test('a position with a current assignment cannot be deleted', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('positions.delete');

    $position = Position::factory()->create(['name' => 'Engineer']);
    $employee = Employee::factory()->active()->create();
    EmploymentAssignment::factory()
        ->for($employee)
        ->for($position)
        ->current()
        ->create();

    $this->actingAs($actor)
        ->from(route('positions.index'))
        ->delete(route('positions.destroy', $position))
        ->assertSessionHasErrors([
            'position' => 'The Engineer position cannot be deleted while it has employee assignment history.',
        ]);

    $this->assertModelExists($position);
});

test('a position with only historical assignments cannot be deleted', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('positions.delete');

    $position = Position::factory()->create(['name' => 'Engineer']);
    $employee = Employee::factory()->terminated()->create();
    EmploymentAssignment::factory()
        ->for($employee)
        ->for($position)
        ->endedOn(now()->subDay())
        ->create();

    $this->actingAs($actor)
        ->from(route('positions.index'))
        ->delete(route('positions.destroy', $position))
        ->assertSessionHasErrors([
            'position' => 'The Engineer position cannot be deleted while it has employee assignment history.',
        ]);

    $this->assertModelExists($position);
});
