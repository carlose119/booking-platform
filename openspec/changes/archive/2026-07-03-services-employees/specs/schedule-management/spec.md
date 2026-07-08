# Schedule Management Specification

## Purpose

Provide per-tenant employee schedule management through the Business Admin panel, enabling tenant administrators to configure employee availability with day-of-week and time range validation.

## Requirements

### Requirement: Employee Schedule CRUD in Tenant Panel

The system SHALL provide a Filament resource in the Tenant panel for creating, reading, updating, and deleting employee schedule records scoped to the active tenant.

#### Scenario: Create a new schedule for an employee

- GIVEN a BusinessAdmin is authenticated in the Tenant panel for tenant T1
- WHEN they submit the schedule creation form with valid data (employee, day_of_week (0-6), start_time, end_time)
- THEN a new EmployeeSchedule record is created with `employee_id` referencing the selected employee
- AND the schedule appears in the employee's schedule list

#### Scenario: Read schedule list for an employee

- GIVEN a BusinessAdmin is authenticated in the Tenant panel for tenant T1
- WHEN they view the schedule list for a specific employee
- THEN only schedules for that employee are displayed
- AND schedules from other employees are not visible

#### Scenario: Update a schedule record

- GIVEN a BusinessAdmin is authenticated in the Tenant panel for tenant T1
- WHEN they modify a schedule's day_of_week, start_time, or end_time and save
- THEN the updated values are persisted
- AND the change is reflected in the schedule list

#### Scenario: Delete a schedule record

- GIVEN a BusinessAdmin is authenticated in the Tenant panel for tenant T1
- WHEN they delete a schedule record
- THEN the schedule is removed from the database
- AND the employee's availability is updated accordingly

### Requirement: Schedule Validation

The system SHALL validate schedule input fields to ensure data integrity.

#### Scenario: End time must be after start time

- GIVEN a BusinessAdmin is authenticated in the Tenant panel for tenant T1
- WHEN they enter an end_time that is before start_time in the schedule creation form
- THEN the form displays a validation error
- AND the schedule is not created

#### Scenario: Day of week must be between 0 and 6

- GIVEN a BusinessAdmin is authenticated in the Tenant panel for tenant T1
- WHEN they enter a day_of_week outside the range 0-6
- THEN the form displays a validation error
- AND the schedule is not created

### Requirement: Employee Selection

The system SHALL only allow selection of employees belonging to the same tenant.

#### Scenario: Employee list is tenant-scoped

- GIVEN tenant T1 has employees E1 and E2
- WHEN a BusinessAdmin creates a schedule for an employee
- THEN only T1's employees are available for selection
- AND employees from other tenants are not selectable