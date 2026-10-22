# Design

## Context

See `proposal.md` — Why. The existing administration module already establishes the patterns this change reuses: one `Permission` enum case per `resource.action`, a policy per model, FormRequests that own validation, Inertia pages rendered from thin controllers with Wayfinder-generated URLs, and Pest feature tests per module. `RoleController` already refuses to delete a role that is assigned to users, which is the precedent for department removal.

Departments are structural labels, not employment or pay facts, so the approval and effective-dating constraints in the project context do not apply to them; `specs/departments/spec.md` records that as behavior. The installation serves a single company, so no tenant scoping is added.

## Goals / Non-Goals

**Goals:**

- One canonical department tree that later HR modules (employees, leave, attendance, payroll) can reference instead of inventing their own grouping.
- Safe re-parenting (no cycles) and safe removal (no dangling references), enforced server-side.
- Reuse the existing admin conventions so the module looks and behaves like Users, Roles, and Permissions.

**Non-Goals:**

- Department codes, cost centers, budgets, headcount targets.
- Effective-dated department history, approval workflow, or soft-delete/archive state.
- Department-scoped permissions, approval chains, or reporting lines beyond the single manager pointer.
- Bulk reassignment of users or department filtering on the users list.

## Decisions

### 1. Adjacency list for the hierarchy

`departments.parent_id` is a nullable self-referencing FK. Children are read by loading the full department set and folding it in PHP.

**Why:** a single-company org chart is small (tens to low hundreds of nodes) and is read as a whole for the tree page, so an adjacency list needs no write-time maintenance.

**Alternatives:** a closure table or nested sets would make SQL-level subtree queries cheap, but add table maintenance and complexity that no current requirement justifies. If payroll or reporting later needs subtree aggregates in SQL, a closure table can be derived from `parent_id` without changing the domain model.

### 2. Sibling uniqueness in validation, backed by a composite index

Names are trimmed and compared case-insensitively among departments sharing the same parent. Both FormRequests use one `App\Concerns\DepartmentValidationRules` concern, following the existing `ProfileValidationRules` / `RoleAssignmentRules` pattern. A composite unique index on `(parent_id, name)` backstops the non-root case.

**Why:** the rule is a user-facing contract, so it belongs at the write boundary where it can return a helpful field error, and the index catches programmer error.

**Alternative:** a normalized name column plus a generated `parent_key` to make roots behave like other siblings. Rejected: schema and portability cost for a rule two code paths already enforce.

**Known gap:** SQL treats `NULL` parents as distinct, so root-level duplicates are prevented by validation only. Both write paths go through the same concern, and a test covers the root case.

### 3. Cycle prevention in the FormRequest `after()` guard

On update, the chosen parent is rejected when it is the department itself or any of its descendants. The guard walks up from the candidate parent with a visited set and a hard iteration cap, so corrupted data cannot hang a request.

**Why:** no trigger or recursive CTE is needed, and the error surfaces as a normal validation message next to the parent field.

**Alternative:** a database trigger — not portable across SQLite and MySQL and invisible to the user.

Re-parenting needs no child updates: children keep pointing at their parent, so the subtree moves with it.

### 4. Block deletes with explicit checks

`DepartmentController@destroy` refuses deletion when the department has children or assigned users, throwing `ValidationException` keyed on `department` — the same shape `RoleController` uses today. FK behavior is the backstop: `parent_id` is `restrictOnDelete`; `users.department_id` and `departments.manager_id` are `nullOnDelete` so deleting a user account never fails because of org-chart ties.

**Why:** the user decision was "block when in use", and explicit checks produce a message naming the reason, which a raw FK violation cannot.

**Alternative:** soft deletes would allow retirement but introduce an archived state the scope explicitly excludes, plus every query would need a scope to avoid deleted departments appearing in pickers.

### 5. One nullable `users.department_id` instead of a pivot

A user belongs to at most one department, so the FK lives on `users` and the existing user create/edit form gains an optional select.

**Why:** it matches the contract exactly, keeps the users list query unchanged, and avoids a join table for a one-to-many relationship.

**Alternative:** a pivot would support multi-department membership later, but it complicates the form, the permission story, and every "my department" lookup. Adding it later is a migration, not a redesign.

### 6. Manager is a nullable user reference

`departments.manager_id` points at a user and is optional. No role, no second level of approval, no reporting-line graph.

**Why:** it satisfies the requested "department has a manager" without implying an approval chain. Using a role for this would conflate application access with organizational responsibility.

### 7. Permissions as enum cases plus a policy

Four new `App\Enums\Permission` cases: `departments.view`, `departments.create`, `departments.update`, `departments.delete`. `DepartmentPolicy` follows the existing policies: `before()` grants everything to Super Admin, each ability checks its permission. The seeder iterates `PermissionEnum::cases()`, so no seeder edit is required.

The shared `auth.can.departments` prop exposes only `view` and `create`, mirroring the existing `roles` and `users` shape; per-row `update` and `delete` abilities come from the policy, as roles already do. `resources/js/types/auth.ts` gains the matching entry.

### 8. Flat, depth-annotated tree payload

The index controller loads all departments with `withCount(['children', 'users'])`, orders siblings by name, and walks the tree into a flat list where each row carries `depth`.

**Why:** Vue renders the tree with one row loop and indentation, and edit/delete actions stay per-row with their own `can` flags. No recursive component, no deeply nested props, and tests can assert on a simple array.

