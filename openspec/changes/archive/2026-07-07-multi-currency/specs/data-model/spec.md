# Delta for Data Model

## MODIFIED Requirements

### Requirement: Tenant Table

The system SHALL persist tenant records with `id`, `name`, `slug`, `default_currency`, `created_at`, and `updated_at`. `default_currency` MUST default to `usd` and be validated as a supported lowercase ISO currency.
(Previously: Tenant table persisted id, name, slug, created_at, and updated_at only.)

#### Scenario: Tenant migration runs
- GIVEN the database is fresh
- WHEN migrations are executed
- THEN the `tenants` table includes `default_currency` with default `usd`
- AND `slug` remains unique across tenants

#### Scenario: Existing tenants are backfilled
- GIVEN tenants exist before the currency migration
- WHEN the migration/backfill runs
- THEN each existing tenant resolves to `usd`

### Requirement: Booking Table

The system SHALL persist booking records with existing booking, payment, cancellation, and reschedule fields plus nullable payment snapshot fields for charged amount and charged currency. Snapshot fields MUST preserve the amount/currency charged at payment time and MUST fall back to USD behavior for existing rows.
(Previously: Booking table tracked payment status and Stripe intent, but no charged amount/currency snapshot.)

#### Scenario: Booking is scoped to tenant
- GIVEN tenant T1 has a booking with charged currency
- WHEN the booking is queried
- THEN `tenant_id` references T1
- AND the booking and currency snapshot are not visible to other tenants

#### Scenario: Payment snapshot persists
- GIVEN a booking requires payment
- WHEN the payment amount is calculated
- THEN charged amount and charged currency are stored on the booking

#### Scenario: Legacy booking snapshot fallback
- GIVEN an existing booking has null charged currency
- WHEN currency-aware reads occur
- THEN currency resolves to `usd` without changing payment status
