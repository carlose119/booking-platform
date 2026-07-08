# Delta for Business Booking Management

## ADDED Requirements

### Requirement: Tenant Booking Rescheduling

The system MUST let authorized tenant Business Admins reschedule an active booking for the same service and same employee only. The action MUST preserve status, payment status, tenant isolation, and cancellation behavior; it MUST NOT allow customer self-reschedule, service change, payment adjustment, cancelled bookings, completed bookings, cross-tenant access, or double booking.

#### Scenario: Business admin reschedules own tenant booking

- GIVEN a Business Admin views an active booking for their tenant
- WHEN they choose a valid new date/time for the same employee and optional reason
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
