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

The system SHALL convert an active hold to a booking when the guest confirms. The booking MUST have status=pending and payment_status=unpaid.

#### Scenario: Successful booking creation

- GIVEN an active hold H1 exists for guest G1 with valid information
- WHEN the guest confirms the booking
- THEN a booking record is created with status=pending, payment_status=unpaid
- AND the hold H1 is deleted or marked as converted
- AND the booking is tenant-scoped to the active tenant

#### Scenario: Expired hold rejection

- GIVEN hold H1 has expires_at < now()
- WHEN the guest attempts to confirm
- THEN the system returns an error message
- AND no booking is created
- AND the guest is redirected to select a new slot

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
