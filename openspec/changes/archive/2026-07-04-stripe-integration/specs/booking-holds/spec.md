# Delta for Booking Holds

## MODIFIED Requirements

### Requirement: Hold to Booking Conversion

The system SHALL convert an active hold to a booking when the guest confirms. For tenants with payment_policy=100upfront or fraction, the booking MUST be created with status=pending and payment_status=unpaid, and a PaymentIntent MUST be created. For tenants with payment_policy=nopayment, the booking MUST be created with status=confirmed and payment_status=unpaid.

(Previously: Hold always converts to booking with status=pending, payment_status=unpaid)

#### Scenario: Successful conversion with no payment

- GIVEN an active hold H1 exists for guest G1
- AND tenant T1 has payment_policy=nopayment
- WHEN the guest confirms the booking
- THEN a booking record is created with status=confirmed, payment_status=unpaid
- AND H1 is deleted or marked as converted

#### Scenario: Successful conversion with payment required

- GIVEN an active hold H1 exists for guest G1
- AND tenant T1 has payment_policy=100upfront
- WHEN the guest confirms the booking
- THEN a booking record is created with status=pending, payment_status=unpaid
- AND a PaymentIntent is created for the full amount
- AND H1 is deleted or marked as converted

#### Scenario: Expired hold cannot convert

- GIVEN hold H1 has expires_at < now()
- WHEN the guest attempts to confirm
- THEN the system returns an error
- AND no booking is created

### Requirement: Hold TTL Extension for Payment

The system SHALL extend the hold TTL to 15 minutes (from 10 minutes) when payment is required, to allow sufficient time for payment completion.

(Previously: Hold TTL is always 10 minutes)

#### Scenario: Extended TTL for payment required

- GIVEN tenant T1 has payment_policy=100upfront
- WHEN a hold is created
- THEN expires_at is now + 15 minutes
- AND the hold record indicates payment is required

#### Scenario: Standard TTL for no payment

- GIVEN tenant T1 has payment_policy=nopayment
- WHEN a hold is created
- THEN expires_at is now + 10 minutes
