# Delta for Guest Checkout

## ADDED Requirements

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
