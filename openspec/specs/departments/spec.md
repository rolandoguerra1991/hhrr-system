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

A department MAY designate one existing user as its manager. The manager SHALL be optional, MAY belong to any department, and SHALL be shown wherever the department is displayed.

#### Scenario: Assigning a manager

- **WHEN** a user with `departments.update` selects an existing user as manager
- **THEN** that user is stored as the department's manager and is shown with the department

#### Scenario: Clearing the manager

- **WHEN** a user with `departments.update` clears the manager selection
- **THEN** the department is saved with no manager

#### Scenario: Manager account removed

- **WHEN** the user who manages a department is deleted from the system
- **THEN** the department remains and its manager becomes empty

### Requirement: Users belong to at most one department

A user MUST belong to at most one department, or to none. Assignment SHALL be made by a user holding `users.create` when creating an account or `users.update` when editing one, and the assigned department SHALL be visible to anyone holding `users.view`.

#### Scenario: Assigning a department to a new user

- **WHEN** a user with `users.create` creates an account and selects a department
- **THEN** the account is created with that department and the department appears on the users list

#### Scenario: User without a department

- **WHEN** an account is created or edited with no department selected
- **THEN** the account is saved without a department and remains usable

#### Scenario: Assignment does not grant department permissions

- **WHEN** a user is assigned to a department without holding any department permission
- **THEN** the assignment alone does not allow them to read or change departments

### Requirement: A department in use cannot be deleted

A department MUST NOT be deleted while it has child departments or assigned users. The request is refused with a message naming the reason, and the department stays intact.

#### Scenario: Department with children

- **WHEN** a user with `departments.delete` deletes a department that has child departments
- **THEN** the deletion is refused, the reason is reported, and nothing is removed

#### Scenario: Department with members

- **WHEN** a user with `departments.delete` deletes a department that has users assigned to it
- **THEN** the deletion is refused, the reason is reported, and nothing is removed

#### Scenario: Unused department

- **WHEN** a user with `departments.delete` deletes a department with no children and no users
- **THEN** the department is removed and disappears from the tree

### Requirement: Department changes apply immediately

Department changes SHALL take effect as soon as they are saved. They are structural labels rather than employment or pay facts, so no approval step, reason, or effective date SHALL be recorded; the audit and approval rules that apply to employee records and payroll do not apply here.

#### Scenario: Rename is visible right away

- **WHEN** a user with `departments.update` renames a department
- **THEN** the new name is shown immediately on the department tree, the users list, and any user currently assigned to it, with no waiting state

#### Scenario: No approval queue

- **WHEN** a department is created, renamed, re-parented, assigned a manager, or deleted
- **THEN** the change is not held for approval and no approver is required
