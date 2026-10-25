# departments Specification

## Purpose

Describes how the company's departments are organized — their hierarchy, naming, optional manager, membership of users, and removal rules — so every HR module can attach people and reporting to the same structure.

## Requirements

### Requirement: Department access is permission-gated

Reading departments MUST require the `departments.view` permission. Creating MUST require `departments.create`, changing a name, parent, or manager MUST require `departments.update`, and deleting MUST require `departments.delete`. Users holding none of these permissions MUST NOT reach department data through any page or action.

#### Scenario: Reader without write permissions

- **WHEN** a user holding only `departments.view` opens the department list
- **THEN** the list is shown, and create, edit, and delete controls are unavailable and refused if requested directly

#### Scenario: No department permission

- **WHEN** a user without any department permission requests the department list or submits a create, update, or delete action
- **THEN** the system denies the request and no department data is returned or changed

#### Scenario: Guest

- **WHEN** an unauthenticated visitor requests a department page
- **THEN** the system redirects to the login page

### Requirement: Departments form a hierarchy

A department MAY have a parent department. The hierarchy SHALL be displayed as an indented tree, and moving a department SHALL carry its descendants with it.

#### Scenario: Root and child departments

- **WHEN** a user with `departments.create` creates "Operations" with no parent and "Warehouse" with "Operations" as parent
- **THEN** both exist, and "Warehouse" is presented beneath "Operations" as its child

#### Scenario: A department cannot become its own ancestor

- **WHEN** a user with `departments.update` sets a department's parent to itself or to one of its own descendants
- **THEN** the change is rejected with a validation error and the hierarchy is unchanged

### Requirement: Department names are unique among siblings

A department name MUST be unique among departments sharing the same parent, compared case-insensitively and ignoring surrounding whitespace. Departments under different parents MAY share a name.

#### Scenario: Duplicate sibling name

- **WHEN** a user creates "Sales" under a parent that already has a "sales" child
- **THEN** the request is rejected with a validation error on the name

#### Scenario: Same name in different branches

- **WHEN** a user creates "Sales" under a different parent that has no "Sales" child
- **THEN** the department is created successfully

#### Scenario: Renaming into a sibling collision

- **WHEN** a user renames a department to a name already used by one of its siblings
- **THEN** the rename is rejected with a validation error and the original name is kept

### Requirement: A department may have a manager

A department MAY designate one existing employee as its manager. The manager MUST be optional, MUST have an employment period covering today, MAY manage multiple departments, MAY belong to any department, and SHALL be shown wherever the department is displayed. Only a user with `departments.update` MAY set or clear the manager; without that permission the action is unavailable and refused.

#### Scenario: Assigning a manager

- **WHEN** a user with `departments.update` selects an active employee as manager
- **THEN** that employee is stored as the department's manager and is shown with the department

#### Scenario: Clearing the manager

- **WHEN** a user with `departments.update` clears the manager selection
- **THEN** the department is saved with no manager

#### Scenario: Terminated employee cannot be selected

- **WHEN** a user attempts to select an employee whose employment period does not cover today as manager
- **THEN** the request is rejected with a validation error and the manager is unchanged

#### Scenario: Manager manages multiple departments

- **WHEN** a user with `departments.update` selects the same active employee as manager of two departments
- **THEN** both departments show that employee as manager

#### Scenario: Manager account removed

- **WHEN** the user account linked to a manager employee is deleted
- **THEN** the department keeps that employee as manager and only the employee's login link is cleared

### Requirement: Department membership comes from current assignments

An employee belongs to the department on their current employment assignment. The department member count MUST count employees whose current assignment is in that department. Unassigned employees, terminated employees, and historical assignments MUST NOT count as current members.

#### Scenario: Counting current members

- **WHEN** a department has two employees currently assigned to it, one unassigned employee, and one former employee whose assignment ended
- **THEN** the department shows two members

#### Scenario: Transfer changes membership

- **WHEN** an employee is transferred from one department to another
- **THEN** the employee is counted in the new department and no longer counted in the old one

#### Scenario: Terminated employee leaves the count

- **WHEN** an employee is terminated
- **THEN** the employee is no longer counted as a current member of their last department

### Requirement: A department in use cannot be deleted

A department MUST NOT be deleted while it has child departments, positions attached, or any employment assignment history. The request is refused with a message naming the reason, and the department stays intact.

#### Scenario: Department with children

- **WHEN** a user with `departments.delete` deletes a department that has child departments
- **THEN** the deletion is refused, the reason is reported, and nothing is removed

#### Scenario: Department with positions

- **WHEN** a user with `departments.delete` deletes a department that has positions attached
- **THEN** the deletion is refused, the reason is reported, and nothing is removed

#### Scenario: Department with members

- **WHEN** a user with `departments.delete` deletes a department that is the current department of one or more employees
- **THEN** the deletion is refused, the reason is reported, and nothing is removed

#### Scenario: Department with assignment history

- **WHEN** a user with `departments.delete` deletes a department that has historical assignments but no current assignments
- **THEN** the deletion is refused, the reason is reported, and nothing is removed

#### Scenario: Unused department

- **WHEN** a user with `departments.delete` deletes a department with no children, no positions, and no assignment history
- **THEN** the department is removed and disappears from the tree

### Requirement: Department changes apply immediately

Department changes SHALL take effect as soon as they are saved. They are structural labels rather than employment or pay facts, so no approval step, reason, or effective date SHALL be recorded; the audit and approval rules that apply to employee records and payroll do not apply here.

#### Scenario: Rename is visible right away

- **WHEN** a user with `departments.update` renames a department
- **THEN** the new name is shown immediately on the department tree, the employee directory, employee details, and any position attached to it

#### Scenario: Manager change is visible right away

- **WHEN** a user with `departments.update` changes a department's manager
- **THEN** the new manager is shown immediately for the department and for every employee currently assigned to it

#### Scenario: No approval queue

- **WHEN** a department is created, renamed, re-parented, assigned a manager, or deleted
- **THEN** the change is not held for approval and no approver is required
