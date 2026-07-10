# Delta for Data Model

## MODIFIED Requirements

### Requirement: Tenant Table

The system SHALL persist tenant records with `id`, `name`, `slug`, `default_currency`, payment account mode, nullable Stripe connected account ID, Connect onboarding/status fields, `created_at`, and `updated_at`. `default_currency` MUST default to `usd`; payment account mode MUST default to direct API key mode for existing tenants.
(Previously: Tenant table persisted identity and default currency only.)

#### Scenario: Tenant migration runs

- GIVEN the database is fresh
- WHEN migrations are executed
- THEN the `tenants` table includes currency plus Connect payment account fields
- AND `slug` has a unique index

#### Scenario: Existing tenants are backfilled

- GIVEN tenants exist before the Connect migration
- WHEN the migration/backfill runs
- THEN each tenant resolves to direct API key mode and `usd` currency
- AND connected account fields remain empty

#### Scenario: Connected account is tenant-scoped

- GIVEN tenant T1 has connected account acct_1 and tenant T2 has acct_2
- WHEN T1 payment account data is queried
- THEN only acct_1 is returned for T1
- AND acct_2 is not visible through T1

### Requirement: Booking Table

The system SHALL persist booking payment fields sufficient to reconcile PaymentIntents, refunds, and webhooks to the original tenant and payment account context, while preserving existing status, cancellation, reschedule, currency snapshot, and availability index behavior.
(Previously: Booking table stored PaymentIntent ID and payment snapshots without explicit Connect account reconciliation requirements.)

#### Scenario: Booking is scoped to tenant

- GIVEN tenant T1 has a booking with payment context
- WHEN the booking is queried
- THEN `tenant_id` references T1
- AND payment context is not visible to other tenants

#### Scenario: Payment snapshot persists

- GIVEN a booking requires payment
- WHEN the payment amount and account context are resolved
- THEN charged amount, charged currency, and payment account context are stored

#### Scenario: Legacy booking snapshot fallback

- GIVEN an existing booking has null charged currency or account context
- WHEN currency-aware or refund reads occur
- THEN currency resolves to `usd` and account resolves from tenant direct mode

#### Scenario: Composite index supports availability queries

- GIVEN bookings exist with indexed availability fields
- WHEN availability query filters by those fields
- THEN the database uses the availability index
