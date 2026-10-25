# Tasks

## 1. Database Foundation and Employee Models

- [x] 1.1 Create the `sequences` migration with the `name`/`value` columns, unique name, and the seeded `employee_number` row; verify `php artisan migrate:fresh` succeeds and the row is present with a schema assertion in `EmployeeSchemaTest`.
- [x] 1.2 Create the `employees` migration with the employee number, names, optional work email, optional unique user link, indexes, and foreign keys; verify `php artisan migrate:fresh` succeeds and `EmployeeSchemaTest` asserts the unique number, email, and user-link constraints.
- [x] 1.3 Create the `employment_periods` and `employment_assignments` migrations with effective dates, reasons, actor references, restrict-on-delete history foreign keys, generated open/current markers, and unique per-employee markers; verify `EmployeeSchemaTest` proves a second open period and a second current assignment are rejected by the database.
- [x] 1.4 Create the `employee_change_logs` migration with the employee, actor, reason, JSON changes, timestamp, and index; verify `EmployeeSchemaTest` asserts the table and indexes exist.
- [x] 1.5 Create `Sequence`, `Employee`, `EmploymentPeriod`, `EmploymentAssignment`, and `EmployeeChangeLog` models with fillable attributes, casts, relations, current/active helpers, full-name and reporting-manager accessors, and PHPDoc blocks; verify `EmployeeModelTest` covers the relations and derived values.
- [x] 1.6 Create `EmployeeFactory`, `EmploymentPeriodFactory`, and `EmploymentAssignmentFactory` with active, terminated, and assigned states that use the number generator; verify `EmployeeModelTest` covers factory defaults and states.
- [x] 1.7 Write `tests/Feature/Employees/EmployeeSchemaTest.php` and `tests/Feature/Employees/EmployeeModelTest.php` covering the schema constraints, one-open-period rule, one-current-assignment rule, relations, active/terminated state, and derived reporting manager; verify with `php artisan test --compact tests/Feature/Employees/EmployeeSchemaTest.php tests/Feature/Employees/EmployeeModelTest.php`.

## 2. Employee Permissions and Core CRUD

- [x] 2.1 Add `EmployeesView`, `EmployeesCreate`, and `EmployeesUpdate` to the permission enum, add the employees resource label, create `EmployeePolicy` with `viewAny`, `view`, `create`, and `update` but no delete, and register the employee routes in `routes/administration.php`; verify `php artisan route:list --name=employees` shows all routes and no delete route.
- [x] 2.2 Add the employees view/create abilities to `HandleInertiaRequests` and the Employees navigation item to `AppSidebar`; verify the permission test asserts readers see the navigation while users without `employees.view` do not.
- [x] 2.3 Implement `GenerateEmployeeNumber`, `CreateEmployee`, and `UpdateEmployee` actions with the sequence lock, first-period creation, core-field audit, required edit reason, and one-to-one user-link rules; verify the creation, number, and audit tests pass.
- [x] 2.4 Add `StoreEmployeeRequest` and `UpdateEmployeeRequest` with trimming, lowercased work email, required start date/reason, unique checks that ignore the current employee, and optional user-link validation; verify validation tests cover duplicate email, duplicate link, missing reason, and future start date.
- [x] 2.5 Implement `EmployeeController@index`, `@create`, `@store`, `@show`, `@edit`, and `@update` with search, active/terminated filters, pagination, current assignment/period mapping, history props, and unlinked user options including the current link on edit; verify the Inertia tests assert the expected props and no delete action.
- [x] 2.6 Write `tests/Feature/Employees/EmployeeManagementTest.php` covering guests, missing permissions, reader-only access, creation, immutable employee number, case-insensitive work email uniqueness, one-to-one user link, required edit reason, audit entry, search/filter behavior, and absence of delete; verify with `php artisan test --compact tests/Feature/Employees/EmployeeManagementTest.php`.

## 3. Employment Lifecycle and Assignments

