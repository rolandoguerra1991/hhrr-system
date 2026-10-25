<?php

use App\Enums\Role as RoleEnum;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeChangeLog;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->withoutVite();

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->seed(RolesAndPermissionsSeeder::class);
});

test('guests cannot view the employee directory', function () {
    $this->get(route('employees.index'))->assertRedirect(route('login'));
});

test('users without the employees view permission are forbidden', function () {
    $actor = User::factory()->create();
    $employee = Employee::factory()->active()->create();

    $this->actingAs($actor)->get(route('employees.index'))->assertForbidden();
    $this->actingAs($actor)->get(route('employees.show', $employee))->assertForbidden();
    $this->actingAs($actor)->get(route('employees.create'))->assertForbidden();
});

test('readers can browse employees without receiving write controls', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('employees.view');

    $employee = Employee::factory()->active()->create();

    $this->actingAs($actor)
        ->get(route('employees.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('employees/Index', false)
            ->has('employees.data', 1)
            ->where('employees.data.0.can.update', false)
            ->where('auth.can.employees.view', true)
            ->where('auth.can.employees.create', false)
            ->where('auth.can.employees.update', false)
        );

    $this->actingAs($actor)
        ->get(route('employees.show', $employee))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('employees/Show', false)
            ->where('employee.can.update', false)
        );

    $this->actingAs($actor)->get(route('employees.create'))->assertForbidden();
    $this->actingAs($actor)->get(route('employees.edit', $employee))->assertForbidden();
});

test('users without employees view do not receive the employees navigation ability', function () {
    $actor = User::factory()->create();

    $this->actingAs($actor)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('auth.can.employees.view', false)
            ->where('auth.can.employees.create', false)
            ->where('auth.can.employees.update', false)
        );
});

test('a user with employees create can create an employee with initial employment', function () {
    $actor = User::factory()->create(['name' => 'Actor']);
    $actor->givePermissionTo('employees.create');

    $linkedUser = User::factory()->create();

    $this->actingAs($actor)
        ->post(route('employees.store'), [
            'first_name' => '  Ada ',
            'last_name' => ' Lovelace ',
            'work_email' => '  ADA@Example.COM ',
            'user_id' => $linkedUser->id,
            'started_on' => now()->subDay()->toDateString(),
        ])
        ->assertRedirect();

    $employee = Employee::query()->sole();

    expect($employee->employee_number)->toBe('EMP-0001');
    expect($employee->first_name)->toBe('Ada');
    expect($employee->last_name)->toBe('Lovelace');
    expect($employee->work_email)->toBe('ada@example.com');
    expect($employee->user_id)->toBe($linkedUser->id);

    $period = $employee->employmentPeriods()->sole();

    expect($period->started_on->toDateString())->toBe(now()->subDay()->toDateString());
    expect($period->ended_on)->toBeNull();
    expect($period->start_reason)->toBe('Initial hire');
    expect($period->created_by)->toBe($actor->id);
    expect($employee->isActive())->toBeTrue();
});

test('creating an employee without a work email or account is allowed', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('employees.create');

    $this->actingAs($actor)
        ->post(route('employees.store'), [
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'started_on' => now()->toDateString(),
        ])
        ->assertRedirect();

    $employee = Employee::query()->sole();

    expect($employee->work_email)->toBeNull();
    expect($employee->user_id)->toBeNull();
    expect($employee->isActive())->toBeTrue();
});

test('employee creation rejects a future employment start date', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('employees.create');

    $this->actingAs($actor)
        ->from(route('employees.create'))
        ->post(route('employees.store'), [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'started_on' => now()->addDay()->toDateString(),
        ])
        ->assertSessionHasErrors('started_on');

    expect(Employee::query()->exists())->toBeFalse();
});

test('the employee number is generated and cannot be replaced', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo(['employees.create', 'employees.update']);

    $this->actingAs($actor)->post(route('employees.store'), [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'employee_number' => 'EMP-9999',
        'started_on' => now()->toDateString(),
    ]);

    $employee = Employee::query()->sole();

    expect($employee->employee_number)->toBe('EMP-0001');

    $this->actingAs($actor)
        ->put(route('employees.update', $employee), [
            'first_name' => 'Ada',
            'last_name' => 'Byron',
            'employee_number' => 'EMP-9999',
            'reason' => 'Corrected last name',
        ])
        ->assertRedirect(route('employees.show', $employee));

    expect($employee->refresh()->employee_number)->toBe('EMP-0001');
});

