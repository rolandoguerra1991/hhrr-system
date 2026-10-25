# employees Specification

## Purpose

Defines the employee record, its employment periods and department assignments, and how employment status controls history, permissions, and login access.

## Requirements

### Requirement: Employee access is permission-gated

Reading employees MUST require `employees.view`. Creating employees and starting employment MUST require `employees.create`. Editing employee details, assigning, terminating, and rehiring MUST require `employees.update`. There is no employee delete permission or delete action. Users without the required permission MUST NOT reach employee data or perform the action.

#### Scenario: Reader without write permissions

- **WHEN** a user holding only `employees.view` opens the employee directory or an employee detail page
- **THEN** the data is shown, and create, edit, assign, terminate, and rehire controls are unavailable and refused if requested directly

#### Scenario: No employee permission

- **WHEN** a user without any employee permission requests an employee page or submits an employee action
- **THEN** the system denies the request and no employee data is returned or changed

#### Scenario: Guest

- **WHEN** an unauthenticated visitor requests an employee page
- **THEN** the system redirects to the login page

### Requirement: Employee numbers are generated and immutable

Each employee MUST have a unique employee number generated automatically in the form `EMP-0001`, with at least four digits. The number MUST NEVER be reused or changed after creation, including after termination and rehire. Gaps caused by failed creation attempts MAY remain.

#### Scenario: Creating an employee

- **WHEN** a user with `employees.create` creates the first employee
- **THEN** the employee receives `EMP-0001`, and the next employee receives `EMP-0002`

#### Scenario: Number is not editable

- **WHEN** a user with `employees.update` edits an employee
- **THEN** the employee number is shown read-only and any submitted replacement is ignored

#### Scenario: Rehire keeps the number

- **WHEN** a terminated employee is rehired
- **THEN** the employee keeps the same employee number and record

### Requirement: Employee records hold minimal identity data

An employee MUST have a required first name and last name. A work email MUST be optional, valid when present, and unique across employees compared case-insensitively. This change SHALL NOT store salary, bank, health, date-of-birth, national-ID, home-address, personal-phone, or emergency-contact data.

#### Scenario: Creating an employee without a work email

- **WHEN** a user with `employees.create` submits a first name, last name, and employment start date but no work email
- **THEN** the employee is created with no work email

#### Scenario: Duplicate work email

- **WHEN** a user submits a work email already used by another employee, ignoring case and surrounding whitespace
- **THEN** the request is rejected with a validation error and no employee is created

### Requirement: The employee directory is readable with employees.view

The employee directory MUST list each employee's number, name, work email, current employment status, current department, and current position when present. It MUST support searching by number, name, and work email, and filtering by active or terminated status. The detail page MUST show the employment periods, assignment history, and core edit audit history.

#### Scenario: Browsing the directory

- **WHEN** a user with `employees.view` opens the employee directory
- **THEN** employees are listed with their current status, department, and position, and no write controls are offered unless the user also holds `employees.update`

#### Scenario: Searching and filtering

- **WHEN** a user with `employees.view` searches by employee number, name, or work email, or filters by active or terminated status
- **THEN** only matching employees are returned

#### Scenario: Reading employee history

- **WHEN** a user with `employees.view` opens an employee detail page
- **THEN** employment periods, assignments, and core edit audit entries are visible

### Requirement: An employee may link to one login account

An employee MAY link to one existing user account, and a user MUST link to at most one employee. Linking and unlinking MUST require `employees.update`. Deleting a user MUST leave the employee record intact and clear the link. A user without an employee link MUST remain unaffected by employee rules.

#### Scenario: Linking a user account

- **WHEN** a user with `employees.update` links an unlinked user to an employee
- **THEN** the employee shows that account and another employee cannot select the same user

#### Scenario: Deleting a linked user

- **WHEN** a user linked to an employee is deleted
- **THEN** the employee remains and its login link becomes empty

### Requirement: Employment periods are effective-dated

