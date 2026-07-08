# Business Booking Management Specification

## Purpose

Provide tenant users a scoped booking management surface with safe business cancellation.

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