test('work email uniqueness ignores case and surrounding whitespace', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo(['employees.create', 'employees.update']);

    Employee::factory()->create(['work_email' => 'ada@example.com']);

    $this->actingAs($actor)
        ->from(route('employees.create'))
        ->post(route('employees.store'), [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'work_email' => ' ADA@EXAMPLE.COM ',
            'started_on' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('work_email');

    expect(Employee::query()->count())->toBe(1);

    $employee = Employee::factory()->active()->create(['work_email' => 'grace@example.com']);

    $this->actingAs($actor)
        ->from(route('employees.edit', $employee))
        ->put(route('employees.update', $employee), [
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'work_email' => 'ADA@example.com',
            'reason' => 'Changed work email',
        ])
        ->assertSessionHasErrors('work_email');

    expect($employee->refresh()->work_email)->toBe('grace@example.com');
});

test('a login account can only be linked to one employee', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo(['employees.create', 'employees.update']);

    $linkedUser = User::factory()->create();
    Employee::factory()->create(['user_id' => $linkedUser->id]);

    $this->actingAs($actor)
        ->from(route('employees.create'))
        ->post(route('employees.store'), [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'user_id' => $linkedUser->id,
            'started_on' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('user_id');

    $employee = Employee::factory()->active()->create();

    $this->actingAs($actor)
        ->from(route('employees.edit', $employee))
        ->put(route('employees.update', $employee), [
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'user_id' => $linkedUser->id,
            'reason' => 'Linked account',
        ])
        ->assertSessionHasErrors('user_id');

    expect($employee->refresh()->user_id)->toBeNull();
});

test('the account selector only offers unlinked accounts and keeps the current link on edit', function () {
    $actor = User::factory()->create(['name' => 'Zed Actor']);
    $actor->givePermissionTo(['employees.create', 'employees.update']);

    $linkedUser = User::factory()->create(['name' => 'Linked User']);
    $unlinkedUser = User::factory()->create(['name' => 'Free User']);
    $employee = Employee::factory()->create(['user_id' => $linkedUser->id]);

    $this->actingAs($actor)
        ->get(route('employees.create'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('employees/Create', false)
            ->has('users', 2)
            ->where('users.0.id', $unlinkedUser->id)
            ->where('users.1.id', $actor->id)
        );

    $this->actingAs($actor)
        ->get(route('employees.edit', $employee))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('employees/Edit', false)
            ->where('employee.employee_number', $employee->employee_number)
            ->where('employee.user_id', $linkedUser->id)
            ->has('users', 3)
            ->where('users.0.id', $unlinkedUser->id)
            ->where('users.1.id', $linkedUser->id)
            ->where('users.2.id', $actor->id)
        );
});

test('a core edit requires a reason', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('employees.update');

    $employee = Employee::factory()->active()->create([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
    ]);

    $this->actingAs($actor)
        ->from(route('employees.edit', $employee))
        ->put(route('employees.update', $employee), [
            'first_name' => 'Ada',
            'last_name' => 'Byron',
            'reason' => '   ',
        ])
        ->assertSessionHasErrors('reason');

    expect($employee->refresh()->last_name)->toBe('Lovelace');
    expect(EmployeeChangeLog::query()->exists())->toBeFalse();
});

test('a core edit records the actor reason and changed values', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('employees.update');

    $employee = Employee::factory()->active()->create([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'work_email' => 'ada@example.com',
    ]);

    $this->actingAs($actor)
        ->put(route('employees.update', $employee), [
            'first_name' => 'Ada',
            'last_name' => 'Byron',
            'work_email' => 'ada@example.com',
            'reason' => 'Corrected last name',
        ])
        ->assertRedirect(route('employees.show', $employee));

    $employee->refresh();

    expect($employee->last_name)->toBe('Byron');

    $log = EmployeeChangeLog::query()->sole();

    expect($log->employee_id)->toBe($employee->id);
    expect($log->changed_by)->toBe($actor->id);
    expect($log->reason)->toBe('Corrected last name');
    expect($log->changes)->toBe([
        'last_name' => ['old' => 'Lovelace', 'new' => 'Byron'],
    ]);
    expect($log->created_at)->not->toBeNull();
});

test('an edit without an audited change writes no audit entry', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('employees.update');

    $employee = Employee::factory()->active()->create([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'work_email' => 'ada@example.com',
    ]);

    $this->actingAs($actor)
        ->put(route('employees.update', $employee), [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'work_email' => 'ada@example.com',
            'reason' => 'Reviewed record',
        ])
        ->assertRedirect(route('employees.show', $employee));

    expect(EmployeeChangeLog::query()->exists())->toBeFalse();
});

