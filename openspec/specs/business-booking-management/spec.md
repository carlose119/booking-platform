# Business Booking Management Specification

## Purpose

Provide tenant users a scoped booking management surface with safe business cancellation and rescheduling.

## Requirements

### Requirement: Tenant Booking Cancellation

The system MUST provide a tenant-scoped booking list/detail surface where authorized business users MAY cancel bookings by providing a non-empty reason. Cancellation MUST set the booking to cancelled, record the actor and reason, send cancellation notification, and be idempotent.

#### Scenario: Business admin cancels own tenant booking

- GIVEN a Business Admin is viewing a confirmed booking for the active tenant
- WHEN they submit a cancellation reason
- THEN the booking is cancelled with reason and actor recorded
- AND cancellation notification and refund handling are triggered as applicable

#### Scenario: Duplicate cancellation is ignored

- GIVEN a booking is already cancelled
- WHEN cancellation is requested again
- THEN no second state transition, notification, or refund trigger occurs

#### Scenario: Cross-tenant cancellation is blocked

- GIVEN a user from tenant A requests cancellation of tenant B's booking
- WHEN the action is submitted
- THEN the system MUST deny the action and leave booking state unchanged

### Requirement: Tenant Booking Rescheduling

The system MUST let authorized tenant Business Admins reschedule an active booking for the same service and same employee only. The action MUST preserve status, payment status, tenant isolation, and cancellation behavior; it MUST NOT allow customer self-reschedule, service change, payment adjustment, cancelled bookings, completed bookings, cross-tenant access, or double booking.

#### Scenario: Business admin reschedules own tenant booking

- GIVEN a Business Admin is viewing an active booking for their tenant
- WHEN they submit a valid new date/time for the same employee and optional reason
- THEN the booking moves to the target slot
- AND status and payment status remain unchanged

#### Scenario: Disallowed booking state is rejected

- GIVEN a booking is cancelled or completed
- WHEN a Business Admin requests reschedule
- THEN the system MUST reject it and leave booking data unchanged

#### Scenario: Cross-tenant or conflicting slot is blocked

- GIVEN the target booking is outside the actor tenant or the target slot is occupied
- WHEN reschedule is requested
- THEN the system MUST deny the action without changing booking state
