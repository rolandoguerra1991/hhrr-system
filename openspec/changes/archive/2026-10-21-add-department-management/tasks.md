# Tasks

## 1. Schema and model

- [x] 1.1 Create the `departments` migration with `name`, nullable `parent_id` self-FK (`restrictOnDelete`), nullable `manager_id` FK to `users` (`nullOnDelete`), the unique `(parent_id, name)` index, and both FKs indexed; verify `php artisan migrate` and `php artisan migrate:rollback` both run cleanly.
- [x] 1.2 Create the `add_department_id_to_users_table` migration with a nullable FK to `departments` (`nullOnDelete`, indexed); verify `php artisan migrate` and `php artisan migrate:rollback` run cleanly in order.
- [x] 1.3 Add `App\Models\Department` using the project's attribute style (`#[Fillable(['name', 'parent_id', 'manager_id'])]`) with `parent()`, `children()` ordered by name, `manager()`, and `users()`; verify `tests/Feature/Departments/DepartmentModelTest.php` asserts each relation and the alphabetical ordering of children.
- [x] 1.4 Add `Database\Factories\DepartmentFactory` with a default state and a `childOf(Department $parent)` state for tree fixtures; verify the factory test creates a root and a child with the expected `parent_id`.

## 2. Permissions, policy, and backend CRUD

- [x] 2.1 Add the `DepartmentsView`, `DepartmentsCreate`, `DepartmentsUpdate`, `DepartmentsDelete` cases to `App\Enums\Permission` and add `App\Policies\DepartmentPolicy` mirroring the existing policies; verify tests assert a permission-less user cannot `viewAny`/`create`, a `departments.view` holder can `viewAny` but not `update` or `delete`, and a Super Admin is granted every ability.
- [x] 2.2 Register the six `departments.*` routes in `routes/administration.php` with `can:` middleware and implement `DepartmentController@index` returning a flat depth-annotated tree with `children_count`, `users_count`, manager name, and per-row `can` flags; verify `php artisan route:list --name=departments` lists all six names and the index test asserts ordering, depth, and payload keys.
- [x] 2.3 Implement `@create`/`@store` with a shared `App\Concerns\DepartmentValidationRules` concern and `StoreDepartmentRequest`; verify tests cover creating a root and a child, and rejecting a duplicate sibling name including the case where both departments are roots (case-insensitive, trimmed).
- [x] 2.4 Implement `@edit`/`@update` with `UpdateDepartmentRequest`; verify tests cover renaming, re-parenting (children follow), rejecting a parent that is the department itself or a descendant, and setting then clearing the manager.
- [x] 2.5 Implement `@destroy` refusing deletion when the department has children or assigned users and succeeding otherwise; verify tests assert both refusals leave the department intact and an unused department is removed.

## 3. Department interface

- [x] 3.1 Add `Department` and `DepartmentOption` types, extend `Auth.can` with `departments.view`/`departments.create`, and add the sidebar entry gated on that ability; verify `npm run types:check` passes and the Administration menu shows Departments only for a user who can view them.
- [x] 3.2 Build `departments/Index.vue` as an indented tree table with manager, member count, edit/delete actions, and the in-use delete dialog disabled with an explanatory title; verify `npm run check` and `npm run build` succeed and `/departments` renders the seeded tree in hierarchy order.
- [x] 3.3 Build `departments/Create.vue`, `departments/Edit.vue`, and `components/departments/DepartmentForm.vue` with name, parent select, and manager select, excluding the department and its descendants from the parent options on edit; verify `npm run types:check` and `npm run build` succeed and the edit form never offers its own subtree as a parent.

## 4. User department assignment

- [x] 4.1 Accept optional `department_id` in `StoreUserRequest`/`UpdateUserRequest` and pass a department catalogue from `UserController@create`/`@edit`; verify tests store a selected department, reject an unknown id, and save no department when the field is blank.
- [x] 4.2 Add the optional department select to `components/users/UserForm.vue` and its create/edit pages; verify `npm run types:check` and `npm run build` succeed and a user saves with and without a department.
- [x] 4.3 Show the assigned department on `users/Index.vue` and include it in the index payload; verify the index test asserts the department name and that a user without one renders empty.
- [x] 4.4 Update the existing user feature tests for the new prop and field; verify `php artisan test tests/Feature/Users` passes.

## 5. Integration checks

- [x] 5.1 Run `vendor/bin/pint --dirty --format agent`, `composer types:check`, and `php artisan test --compact`; fix anything they report and verify all three pass.
- [x] 5.2 Run `npm run types:check`, `npm run check`, and `npm run build`, confirm the pages import `DepartmentController` from `@/actions` rather than hardcoded URLs, and verify all three commands succeed.

## Workflow follow-up

- Archive the change after review satisfies the project's requirements.
- Verify the archived `departments` spec with `openspec validate --specs`.