- [x] 3.1 Implement `AssignEmployee` with employee-row locking, active-employment validation, required department, optional position, position/department scope validation, effective-date ordering, closure of the current assignment, and insertion of the new assignment; verify the assignment tests pass.
- [x] 3.2 Implement `TerminateEmployee` with employee-row locking, date and reason validation, department-manager blocking, period closure, current-assignment closure, linked-user remember-token clearing, and database session-row deletion; verify the lifecycle tests pass.
- [x] 3.3 Implement `RehireEmployee` with employee-row locking, previous-period ordering, no-open-period validation, and first new period creation without restoring an assignment; verify the lifecycle tests pass.
- [x] 3.4 Add `StoreEmployeeAssignmentRequest`, `TerminateEmployeeRequest`, and `RehireEmployeeRequest`, add `EmployeeAssignmentController@store`, and add the assignment, terminate, and rehire controller actions and routes; verify `php artisan route:list --name=employees` shows the three POST routes under the update permission.
- [x] 3.5 Write `tests/Feature/Employees/EmployeeAssignmentTest.php` covering active-only assignment, department-scoped position mismatch, company-wide position, effective date before employment start/current assignment, history append, one current assignment, and derived reporting manager; verify with `php artisan test --compact tests/Feature/Employees/EmployeeAssignmentTest.php`.
- [x] 3.6 Write `tests/Feature/Employees/EmployeeLifecycleTest.php` covering termination date validation, department-manager refusal with named departments, period and assignment closure, session/remember-token invalidation, rehire ordering, same employee number, no restored assignment, and no hard delete; verify with `php artisan test --compact tests/Feature/Employees/EmployeeLifecycleTest.php`.

## 4. Employment Login Gate

- [x] 4.1 Implement `App\Actions\Fortify\EnsureUserHasActiveEmployment` and register the Fortify login pipeline so password logins are refused before the authenticated session is prepared; verify a terminated linked user receives the login error and remains a guest.
- [x] 4.2 Implement a custom two-factor login response that checks active employment after a valid code, logs out and invalidates the session when inactive, and redirects to login with an error; verify the two-factor test for a terminated employee does not leave an authenticated session.
- [x] 4.3 Implement `App\Http\Middleware\EnsureUserHasActiveEmployment`, append it to the web middleware group, and enforce logout/invalidation/redirect for authenticated users whose linked employee has no open period; verify the existing-session test logs the user out on the next request.
- [x] 4.4 Write `tests/Feature/Auth/EmployeeLoginGateTest.php` covering active linked login, terminated linked login, unlinked user login, existing terminated session, rehire restoration, and the two-factor path; verify with `php artisan test --compact tests/Feature/Auth/EmployeeLoginGateTest.php`.

## 5. Department Integration

- [x] 5.1 Add the migration that points `departments.manager_id` at `employees`, including the abort check for non-empty manager data; verify `php artisan migrate:fresh` and a scratch-database rollback both succeed on the current empty data.
- [x] 5.2 Update the `Department` model with the employee manager relation, assignment relations, current-assignment count, and removal of the users relation; verify the updated department model test covers the manager and membership relations.
- [x] 5.3 Update `DepartmentValidationRules` and `DepartmentController` so manager options are active employees with name and employee number, manager validation rejects inactive employees, the index returns `employees_count` and `assignments_count`, and deletion refuses any assignment history; verify the department management tests pass.
- [x] 5.4 Update `tests/Feature/Departments/DepartmentModelTest.php` and `tests/Feature/Departments/DepartmentManagementTest.php` for employee managers, multiple departments per manager, active-only selection, current member counts, historical assignment counts, deletion blocked by current/historical assignments, and the removed user membership behavior; verify with `php artisan test --compact tests/Feature/Departments/DepartmentModelTest.php tests/Feature/Departments/DepartmentManagementTest.php`.

## 6. Position Integration

- [x] 6.1 Add the assignment relation to `Position`, expose `is_in_use`/assignment history from `PositionController@index`, and refuse deletion when any assignment history exists; verify the position management tests cover current and historical deletion refusal.
- [x] 6.2 Update `PositionValidationRules` and `PositionController@update` so the department scope is immutable once a position has any assignment, while the name remains editable; verify the scope-lock and rename tests pass.
- [x] 6.3 Update `tests/Feature/Positions/PositionModelTest.php` and `tests/Feature/Positions/PositionManagementTest.php` for the assignment relation, in-use flags, historical deletion refusal, scope lock, and allowed rename; verify with `php artisan test --compact tests/Feature/Positions/PositionModelTest.php tests/Feature/Positions/PositionManagementTest.php`.

## 7. User Administration Cleanup

