<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentAssignment;
use App\Models\EmploymentPeriod;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->withoutVite();

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->seed(RolesAndPermissionsSeeder::class);

    $this->actor = User::factory()->create();
    $this->actor->givePermissionTo('employees.update');
});

test('termination closes the employment period and current assignment', function () {
    $department = Department::factory()->create();
    $employee = Employee::factory()->active()->create();

    $period = $employee->currentPeriod;
    $assignment = EmploymentAssignment::factory()
        ->for($employee)
        ->for($department)
        ->current()
        ->create(['effective_from' => now()->subMonth()->toDateString()]);

    $this->actingAs($this->actor)
        ->post(route('employees.terminate', $employee), [
            'ended_on' => now()->toDateString(),
            'termination_reason' => 'Resigned',
        ])
        ->assertRedirect(route('employees.show', $employee));

    $period->refresh();
    $assignment->refresh();
    $employee->refresh();

    expect($period->ended_on->toDateString())->toBe(now()->toDateString());
    expect($period->termination_reason)->toBe('Resigned');
    expect($period->terminated_by)->toBe($this->actor->id);
    expect($assignment->effective_to->toDateString())->toBe(now()->toDateString());
    expect($employee->isTerminated())->toBeTrue();
    expect($employee->currentAssignment)->toBeNull();
    expect(Employee::query()->whereKey($employee->id)->exists())->toBeTrue();
});

test('termination keeps the period and assignment history', function () {
    $employee = Employee::factory()->active()->create();
    $department = Department::factory()->create();

    EmploymentAssignment::factory()
        ->for($employee)
        ->for($department)
        ->current()
        ->create(['effective_from' => now()->subMonth()->toDateString()]);

    $this->actingAs($this->actor)
        ->post(route('employees.terminate', $employee), [
            'ended_on' => now()->toDateString(),
            'termination_reason' => 'Resigned',
        ]);

    expect($employee->employmentPeriods()->count())->toBe(1);
    expect($employee->employmentAssignments()->count())->toBe(1);
});

test('the termination date cannot be in the future', function () {
    $employee = Employee::factory()->active()->create();

    $this->actingAs($this->actor)
        ->from(route('employees.show', $employee))
        ->post(route('employees.terminate', $employee), [
            'ended_on' => now()->addDay()->toDateString(),
            'termination_reason' => 'Resigned',
        ])
        ->assertSessionHasErrors('ended_on');

    expect($employee->refresh()->isActive())->toBeTrue();
});

test('the termination date cannot be before the employment start', function () {
    $employee = Employee::factory()->active()->create();
    $employee->currentPeriod->update(['started_on' => now()->subDays(5)->toDateString()]);

    $this->actingAs($this->actor)
        ->from(route('employees.show', $employee))
        ->post(route('employees.terminate', $employee), [
            'ended_on' => now()->subDays(10)->toDateString(),
            'termination_reason' => 'Resigned',
        ])
        ->assertSessionHasErrors('ended_on');

    expect($employee->refresh()->isActive())->toBeTrue();
});

test('termination requires a reason', function () {
    $employee = Employee::factory()->active()->create();

    $this->actingAs($this->actor)
        ->from(route('employees.show', $employee))
        ->post(route('employees.terminate', $employee), [
            'ended_on' => now()->toDateString(),
            'termination_reason' => '   ',
        ])
        ->assertSessionHasErrors('termination_reason');

    expect($employee->refresh()->isActive())->toBeTrue();
});

test('a department manager cannot be terminated until the department is reassigned', function () {
    $employee = Employee::factory()->active()->create();
    Department::factory()->create(['name' => 'Operations', 'manager_id' => $employee->id]);
    Department::factory()->create(['name' => 'Finance', 'manager_id' => $employee->id]);

    $this->actingAs($this->actor)
        ->from(route('employees.show', $employee))
        ->post(route('employees.terminate', $employee), [
            'ended_on' => now()->toDateString(),
            'termination_reason' => 'Resigned',
        ])
        ->assertSessionHasErrors('employee');

    expect(session('errors')->get('employee')[0])
        ->toContain('Finance')
        ->toContain('Operations');

    expect($employee->refresh()->isActive())->toBeTrue();
});

