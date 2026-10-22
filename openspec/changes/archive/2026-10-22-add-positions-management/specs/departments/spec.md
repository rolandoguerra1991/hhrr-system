# Spec Delta

## MODIFIED Requirements

### Requirement: A department in use cannot be deleted

A department MUST NOT be deleted while it has child departments, assigned users, or positions attached. The request is refused with a message naming the reason, and the department stays intact.

#### Scenario: Department with children

- **WHEN** a user with `departments.delete` deletes a department that has child departments
- **THEN** the deletion is refused, the reason is reported, and nothing is removed

#### Scenario: Department with members

- **WHEN** a user with `departments.delete` deletes a department that has users assigned to it
- **THEN** the deletion is refused, the reason is reported, and nothing is removed

#### Scenario: Department with positions

- **WHEN** a user with `departments.delete` deletes a department that has positions attached
- **THEN** the deletion is refused, the reason is reported, and nothing is removed

#### Scenario: Unused department

- **WHEN** a user with `departments.delete` deletes a department with no children, no users, and no positions
- **THEN** the department is removed and disappears from the tree
