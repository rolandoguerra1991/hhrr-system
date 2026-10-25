<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentAssignment;
use App\Models\EmploymentPeriod;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\EmployeeSeeder;

test('local and development environments seed demo employees with history', function (string $environment) {
    $this->app['env'] = $environment;

    $this->seed(DatabaseSeeder::class);

    expect(Employee::query()->count())->toBe(5)
        ->and(Employee::query()->whereHas('currentPeriod')->count())->toBe(4);

    $manager = Employee::query()
        ->where('work_email', 'grace.hopper@example.com')
        ->firstOrFail();

    expect($manager->employee_number)->not->toBeEmpty()
        ->and($manager->currentAssignment)->not->toBeNull()
        ->and($manager->currentAssignment->department->name)->toBe('Human Resources')
        ->and($manager->currentAssignment->position->name)->toBe('HR Manager');

    $formerEmployee = Employee::query()
        ->where('work_email', 'margaret.hamilton@example.com')
        ->firstOrFail();

    expect($formerEmployee->isTerminated())->toBeTrue()
        ->and($formerEmployee->employmentPeriods()->whereNotNull('ended_on')->exists())->toBeTrue()
        ->and($formerEmployee->employmentAssignments()->whereNotNull('effective_to')->exists())->toBeTrue()
        ->and($formerEmployee->currentAssignment)->toBeNull();

    $humanResources = Department::query()->where('name', 'Human Resources')->firstOrFail();
    $operations = Department::query()->where('name', 'Operations')->firstOrFail();

    expect($humanResources->manager_id)->toBe($manager->id)
        ->and($operations->manager)->not->toBeNull()
        ->and($operations->manager->isActive())->toBeTrue();
})->with(['local', 'development']);

test('demo employees are skipped outside the local and development environments', function () {
    $this->app['env'] = 'production';

    $this->artisan('db:seed', [
        '--class' => DatabaseSeeder::class,
        '--force' => true,
    ]);

    expect(Employee::query()->count())->toBe(0)
        ->and(Department::query()->count())->toBe(0);
});

test('rerunning the employee seeder does not duplicate demo data', function () {
    $this->app['env'] = 'local';

    $this->seed(DatabaseSeeder::class);

    $employeeCount = Employee::query()->count();
    $periodCount = EmploymentPeriod::query()->count();
    $assignmentCount = EmploymentAssignment::query()->count();
    $managerIds = Department::query()->pluck('manager_id', 'id')->all();

    $this->seed(EmployeeSeeder::class);

    expect(Employee::query()->count())->toBe($employeeCount)
        ->and(EmploymentPeriod::query()->count())->toBe($periodCount)
        ->and(EmploymentAssignment::query()->count())->toBe($assignmentCount)
        ->and(Department::query()->pluck('manager_id', 'id')->all())->toBe($managerIds);
});
