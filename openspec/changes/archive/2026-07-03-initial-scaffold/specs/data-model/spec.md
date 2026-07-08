# Data Model Specification

## Purpose

Define the core database schema and Eloquent models for the six foundational entities: Tenant, User, Service, Employee, EmployeeSchedule, and Booking.

## Requirements

### Requirement: Tenant Table

The system SHALL persist tenant records with `id`, `name`, `slug`, `created_at`, `updated_at`.

#### Scenario: Tenant migration runs

- GIVEN the database is fresh
- WHEN migrations are executed
- THEN the `tenants` table exists with the specified columns
- AND `slug` has a unique index

### Requirement: User Table

The system SHALL persist user records with `id`, `tenant_id` (FK), `name`, `email`, `role` (enum), `password`, `notification_channel`, `created_at`, `updated_at`.

#### Scenario: User belongs to a tenant

- GIVEN tenants T1 and users U1 (belongs to T1)
- WHEN U1 is queried
- THEN `tenant_id` references T1's `id`
- AND cascade behavior is defined (restrict or cascade on delete)

#### Scenario: Email is unique per tenant

- GIVEN tenant T1 has a user with email "staff@salon.com"
- WHEN a second user in T1 is created with "staff@salon.com"
- THEN creation fails with a uniqueness constraint violation

### Requirement: Service Table

The system SHALL persist service records with `id`, `tenant_id` (FK), `name`, `description`, `price_cents` (integer), `duration_minutes` (integer), `active` (boolean), `created_at`, `updated_at`.

#### Scenario: Service is scoped to tenant

- GIVEN tenant T1 has a service "Haircut"
- WHEN the service is queried
- THEN `tenant_id` references T1
- AND the service is not visible to other tenants

#### Scenario: Price is stored in cents

- GIVEN a service costs $25.00
- WHEN the service is stored
- THEN `price_cents` is 2500
- AND no floating-point precision issues exist

### Requirement: Employee Schedule Table

The system SHALL persist employee schedule records with `id`, `employee_id` (FK to users), `day_of_week` (integer 0-6), `start_time` (time), `end_time` (time), `created_at`, `updated_at`.

#### Scenario: Schedule belongs to an employee

- GIVEN employee E1 exists
- WHEN E1's schedule is queried
- THEN only E1's schedule records are returned
- AND `employee_id` references E1's user `id`

#### Scenario: Time range is valid

- GIVEN a schedule record with `start_time` = 09:00 and `end_time` = 17:00
- WHEN the record is saved
- THEN the save succeeds
- AND `end_time` is after `start_time`

### Requirement: Booking Table

The system SHALL persist booking records with `id`, `tenant_id` (FK), `service_id` (FK), `employee_id` (FK, nullable), `client_id` (FK, nullable), `client_name`, `client_email`, `client_phone`, `date`, `start_time`, `end_time`, `status` (enum), `payment_status` (enum), `stripe_payment_intent_id`, `notification_channel`, `notes`, `created_at`, `updated_at`.

#### Scenario: Booking is scoped to tenant

- GIVEN tenant T1 has a booking
- WHEN the booking is queried
- THEN `tenant_id` references T1
- AND the booking is not visible to other tenants

#### Scenario: Nullable employee and client

- GIVEN a booking is created for a service
- WHEN `employee_id` and `client_id` are not provided
- THEN the booking is persisted successfully
- AND both nullable FK fields are stored as NULL

### Requirement: Eloquent Model Relationships

The system SHALL define Eloquent relationships on all models to enable traversal between entities.

#### Scenario: Tenant has many users, services, bookings

- GIVEN a tenant T1 with users, services, and bookings
- WHEN `$t1->users`, `$t1->services`, `$t1->bookings` are called
- THEN the correct related records are returned for T1 only

#### Scenario: Employee services pivot

- GIVEN an employee E1 associated with services S1 and S2
- WHEN `$e1->services` is called
- THEN S1 and S2 are returned
- AND the pivot table `employee_services` stores the associations
