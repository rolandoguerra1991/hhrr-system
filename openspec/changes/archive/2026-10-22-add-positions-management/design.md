# Design

## Context

See `proposal.md` — Why. The administration area already establishes the patterns this change follows: enum-backed permissions, one policy per model, `can:` route middleware, a validation concern shared by store/update requests, and Inertia pages under `resources/js/pages`. Departments provide the tree and the ownership checks that positions build on.

There are no employees yet, so nothing references a position. This change is a catalogue only; the effective-dated assignment of a position to an employee belongs to the employees capability.

## Goals / Non-Goals

**Goals:**

- Add a `positions` catalogue with immediate CRUD, an optional single department, and sibling-scope naming.
- Reuse the department tree and its flattened option list rather than duplicating hierarchy code.
- Extend department deletion so a department with positions is treated as in use.

**Non-Goals:**

- No employee/user position assignment, position history, salary data, headcount, position hierarchy, free-text description, activation state, or approval workflow. See `proposal.md` — Out of Scope.

## Decisions

### Data model

New migration `create_positions_table`:

| Column | Type | Constraints |
| --- | --- | --- |
| `id` | big integer | primary key |
| `name` | string(255) | not null |
| `department_id` | foreignId, nullable | references `departments.id`, `restrictOnDelete`, indexed |
| `created_at`, `updated_at` | timestamps | |

Indexes: `index(department_id)` plus a composite `unique(department_id, name)` backstop.

- `App\Models\Position` uses the project's `#[Fillable(['name', 'department_id'])]` style and exposes `department(): BelongsTo`.
- `Department` gains `positions(): HasMany`.
- `PositionFactory` defaults to a company-wide position and offers an `inDepartment(Department $department)` state for fixtures.

No effective-dating table: a position is a structural label, not an employment or pay fact. There is no personal data and no retention obligation beyond the row itself; deleting a position is a hard delete. History for the eventual employee assignment is modelled with that assignment, not here.

The `restrictOnDelete` FK is a database backstop; the controller performs the friendly check first.

### Naming validation

`App\Concerns\PositionValidationRules` is shared by `StorePositionRequest` and `UpdatePositionRequest`, mirroring `DepartmentValidationRules`:

- `name`: required, string, max 255; a closure compares `LOWER(name)` after trimming, scoped to the submitted `department_id` (blank treated as `null`), excluding the current position on update.
- `department_id`: nullable integer, exists in `departments`.

The controller trims the name before saving, matching the department controller. Company-wide uniqueness cannot be enforced by the composite index because SQL databases treat `NULL` values as distinct; the closure is therefore the primary rule and the index only backstops same-department collisions. Controllers wrap writes in the normal request flow; no extra transaction is needed for a single-row insert/update.

### Department deletion and index payload

`DepartmentController@index` adds `positions_count` through `withCount(['children', 'users', 'positions'])`. `destroy` refuses with a message naming positions when the count is positive, before the child and user checks or alongside them. `departments/Index.vue` treats a nonzero positions count as in use, disables the delete action, and extends the explanatory title.

### Shared department tree

`DepartmentController` currently owns the flattening and option-building helpers as private methods. Extract them into `App\Concerns\BuildsDepartmentTree` (`departmentTreeRows`, `departmentSubtreeIds`, `departmentOptions`) and use the concern from both `DepartmentController` and `PositionController`. This keeps tree order, depth annotation, and subtree exclusion identical without turning the model into a view helper. The existing department tests cover the extracted behaviour.

### Permissions, policy, and enforcement

Add four cases to `App\Enums\Permission` (`positions.view/create/update/delete`) and a `positions` entry to `resourceLabels()`. `RolesAndPermissionsSeeder` iterates the enum, so no seeder edit is needed. `App\Policies\PositionPolicy` mirrors `DepartmentPolicy`, including the Super Admin `before()` bypass; Laravel discovers it by naming convention.