An employee MUST have one or more employment periods, each with a start date, an optional end date, a start reason, and a creator. A terminated period MUST also record a termination reason and the user who terminated it. Periods MUST NOT overlap, and an employee MUST have at most one open period. Start and end dates MUST be inclusive and MUST NOT be in the future.

#### Scenario: Multiple employment periods

- **WHEN** an employee is terminated and later rehired
- **THEN** the employee has two non-overlapping periods, with the later period open and the earlier period ended

#### Scenario: Overlapping period is rejected

- **WHEN** a user attempts to start a rehire on or before the previous period's end date
- **THEN** the request is rejected with a validation error and no new period is created

#### Scenario: Future date is rejected

- **WHEN** a user submits an employment start or end date after today
- **THEN** the request is rejected with a validation error

### Requirement: Creating an employee starts employment

Creating an employee MUST require an employment start date on or before today and MUST create the first open employment period in the same action. The start reason for this period SHALL be `Initial hire`. An initial department assignment is optional.

#### Scenario: Creating an active employee

- **WHEN** a user with `employees.create` creates an employee with a start date of today
- **THEN** the employee is active immediately, has one open period, and may still be unassigned

#### Scenario: Backdated start

- **WHEN** a user with `employees.create` creates an employee with a start date in the past
- **THEN** the employee is created with the backdated start date and is active

### Requirement: Core employee edits require a recorded reason

Every change to an employee's name, work email, or linked user MUST require `employees.update` and a non-empty reason. The system MUST record the actor, reason, changed fields, and timestamp in an audit entry. No approval step is required. Audit entries MUST be readable by `employees.view` and MUST NOT be editable or deletable through the application.

#### Scenario: Editing a name with a reason

- **WHEN** a user with `employees.update` changes an employee's last name and supplies a reason
- **THEN** the change is saved and an audit entry records the actor, reason, and old and new values

#### Scenario: Missing reason is rejected

- **WHEN** a user submits a core employee edit without a reason
- **THEN** the request is rejected and the employee is unchanged

#### Scenario: Employee number is not audited as editable

- **WHEN** a user opens the employee edit form
- **THEN** the employee number is read-only and is not submitted as a change

### Requirement: An employee has at most one current assignment

An active employee MAY have zero or one current assignment. An assignment MUST have a department, MAY have a position, and MUST NOT store a manager. When the position belongs to a department, the assignment department MUST match it. A company-wide position MAY be assigned within any department. Historical assignments MUST be retained. A terminated employee MUST NOT receive a new assignment.

#### Scenario: Assigning an unassigned employee

- **WHEN** a user with `employees.update` assigns an active employee to a department and a position from that department
- **THEN** the employee shows that current department and position

#### Scenario: Mismatched position department

- **WHEN** a user assigns an employee to one department with a position scoped to another department
- **THEN** the request is rejected with a validation error

#### Scenario: Company-wide position

- **WHEN** a user assigns an employee to a department with a company-wide position
- **THEN** the assignment is accepted

#### Scenario: Employee without an assignment

- **WHEN** an active employee has no current assignment
- **THEN** the employee is still listed and shown as unassigned

#### Scenario: Terminated employee cannot be assigned

- **WHEN** a user attempts to assign a terminated employee to a department
- **THEN** the request is rejected with a validation error and no assignment is created

### Requirement: Assignment changes append history

An assignment change MUST close the current assignment on the day before the new effective date and insert a new current assignment. The effective date MUST be today or earlier, MUST be on or after the current employment period start, and MUST be later than the current assignment's start date when an assignment exists. Each new assignment MUST record a non-empty reason and the user who made the change. Existing assignments MUST NOT be overwritten or deleted. Approval is not required.

#### Scenario: Transferring an employee

- **WHEN** a user with `employees.update` transfers an employee to another department effective today
- **THEN** the previous assignment ends yesterday, a new open assignment starts today, and both remain visible in history

#### Scenario: Backdated transfer

- **WHEN** a user records a transfer with a past effective date that is later than the current assignment start
- **THEN** the previous assignment ends the day before that date and the new assignment starts on it

#### Scenario: Effective date before current assignment

