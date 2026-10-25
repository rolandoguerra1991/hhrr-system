# Spec Delta

## MODIFIED Requirements

### Requirement: A position may belong to one department

A position MAY belong to exactly one department or to none, in which case it is company-wide. While no employment assignment references it, a user with `positions.update` MUST be able to move it between departments or back to company-wide. Once any employment assignment references it, its department scope MUST be immutable; its name MAY still change. A position MUST be shown with its department wherever positions are listed.

#### Scenario: Company-wide position

- **WHEN** a user with `positions.create` creates a position with no department
- **THEN** the position exists as company-wide and is shown without a department

#### Scenario: Position attached to a department

- **WHEN** a user with `positions.create` selects a department
- **THEN** the position is stored with that department and is shown with it

#### Scenario: Moving a position

- **WHEN** a user with `positions.update` changes an unused position's department or clears it
- **THEN** the new scope is saved and the position appears under it immediately

#### Scenario: Moving a position in use

- **WHEN** a user with `positions.update` changes the department scope of a position referenced by a current or historical assignment
- **THEN** the request is rejected with a validation error and the original scope is kept

#### Scenario: Renaming a position in use

- **WHEN** a user with `positions.update` changes only the name of a position referenced by an assignment
- **THEN** the rename is accepted without changing the position's department scope

### Requirement: A position can be removed

A user with `positions.delete` MUST be able to delete a position that has no employment assignment history. A position referenced by any current or historical assignment MUST NOT be deleted; the request is refused with a message and the position stays intact. The position list MUST identify in-use positions and MUST NOT offer deletion for them.

#### Scenario: Removing an unused position

- **WHEN** a user with `positions.delete` deletes a position with no assignment history
- **THEN** the position is removed and disappears from the position list

#### Scenario: Removing a current position

- **WHEN** a user with `positions.delete` deletes a position held by a current assignment
- **THEN** the deletion is refused, the reason is reported, and nothing is removed

#### Scenario: Removing a historical position

- **WHEN** a user with `positions.delete` deletes a position referenced only by historical assignments
- **THEN** the deletion is refused, the reason is reported, and nothing is removed

#### Scenario: Delete control is unavailable

- **WHEN** a user with `positions.view` and `positions.delete` opens the position list
- **THEN** in-use positions do not offer a delete action

### Requirement: A department with positions cannot be deleted

A department MUST NOT be deleted while positions are attached to it. The request is refused with a message naming the reason, and the department and its positions stay intact.

#### Scenario: Department with positions

- **WHEN** a user with `departments.delete` deletes a department that has positions attached
- **THEN** the deletion is refused, the reason is reported, and nothing is removed

#### Scenario: Removing the last position unblocks deletion

- **WHEN** a department has no child departments or employment assignment history and its last position is deleted
- **THEN** the department can then be deleted
