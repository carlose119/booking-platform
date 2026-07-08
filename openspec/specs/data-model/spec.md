# Data Model Specification

## Purpose

Define the core database schema and Eloquent models for the six foundational entities: Tenant, User, Service, Employee, EmployeeSchedule, and Booking.

## Requirements

### Requirement: Tenant Table

The system SHALL persist tenant records with `id`, `name`, `slug`, `default_currency`, `created_at`, and `updated_at`. `default_currency` MUST default to `usd` and be validated as a supported lowercase ISO currency.

#### Scenario: Tenant migration runs

- GIVEN the database is fresh
- WHEN migrations are executed
- THEN the `tenants` table includes `default_currency` with default `usd`
- AND `slug` has a unique index

#### Scenario: Existing tenants are backfilled

- GIVEN tenants exist before the currency migration
- WHEN the migration/backfill runs
- THEN each existing tenant resolves to `usd`

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

The system SHALL persist booking records with `id`, `tenant_id` (FK), `service_id` (FK), `employee_id` (FK, nullable), `client_id` (FK, nullable), `client_name`, `client_email`, `client_phone`, `date`, `start_time`, `end_time`, `status` (enum), `payment_status` (enum), `stripe_payment_intent_id`, `notification_channel`, `notes`, nullable cancellation audit fields `cancelled_at`, `cancellation_reason`, and `cancelled_by_user_id` (or equivalent actor FK), plus nullable reschedule audit fields for previous date/start/end, reschedule actor, and optional reason, plus nullable payment snapshot fields for charged amount and charged currency. A composite index on `(tenant_id, employee_id, date, status, start_time, end_time)` SHALL exist for availability query performance.

(Previously: Booking table had status/payment fields and availability index, but no cancellation audit fields.)

#### Scenario: Booking is scoped to tenant

- GIVEN tenant T1 has a booking with charged currency
- WHEN the booking is queried
- THEN `tenant_id` references T1
- AND the booking and currency snapshot are not visible to other tenants

#### Scenario: Nullable employee and client

- GIVEN a booking is created for a service
- WHEN `employee_id` and `client_id` are not provided
- THEN the booking is persisted successfully
- AND both nullable FK fields are stored as NULL

#### Scenario: Payment snapshot persists

- GIVEN a booking requires payment
- WHEN the payment amount is calculated
- THEN charged amount and charged currency are stored on the booking

#### Scenario: Legacy booking snapshot fallback

- GIVEN an existing booking has null charged currency
- WHEN currency-aware reads occur
- THEN currency resolves to `usd` without changing payment status

#### Scenario: Cancellation audit fields persist

- GIVEN a business user cancels a booking with reason "Staff unavailable"
- WHEN the booking is saved
- THEN `cancelled_at`, `cancellation_reason`, and cancellation actor are persisted

#### Scenario: Reschedule audit fields persist

- GIVEN a business user reschedules a booking with optional reason
- WHEN the booking is saved
- THEN previous date/time, actor, and reason are persisted

#### Scenario: Composite index supports availability queries

- GIVEN bookings exist with various tenant_id, employee_id, date, status, start_time, end_time values
- WHEN an availability query filters by all six indexed columns
- THEN the database uses the composite index for the lookup
- AND query performance is within acceptable limits for the calendar UI

#### Scenario: Index created via migration

- GIVEN the database migrations are run
- WHEN the availability index migration executes
- THEN a composite index `idx_bookings_availability` exists on `(tenant_id, employee_id, date, status, start_time, end_time)`
- AND the index is non-unique

### Requirement: Booking Holds Table

The system SHALL persist booking hold records with `id`, `tenant_id` (FK), `employee_id` (FK), `service_id` (FK), `date`, `start_time`, `end_time`, `client_name`, `client_email`, `client_phone`, `session_id`, `expires_at`, `created_at`, `updated_at`. A composite unique index on `(tenant_id, employee_id, date, start_time, end_time)` WHERE `expires_at > now()` SHALL exist to prevent duplicate active holds.

#### Scenario: Booking hold is scoped to tenant

- GIVEN tenant T1 has a booking hold
- WHEN the hold is queried
- THEN `tenant_id` references T1
- AND the hold is not visible to other tenants

#### Scenario: Expired hold does not block new hold

- GIVEN hold H1 exists with `expires_at` in the past
- WHEN a new hold H2 is attempted for the same slot
- THEN H2 is created successfully
- AND the unique index only considers active (non-expired) holds

#### Scenario: Hold TTL is 10 minutes

- GIVEN a hold is created at 10:00
- WHEN the hold is queried
- THEN `expires_at` is 10:10

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
