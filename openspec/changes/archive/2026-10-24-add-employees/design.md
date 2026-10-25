# Design

## Context

See `proposal.md` for motivation. The current application has administration only: users, roles, permissions, departments, and positions. Department membership is stored as `users.department_id`, and `departments.manager_id` references a login user. There is no person record and no way to connect a position to the person holding it.

The existing stack is Laravel 13, Inertia v3 with Vue, Fortify with two-factor authentication enabled, Spatie permissions, and MySQL by default. The session driver defaults to the database. Current data has one user, zero user department assignments, zero department managers, eight departments, and fifteen positions.

The employee design must fit alongside the planned leave, attendance, and payroll modules without making those modules depend on login accounts. It must also preserve employment history and avoid silent overwrites.

## Goals / Non-Goals

**Goals:**

- Make the employee record the source of truth for identity, employment status, department membership, and position.
- Keep login accounts optional and separate from employees.
- Store employment periods and department assignments as effective-dated history with one current row.
- Enforce employee permissions, audit reasons, and the employment-based login gate server-side.
- Move department management and membership off users without losing the existing department tree or position catalogue.
- Keep the migration safe for the current empty department/member data and provide a clear rollback story.

**Non-Goals:**

- Payroll, leave, attendance, contracts, or approval queues.
- Salary, bank, health, date-of-birth, national-ID, address, personal-phone, or emergency-contact fields.
- Future-dated hires, rehires, transfers, or terminations.
- Concurrent assignments, allocation percentages, or a per-assignment manager.
- Reporting-manager history, department-manager history, retention purges, erasure requests, or field-level masking.
- Automatic user creation or a manual employee “active” toggle.
- Employee deletion.

## Data Model

### `sequences`

A small named-counter table used to generate employee numbers without exposing primary keys.

| Column | Type | Constraints |
| --- | --- | --- |
| `id` | bigint unsigned | primary key |
| `name` | varchar(50) | unique, not null |
| `value` | bigint unsigned | not null, default 0 |
| `created_at`, `updated_at` | timestamps | nullable |

The migration inserts `employee_number` with value `0`. The number generator locks the row with `lockForUpdate()`, increments the value, and formats it as `EMP-` followed by at least four zero-padded digits. The employee creation transaction includes the sequence increment, so a failed creation does not permanently consume a number. Gaps are acceptable if a number is allocated outside a successful transaction.

Why not use `AUTO_INCREMENT`: the employee number must be immutable, separate from the user and employee primary keys, and readable. Why not use `count() + 1`: concurrent creations would collide. Why not use a ULID: the required format is sequential and human-readable.

### `employees`

| Column | Type | Constraints |
| --- | --- | --- |
| `id` | bigint unsigned | primary key |
| `employee_number` | varchar(20) | unique, not null, immutable |
| `first_name` | varchar(100) | not null |
| `last_name` | varchar(100) | not null |
| `work_email` | varchar(255) | nullable, unique, stored trimmed and lowercased |
| `user_id` | bigint unsigned | nullable, unique, FK `users.id` null on delete |
| `created_at`, `updated_at` | timestamps | nullable |

Indexes: unique `employee_number`, unique `work_email`, unique `user_id`, and a non-unique `last_name, first_name` index for directory sorting. There is no `deleted_at` column and no soft-delete trait.

The `user_id` unique constraint enforces the one-to-one account link. Deleting a login account leaves the employee and clears the link. The employee number is never accepted as editable input.

### `employment_periods`

| Column | Type | Constraints |
| --- | --- | --- |
| `id` | bigint unsigned | primary key |
| `employee_id` | bigint unsigned | FK `employees.id` restrict on delete |
| `started_on` | date | not null |
| `ended_on` | date | nullable |
| `start_reason` | varchar(255) | not null |
| `termination_reason` | text | nullable; required when `ended_on` is set |
| `created_by` | bigint unsigned | nullable, FK `users.id` null on delete |
| `terminated_by` | bigint unsigned | nullable, FK `users.id` null on delete |
| `created_at`, `updated_at` | timestamps | nullable |

Indexes: `employee_id, started_on`; `ended_on`. A generated nullable marker `open_marker` is `CASE WHEN ended_on IS NULL THEN 1 ELSE NULL END`, with a unique key on `employee_id, open_marker`. MySQL and SQLite allow multiple nulls in a unique index, so the table permits any number of closed periods but only one open period per employee.

