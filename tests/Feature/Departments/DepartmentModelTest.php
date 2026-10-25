<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentAssignment;
use App\Models\User;

test('a department exposes its hierarchy, employee manager, and assignments', function () {
    $parent = Department::factory()->create(['name' => 'Operations']);
    $manager = Employee::factory()->active()->create();
    $member = Employee::factory()->active()->create();

    $parent->update(['manager_id' => $manager->id]);

    Department::factory()->childOf($parent)->create(['name' => 'Beta']);
    Department::factory()->childOf($parent)->create(['name' => 'Alpha']);

    $assignment = EmploymentAssignment::factory()
        ->for($member)
        ->for($parent)
        ->current()
        ->create();

    expect($parent->children->pluck('name')->all())->toBe(['Alpha', 'Beta']);
    expect($parent->children->first()->parent->is($parent))->toBeTrue();
    expect($parent->manager->is($manager))->toBeTrue();
    expect($parent->currentAssignments->sole()->is($assignment))->toBeTrue();
    expect($member->currentAssignment->department->is($parent))->toBeTrue();
});

test('an employee can manage multiple departments', function () {
    $manager = Employee::factory()->active()->create();
    $operations = Department::factory()->create(['manager_id' => $manager->id]);
    $support = Department::factory()->create(['manager_id' => $manager->id]);

    expect($manager->departmentsManaged->modelKeys())->toBe([$operations->id, $support->id]);
});

test('the factory creates a root department and a child with the expected parent', function () {
    $root = Department::factory()->create();
    $child = Department::factory()->childOf($root)->create();

    expect($root->parent_id)->toBeNull();
    expect($child->parent_id)->toBe($root->id);
});

test('deleting a manager account preserves the employee manager', function () {
    $user = User::factory()->create();
    $manager = Employee::factory()->active()->forUser($user)->create();
    $department = Department::factory()->create(['manager_id' => $manager->id]);

    $user->delete();

    expect($department->refresh()->manager->is($manager))->toBeTrue();
    expect($manager->refresh()->user_id)->toBeNull();
});