test('the directory shows current assignment and reporting manager', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('employees.view');

    $manager = Employee::factory()->active()->create([
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
    ]);

    $department = Department::factory()->create([
        'name' => 'Operations',
        'manager_id' => $manager->id,
    ]);
    $position = Position::factory()->inDepartment($department)->create(['name' => 'Engineer']);

    Employee::factory()
        ->assigned($department, $position)
        ->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);

    $this->actingAs($actor)
        ->get(route('employees.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('employees.data', 2)
            ->where('employees.data.0.full_name', 'Grace Hopper')
            ->where('employees.data.0.status', 'active')
            ->where('employees.data.1.full_name', 'Ada Lovelace')
            ->where('employees.data.1.current_department', 'Operations')
            ->where('employees.data.1.current_position', 'Engineer')
            ->where('employees.data.1.reporting_manager', 'Grace Hopper')
        );
});

test('the directory can be searched by number name and work email', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('employees.view');

    $ada = Employee::factory()->active()->create([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'work_email' => 'ada@example.com',
    ]);

    Employee::factory()->active()->create([
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
        'work_email' => 'grace@example.com',
    ]);

    foreach ([$ada->employee_number, 'Lovelace', 'grace@example.com'] as $index => $search) {
        $expected = $index === 2 ? 'Grace Hopper' : 'Ada Lovelace';

        $this->actingAs($actor)
            ->get(route('employees.index', ['search' => $search]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('employees.data', 1)
                ->where('employees.data.0.full_name', $expected)
                ->where('filters.search', $search)
            );
    }
});

test('the directory can be filtered by active and terminated status', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('employees.view');

    Employee::factory()->active()->create(['first_name' => 'Active', 'last_name' => 'Employee']);
    Employee::factory()->terminated()->create(['first_name' => 'Former', 'last_name' => 'Employee']);

    $this->actingAs($actor)
        ->get(route('employees.index', ['status' => 'active']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('employees.data', 1)
            ->where('employees.data.0.full_name', 'Active Employee')
            ->where('filters.status', 'active')
        );

    $this->actingAs($actor)
        ->get(route('employees.index', ['status' => 'terminated']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('employees.data', 1)
            ->where('employees.data.0.full_name', 'Former Employee')
            ->where('employees.data.0.status', 'terminated')
            ->where('filters.status', 'terminated')
        );
});

test('the detail page exposes periods assignments audit history and options', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo(['employees.view', 'employees.update']);

    $manager = Employee::factory()->active()->create(['first_name' => 'Grace', 'last_name' => 'Hopper']);
    $department = Department::factory()->create(['name' => 'Operations', 'manager_id' => $manager->id]);
    $position = Position::factory()->inDepartment($department)->create(['name' => 'Engineer']);

    $employee = Employee::factory()
        ->assigned($department, $position)
        ->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);

    EmployeeChangeLog::create([
        'employee_id' => $employee->id,
        'changed_by' => $actor->id,
        'reason' => 'Corrected spelling',
        'changes' => ['last_name' => ['old' => 'Lovelase', 'new' => 'Lovelace']],
    ]);

    $this->actingAs($actor)
        ->get(route('employees.show', $employee))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('employees/Show', false)
            ->where('employee.employee_number', $employee->employee_number)
            ->where('employee.can.update', true)
            ->where('auth.can.employees.update', true)
            ->where('employee.current_assignment.department_name', 'Operations')
            ->where('employee.current_assignment.position_name', 'Engineer')
            ->where('employee.reporting_manager', 'Grace Hopper')
            ->has('periods', 1)
            ->has('assignments', 1)
            ->where('assignments.0.department_name', 'Operations')
            ->has('changeLogs', 1)
            ->where('changeLogs.0.reason', 'Corrected spelling')
            ->has('departmentOptions', 1)
            ->has('positionOptions', 1)
        );
});

test('employees have no delete route or delete ability', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo(['employees.view', 'employees.update']);
    $employee = Employee::factory()->active()->create();

    expect(Route::has('employees.destroy'))->toBeFalse();
    expect($actor->can('delete', $employee))->toBeFalse();

    $this->actingAs($actor)
        ->delete("/employees/{$employee->id}")
        ->assertStatus(405);

    expect(Employee::query()->whereKey($employee->id)->exists())->toBeTrue();
});

test('super admins can access employees through the policy before hook', function () {
    $actor = User::factory()->create();
    $actor->assignRole(RoleEnum::SuperAdmin->value);

    $this->actingAs($actor)->get(route('employees.index'))->assertOk();
});
