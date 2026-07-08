# Delta for Guest Checkout

## MODIFIED Requirements

### Requirement: Booking Confirmation

The system SHALL convert an active hold to a booking when the guest confirms. For tenants with payment_policy=100upfront or fraction, the booking MUST be created with status=pending and payment_status=unpaid, and a PaymentIntent MUST be created before confirmation. For tenants with payment_policy=nopayment, the booking MUST be created with status=confirmed and payment_status=unpaid.

(Previously: Booking always created with status=pending, payment_status=unpaid)

#### Scenario: Successful booking creation with no payment

- GIVEN an active hold H1 exists for guest G1 with valid information
- AND tenant T1 has payment_policy=nopayment
- WHEN the guest confirms the booking
- THEN a booking record is created with status=confirmed, payment_status=unpaid
- AND the hold H1 is deleted or marked as converted
- AND the booking is tenant-scoped to the active tenant

#### Scenario: Successful booking creation with full payment

- GIVEN an active hold H1 exists for guest G1 with valid information
- AND tenant T1 has payment_policy=100upfront
- WHEN the guest confirms the booking
- THEN a booking record is created with status=pending, payment_status=unpaid
- AND a PaymentIntent is created for the full amount
- AND the guest is redirected to complete payment

#### Scenario: Successful booking creation with deposit

- GIVEN an active hold H1 exists for guest G1 with valid information
- AND tenant T1 has payment_policy=fraction, deposit_percentage=20
- WHEN the guest confirms the booking
- THEN a booking record is created with status=pending, payment_status=unpaid
- AND a PaymentIntent is created for the deposit amount
- AND the remaining balance is recorded for in-person payment

#### Scenario: Expired hold rejection

- GIVEN hold H1 has expires_at < now()
- WHEN the guest attempts to confirm
- THEN the system returns an error message
- AND no booking is created
- AND the guest is redirected to select a new slot

### Requirement: Payment Step Integration

The system SHALL display a payment step after guest information collection for tenants with payment_policy=100upfront or fraction. The payment step MUST use Stripe Elements or redirect to Stripe Checkout.

(Previously: No payment step in checkout flow)

#### Scenario: Payment step displayed for upfront payment

- GIVEN tenant T1 has payment_policy=100upfront
- WHEN the guest completes information form
- THEN the payment step is displayed
- AND Stripe Elements are loaded for card input

#### Scenario: Payment step displayed for deposit

- GIVEN tenant T1 has payment_policy=fraction, deposit_percentage=20
- WHEN the guest completes information form
- THEN the payment step shows the deposit amount
- AND Stripe Elements are loaded for card input

#### Scenario: No payment step for nopayment

- GIVEN tenant T1 has payment_policy=nopayment
- WHEN the guest completes information form
- THEN the booking is confirmed immediately
- AND no payment step is displayed

#### Scenario: Payment failure handling

- GIVEN a guest enters invalid card details
- WHEN payment is attempted
- THEN an error message is displayed
- AND the guest can retry with different card details
- AND the booking remains in pending status
