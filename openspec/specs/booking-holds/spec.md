# Booking Holds Specification

## Purpose

Prevent double-booking during guest checkout by holding slots with TTL-based expiry and database-level race condition prevention.

## Requirements

### Requirement: Hold Creation

The system SHALL create a booking hold record when a guest selects a slot. The hold MUST include tenant_id, employee_id, service_id, date, start_time, end_time, guest fields (client_name, client_email, client_phone), and expires_at (current time + 10 minutes).

#### Scenario: Successful hold creation

- GIVEN a guest selects slot 10:00-10:30 for employee E1 on Monday
- WHEN the hold is created
- THEN a booking_holds record exists with expires_at = now + 10 minutes
- AND the hold is tenant-scoped to the active tenant

#### Scenario: Hold TTL is 10 minutes

- GIVEN a hold is created at 10:00
- WHEN the hold is queried
- THEN expires_at is 10:10

### Requirement: Race Condition Prevention

The system SHALL enforce a composite unique index on (tenant_id, employee_id, date, start_time, end_time) where expires_at > now(). The database MUST reject duplicate holds for the same slot.

#### Scenario: Second hold on same slot rejected

- GIVEN hold H1 exists for E1 on Monday 10:00-10:30 with expires_at > now()
- WHEN a second hold H2 is attempted for the same slot
- THEN the database throws a unique constraint violation
- AND H2 is not created

#### Scenario: Expired hold does not block new hold

- GIVEN hold H1 exists for E1 on Monday 10:00-10:30 with expires_at < now()
- WHEN a new hold H2 is attempted for the same slot
- THEN H2 is created successfully
- AND the unique index only considers active (non-expired) holds

### Requirement: Hold Exclusion from Availability

The system SHALL exclude slots with active (expires_at > now()) holds from available slots in AvailabilityService.

#### Scenario: Active hold hides slot

- GIVEN hold H1 exists for E1 on Monday 10:00-10:30 with expires_at > now()
- WHEN availability is requested for E1 on Monday
- THEN the 10:00 slot is not shown as available

#### Scenario: Expired hold shows slot

- GIVEN hold H1 exists for E1 on Monday 10:00-10:30 with expires_at < now()
- WHEN availability is requested for E1 on Monday
- THEN the 10:00 slot is shown as available

### Requirement: Expired Hold Cleanup

The system SHALL run a scheduled command every minute to delete holds where expires_at < now(). The cleanup MUST be tenant-scoped.

#### Scenario: Expired holds deleted

- GIVEN holds H1 (expires_at = 9:50) and H2 (expires_at = 10:05) exist
- WHEN the cleanup command runs at 10:00
- THEN H1 is deleted
- AND H2 remains

#### Scenario: Cleanup respects tenant isolation

- GIVEN tenant T1 has expired hold H1 and tenant T2 has expired hold H2
- WHEN the cleanup command runs for T1
- THEN only H1 is deleted
- AND T2's H2 remains until T2's cleanup runs

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