| Action | Permission | Enforcement |
| --- | --- | --- |
| View the position list | `positions.view` | `PositionPolicy@viewAny`, `can:viewAny,Position::class` |
| Open the create form and store | `positions.create` | `PositionPolicy@create`, `can:create,Position::class` |
| Open the edit form and update | `positions.update` | `PositionPolicy@update`, `can:update,position` |
| Delete | `positions.delete` | `PositionPolicy@delete`, `can:delete,position` |
| Show Positions in the sidebar | `positions.view` | shared `auth.can.positions.view` |
| API/page access without permission | — | 403; guests redirect to login |

Positions contain no sensitive fields. Everyone holding `positions.view` may read the whole row; there is no field-level masking. No approval is involved — the catalogue applies immediately.

### Routes, controllers, and requests

`routes/administration.php` adds six routes named `positions.index/create/store/edit/update/destroy`, all inside the existing `auth` + `verified` group, with the `can:` middleware above. Model binding uses `{position}`.

`PositionController`:

- `index`: positions ordered by name, eager-loading `department:id,name`, returning `id`, `name`, `department_id`, `department_name`, and per-row `can.update`/`can.delete`.
- `create`: department options from the shared tree concern.
- `store`: trimmed name and nullable department; success toast; redirect to the index.
- `edit`: position details plus department options.
- `update`: same fields; success toast; redirect to the index.
- `destroy`: hard delete; success toast; redirect to the index. No in-use guard yet because nothing references positions; add one when employee assignments arrive.

### Frontend

- `resources/js/types/position.ts`: `Position` (index row), `PositionDetails` (edit form), reusing `DepartmentOption` for the select.
- `resources/js/pages/positions/Index.vue`: table of name, department (or “Company-wide”), and edit/delete actions; delete dialog matching `departments/Index.vue`.
- `resources/js/pages/positions/Create.vue` and `Edit.vue`: breadcrumb/header shell matching the department pages.
- `components/positions/PositionForm.vue`: name input and a department `Select` (tree labels with depth; “No department” option).
- `AppSidebar.vue`: Positions entry with a briefcase icon after Departments, gated on `can.positions.view`.
- `HandleInertiaRequests` and `resources/js/types/auth.ts`: add `positions.view`/`positions.create`.
- Regenerate Wayfinder types so `DepartmentController`-style imports from `@/actions/App/Http/Controllers/PositionController` resolve; no hardcoded URLs.

### Tests

- `tests/Feature/Positions/PositionModelTest.php`: relations, factory default state, department state, and `Department::positions`.
- `tests/Feature/Positions/PositionManagementTest.php`: permission and guest gates; index payload and ordering; create company-wide/with department; duplicate name in the same department and company-wide; same name in different departments; re-scoping; rename collision; delete.
- `tests/Feature/Departments/DepartmentManagementTest.php`: extend deletion coverage so a department with a position is refused and remains intact, and an otherwise unused department becomes deletable after its last position is removed.

All position feature tests use `$this->withoutVite()` like the department tests.

## Risks / Trade-offs

- **Department deletion now has a third refusal reason** → The index payload exposes the count, the button is disabled with a specific title, and the controller message names positions.
- **Company-wide uniqueness relies on validation** because `NULL` values are distinct in the composite unique index → The closure is the authoritative check; the index still protects same-department duplicates. Concurrent inserts could race, which is acceptable for a low-volume admin catalogue.
- **Extracting department tree helpers could regress the existing pages** → The department index and form-option tests already cover tree order, depth, and excluded subtrees; run them with the new position tests.
- **Positions can be deleted without an in-use check while no assignments exist** → When the employees capability adds `employee.position_id`, add a guard mirroring department deletion and update the positions spec.

## Migration Plan

1. Run `php artisan migrate`; the migration only creates `positions` and needs no backfill.
2. Seed roles and permissions through the existing seeder so `positions.*` exist for role assignment; Super Admin bypasses policy checks regardless.
3. Deploy the backend and run `npm run build` for the new pages; regenerate Wayfinder types as part of the build.
4. Rollback drops the `positions` table, discarding catalogue rows. The `restrictOnDelete` FK means the migration must be rolled back only after the positions table is removed (it is dropped first by its own `down()`); no department rows are affected.
