# Proposal

## Why

The system can model who may use the application (users, roles, permissions) but not how the company is organized. Every planned HR capability — employees, leave, attendance, payroll — needs a department to attach people, approvals, and reporting to. Without one shared org structure, each module would invent its own grouping.

## What Changes

- Introduce a `departments` table with a nullable parent (nested org tree) and a nullable manager who is an existing user.
- Add department CRUD under Administration, gated by `departments.view`, `departments.create`, `departments.update`, and `departments.delete`.
- Enforce unique department names among siblings, and reject a parent assignment that would create a cycle.
- Add `users.department_id` so a user belongs to at most one department, selectable from the user create/edit form and shown on the users list.
- Block deleting a department that still has child departments or assigned users.
- Show Departments in the Administration sidebar for users who can view them.

No breaking changes: `users.department_id` is nullable, so existing users keep working without a department.

## Capabilities

### New Capabilities

- `departments`: The company's department tree — creating, renaming, re-parenting, and removing departments, the sibling naming rule, the optional manager, and which users belong to each department.

### Modified Capabilities

None. No main specs exist yet, and the user-facing changes in the users area are specified inside `departments` as department membership rather than as a separate capability.

## Impact

- New `departments` table, new `users.department_id` column, new `App\Models\Department`, `DepartmentPolicy`, form requests, and `DepartmentController`.
- `App\Enums\Permission` gains four cases; `RolesAndPermissionsSeeder` already iterates the enum, so permissions are created without seeder changes.
- `HandleInertiaRequests` shared `auth.can` prop, `resources/js/types/auth.ts`, and `AppSidebar.vue` navigation gain a departments entry.
- New Inertia pages `departments/{Index,Create,Edit}.vue` plus a shared form component; `users/{Create,Edit}.vue` and `users/Index.vue` gain department selection and display.
- Tests: new `tests/Feature/Departments/DepartmentManagementTest.php`; existing user tests are updated for the new optional field.
- No new dependencies. Wayfinder regenerates the controller actions from the new routes.

## Out of Scope

- No department codes, cost centers, budgets, or headcount targets.
- No approval chains, department-level permission scopes, notifications, or reporting-line semantics beyond the single manager pointer.
- No department history or effective-dating: names and parents are edited in place, and reference integrity is protected by blocking deletes instead.
- No soft deletes or archive state for departments.
- No bulk user reassignment, department filtering on the users list, or self-service department changes from the profile settings.
- No payroll mapping; that belongs to the payroll change.
