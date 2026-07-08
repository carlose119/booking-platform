# Delta for Data Model

## MODIFIED Requirements

### Requirement: Booking Table

The system SHALL persist booking records with `id`, `tenant_id`, `service_id`, `employee_id`, `client_id`, client contact fields, `date`, `start_time`, `end_time`, `status`, `payment_status`, Stripe/payment fields, `notification_channel`, `notes`, nullable cancellation audit fields, and nullable reschedule audit fields for previous date/start/end, reschedule actor, and optional reason. A composite index on `(tenant_id, employee_id, date, status, start_time, end_time)` SHALL support availability queries.

(Previously: Booking table included cancellation audit fields and availability index, but no reschedule audit fields.)

#### Scenario: Booking is scoped to tenant

- GIVEN tenant T1 has a booking
- WHEN the booking is queried
- THEN `tenant_id` references T1 and other tenants cannot access it

#### Scenario: Nullable employee and client

- GIVEN a booking is created for a service
- WHEN `employee_id` and `client_id` are omitted
- THEN both nullable FK fields are stored as NULL

#### Scenario: Cancellation audit fields persist

- GIVEN a business user cancels a booking with reason
- WHEN the booking is saved
- THEN cancellation timestamp, reason, and actor are persisted

#### Scenario: Reschedule audit fields persist

- GIVEN a business user reschedules a booking with optional reason
- WHEN the booking is saved
- THEN previous date/time, actor, and reason are persisted

#### Scenario: Availability index exists

- GIVEN migrations are run
- WHEN availability queries filter by tenant, employee, date, status, start, and end
- THEN the composite non-unique availability index supports lookup