- **WHEN** a user submits an assignment effective date earlier than the current assignment start
- **THEN** the request is rejected and the history is unchanged

#### Scenario: Effective date before employment starts

- **WHEN** a user submits an assignment effective date earlier than the current employment period start
- **THEN** the request is rejected with a validation error and no assignment is created

### Requirement: Reporting manager is derived from the department

An employee's reporting manager MUST be the manager of the department in the employee's current assignment. Assignments MUST NOT store a separate reporting manager. Changing a department's manager MUST immediately change the derived reporting manager for its currently assigned employees. Per-employee reporting-manager history is out of scope.

#### Scenario: Department manager is the reporting manager

- **WHEN** an employee is assigned to a department with a manager
- **THEN** the employee's reporting manager is that department manager

#### Scenario: Department manager changes

- **WHEN** a user with `departments.update` changes a department's manager
- **THEN** every currently assigned employee in that department reports to the new manager immediately

### Requirement: Termination closes employment and blocks login

Terminating an employee MUST require `employees.update`, a termination date on or before today, and a non-empty reason. Termination MUST be refused while the employee manages any department. A successful termination MUST close the open employment period, close the current assignment on the termination date, keep all history, block the linked user from authenticating, and invalidate existing sessions. The employee MUST NOT be deleted.

#### Scenario: Terminating an employee

- **WHEN** a user with `employees.update` terminates an employee who is not a department manager
- **THEN** the open period and current assignment end on the termination date, and the record remains readable

#### Scenario: Terminating a department manager

- **WHEN** a user attempts to terminate an employee who manages one or more departments
- **THEN** the request is refused with a message naming the blocking departments and nothing changes

#### Scenario: Login after termination

- **WHEN** a terminated employee's linked user attempts to sign in or has an active session
- **THEN** authentication is refused and the session is invalidated

### Requirement: Rehire reuses the employee record

Rehiring MUST require `employees.update` and a new start date after the previous period's end date and no later than today. Rehire MUST create a new open period with a non-empty start reason, keep the same employee record and number, restore login access for the linked user, and MUST NOT restore the previous assignment or department-manager role.

#### Scenario: Rehiring a terminated employee

- **WHEN** a user with `employees.update` rehires an employee with a valid start date
- **THEN** the employee becomes active, keeps the employee number, and starts without an assignment

#### Scenario: Rehire before previous end

- **WHEN** a user submits a rehire start date on or before the previous period's end date
- **THEN** the request is rejected and the employee remains terminated

### Requirement: Login access follows employment status

A user linked to an employee MUST authenticate only while the employee has an employment period covering today. A user without an employee link MUST authenticate under the existing account rules. Rehire MUST restore access without a separate account toggle. No manual employee active flag SHALL exist.

#### Scenario: Active employee signs in

- **WHEN** a user linked to an employee with an open period covering today signs in
- **THEN** authentication succeeds

#### Scenario: Unlinked account signs in

- **WHEN** a user without an employee link signs in with valid credentials
- **THEN** authentication succeeds

### Requirement: Employee data is retained and visible to employees.view

All employee fields and history introduced by this change MUST be visible to `employees.view`. Employee records and history MUST be retained after termination; this change SHALL provide no automatic purge, erasure, or deletion path. No field-level masking is required because no salary, bank, health, or similarly sensitive fields are introduced.

#### Scenario: Terminated record remains readable

- **WHEN** a user with `employees.view` opens a terminated employee
- **THEN** the employee, periods, assignments, and audit history remain visible

#### Scenario: No delete action

- **WHEN** a user with `employees.update` views an employee
- **THEN** no delete action is offered or accepted

### Requirement: Employee changes apply without approval

Except where a rule explicitly refuses an action, employee changes MUST take effect as soon as validated and saved. The recorded reason and actor satisfy the approval rule; no approval queue or pending state is introduced.

#### Scenario: Immediate effect

- **WHEN** a user with the required permission saves a valid employee, assignment, termination, or rehire change
- **THEN** the change is visible immediately with no pending approval state
