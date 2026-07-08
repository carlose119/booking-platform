# Delta for Notification Events

## MODIFIED Requirements

### Requirement: Booking Reschedule Notification

The system SHALL send a BookingRescheduled notification to the client when an authorized business user reschedules a booking. The notification MUST include original and new appointment date/time. Customer self-reschedule MUST NOT be introduced in this slice.

(Previously: Reschedule notification covered both business and client-initiated reschedules.)

#### Scenario: Business reschedule notification sent to client

- GIVEN a business admin reschedules a client's booking
- WHEN the reschedule is committed
- THEN a BookingRescheduled notification is dispatched to the client
- AND it includes original and new date/time

#### Scenario: Notification failure does not corrupt reschedule

- GIVEN the booking has no resolvable client recipient
- WHEN business reschedule is committed
- THEN the booking remains rescheduled with audit fields
- AND notification failure does not trigger cancellation, refund, or payment changes