The generated marker is the database backstop for the one-open-period rule. The application also validates period ordering and dates before writing. Active status is derived from the presence of an open period; it is not a stored boolean.

### `employment_assignments`

| Column | Type | Constraints |
| --- | --- | --- |
| `id` | bigint unsigned | primary key |
| `employee_id` | bigint unsigned | FK `employees.id` restrict on delete |
| `department_id` | bigint unsigned | FK `departments.id` restrict on delete |
| `position_id` | bigint unsigned | nullable, FK `positions.id` restrict on delete |
| `effective_from` | date | not null |
| `effective_to` | date | nullable |
| `change_reason` | varchar(500) | not null |
| `changed_by` | bigint unsigned | nullable, FK `users.id` null on delete |
| `created_at`, `updated_at` | timestamps | nullable |

Indexes: `employee_id, effective_from`; `department_id`; `position_id`; `effective_to`. A generated nullable `current_marker` is `CASE WHEN effective_to IS NULL THEN 1 ELSE NULL END`, with a unique key on `employee_id, current_marker`. This enforces at most one current assignment per employee while retaining closed assignments.

The assignment department is required. The position is optional. Position scope is validated in the assignment action because it is a cross-row rule, not a foreign key:

- A position with a `department_id` must be used with the same department.
- A position with a null `department_id` is company-wide and may be used in any department.

Assignments are appended, never updated in place except to close the current row on the day before a new effective date. The current assignment is the row with `effective_to` null.

### `employee_change_logs`

| Column | Type | Constraints |
| --- | --- | --- |
| `id` | bigint unsigned | primary key |
| `employee_id` | bigint unsigned | FK `employees.id` restrict on delete |
| `changed_by` | bigint unsigned | nullable, FK `users.id` null on delete |
| `reason` | varchar(500) | not null |
| `changes` | json | not null |
| `created_at` | timestamp | not null, defaults to current time |

Index: `employee_id, created_at`. The model has no update path. The `changes` object stores old and new values keyed by field, for example `{"last_name":{"old":"Smith","new":"Jones"}}`. Only audited fields are included: first name, last name, work email, and linked user. An edit with no audited change writes no row.

Audit actor references use `nullOnDelete`, matching the existing user-reference pattern. Deleting a user leaves the audit row, reason, and change snapshot intact but nulls the actor link. If permanent actor identity becomes a requirement, add a deletion guard or an actor-name snapshot rather than changing the employee history model.

### Changes to existing tables

**`departments`**

- `manager_id` remains nullable but its foreign key changes from `users.id` to `employees.id`, still `nullOnDelete`.
- The department gains `employmentAssignments()` and `currentAssignments()` relations.
- The `users()` relation is removed.
- Current membership is counted from assignments with `effective_to` null.
- The index also exposes the total assignment count so the delete control can be disabled when only history remains.

**`users`**

- `department_id`, its foreign key, and its index are removed.
- The `department()` relation is removed.
- The user gains a `employee()` has-one relation and an `hasActiveEmployment()` helper.
- The user’s existing account, role, and password behavior is unchanged for accounts without an employee link.

**`positions`**

- No column change.
- The position gains `employmentAssignments()`.
- The position list exposes whether the position has assignment history so deletion can be disabled.

### Retention and deletion

- Employees and their history are retained after termination. There is no employee delete route, policy method, permission, or UI action.
- `employment_periods`, `employment_assignments`, and `employee_change_logs` use restrict-on-delete foreign keys to the employee.
- `employment_assignments.department_id` and `position_id` use restrict-on-delete, so a direct database delete is blocked while history exists.
- `departments.manager_id` and `employees.user_id` use null-on-delete.
- User deletion does not delete employee data. It clears the employee link and may null historical actor references.
- No purge, erasure, or masking is added in this change because no salary, bank, health, or similar sensitive fields are introduced.
- All fields introduced here are visible to `employees.view`.

### Migration order

1. Create `sequences` and insert the `employee_number` row.
2. Create `employees`.
3. Create `employment_periods` with the open marker and unique key.
4. Create `employment_assignments` with the current marker and unique key.
5. Create `employee_change_logs`.
6. Point `departments.manager_id` at `employees`.
7. Drop `users.department_id`.

