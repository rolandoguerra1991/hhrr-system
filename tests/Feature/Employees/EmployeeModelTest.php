<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeChangeLog;
use App\Models\EmploymentAssignment;
use App\Models\EmploymentPeriod;
use App\Models\Position;
use App\Models\User;

test('an employee exposes its period, assignment, audit, and reporting relations', function () {
    $user = User::factory()->create();
    $employee = Employee::factory()
        ->forUser($user)
        ->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);

    $period = EmploymentPeriod::factory()
        ->for($employee)
        ->active()
        ->create(['started_on' => now()->subYear()->toDateString()]);

    $manager = Employee::factory()
        ->active()
        ->create(['first_name' => 'Grace', 'last_name' => 'Hopper']);

    $department = Department::factory()->create(['name' => 'Operations']);
    $department->update(['manager_id' => $manager->id]);

    $position = Position::factory()->inDepartment($department)->create(['name' => 'Engineer']);

    $assignment = EmploymentAssignment::factory()
        ->for($employee)
        ->for($department)
        ->current()
        ->create(['position_id' => $position->id]);

    $log = EmployeeChangeLog::create([
        'employee_id' => $employee->id,
        'changed_by' => $user->id,
        'reason' => 'Corrected spelling',
        'changes' => ['last_name' => ['old' => 'Lovelace', 'new' => 'Byron']],
    ]);

    expect($employee->full_name)->toBe('Ada Lovelace');
    expect($employee->isActive())->toBeTrue();
    expect($employee->isTerminated())->toBeFalse();
    expect($employee->user->is($user))->toBeTrue();
    expect($employee->currentPeriod->is($period))->toBeTrue();
    expect($employee->currentAssignment->is($assignment))->toBeTrue();
    expect($employee->currentAssignment->department->is($department))->toBeTrue();
    expect($employee->currentAssignment->position->is($position))->toBeTrue();
    expect($employee->reporting_manager?->is($manager))->toBeTrue();
    expect($employee->employmentPeriods)->toHaveCount(1);
    expect($employee->employmentAssignments)->toHaveCount(1);
    expect($employee->changeLogs)->toHaveCount(1);
    expect($employee->changeLogs->first()->is($log))->toBeTrue();
    expect($log->changedBy->is($user))->toBeTrue();
    expect($department->currentAssignments->first()->is($assignment))->toBeTrue();
    expect($position->employmentAssignments->first()->is($assignment))->toBeTrue();
});

test('the employee factory creates identity data without employment history by default', function () {
    $employee = Employee::factory()->create();

    expect($employee->employee_number)->toBe('EMP-0001');
    expect($employee->currentPeriod)->toBeNull();
    expect($employee->currentAssignment)->toBeNull();
    expect($employee->isActive())->toBeFalse();
});

test('the active factory state creates an open period', function () {
    $employee = Employee::factory()->active()->create();

    expect($employee->currentPeriod)->not->toBeNull();
    expect($employee->currentPeriod->ended_on)->toBeNull();
    expect($employee->isActive())->toBeTrue();
});

test('the terminated factory state creates a closed period', function () {
    $employee = Employee::factory()->terminated()->create();

    expect($employee->currentPeriod)->toBeNull();
    expect($employee->employmentPeriods)->toHaveCount(1);
    expect($employee->employmentPeriods->first()->ended_on)->not->toBeNull();
    expect($employee->isTerminated())->toBeTrue();
});

test('the assigned factory state creates a current assignment', function () {
    $department = Department::factory()->create();
    $employee = Employee::factory()->assigned($department)->create();

    expect($employee->isActive())->toBeTrue();
    expect($employee->currentAssignment)->not->toBeNull();
    expect($employee->currentAssignment->department->is($department))->toBeTrue();
});

test('employee numbers increment sequentially', function () {
    $first = Employee::factory()->create();
    $second = Employee::factory()->create();

    expect($first->employee_number)->toBe('EMP-0001');
    expect($second->employee_number)->toBe('EMP-0002');
});
