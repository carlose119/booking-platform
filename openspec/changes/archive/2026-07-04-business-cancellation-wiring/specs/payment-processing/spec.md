# Delta for Payment Processing

## MODIFIED Requirements

### Requirement: Scheduled Auto-Refund

The system SHALL use existing asynchronous refund primitives when a business cancels a booking with `payment_status` paid or partial. Eligible bookings MUST be refunded according to tenant refund rules, and refund processing MUST be idempotent.

(Previously: Auto-refund only described scheduled checks for cancelled bookings, not business cancellation wiring or idempotency.)

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
