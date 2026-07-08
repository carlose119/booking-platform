# Delta for User Management

## MODIFIED Requirements

### Requirement: Employee-to-Service Association

The system SHALL support associating employees with services via a many-to-many pivot table, managed through a Repeater in the user form when the user's role is Employee. The Repeater SHALL be visible only when role=Employee and SHALL list tenant services as selectable items.

(Previously: The system SHALL support associating employees with services via a many-to-many pivot table.)

#### Scenario: Employee is linked to services

- GIVEN an Employee user exists under tenant T1
- WHEN the employee is associated with specific services via the user form Repeater
- THEN the `employee_services` pivot table records the associations
- AND the associations are retrievable from the Employee model

#### Scenario: Services are scoped to tenant

- GIVEN tenant T1 has services S1 and S2
- WHEN a BusinessAdmin associates an employee with services via the user form Repeater
- THEN only T1's services are available for selection
- AND services from other tenants are not selectable

#### Scenario: Repeater visibility based on role

- GIVEN a BusinessAdmin is editing a user with role=Employee
- WHEN the user form loads
- THEN the service association Repeater is visible
- AND the Repeater lists tenant services as selectable items

#### Scenario: Repeater hidden for non-employee roles

- GIVEN a BusinessAdmin is editing a user with role=Client
- WHEN the user form loads
- THEN the service association Repeater is not visible