The migration for step 6 aborts with a clear message if any department currently has a non-null manager, because a user ID cannot be mapped to an employee ID automatically. The migration for step 7 aborts the same way if any user currently has a department. Current data satisfies both preconditions. This is deliberate: silently clearing or reinterpreting those values would be a data-loss bug.

The rollback restores `users.department_id` as a nullable foreign key, clears `departments.manager_id` because employee IDs are not user IDs, points the manager foreign key back at `users`, and drops the employee-related tables in reverse dependency order. Rollback does not restore old department membership or manager data; there is none in the current database. Employee data is intentionally lost on rollback.

## Decisions

### Employee is a separate aggregate

Employees own identity, employment dates, assignments, and audit history. Login accounts remain in `users` and are optional. An employee can exist without a user, and a user can exist without an employee. The only bridge is `employees.user_id`.

Alternatives considered:

- Extend `users` with employee columns: rejected because login accounts would become mandatory and account concerns would be mixed with employment history.
- Reuse `users.id` as the employee number: rejected because employee numbers are immutable business identifiers and account records are not employees.
- Use a UUID: rejected because the accepted format is sequential `EMP-0001`.

### Effective-dated periods and assignments with one open row

A single open employment period represents active employment. A single open assignment represents the current department and optional position. Both tables keep closed rows as history and use a generated nullable marker plus a unique key to enforce one open row per employee.

Alternatives considered:

- Store `is_active` or `current_department_id` on the employee: rejected because it creates a second source of truth and loses history.
- Overwrite the current assignment on transfer: rejected because the proposal requires history and an audit reason.
- Add future-dated rows: rejected because the scope explicitly excludes future scheduling. Every write is effective today or earlier.
- Use soft deletes on assignments: rejected because closed rows are meaningful history, not deleted records.

### Lifecycle writes go through focused actions

Multi-table writes use action classes in `app/Actions/Employee`: create, update, assign, terminate, rehire, and generate employee number. Each action runs in a database transaction and locks the employee row before changing employment or assignment state. This keeps controllers small and makes the invariants testable without duplicating transaction logic.

Alternatives considered:

- Put everything in the controller: workable for the current codebase, but the lifecycle actions have enough date, lock, and history rules that separate actions are easier to test.
- Put behavior on the model: rejected because the actions span several models and need request-actor context.
- Add an approval workflow: out of scope. The recorded reason is the approval substitute for this change.

### Core edits require a reason and write an audit row

`UpdateEmployeeRequest` requires a non-empty reason. The update action snapshots the audited fields before saving, updates the employee, and writes an `employee_change_logs` row with the actor, reason, and old/new values. Editing without a reason is rejected. There is no pending state or approver.

Alternatives considered:

- Add a general audit package: unnecessary for this scope and would add a dependency.
- Use Laravel activity log conventions: possible, but a small employee-specific table gives a clearer field-level change shape and one fewer package.
- Require approval: out of scope and would block immediate changes.

### Department manager and membership move to employees

`departments.manager_id` references employees. A manager must have an open employment period, may manage several departments, and need not be assigned to those departments. Department screens display the manager’s employee name. The manager selector lists active employees and exposes only their name and employee number; full employee records still require `employees.view`.

Department membership is derived from current assignments. The department index exposes two counts:

- `employees_count`: current assignments, used for the Members column.
- `assignments_count`: all assignment rows, used to disable deletion when only history remains.

Without the second count, a department with only former employees would look deletable in the UI while the backend refused the delete.

Alternatives considered:

- Keep `users.department_id`: rejected because it creates two membership sources and leaves login accounts owning org structure.
- Keep both user membership and assignment membership: rejected for the same reason.
- Keep manager as a user: rejected because a manager may not have a login account and should be an employee record.
- Add manager history: out of scope. Manager changes remain immediate and current-only.

### Deletion and scope guards are enforced at the application boundary and reinforced by foreign keys

- Department deletion is refused for child departments, attached positions, or any assignment history.
- Position deletion is refused for any assignment history.
- Position department scope is immutable once any assignment references it. The name remains editable.
- Employee deletion does not exist.

The controllers throw validation errors with a named reason so the UI can show a useful message. The restrict-on-delete foreign keys are the last line of defense. Position scope immutability is an application rule because it is a cross-column policy, not a foreign key; the UI disables the scope field when the position is in use and the server rejects a changed scope.

