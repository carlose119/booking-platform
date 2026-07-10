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

### Requirement: Tenant Schedule Administration Exposure

The system SHALL expose employee schedule management to BusinessAdmin users for active-tenant employees only.

#### Scenario: BusinessAdmin manages tenant schedules

- GIVEN a BusinessAdmin is active in tenant T1
- WHEN they open the schedule resource
- THEN only schedules for T1 employees are listed
- AND employees from other tenants are not selectable.

#### Scenario: Direct cross-tenant schedule access is denied

- GIVEN a BusinessAdmin belongs to tenant T1
- WHEN they request a schedule record for a T2 employee
- THEN access is denied
- AND the record is not displayed or mutated.

### Requirement: Simple Recurring Schedule Rules

The system MUST support simple recurring employee availability by day of week and start/end time; exceptions and vacations are out of scope.

#### Scenario: Create valid recurring hours

- GIVEN a BusinessAdmin selects a T1 Employee
- WHEN they submit day_of_week 0-6 with start_time before end_time
- THEN the recurring schedule is saved
- AND it appears in the tenant schedule list.

#### Scenario: Reject invalid schedule input

- GIVEN a BusinessAdmin submits an invalid day or end_time not after start_time
- WHEN validation runs
- THEN the form shows errors
- AND no schedule is created or updated.

### Requirement: Employee Own Schedule Read-Only Access

The system SHALL allow Employees to view only their own schedule and MUST NOT allow them to create, update, delete, or view other employees' schedules.

#### Scenario: Employee views own schedule

- GIVEN an Employee belongs to tenant T1 and has schedule entries
- WHEN they open their schedule page
- THEN only their own schedule entries are visible.

#### Scenario: Employee cannot edit schedules

- GIVEN an Employee is viewing a schedule
- WHEN they attempt create, edit, delete, or direct access to another employee schedule
- THEN access is denied
- AND no schedule data changes.
