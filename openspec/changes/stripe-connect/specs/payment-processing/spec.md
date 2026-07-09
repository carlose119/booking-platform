# Delta for Payment Processing

## ADDED Requirements

### Requirement: Payment Account Resolution

The system MUST resolve each payment operation to direct API key mode or Stripe Connect mode. Direct mode MUST keep tenant credentials. Connect mode MUST use the platform key with tenant account context. The system MUST NOT create fees, perform FX conversion, or cross tenant boundaries.

#### Scenario: Direct mode is preserved

- GIVEN tenant T1 uses direct API key mode
- WHEN a payment, refund, or webhook operation is processed
- THEN tenant T1's Stripe API key and webhook secret are used
- AND no connected account is required

## MODIFIED Requirements

### Requirement: PaymentIntent Creation

The system SHALL create a Stripe PaymentIntent for paid bookings using the resolved account. Amount, currency, and snapshots MUST be preserved; `usd` fallback remains; unsupported currencies and inactive Connect capabilities MUST block creation.
(Previously: PaymentIntents used tenant Stripe configuration only.)

#### Scenario: Full payment

- GIVEN tenant T1 has payment_policy=100upfront and resolved payment account A1
- WHEN a guest confirms booking
- THEN PaymentIntent is created in A1 for the full amount
- AND payment ID, charged amount, and charged currency are stored

#### Scenario: Deposit payment

- GIVEN tenant T1 has fraction policy and deposit_percentage=20
- WHEN a guest confirms a 5000 minor-unit booking
- THEN PaymentIntent is created for 1000 minor units in T1's account
- AND deposit and snapshot fields are recorded

#### Scenario: Currency is missing or unsupported

- GIVEN tenant T1 has missing or unsupported currency
- WHEN payment is attempted
- THEN missing currency uses `usd`, unsupported currency creates no PaymentIntent
- AND no other tenant is touched

#### Scenario: Connect not ready

- GIVEN tenant T1 uses Connect without active charge capability
- WHEN payment is attempted
- THEN no PaymentIntent is created
- AND the booking remains unpaid or pending

### Requirement: Webhook Endpoint

The system SHALL verify signatures and process succeeded/failed PaymentIntent events for direct and Connect tenants. Events MUST resolve to one tenant/account context and remain idempotent.
(Previously: Webhooks assumed direct Stripe events only.)

#### Scenario: Successful payment

- GIVEN a direct or Connect PaymentIntent succeeds
- WHEN a valid webhook is received
- THEN the matching booking is marked paid or partial and confirmed
- AND duplicate delivery is idempotent

#### Scenario: Failed payment

- GIVEN a direct or Connect PaymentIntent fails
- WHEN a valid webhook is received
- THEN payment remains unpaid and the pending slot may expire

#### Scenario: Invalid or ambiguous

- GIVEN a webhook has an invalid signature or unresolved account
- WHEN the endpoint receives it
- THEN HTTP 400 is returned
- AND no booking state is modified

### Requirement: Manual Refund

The system SHALL refund paid bookings through the original charge account context. Refunds MUST update payment_status to refunded and MUST NOT create cross-tenant refunds.
(Previously: Refunds used direct Stripe API behavior only.)

#### Scenario: Admin refunds payment

- GIVEN a paid booking with Stripe PaymentIntent context exists
- WHEN BusinessAdmin initiates refund
- THEN Stripe refund is created in the original account
- AND booking payment_status is updated to refunded

#### Scenario: Admin refunds deposit

- GIVEN a fraction booking has deposit paid
- WHEN BusinessAdmin initiates refund
- THEN only the deposit amount is refunded in the original account
- AND booking payment_status is updated to refunded

### Requirement: Scheduled Auto-Refund

The system SHALL automatically refund eligible cancellations through the original payment account. Processing MUST remain asynchronous and idempotent for direct and Connect tenants.
(Previously: Auto-refund did not define Connect account context.)

#### Scenario: Eligible auto-refund

- GIVEN a paid booking is cancelled within tenant refund rules
- WHEN the scheduled job runs
- THEN the refund is created in the original account

#### Scenario: Ineligible auto-refund

- GIVEN cancellation is outside rules or already refunded
- WHEN the scheduled job runs
- THEN no second refund is created
