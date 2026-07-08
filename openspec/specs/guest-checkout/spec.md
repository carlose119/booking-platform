# Guest Checkout Specification

## Purpose

Collect guest information and create confirmed bookings from active holds via a Livewire-based checkout form.

## Requirements

### Requirement: Guest Information Collection

The system SHALL collect guest name, email, and phone via a Livewire form. All three fields MUST be required and validated server-side.

#### Scenario: All fields provided

- GIVEN a guest fills in name="John Doe", email="john@example.com", phone="+1234567890"
- WHEN the form is submitted
- THEN validation passes
- AND the hold is ready for conversion

#### Scenario: Missing required field

- GIVEN a guest leaves the phone field empty
- WHEN the form is submitted
- THEN validation fails with error message
- AND no booking is created

#### Scenario: Invalid email format

- GIVEN a guest enters email="not-an-email"
- WHEN the form is submitted
- THEN validation fails with email format error
- AND no booking is created

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

### Requirement: Slot Selection to Form Transition

The system SHALL transition from slot selection to guest form when a slot is clicked. The hold MUST be created before displaying the form.

#### Scenario: Slot click triggers hold

- GIVEN available slot 10:00-10:30 for employee E1
- WHEN the guest clicks the slot
- THEN a hold is created via BookingService::createHold()
- AND the guest form is displayed with the hold details

#### Scenario: Hold creation failure

- GIVEN slot 10:00-10:30 is already held by another guest
- WHEN the guest clicks the slot
- THEN hold creation fails
- AND the guest sees an error message
- AND the available slots refresh to show current availability

### Requirement: Tenant Isolation

The system SHALL scope all hold and booking operations to the active tenant. No cross-tenant data SHALL be accessible or modifiable.

#### Scenario: Tenant-scoped hold creation

- GIVEN tenants T1 and T2 both have employee E1 with slot 10:00-10:30
- WHEN a guest creates a hold for T1
- THEN the hold is created for T1 only
- AND T2's availability is unaffected

#### Scenario: Tenant-scoped booking creation

- GIVEN a hold exists for tenant T1
- WHEN the guest confirms the booking
- THEN the booking is created for T1
- AND the booking is not visible to T2

### Requirement: Touch-Friendly Guest Form UX

The system MUST present guest information and notification preference controls in a touch-friendly layout while preserving existing server-side validation and confirmation behavior.

#### Scenario: Guest form remains usable on mobile

- GIVEN a guest has an active hold
- WHEN the guest views the checkout form on a small viewport
- THEN name, email, phone, and preference controls are easy to read and tap
- AND submitting valid data follows the existing confirmation or payment branch

#### Scenario: Validation errors are actionable

- GIVEN required or invalid guest data is submitted
- WHEN validation fails
- THEN each error is shown near the relevant control
- AND no booking or payment is created

### Requirement: Checkout Recovery and Confirmation Clarity

The system MUST show clear expired-hold, payment-error, loading, disabled, and confirmation states while preserving Stripe and booking status semantics.

#### Scenario: Expired hold recovery

- GIVEN the active hold has expired
- WHEN the guest attempts to confirm
- THEN the guest sees a message explaining the slot expired
- AND the guest is guided to choose a new slot without creating a booking

#### Scenario: Payment error retry

- GIVEN a payment-required booking reaches the payment step
- WHEN payment fails
- THEN the guest sees a clear payment error and can retry
- AND Stripe internals and booking payment status remain unchanged

#### Scenario: Confirmation summarizes the booking

- GIVEN a booking is successfully confirmed or sent to payment completion
- WHEN the guest reaches the confirmation state
- THEN the page clearly shows service, date/time, guest contact, and next-step information
- AND notification behavior remains unchanged
