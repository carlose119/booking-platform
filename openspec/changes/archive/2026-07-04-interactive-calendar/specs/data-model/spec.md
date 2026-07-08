# Delta for Data Model

## MODIFIED Requirements

### Requirement: Booking Table

The system SHALL persist booking records with `id`, `tenant_id` (FK), `service_id` (FK), `employee_id` (FK, nullable), `client_id` (FK, nullable), `client_name`, `client_email`, `client_phone`, `date`, `start_time`, `end_time`, `status` (enum), `payment_status` (enum), `stripe_payment_intent_id`, `notification_channel`, `notes`, `created_at`, `updated_at`. A composite index on `(tenant_id, employee_id, date, status, start_time, end_time)` SHALL exist for availability query performance.

(Previously: Booking table without composite index for availability queries)

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