Alternatives considered:

- Rely only on foreign-key errors: rejected because database errors are not good user feedback.
- Allow deletion and null out history: rejected because it destroys audit and assignment history.
- Allow position re-scoping after use: rejected because historical assignments would silently point at a differently scoped position.

### Login access is checked at login and on every authenticated request

There are three layers:

1. **Password login**: a Fortify pipeline action, `App\Actions\Fortify\EnsureUserHasActiveEmployment`, runs after `AttemptToAuthenticate` and before `PrepareAuthenticatedSession`. If the authenticated user is linked to an employee with no open employment period, it logs the user out, invalidates the session, regenerates the CSRF token, and throws a validation error on the login email. The user is not left authenticated after a refused login.

2. **Two-factor login**: Fortify’s two-factor controller logs the user in directly and does not use the password pipeline. A custom `TwoFactorLoginResponse` checks the same condition after the code is accepted, logs out and invalidates the session when employment is inactive, and redirects back to the login page with an error. Register the response implementation for the Fortify `TwoFactorLoginResponse` contract in `FortifyServiceProvider::register()`.

3. **Existing sessions**: a web middleware, `App\Http\Middleware\EnsureUserHasActiveEmployment`, checks the authenticated user on each web request. If the linked employee has no open period, it logs out, invalidates the session, regenerates the token, and redirects to login. This is the backstop for surviving sessions, remember-me cookies, and any future authentication flow that bypasses the pipeline. Append it to the `web` middleware group in `bootstrap/app.php`.

Termination also clears the linked user’s `remember_token` and deletes their database session rows so access ends immediately. Rehire restores access by creating an open employment period; there is no separate account toggle. Users without an employee link are unaffected.

Alternatives considered:

- Disable the user account on termination: rejected because it couples employment to account administration and requires manual re-enabling.
- Only check at login: insufficient for existing sessions.
- Only use middleware: would let a terminated password login briefly authenticate and would produce a confusing 2FA redirect.
- Add a custom guard: more invasive than the three focused checks and unnecessary for this one rule.

### Permissions stay resource-oriented

The permission enum gains `employees.view`, `employees.create`, and `employees.update`. There is no `employees.delete`. The existing Super Admin `before` behavior remains. Employee routes use `can:` middleware backed by `EmployeePolicy`. The employee assignment, termination, and rehire actions all require `employees.update`; they are not separate permissions in this change.

The employee link selector lists unlinked accounts only and exposes only the account name and email needed to choose one. Linking is saved under `employees.update`; full user administration remains under `users.view`. A linked account’s name and email are part of the employee record for display to `employees.view`.

Alternatives considered:

- Add separate `employees.assign`, `employees.terminate`, and `employees.rehire` permissions: more granular, but not needed for the first version and not in the accepted permission list.
- Make employee management part of `users.*`: rejected because employees are a separate aggregate and can exist without accounts.

### User administration loses department assignment

The user create/edit form, validation, controller payloads, and index column no longer read or write `department_id`. The `DepartmentMembershipRules` concern is removed once no request uses it. The employee directory and employee detail become the place to see department and position. This removes the duplicate membership source and keeps user administration focused on authentication and authorization.

Alternatives considered:

- Keep the column as a cached display field: rejected because it would drift from assignment history.
- Keep the user form field and sync it to an assignment: rejected because it would make user edits silently rewrite employment history without the required assignment reason.

## Permission Matrix

| Action | Permission | Enforcement |
| --- | --- | --- |
| View employee directory and detail | `employees.view` | `EmployeePolicy@viewAny` / `@view`; route `can:` middleware; shared `auth.can.employees.view` for navigation |
| Create employee and first employment period | `employees.create` | `EmployeePolicy@create`; route `can:create,App\Models\Employee` |
| Edit core employee fields and linked account | `employees.update` | `EmployeePolicy@update`; route `can:update,employee`; `UpdateEmployeeRequest` requires a reason |
| Assign, transfer, terminate, and rehire | `employees.update` | `EmployeePolicy@update`; route `can:update,employee`; action-level state guards |
| List selectable login accounts | `employees.update` | Employee form receives unlinked account options only for the link action; full user administration remains under `users.view` |
| List active employees for a department manager selector | `departments.create` / `.update` | Manager options expose only active employee name and number; department change still requires the matching department permission |
| Create, update, delete departments | `departments.create` / `.update` / `.delete` | Existing `DepartmentPolicy`; manager changes also require `departments.update` |
| Create, update, delete positions | `positions.create` / `.update` / `.delete` | Existing `PositionPolicy`; scope changes and deletions additionally guarded by assignment history |
| Delete an employee | none | No route, no policy method, no permission, no UI action |
| Authenticate with an employee link | none | Fortify pipeline action, two-factor response, web middleware; active employment period required |
| Delete a user | `users.delete` | Existing `UserPolicy`; deletion clears the employee link and may null audit actor references |

