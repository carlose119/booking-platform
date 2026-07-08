# Delta for Notification Events

## MODIFIED Requirements

### Requirement: Booking Cancellation Notification

The system SHALL send a cancellation notification when a business cancels a booking. The notification MUST use the existing booking-cancelled path and include the cancellation reason when provided and refund information when applicable.

(Previously: Cancellation notification existed, but business cancellation workflow wiring was not specified.)

#### Scenario: Cancellation notification sent to client

- GIVEN a business cancels a client's booking with a reason
- WHEN the cancellation is processed
- THEN a cancellation notification is sent to the client
- AND the message includes the reason and refund information when applicable

#### Scenario: Cancellation without notifiable client

- GIVEN a booking has no resolvable client recipient
- WHEN the business cancellation is processed
- THEN cancellation still succeeds
- AND no notification failure blocks audit or refund handling

#### Scenario: Duplicate cancellation sends no duplicate notification

- GIVEN a booking is already cancelled
- WHEN cancellation is requested again
- THEN no additional BookingCancelled notification is dispatched
