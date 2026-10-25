<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentAssignment;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->withoutVite();

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->seed(RolesAndPermissionsSeeder::class);

    $this->actor = User::factory()->create();
    $this->actor->givePermissionTo('employees.update');
});

test('an active employee can be assigned to a department and position', function () {
    $department = Department::factory()->create();
    $position = Position::factory()->inDepartment($department)->create();
    $employee = Employee::factory()->active()->create();

    $this->actingAs($this->actor)
        ->post(route('employees.assignments.store', $employee), [
            'department_id' => $department->id,
            'position_id' => $position->id,
            'effective_from' => now()->toDateString(),
            'reason' => 'Initial assignment',
        ])
        ->assertRedirect(route('employees.show', $employee));

    $assignment = EmploymentAssignment::query()->sole();

    expect($assignment->employee_id)->toBe($employee->id);
    expect($assignment->department_id)->toBe($department->id);
    expect($assignment->position_id)->toBe($position->id);
    expect($assignment->effective_from->toDateString())->toBe(now()->toDateString());
    expect($assignment->effective_to)->toBeNull();
    expect($assignment->change_reason)->toBe('Initial assignment');
    expect($assignment->changed_by)->toBe($this->actor->id);
});

test('the position is optional when assigning an employee', function () {
    $department = Department::factory()->create();
    $employee = Employee::factory()->active()->create();

    $this->actingAs($this->actor)
        ->post(route('employees.assignments.store', $employee), [
            'department_id' => $department->id,
            'effective_from' => now()->toDateString(),
            'reason' => 'Initial assignment',
        ])
        ->assertRedirect(route('employees.show', $employee));

    expect(EmploymentAssignment::query()->sole()->position_id)->toBeNull();
});

test('a terminated employee cannot be assigned', function () {
    $department = Department::factory()->create();
    $employee = Employee::factory()->terminated()->create();

    $this->actingAs($this->actor)
        ->from(route('employees.show', $employee))
        ->post(route('employees.assignments.store', $employee), [
            'department_id' => $department->id,
            'effective_from' => now()->toDateString(),
            'reason' => 'Reassignment',
        ])
        ->assertSessionHasErrors('employee');

    expect(EmploymentAssignment::query()->exists())->toBeFalse();
});

test('a position scoped to another department is rejected', function () {
    $employee = Employee::factory()->active()->create();
    $department = Department::factory()->create();
    $otherDepartment = Department::factory()->create();
    $position = Position::factory()->inDepartment($otherDepartment)->create();

    $this->actingAs($this->actor)
        ->from(route('employees.show', $employee))
        ->post(route('employees.assignments.store', $employee), [
            'department_id' => $department->id,
            'position_id' => $position->id,
            'effective_from' => now()->toDateString(),
            'reason' => 'Initial assignment',
        ])
        ->assertSessionHasErrors('position_id');

    expect(EmploymentAssignment::query()->exists())->toBeFalse();
});

test('a company-wide position can be used in any department', function () {
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => null]);
    $employee = Employee::factory()->active()->create();

    $this->actingAs($this->actor)
        ->post(route('employees.assignments.store', $employee), [
            'department_id' => $department->id,
            'position_id' => $position->id,
            'effective_from' => now()->toDateString(),
            'reason' => 'Initial assignment',
        ])
        ->assertRedirect(route('employees.show', $employee));

    expect(EmploymentAssignment::query()->sole()->position_id)->toBe($position->id);
});

test('an assignment cannot start before the employment period', function () {
    $department = Department::factory()->create();
    $employee = Employee::factory()->active()->create();
    $employee->currentPeriod->update(['started_on' => now()->subDays(5)->toDateString()]);

    $this->actingAs($this->actor)
        ->from(route('employees.show', $employee))
        ->post(route('employees.assignments.store', $employee), [
            'department_id' => $department->id,
            'effective_from' => now()->subDays(10)->toDateString(),
            'reason' => 'Initial assignment',
        ])
        ->assertSessionHasErrors('effective_from');

    expect(EmploymentAssignment::query()->exists())->toBeFalse();
});