## Routes, Controllers, and Pages

### Routes

All employee routes live in `routes/administration.php` inside the existing `auth`, `verified` group.

| Method | URI | Name | Controller | Middleware |
| --- | --- | --- | --- | --- |
| GET | `/employees` | `employees.index` | `EmployeeController@index` | `can:viewAny,App\Models\Employee` |
| GET | `/employees/create` | `employees.create` | `EmployeeController@create` | `can:create,App\Models\Employee` |
| POST | `/employees` | `employees.store` | `EmployeeController@store` | `can:create,App\Models\Employee` |
| GET | `/employees/{employee}` | `employees.show` | `EmployeeController@show` | `can:view,employee` |
| GET | `/employees/{employee}/edit` | `employees.edit` | `EmployeeController@edit` | `can:update,employee` |
| PUT | `/employees/{employee}` | `employees.update` | `EmployeeController@update` | `can:update,employee` |
| POST | `/employees/{employee}/assignments` | `employees.assignments.store` | `EmployeeAssignmentController@store` | `can:update,employee` |
| POST | `/employees/{employee}/terminate` | `employees.terminate` | `EmployeeController@terminate` | `can:update,employee` |
| POST | `/employees/{employee}/rehire` | `employees.rehire` | `EmployeeController@rehire` | `can:update,employee` |

`/employees/create` must be registered before `/employees/{employee}` so the literal path is not captured as a model binding. The assignment route is a POST because it appends history; it is not a PUT that overwrites the current assignment.

### Controllers

**`App\Http\Controllers\EmployeeController`**

- `index`: search by employee number, first name, last name, or work email; filter active/terminated; paginate; eager load current period, current assignment with department and position, and linked user; expose per-row `can.update`.
- `create`: pass unlinked user options for the optional account link.
- `store`: call `CreateEmployee`; redirect to the employee detail with a success flash.
- `show`: load the employee with period history, assignment history, audit history, current assignment, linked user, and the departments the employee manages; pass department and position options for the assignment dialog; expose `can.update`.
- `edit`: pass employee details, unlinked user options plus the currently linked account, and the read-only employee number.
- `update`: call `UpdateEmployee`; redirect to the detail with a success flash.
- `terminate`: call `TerminateEmployee`; redirect to the detail with a success flash.
- `rehire`: call `RehireEmployee`; redirect to the detail with a success flash.

**`App\Http\Controllers\EmployeeAssignmentController`**

- `store`: call `AssignEmployee`; redirect to the employee detail with a success flash.

**Existing controllers**

- `DepartmentController`: load the manager as `Employee`, count `currentAssignments` and `assignments`, return current and historical counts, pass active employee manager options, and refuse deletion when assignment history exists.
- `PositionController`: return `is_in_use` from assignment history, refuse deletion when history exists, and reject a department-scope change on an in-use position.
- `UserController`: remove department eager loading, department payloads, department catalogue, and department writes.

### Form requests

- `StoreEmployeeRequest`: first name, last name, optional work email, optional user link, and required employment start date on or before today.
- `UpdateEmployeeRequest`: first name, last name, optional work email, optional user link, and required reason. Unique checks ignore the current employee.
- `StoreEmployeeAssignmentRequest`: department, optional position, effective date, and reason.
- `TerminateEmployeeRequest`: termination date and termination reason.
- `RehireEmployeeRequest`: start date and start reason.
- `StoreDepartmentRequest` / `UpdateDepartmentRequest`: manager must be an active employee.
- `UpdatePositionRequest`: reject a changed department when the position has assignment history.
- `StoreUserRequest` / `UpdateUserRequest`: remove department membership rules.

