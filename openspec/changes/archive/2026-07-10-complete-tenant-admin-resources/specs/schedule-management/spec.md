# Delta for Schedule Management

## ADDED Requirements

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
