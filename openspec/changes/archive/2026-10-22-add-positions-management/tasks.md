# Tasks

## 1. Schema and model

- [x] 1.1 Create the `create_positions_table` migration with `name`, nullable `department_id` FK to `departments` (`restrictOnDelete`, indexed) and the unique `(department_id, name)` index; verify `php artisan migrate` and `php artisan migrate:rollback` both run cleanly.
- [x] 1.2 Add `App\Models\Position` with `#[Fillable(['name', 'department_id'])]` and a `department()` relation, and add `positions()` to `App\Models\Department`; verify `tests/Feature/Positions/PositionModelTest.php` asserts the position-to-department link and `Department::positions`.
- [x] 1.3 Add `Database\Factories\PositionFactory` with a company-wide default state and an `inDepartment(Department $department)` state; verify the factory test creates a company-wide position and a department-scoped one with the expected `department_id`.

## 2. Permissions, shared tree, and backend CRUD

- [x] 2.1 Add `PositionsView`, `PositionsCreate`, `PositionsUpdate`, and `PositionsDelete` to `App\Enums\Permission`, add the `positions` resource label, and add `App\Policies\PositionPolicy` mirroring the department policy; verify tests assert a permission-less user cannot `viewAny`/`create`/`update`/`delete`, a `positions.view` holder can only read, and a Super Admin is granted every ability.
- [x] 2.2 Extract the department flattening and option helpers from `DepartmentController` into `App\Concerns\BuildsDepartmentTree`, using it from both `DepartmentController` and the new controller; verify `tests/Feature/Departments` still passes, including tree order, depth, and excluded subtrees.
- [x] 2.3 Register the six `positions.*` routes in `routes/administration.php` with `can:` middleware and implement `PositionController@index` returning name, department, and per-row `can` flags; verify `php artisan route:list --name=positions` lists all six names and the index test asserts ordering and payload keys.
- [x] 2.4 Implement `@create`/`@store` with `App\Concerns\PositionValidationRules` and `StorePositionRequest`; verify tests create a company-wide position and a position with a department, and reject a duplicate name within the same department and among company-wide positions.
- [x] 2.5 Implement `@edit`/`@update` with `UpdatePositionRequest`; verify tests cover renaming, moving between departments, clearing the department, and rejecting a rename that collides within its scope.
- [x] 2.6 Implement `@destroy`; verify the test deletes an unused position and confirms it disappears from the list without changing its department.
- [x] 2.7 Add `positions_count` to the department index payload and refuse deleting a department that still has positions in `DepartmentController@destroy`; verify `tests/Feature/Departments/DepartmentManagementTest.php` asserts the refusal leaves the department and position intact and that deleting the last position unblocks department deletion.

## 3. Position interface

- [x] 3.1 Add `Position` and `PositionDetails` types, extend `Auth.can` with `positions.view`/`positions.create`, expose the shared permission prop, and add the sidebar entry gated on that ability; verify `npm run types:check` passes and the Administration menu shows Positions only for a user who can view them.
- [x] 3.2 Build `positions/Index.vue` with name, department (or company-wide), edit/delete actions, and a delete confirmation dialog; verify `npm run types:check`, `npm run check`, and `npm run build` succeed and the page renders the catalogue with its department scope.
- [x] 3.3 Build `positions/Create.vue`, `positions/Edit.vue`, and `components/positions/PositionForm.vue` with name and department select; verify `npm run types:check`, `npm run check`, and `npm run build` succeed and a position saves both with and without a department.
- [x] 3.4 Update `departments/Index.vue` so the delete action is disabled and explained when `positions_count` is greater than zero; verify `npm run types:check`, `npm run check`, and `npm run build` succeed and the in-use title names the attached positions.

## 4. Integration checks

- [x] 4.1 Run `vendor/bin/pint --dirty --format agent`, `composer types:check`, and `php artisan test --compact`; fix anything they report and verify all three pass.
- [x] 4.2 Run `npm run types:check`, `npm run check`, and `npm run build`, and confirm the position pages import `PositionController` from `@/actions` rather than hardcoded URLs; verify all three commands succeed.

## Workflow follow-up

- Archive the change after review satisfies the project's requirements.
- Verify the archived `positions` spec and the updated `departments` spec with `openspec validate --specs`.
