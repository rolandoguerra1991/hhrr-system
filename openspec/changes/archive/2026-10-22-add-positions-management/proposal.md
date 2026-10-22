# Proposal

## Why

The system can describe how the company is organized (users and departments) but not the jobs people hold. Every planned HR capability — employees, payroll, reporting — needs a shared catalogue of positions so contracts, headcount, and approvals refer to the same job titles instead of free-text strings invented per module.

## What Changes

- Introduce a `positions` table with a name and an optional department.
- Add position CRUD under Administration, gated by `positions.view`, `positions.create`, `positions.update`, and `positions.delete`.
- Keep position names unique within their scope — among positions in the same department, or among company-wide positions — compared case-insensitively after trimming. Positions in different departments may share a name.
- Let a position be company-wide or belong to exactly one department, selectable from the department tree.
- Refuse deleting a department that still has positions assigned to it, alongside the existing child-department and user checks.
- Show Positions in the Administration sidebar for users who can view them.

No breaking changes: the new table is additive, and no existing records gain required references.

## Capabilities

### New Capabilities

- `positions`: The company's job-position catalogue — creating, renaming, re-scoping to a department, and removing positions, the per-department naming rule, the optional department attachment, and the permission gates around it.

### Modified Capabilities

- `departments`: The rule that a department in use cannot be deleted now also covers departments that still have positions attached.

## Impact

- New `positions` table and migration, new `App\Models\Position` with a `department()` relation, `PositionFactory`, `PositionPolicy`, `PositionController`, a shared validation concern, and store/update form requests.
- `App\Enums\Permission` gains four cases and a `positions` resource label; `RolesAndPermissionsSeeder` creates them without further changes.
- `routes/administration.php` gains six `positions.*` routes with `can:` middleware; Wayfinder regenerates the controller actions.
- `HandleInertiaRequests`, `resources/js/types/auth.ts`, and `AppSidebar.vue` gain the positions view ability and navigation entry.
- New Inertia pages `positions/{Index,Create,Edit}.vue` plus `components/positions/PositionForm.vue`; new `resources/js/types/position.ts`.
- `DepartmentController@index` includes a position count, and `departments/Index.vue` disables the delete action for departments that still have positions, explaining why.
- Tests: new position model/factory tests and position management tests; existing department deletion tests cover the new refusal.

## Out of Scope

- No assigning positions to users or employees, and no `users.position_id`; that belongs to the employees capability, where the assignment will be effective-dated.
- No position history or effective dating: the catalogue is edited in place, like departments.
- No salary bands, pay grades, headcount targets, or cost-center data.
- No position hierarchy or reporting lines between positions.
- No description, notes, or other free-text fields on positions.
- No activation/archive state, soft deletes, bulk import, or position codes.
- No approval workflow for catalogue edits; the approval rules apply to employee records and pay, not to structural labels.
- No department-level permission scoping, notifications, or payroll mapping.
