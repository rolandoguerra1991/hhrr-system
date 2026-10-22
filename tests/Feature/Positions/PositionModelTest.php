<?php

use App\Models\Department;
use App\Models\Position;

test('a position exposes its department and a department exposes its positions', function () {
    $department = Department::factory()->create(['name' => 'Operations']);

    $position = Position::create([
        'name' => 'Engineer',
        'department_id' => $department->id,
    ]);
    $companyWide = Position::create(['name' => 'General Manager']);

    expect($position->department->is($department))->toBeTrue();
    expect($companyWide->department)->toBeNull();
    expect($department->positions->pluck('name')->all())->toBe(['Engineer']);
});

test('the factory creates a company-wide position and a department position', function () {
    $companyWide = Position::factory()->create();
    $department = Department::factory()->create();
    $scoped = Position::factory()->inDepartment($department)->create();

    expect($companyWide->department_id)->toBeNull();
    expect($scoped->department_id)->toBe($department->id);
});