Basic shape and existence checks live in the form requests. Cross-table date, state, position-scope, and department-manager-blocking rules live in the actions and throw field-level `ValidationException`s so the Vue dialogs can display them.

### Action classes

- `CreateEmployee`: generate the number, create the employee, create the first open period with `Initial hire`, and record the creator. No assignment is required.
- `UpdateEmployee`: capture the audited field changes, update the employee, and write the audit row when something changed.
- `AssignEmployee`: lock the employee, verify active employment, validate the position scope and effective date, close the current assignment the day before the new date, and insert the new assignment.
- `TerminateEmployee`: lock the employee, verify the date and manager guard, close the period and current assignment, clear the linked user’s remember token, and delete the linked user’s database session rows.
- `RehireEmployee`: lock the employee, verify the new date is after the previous end and no period is open, and create the new period. No assignment or manager role is restored.
- `GenerateEmployeeNumber`: lock the sequence row, increment it, and return the formatted number.

All lifecycle actions run inside `DB::transaction` and lock the employee row. The unique open markers and unique employee number are the database backstops.

### Inertia pages and components

New pages:

- `resources/js/pages/employees/Index.vue`: directory with search, status filter, current department, current position, reporting manager, and edit action.
- `resources/js/pages/employees/Create.vue`: employee form with first name, last name, work email, optional account link, and employment start date.
- `resources/js/pages/employees/Edit.vue`: core edit form with read-only employee number and required reason.
- `resources/js/pages/employees/Show.vue`: employee summary, current employment, period history, assignment history, and core edit audit history, with assignment, termination, and rehire dialogs.

New components:

- `resources/js/components/employees/EmployeeForm.vue`
- `resources/js/components/employees/AssignEmployeeDialog.vue`
- `resources/js/components/employees/TerminateEmployeeDialog.vue`
- `resources/js/components/employees/RehireEmployeeDialog.vue`
- `resources/js/components/employees/EmployeeStatusBadge.vue`

Existing pages updated:

- `resources/js/pages/departments/Index.vue`: display current member count, manager employee name, and disable deletion on assignment history. The reason text must mention employment history, not just current members.
- `resources/js/components/departments/DepartmentForm.vue`: manager options are active employees with employee numbers.
- `resources/js/pages/positions/Index.vue`: show an in-use indicator and disable deletion for positions with assignment history.
- `resources/js/components/positions/PositionForm.vue`: show the department scope as read-only when the position is in use.
- `resources/js/pages/users/Index.vue`, `Create.vue`, `Edit.vue`, and `resources/js/components/users/UserForm.vue`: remove department props, fields, and columns.

Reuse the existing `Heading`, `Button`, `Table`, `Dialog`, `Input`, `Label`, `InputError`, `Select`, `Badge`, and `Pagination` components. Do not add a new base UI folder.

### Wayfinder and shared types

Wayfinder must be regenerated after the controller actions exist, producing:

- `@/actions/App/Http/Controllers/EmployeeController`
- `@/actions/App/Http/Controllers/EmployeeAssignmentController`

New types in `resources/js/types/employee.ts`:

- `Employee`, `EmployeeDetails`, `EmployeeOption`
- `EmploymentPeriod`, `EmploymentAssignment`, `EmployeeChangeLog`
- `EmployeeStatus`, `EmployeeAbility`

Existing types updated:

- `department.ts`: replace `users_count` with `employees_count`; add `assignments_count`; add `employee_number` to `DepartmentManagerOption`.
- `position.ts`: add `is_in_use`; add `is_in_use` to `PositionDetails`.
- `user.ts`: remove `department`.
- `auth.ts`: add `employees: { view: boolean; create: boolean }`.
- `index.ts`: export the employee types.

`HandleInertiaRequests` adds the employees `view` and `create` abilities. `AppSidebar` adds an Employees item using an id-card style Lucide icon, shown when `auth.can.employees.view` is true.

## Testing Strategy