test('an assignment cannot start on or before the current assignment', function () {
    $employee = Employee::factory()->active()->create();
    $department = Department::factory()->create();
    $otherDepartment = Department::factory()->create();

    EmploymentAssignment::factory()
        ->for($employee)
        ->for($department)
        ->current()
        ->create(['effective_from' => now()->subDays(5)->toDateString()]);

    $this->actingAs($this->actor)
        ->from(route('employees.show', $employee))
        ->post(route('employees.assignments.store', $employee), [
            'department_id' => $otherDepartment->id,
            'effective_from' => now()->subDays(5)->toDateString(),
            'reason' => 'Transfer',
        ])
        ->assertSessionHasErrors('effective_from');

    expect($employee->employmentAssignments()->count())->toBe(1);
});

test('a transfer closes the current assignment and appends history', function () {
    $employee = Employee::factory()->active()->create();
    $department = Department::factory()->create(['name' => 'Operations']);
    $otherDepartment = Department::factory()->create(['name' => 'Finance']);

    $previous = EmploymentAssignment::factory()
        ->for($employee)
        ->for($department)
        ->current()
        ->create(['effective_from' => now()->subMonth()->toDateString()]);

    $this->actingAs($this->actor)
        ->post(route('employees.assignments.store', $employee), [
            'department_id' => $otherDepartment->id,
            'effective_from' => now()->toDateString(),
            'reason' => 'Department transfer',
        ])
        ->assertRedirect(route('employees.show', $employee));

    $previous->refresh();

    expect($previous->effective_to->toDateString())->toBe(now()->subDay()->toDateString());
    expect($employee->employmentAssignments()->count())->toBe(2);
    expect($employee->currentAssignment->department_id)->toBe($otherDepartment->id);
    expect($employee->employmentAssignments()->whereNull('effective_to')->count())->toBe(1);
});

test('a backdated transfer is allowed after the current assignment start', function () {
    $employee = Employee::factory()->active()->create();
    $department = Department::factory()->create();
    $otherDepartment = Department::factory()->create();

    $previous = EmploymentAssignment::factory()
        ->for($employee)
        ->for($department)
        ->current()
        ->create(['effective_from' => now()->subMonth()->toDateString()]);

    $effectiveFrom = now()->subDays(10)->toDateString();

    $this->actingAs($this->actor)
        ->post(route('employees.assignments.store', $employee), [
            'department_id' => $otherDepartment->id,
            'effective_from' => $effectiveFrom,
            'reason' => 'Backdated transfer',
        ])
        ->assertRedirect(route('employees.show', $employee));

    expect($previous->refresh()->effective_to->toDateString())->toBe(now()->subDays(11)->toDateString());
    expect($employee->currentAssignment->effective_from->toDateString())->toBe($effectiveFrom);
});

test('a future assignment date is rejected', function () {
    $employee = Employee::factory()->active()->create();
    $department = Department::factory()->create();

    $this->actingAs($this->actor)
        ->from(route('employees.show', $employee))
        ->post(route('employees.assignments.store', $employee), [
            'department_id' => $department->id,
            'effective_from' => now()->addDay()->toDateString(),
            'reason' => 'Future transfer',
        ])
        ->assertSessionHasErrors('effective_from');

    expect(EmploymentAssignment::query()->exists())->toBeFalse();
});

test('an assignment requires a department and a reason', function () {
    $employee = Employee::factory()->active()->create();

    $this->actingAs($this->actor)
        ->from(route('employees.show', $employee))
        ->post(route('employees.assignments.store', $employee), [
            'effective_from' => now()->toDateString(),
            'reason' => '   ',
        ])
        ->assertSessionHasErrors(['department_id', 'reason']);

    expect(EmploymentAssignment::query()->exists())->toBeFalse();
});

test('the reporting manager is derived from the assigned department', function () {
    $manager = Employee::factory()->active()->create([
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
    ]);

    $department = Department::factory()->create(['manager_id' => $manager->id]);
    $position = Position::factory()->inDepartment($department)->create();
    $employee = Employee::factory()->active()->create();

    $this->actingAs($this->actor)
        ->post(route('employees.assignments.store', $employee), [
            'department_id' => $department->id,
            'position_id' => $position->id,
            'effective_from' => now()->toDateString(),
            'reason' => 'Initial assignment',
        ]);

    expect($employee->refresh()->reporting_manager?->full_name)->toBe('Grace Hopper');
});
