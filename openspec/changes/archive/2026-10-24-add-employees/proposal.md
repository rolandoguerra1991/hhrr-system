# Proposal

## Why

The system currently has no person record. Department membership lives on `users`, department managers reference login accounts, and positions cannot be connected to the people who hold them. Leave, attendance, and payroll all need one employee record with a stable employment history.

This change introduces employees as a separate aggregate, with optional login accounts and effective-dated employment and assignment history. It also moves department membership and department management off `users` so the employee record becomes the single source of truth.

## What Changes

- Add an employee directory with auto-generated, immutable employee numbers (`EMP-0001`).
- Add employment periods so one employee record can be terminated and rehired without duplicating identity or splitting history.
- Add effective-dated employment assignments for department and optional position. An employee has at most one current assignment.
- Derive an employee's reporting manager from the current department manager. Assignments do not store a separate manager.
- Add core employee edit auditing with a required reason.
- Add termination and rehire actions. Termination closes the current assignment, blocks login, and invalidates sessions. Rehire restores login automatically.
- Add `employees.view`, `employees.create`, and `employees.update` permissions. Employees cannot be deleted.
- **BREAKING** Remove `users.department_id`; department membership is derived from current employment assignments.
- **BREAKING** Change `departments.manager_id` to reference employees instead of users.
- Refuse department deletion while any assignment history references it.
- Refuse position deletion while any assignment history references it, and make a position's department scope immutable once used.
- Add local-only demo employees behind the existing environment guard.

## Capabilities

### New Capabilities

- `employees`: Employee records, employment periods, assignments, termination and rehire, core edit auditing, employee permissions, and employment-based login access.

### Modified Capabilities

- `departments`: The manager is an employee, membership comes from employment assignments, and deletion guards use assignment history instead of users.
- `positions`: Positions in use by an assignment cannot be deleted, and their department scope becomes immutable once referenced.

## Impact

- Database: new `employees`, `employment_periods`, `employment_assignments`, `employee_change_logs`, and employee-number sequence tables; `departments.manager_id` foreign key changes; `users.department_id` is dropped.
- Backend: employee models, policies, controllers, form requests, assignment and lifecycle actions, permission enum, seeders, factories, and an employment-aware login gate.
- Frontend: employee index, create, edit, and detail pages; assignment, termination, and rehire dialogs; updated user, department, and position pages; new shared types; Wayfinder regeneration.
- Existing data: current data has one user, zero user department assignments, and zero department managers, so the migration does not need to preserve either relationship.
- Documentation/specs: `departments` and `positions` requirements change; `employees` is added.

## Out of Scope

- Salary, bank, health, date-of-birth, national-ID, address, personal-phone, and emergency-contact fields.
- Payroll, leave, attendance, and contract management.
- Approval workflows. This change records reasons and actors but does not add an approval queue.
- Future-dated hires, rehires, transfers, or terminations.
- Concurrent assignments or allocation percentages.
- Reporting-manager history and department-manager history.
- Retention purges, erasure requests, and field-level masking for future sensitive fields.
- Automatic creation of user accounts when an employee is created.
