# Payment Processing Specification

## Purpose

Handle Stripe payment integration including PaymentIntent creation, webhook processing, refund management, and tenant-specific payment configuration.

## Requirements

### Requirement: Tenant Payment Configuration

The system SHALL allow each tenant to configure their payment policy via Stripe payment settings. Settings MUST include: payment_policy (100upfront, fraction, nopayment), deposit_percentage (1-100, nullable), refund_window_hours (integer), stripe_api_key (encrypted), stripe_webhook_secret (encrypted).

#### Scenario: Tenant configures 100% upfront payment

- GIVEN a BusinessAdmin configures tenant T1 with payment_policy=100upfront
- WHEN a booking is created for T1
- THEN full payment is required before confirmation

#### Scenario: Tenant configures deposit payment

- GIVEN a BusinessAdmin configures tenant T1 with payment_policy=fraction, deposit_percentage=20
- WHEN a booking is created for T1
- THEN only 20% deposit is charged via Stripe
- AND the remaining 80% is recorded for in-person payment

#### Scenario: Tenant configures no mandatory payment

- GIVEN a BusinessAdmin configures tenant T1 with payment_policy=nopayment
- WHEN a booking is created for T1
- THEN booking is confirmed immediately without payment

### Requirement: Stripe API Key Encryption

The system SHALL encrypt tenant Stripe API keys at rest using application-level encryption. Keys MUST NOT be stored in plaintext.

#### Scenario: API key stored encrypted

- GIVEN a tenant provides stripe_api_key="sk_test_abc123"
- WHEN the key is saved to database
- THEN the stored value is encrypted
- AND the decrypted value matches the original

### Requirement: PaymentIntent Creation

The system SHALL create a Stripe PaymentIntent when a guest confirms a booking for tenants with payment_policy=100upfront or fraction. The PaymentIntent amount MUST match the required payment, currency MUST match the tenant default currency resolved at charge time, and booking payment snapshot fields MUST store the charged amount and currency. Existing or missing currency data MUST fall back to `usd`.

#### Scenario: Full payment PaymentIntent

- GIVEN tenant T1 has payment_policy=100upfront, default_currency=`eur`, and service costs 5000 minor units
- WHEN a guest confirms booking
- THEN PaymentIntent is created with amount=5000 and currency=`eur`
- AND PaymentIntent ID, charged amount, and charged currency are stored on the booking

#### Scenario: Deposit payment PaymentIntent

- GIVEN tenant T1 has payment_policy=fraction, deposit_percentage=20, default_currency=`usd`, and service costs 5000 minor units
- WHEN a guest confirms booking
- THEN PaymentIntent is created with amount=1000 and currency=`usd`
- AND deposit amount, total, charged amount, and charged currency are recorded on booking

#### Scenario: Missing tenant currency falls back to USD

- GIVEN tenant T1 has no configured currency
- WHEN payment is required for a booking
- THEN PaymentIntent currency is `usd`
- AND booking charged currency is `usd`

#### Scenario: Unsupported Stripe currency is rejected

- GIVEN tenant T1 has an unsupported currency configured
- WHEN payment is attempted
- THEN no PaymentIntent is created
- AND the booking remains unpaid or pending without cross-tenant side effects

### Requirement: Webhook Endpoint

The system SHALL expose a POST webhook endpoint that verifies Stripe signature and processes payment_intent.succeeded and payment_intent.payment_failed events. Webhooks MUST be idempotent.

#### Scenario: Successful payment webhook

- GIVEN a PaymentIntent succeeds in Stripe
- WHEN webhook is received with valid signature
- THEN booking payment_status is updated to paid
- AND booking status is updated to confirmed
- AND duplicate webhook is handled idempotently

#### Scenario: Failed payment webhook

- GIVEN a PaymentIntent fails in Stripe
- WHEN webhook is received with valid signature
- THEN booking payment_status remains unpaid
- AND booking status remains pending
- AND the held slot is released after TTL expires

#### Scenario: Invalid webhook signature

- GIVEN a webhook request with invalid signature
- WHEN the endpoint receives the request
- THEN HTTP 400 is returned
- AND no booking state is modified

### Requirement: Manual Refund

The system SHALL allow BusinessAdmin to manually refund a paid booking via Stripe API. Refund MUST update booking payment_status to refunded.

#### Scenario: Admin refunds full payment

- GIVEN a booking with payment_status=paid and stripe_payment_intent_id exists
- WHEN BusinessAdmin initiates refund
- THEN Stripe refund is created
- AND booking payment_status is updated to refunded

#### Scenario: Admin refunds deposit payment

- GIVEN a booking with payment_policy=fraction and deposit paid
- WHEN BusinessAdmin initiates refund
- THEN only the deposit amount is refunded
- AND booking payment_status is updated to refunded

### Requirement: Scheduled Auto-Refund

The system SHALL run a scheduled command that checks bookings cancelled within the tenant's refund_window_hours. Eligible bookings MUST be automatically refunded via Stripe API, and business cancellations with `payment_status` paid or partial MUST use the existing asynchronous refund primitives. Refund processing MUST be idempotent.

#### Scenario: Auto-refund within window

- GIVEN a booking cancelled 12 hours before appointment
- AND tenant refund_window_hours=24
- WHEN the scheduled job runs
- THEN the booking is eligible for auto-refund
- AND Stripe refund is created automatically

#### Scenario: Auto-refund outside window

- GIVEN a booking cancelled 30 hours before appointment
- AND tenant refund_window_hours=24
- WHEN the scheduled job runs
- THEN the booking is NOT eligible for auto-refund
- AND no refund is processed

#### Scenario: Business cancellation queues eligible refund

- GIVEN a paid or partial booking is cancelled by the business within refund rules
- WHEN cancellation completes
- THEN refund processing is queued or marked for scheduled processing
- AND the UI is not blocked by the payment provider

#### Scenario: Duplicate cancellation does not double refund

- GIVEN a cancelled paid booking already has refund processing started or completed
- WHEN cancellation or refund processing runs again
- THEN no second refund is created

#### Scenario: Non-paid booking skips refund

- GIVEN a booking has `payment_status=unpaid`
- WHEN the business cancels it
- THEN no refund job or provider refund is created

### Requirement: Payment Status Tracking

The system SHALL track payment_status on booking records with states: unpaid, paid, refunded, partial. Status transitions MUST be auditable.

#### Scenario: Status transitions

- GIVEN a booking with payment_status=unpaid
- WHEN payment succeeds via webhook
- THEN payment_status transitions to paid
- AND transition is logged for audit

#### Scenario: Partial payment status

- GIVEN a booking with payment_policy=fraction and deposit paid
- WHEN payment succeeds
- THEN payment_status is set to partial
- AND remaining balance is recorded for in-person collection