- [x] 7.1 Add the migration that drops `users.department_id` with the abort check for non-empty department membership, and remove `department_id` from `UserController`, `StoreUserRequest`, `UpdateUserRequest`, the `User` model, and the now-unused `DepartmentMembershipRules` concern; verify `php artisan migrate:fresh` succeeds and no user request or controller reads or writes `department_id`.
- [x] 7.2 Remove the department selector, department props, and department column from `UserForm.vue`, `users/Create.vue`, `users/Edit.vue`, `users/Index.vue`, and the `ManagedUser` type; verify `npm run types:check` passes after the frontend cleanup.
- [x] 7.3 Update `tests/Feature/Users/UserManagementTest.php` so user creation/update and the index no longer expect a department, and add a test that deleting a linked user leaves the employee intact with no link; verify with `php artisan test --compact tests/Feature/Users/UserManagementTest.php`.
- [x] 7.4 Search the user administration code for remaining `department_id`/`department` references and remove any stale props, imports, or validation; verify with `rg -n "department" app/Http/Controllers/UserController.php app/Http/Requests/User app/Models/User.php resources/js/components/users resources/js/pages/users resources/js/types/user.ts` and confirm only intentional wording remains.

## 8. Employee Frontend and Shared Types

- [x] 8.1 Regenerate Wayfinder after the employee controllers exist, add `resources/js/types/employee.ts`, export it from `types/index.ts`, and add the employee ability to `auth.ts` plus department/position/user type changes required by the backend props; verify the generated `EmployeeController` and `EmployeeAssignmentController` action files exist and `npm run types:check` reaches the new employee pages.
- [x] 8.2 Build `EmployeeForm.vue`, `EmployeeStatusBadge.vue`, `AssignEmployeeDialog.vue`, `TerminateEmployeeDialog.vue`, and `RehireEmployeeDialog.vue` using the existing UI primitives; verify the components render their required fields, errors, disabled states, and confirmation flows through `npm run check` and `npm run types:check`.
- [x] 8.3 Build `employees/Index.vue`, `employees/Create.vue`, `employees/Edit.vue`, and `employees/Show.vue` with search/filter, current assignment and reporting manager, period history, assignment history, audit history, read-only employee number, required edit reason, and lifecycle actions; verify `npm run types:check` passes and `npm run build` succeeds.
- [x] 8.4 Verify the employee pages expose no delete action and only offer assign/terminate/rehire based on current state and `can.update`; verify with the Inertia employee management tests plus a manual page check in local.

## 9. Department, Position, and User Frontend Updates

- [x] 9.1 Update `departments/Index.vue` and `DepartmentForm.vue` to show current employee counts, manager employee names, historical assignment blocking reasons, and active employee manager options with employee numbers; verify the department Inertia tests and `npm run types:check` pass.
- [x] 9.2 Update `positions/Index.vue` and `PositionForm.vue` to show in-use state, disable deletion for in-use positions, and make the department scope read-only when a position is in use; verify the position Inertia tests and `npm run types:check` pass.
- [x] 9.3 Confirm the user pages compile without the department field after the cleanup, and verify `npm run check`, `npm run types:check`, and `npm run build` all pass.

## 10. Local Demo Data

- [x] 10.1 Create `EmployeeSeeder` with active employees, at least one terminated employee for history, department assignments, and optional demo department managers, using the employee creation/assignment actions for valid numbers; verify `php artisan migrate:fresh --seed` in local creates the demo data.
- [x] 10.2 Add `EmployeeSeeder` to `DatabaseSeeder` only inside the existing `local`/`development` guard and keep it idempotent; verify re-running the seeder does not duplicate employees or assignments.
- [x] 10.3 Write `tests/Feature/Employees/EmployeeSeederTest.php` covering local/development demo creation, production skipping, and idempotent reruns; verify with `php artisan test --compact tests/Feature/Employees/EmployeeSeederTest.php`.

## 11. Integration Verification

- [x] 11.1 Run `vendor/bin/pint --dirty --format agent` and fix any formatting changes; verify the command exits successfully.
- [x] 11.2 Run the full PHP static analysis with `composer types:check`; verify it passes.
- [x] 11.3 Run `php artisan test --compact` for the complete Pest suite; verify all tests pass, then ask the user to run the complete suite in their environment if they prefer.
- [x] 11.4 Run `npm run check`, `npm run types:check`, and `npm run build`; verify all three commands pass.
- [x] 11.5 Perform a local Herd smoke test: create an employee, assign a department and position, transfer, attempt termination while a department manager, terminate a non-manager, confirm login is blocked and sessions are invalidated, rehire, and confirm the employee number and history are preserved; verify the observable behavior matches the specs.