test('termination clears the linked account remember token and database sessions', function () {
    config(['session.driver' => 'database']);

    $user = User::factory()->create();
    $employee = Employee::factory()->active()->forUser($user)->create();

    $user->forceFill(['remember_token' => 'remember-me'])->save();

    DB::table('sessions')->insert([
        'id' => 'linked-session',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($this->actor)
        ->post(route('employees.terminate', $employee), [
            'ended_on' => now()->toDateString(),
            'termination_reason' => 'Resigned',
        ])
        ->assertRedirect(route('employees.show', $employee));

    expect($user->refresh()->remember_token)->toBeNull();
    expect(DB::table('sessions')->where('id', 'linked-session')->exists())->toBeFalse();
});

test('a terminated employee can be rehired without restoring the assignment', function () {
    $department = Department::factory()->create();
    $employee = Employee::factory()->active()->create();
    $employeeNumber = $employee->employee_number;

    EmploymentAssignment::factory()
        ->for($employee)
        ->for($department)
        ->current()
        ->create([
            'effective_from' => now()->subMonths(2)->toDateString(),
            'effective_to' => now()->subMonth()->toDateString(),
        ]);

    $employee->currentPeriod->update([
        'ended_on' => now()->subMonth()->toDateString(),
        'termination_reason' => 'Resigned',
    ]);

    $previousEnd = now()->subMonth()->toDateString();

    $this->actingAs($this->actor)
        ->post(route('employees.rehire', $employee), [
            'started_on' => now()->toDateString(),
            'start_reason' => 'Rehired',
        ])
        ->assertRedirect(route('employees.show', $employee));

    $employee->refresh();

    expect($employee->employee_number)->toBe($employeeNumber);
    expect($employee->isActive())->toBeTrue();
    expect($employee->employmentPeriods()->count())->toBe(2);
    expect($employee->currentPeriod->started_on->toDateString())->toBe(now()->toDateString());
    expect($employee->currentPeriod->start_reason)->toBe('Rehired');
    expect($employee->currentPeriod->created_by)->toBe($this->actor->id);
    expect($employee->currentAssignment)->toBeNull();

    $previousPeriod = $employee->employmentPeriods()->orderBy('started_on')->first();

    expect($previousPeriod->ended_on->toDateString())->toBe($previousEnd);
});

test('rehire must start after the previous employment end', function () {
    $employee = Employee::factory()->terminated()->create();

    $endedOn = $employee->employmentPeriods()->first()->ended_on;

    $this->actingAs($this->actor)
        ->from(route('employees.show', $employee))
        ->post(route('employees.rehire', $employee), [
            'started_on' => $endedOn->toDateString(),
            'start_reason' => 'Rehired',
        ])
        ->assertSessionHasErrors('started_on');

    expect($employee->refresh()->isTerminated())->toBeTrue();
    expect($employee->employmentPeriods()->count())->toBe(1);
});

test('rehire cannot be in the future', function () {
    $employee = Employee::factory()->terminated()->create();

    $this->actingAs($this->actor)
        ->from(route('employees.show', $employee))
        ->post(route('employees.rehire', $employee), [
            'started_on' => now()->addDay()->toDateString(),
            'start_reason' => 'Rehired',
        ])
        ->assertSessionHasErrors('started_on');

    expect($employee->refresh()->isTerminated())->toBeTrue();
});

test('an active employee cannot be rehired', function () {
    $employee = Employee::factory()->active()->create();

    $this->actingAs($this->actor)
        ->from(route('employees.show', $employee))
        ->post(route('employees.rehire', $employee), [
            'started_on' => now()->toDateString(),
            'start_reason' => 'Rehired',
        ])
        ->assertSessionHasErrors('employee');

    expect($employee->employmentPeriods()->count())->toBe(1);
});

test('rehire requires a start reason', function () {
    $employee = Employee::factory()->terminated()->create();

    $this->actingAs($this->actor)
        ->from(route('employees.show', $employee))
        ->post(route('employees.rehire', $employee), [
            'started_on' => now()->toDateString(),
            'start_reason' => '   ',
        ])
        ->assertSessionHasErrors('start_reason');

    expect($employee->refresh()->isTerminated())->toBeTrue();
});

test('termination does not hard delete the employee record', function () {
    $employee = Employee::factory()->active()->create();

    $this->actingAs($this->actor)
        ->post(route('employees.terminate', $employee), [
            'ended_on' => now()->toDateString(),
            'termination_reason' => 'Resigned',
        ]);

    expect(Employee::query()->whereKey($employee->id)->exists())->toBeTrue();
    expect(EmploymentPeriod::query()->where('employee_id', $employee->id)->exists())->toBeTrue();
});