**Alternative:** nested `children` arrays would suit collapsible UI better, but complicate per-node abilities and payload assertions. The flat list can be regrouped client-side later if collapsing is added.

### 9. Attach assignment to the existing user forms

`UserController@create/edit` pass a department catalogue; `UserForm.vue` gains an optional select; `users/Index.vue` shows the department name. `StoreUserRequest` / `UpdateUserRequest` accept `department_id` as `nullable|exists:departments,id` through the shared profile rules concern or a sibling rule set.

## Data model

**`departments`**

| Column                     | Type                           | Notes                       |
| -------------------------- | ------------------------------ | --------------------------- |
| `id`                       | bigint PK                      |                             |
| `name`                     | string(255)                    | trimmed; unique per parent  |
| `parent_id`                | nullable FK → `departments.id` | `restrictOnDelete`, indexed |
| `manager_id`               | nullable FK → `users.id`       | `nullOnDelete`, indexed     |
| `created_at`, `updated_at` | timestamps                     |                             |

Indexes: `unique(parent_id, name)`, `parent_id`, `manager_id`.

**`users`** gains `department_id` — nullable FK → `departments.id`, `nullOnDelete`, indexed.

Migrations, in order:

1. `create_departments_table` — table plus the self FK.
2. `add_department_id_to_users_table` — column plus the users FK.

`App\Models\Department` uses the project's attribute style — `#[Fillable(['name', 'parent_id', 'manager_id'])]`, `parent()`, `children()` (ordered by name), `manager()`, `users()`.

## Permission matrix

| Action                                         | Permission                      | Enforced by                                             |
| ---------------------------------------------- | ------------------------------- | ------------------------------------------------------- |
| View the department tree                       | `departments.view`              | `DepartmentPolicy@viewAny` via `can:viewAny,Department` |
| Open the create form, store                    | `departments.create`            | `DepartmentPolicy@create`                               |
| Open the edit form, update name/parent/manager | `departments.update`            | `DepartmentPolicy@update`                               |
| Delete an unused department                    | `departments.delete`            | `DepartmentPolicy@delete`                               |
| Set a user's department                        | `users.create` / `users.update` | existing `UserPolicy`                                   |
| See a user's department on the list            | `users.view`                    | existing `UserPolicy`                                   |

Super Admin bypasses all of these through `before()`, consistent with the other policies.

## Routes

All added to the existing `auth`, `verified` group in `routes/administration.php`:

| Method | URI                              | Name                  | Action    |
| ------ | -------------------------------- | --------------------- | --------- |
| GET    | `/departments`                   | `departments.index`   | `index`   |
| GET    | `/departments/create`            | `departments.create`  | `create`  |
| POST   | `/departments`                   | `departments.store`   | `store`   |
| GET    | `/departments/{department}/edit` | `departments.edit`    | `edit`    |
| PUT    | `/departments/{department}`      | `departments.update`  | `update`  |
| DELETE | `/departments/{department}`      | `departments.destroy` | `destroy` |

## Pages and frontend

- `departments/Index.vue` — tree table: indented name, manager, member count, edit/delete actions; delete dialog mirrors `roles/Index.vue`, with the button disabled while the department is in use and the server still refusing.
- `departments/Create.vue`, `departments/Edit.vue` — thin wrappers around a shared `components/departments/DepartmentForm.vue` holding name, parent select, and manager select. On edit, the parent options exclude the department and its descendants.
- `users/Index.vue` — department column; `components/users/UserForm.vue` and the create/edit pages — optional department select.
- `AppSidebar.vue` — Departments entry in the Administration group, gated on `auth.can.departments.view`.
- `resources/js/types/{auth,department,user}.ts` — `auth.can.departments`, `Department`, `DepartmentOption`, and the user's department fields.
- Wayfinder actions regenerate from the new routes during `npm run build`; no generated file is edited by hand.

## Risks / Trade-offs

- **Root-level duplicates are not enforceable by the composite index** → the shared FormRequest concern is the only guard; both write paths use it and a root-case test locks it in.
- **N+1 reads on the tree page** → load all departments once with `withCount(['children', 'users'])` rather than querying per row.
- **Wide or deep trees inflate the index payload** → acceptable for one company; if departments reach the thousands, move to per-level lazy loading.
- **Parent picker grows with the org** → exclude descendants and keep siblings alphabetical; a searchable picker is a later, isolated change.
- **Concurrent re-parenting could slip a cycle past validation** → wrap the update in a transaction and re-run the ancestor guard inside it; a cycle would still be visible in the UI, and the fix is a data correction rather than a redesign.
- **Blocking deletes leaves no retirement path** → renaming is the only option within scope; if departments need to be retired, add an archive state in a dedicated change.
- **Existing user tests assert exact Inertia props** → update them alongside the new optional field so prop-shape assertions stay truthful.

## Migration Plan

1. `php artisan migrate` — additive only: create `departments`, then add `users.department_id`. Existing rows are untouched and get `NULL`.
2. Seed permissions: `php artisan db:seed --class=RolesAndPermissionsSeeder` so `departments.*` exist for non-super-admin roles. Super admins are unaffected because `before()` grants everything.
3. No data backfill is required or attempted.
4. Rollback: `php artisan migrate:rollback` drops `users.department_id` first, then `departments`; manager and membership FKs disappear with them. No external system is touched.
5. Build frontend after migrating so Wayfinder regenerates the `DepartmentController` actions.

## Open Questions

None. Depth limits, department codes, retirement/archive state, and department-scoped permissions are explicitly out of scope and can be raised as their own changes.
