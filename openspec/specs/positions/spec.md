# positions Specification

## Purpose

Describes the company's catalogue of job positions — their names, optional attachment to a department, and removal rules — so employees, payroll, and reporting can refer to the same job titles.

## Requirements

### Requirement: Position access is permission-gated

Reading positions MUST require the `positions.view` permission. Creating MUST require `positions.create`, while changing a position's name or department SHOULD require `positions.update`. Deleting MUST require `positions.delete`. Users holding none of these permissions MUST NOT reach position data through any page or action. Position data is structural and is visible to every holder of the relevant permission; no field is restricted further.

#### Scenario: Reader without write permissions

- **WHEN** a user holding only `positions.view` opens the position list
- **THEN** the list is shown, and create, edit, and delete controls are unavailable and refused if requested directly

#### Scenario: No position permission

- **WHEN** a user without any position permission requests the position list or submits a create, update, or delete action
- **THEN** the system denies the request and no position data is returned or changed

#### Scenario: Guest

- **WHEN** an unauthenticated visitor requests a position page
- **THEN** the system redirects to the login page

### Requirement: Positions have a name

Each position MUST have a name of at most 255 characters, stored with surrounding whitespace removed. A position has no description or other free-text field.

#### Scenario: Creating a position

- **WHEN** a user with `positions.create` submits a name
- **THEN** the position is created with that name and shown in the position list

### Requirement: Position names are unique within their scope

A position name MUST be unique among positions attached to the same department, or among company-wide positions, compared case-insensitively and ignoring surrounding whitespace. Positions attached to different departments MAY share a name.

#### Scenario: Duplicate name in the same department

- **WHEN** a user creates "Engineer" in a department that already has an "engineer" position
- **THEN** the request is rejected with a validation error on the name

#### Scenario: Duplicate company-wide name

- **WHEN** a user creates a company-wide "Engineer" while another company-wide "Engineer" exists
- **THEN** the request is rejected with a validation error on the name

#### Scenario: Same name in different departments

- **WHEN** a user creates "Engineer" in a department that has no such position while another department has one
- **THEN** the position is created successfully

#### Scenario: Renaming into a collision

- **WHEN** a user renames a position to a name already used within its scope
- **THEN** the rename is rejected with a validation error and the original name is kept

### Requirement: A position may belong to one department

A position MAY belong to exactly one department or to none, in which case it is company-wide. A user with `positions.update` MUST be able to move a position between departments or back to company-wide, and the position MUST be shown with its department wherever positions are listed.

#### Scenario: Company-wide position

- **WHEN** a user with `positions.create` creates a position with no department
- **THEN** the position exists as company-wide and is shown without a department

#### Scenario: Position attached to a department

- **WHEN** a user with `positions.create` selects a department
- **THEN** the position is stored with that department and is shown with it

#### Scenario: Moving a position

- **WHEN** a user with `positions.update` changes a position's department or clears it
- **THEN** the new scope is saved and the position appears under it immediately

### Requirement: A position can be removed

A user with `positions.delete` MUST be able to delete a position that is not in use. Deleting a position MUST NOT change its department or any other position.

#### Scenario: Removing an unused position

- **WHEN** a user with `positions.delete` deletes a position
- **THEN** the position is removed and disappears from the position list

### Requirement: A department with positions cannot be deleted

A department MUST NOT be deleted while positions are attached to it. The request is refused with a message naming the reason, and the department and its positions stay intact.

#### Scenario: Department with positions

- **WHEN** a user with `departments.delete` deletes a department that has positions attached
- **THEN** the deletion is refused, the reason is reported, and nothing is removed

#### Scenario: Removing the last position unblocks deletion

- **WHEN** a department has no child departments or users and its last position is deleted
- **THEN** the department can then be deleted

### Requirement: Position changes apply immediately

Position changes SHALL take effect as soon as they are saved. Positions are structural labels rather than employment or pay facts, so no approval step, reason, or effective date SHALL be recorded; the audit and approval rules that apply to employee records and payroll do not apply here.

#### Scenario: Rename is visible right away

- **WHEN** a user with `positions.update` renames a position
- **THEN** the new name is shown immediately in the position list with no waiting state

#### Scenario: No approval queue

- **WHEN** a position is created, renamed, re-scoped to a department, or deleted
- **THEN** the change is not held for approval and no approver is required
