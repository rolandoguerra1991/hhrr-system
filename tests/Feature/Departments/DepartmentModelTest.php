<?php

use App\Models\Department;
use App\Models\User;

test('a department exposes its parent, children, manager, and users', function () {
    $parent = Department::factory()->create(['name' => 'Operations']);
    $manager = User::factory()->create();

    $parent->update(['manager_id' => $manager->id]);

    Department::factory()->childOf($parent)->create(['name' => 'Beta']);
    Department::factory()->childOf($parent)->create(['name' => 'Alpha']);

    $member = User::factory()->create(['department_id' => $parent->id]);

    expect($parent->children->pluck('name')->all())->toBe(['Alpha', 'Beta']);
    expect($parent->children->first()->parent->is($parent))->toBeTrue();
    expect($parent->manager->is($manager))->toBeTrue();
    expect($parent->users->pluck('id')->all())->toBe([$member->id]);
    expect($member->department->is($parent))->toBeTrue();
});

test('the factory creates a root department and a child with the expected parent', function () {
    $root = Department::factory()->create();
    $child = Department::factory()->childOf($root)->create();

    expect($root->parent_id)->toBeNull();
    expect($child->parent_id)->toBe($root->id);
});

test('deleting the manager leaves the department without a manager', function () {
    $manager = User::factory()->create();
    $department = Department::factory()->create(['manager_id' => $manager->id]);

    $manager->delete();

    expect($department->refresh()->manager_id)->toBeNull();
});
