<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentAssignment;
use App\Models\EmploymentPeriod;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('the employee number sequence is seeded', function () {
    $sequence = DB::table('sequences')->where('name', 'employee_number')->first();

    expect($sequence)->not->toBeNull();
    expect((int) $sequence->value)->toBe(0);
});

test('employee numbers are unique', function () {
    Employee::factory()->create(['employee_number' => 'EMP-0001']);

    expect(fn () => Employee::factory()->create(['employee_number' => 'EMP-0001']))
        ->toThrow(QueryException::class);
});

test('work emails are unique', function () {
    Employee::factory()->create(['work_email' => 'ada@example.com']);

    expect(fn () => Employee::factory()->create(['work_email' => 'ada@example.com']))
        ->toThrow(QueryException::class);
});

test('a login account can only be linked to one employee', function () {
    $user = User::factory()->create();

    Employee::factory()->forUser($user)->create();

    expect(fn () => Employee::factory()->forUser($user)->create())
        ->toThrow(QueryException::class);
});

test('an employee can only have one open employment period', function () {
    $employee = Employee::factory()->create();

    EmploymentPeriod::factory()->for($employee)->active()->create();

    expect(fn () => EmploymentPeriod::factory()->for($employee)->active()->create())
        ->toThrow(QueryException::class);
});

test('an employee can only have one current assignment', function () {
    $employee = Employee::factory()->create();
    $department = Department::factory()->create();

    EmploymentAssignment::factory()->for($employee)->for($department)->current()->create();

    expect(fn () => EmploymentAssignment::factory()->for($employee)->for($department)->current()->create())
        ->toThrow(QueryException::class);
});

test('the employee change log table and its index exist', function () {
    expect(Schema::hasTable('employee_change_logs'))->toBeTrue();
    expect(Schema::hasIndex('employee_change_logs', ['employee_id', 'created_at']))->toBeTrue();
});

test('the employee directory index exists', function () {
    expect(Schema::hasIndex('employees', ['last_name', 'first_name']))->toBeTrue();
});