- Employee model and factory tests: optional user link, full name, active period, current assignment, reporting manager.
- Employee management tests: guests, missing permissions, reader-only, create, immutable number, case-insensitive work email uniqueness, one-to-one user link, required edit reason, audit row, no delete.
- Employment lifecycle tests: initial period, backdating, one open period, overlapping/date validation, termination guard for department managers, assignment closure, session and remember-token invalidation, rehire ordering, same employee number, no restored assignment.
- Assignment tests: active-only, required department, optional company-wide position, department-scoped position mismatch, effective-date ordering, history append, one current assignment.
- Department tests: manager must be an active employee, manager may manage multiple departments, current member counts, assignment-history counts, deletion blocked by history, user membership removed.
- Position tests: in-use deletion refused, historical deletion refused, scope lock after use, name change allowed, unused scope change allowed.
- Auth tests: active linked user can log in, terminated linked user cannot, unlinked user is unaffected, terminated user with an existing session is logged out, rehire restores login, two-factor path respects the gate.
- User management tests: department field and column removed, user deletion unlinks an employee.
- Seeder test: demo employees are created only in `local`/`development` and are idempotent.
- Frontend verification: `npm run types:check`, `npm run check`, and `npm run build`; run `php artisan test --compact` as the final suite.

## Migration Plan

### Pre-deployment

Confirm that no department has a manager and no user has a department. If either is non-empty, stop and map the records before running the migration. The current database meets both conditions.

### Deployment

1. Run the migrations in the order above.
2. Run `php artisan db:seed --class=RolesAndPermissionsSeeder` to add the three employee permissions.
3. In `local` or `development`, run `DatabaseSeeder` or `EmployeeSeeder` to add demo employees and assignments. The seeder uses the same creation and assignment actions so employee numbers and history stay valid.
4. Regenerate Wayfinder and build the frontend: `php artisan wayfinder:generate`, then `npm run build`. If the Vite plugin is running, a dev restart also regenerates the actions.
5. Clear configuration and route caches as required by the deployment.
6. Smoke test: create an employee, assign a department, change the assignment, terminate, confirm login is blocked, rehire, confirm login is restored.

### Rollback

Run the migrations down in reverse dependency order. The rollback restores `users.department_id` and points `departments.manager_id` back at users, clearing the manager values because employee IDs cannot be mapped to user IDs. Employee, period, assignment, audit, and sequence tables are dropped. Rollback is intentionally destructive for employee data and does not restore old membership or manager values.

## Risks / Trade-offs

- **Existing department managers or user memberships cannot be mapped automatically** → The migrations abort before changing the foreign keys. Pre-check the two columns and map them manually if the production database is not the audited empty state.
- **Two-factor login bypasses the password pipeline** → A custom two-factor response checks employment after the code is accepted, and the web middleware is the backstop. Test the 2FA path explicitly.
- **Session invalidation depends on the session driver** → Termination clears the remember token and deletes database session rows; the middleware catches any surviving session on the next request. If the driver changes to files or cookies, the middleware remains the guarantee but immediate revocation is weaker.
- **Deleting a user nulls audit actor links** → The audit row, reason, and change snapshot remain. Add a user-deletion guard or actor-name snapshot later if permanent actor identity becomes required.
- **Employee number generation serializes creation** → The sequence row lock is short and acceptable for an HR admin workflow. Gaps are allowed; the unique employee number prevents duplicates.
- **Generated-column unique markers are database-specific SQL** → MySQL and SQLite both support the `CASE` expression used here. Add a migration test against the configured connection. The actions also lock and validate so the markers are a backstop, not the only guard.
- **Position scope immutability is enforced in application code, not a database constraint** → There is no API for direct scope changes, the form disables the field when in use, and the server rejects the change. A future database trigger can harden it if direct SQL writes become a concern.
- **Department and position delete guards are duplicated by foreign keys** → The controller provides the user-facing message; the foreign key prevents accidental direct deletion.
- **No employee deletion means mistaken records persist** → Employees can be edited with an audit reason and terminated, but not removed. This is deliberate for retention and the accepted scope.
- **No reporting-manager or department-manager history** → Reports reflect the current department manager only. Historical reporting lines are out of scope and should be added as a separate effective-dated fact if needed.
- **Backdated changes alter the derived current state immediately** → Every change carries a reason and actor and preserves closed rows. There is no future scheduling.
- **Manager names are visible as part of department data** → Only the display name and employee number are exposed through the department manager selector and department display; the full employee record requires `employees.view`.
- **No sensitive fields yet, so no masking or purge policy is implemented** → All introduced fields are visible to `employees.view`. Future salary, bank, health, or identity fields must add field-level policies, retention, and erasure before they are stored.
