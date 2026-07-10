# Delta for Notification Events

## MODIFIED Requirements

### Requirement: Booking Confirmation Notification

The system SHALL send booking confirmation to registered clients after successful no-payment booking creation, to guest no-payment bookings immediately after creation, and to paid guest bookings only after payment webhook success confirms the booking. Confirmation dispatch MUST be idempotent.
(Previously: Confirmation was sent immediately after a successful booking was created.)

#### Scenario: Booking confirmation sent via email

- GIVEN a recipient with notification_channel="email"
- WHEN the booking reaches the eligible confirmed state
- THEN a confirmation email is sent within 30 seconds
- AND it includes booking details

#### Scenario: Booking confirmation sent via SMS

- GIVEN a recipient with notification_channel="sms"
- WHEN the booking reaches the eligible confirmed state
- THEN a confirmation SMS is sent within 30 seconds
- AND it includes booking details

#### Scenario: Booking confirmation sent via both channels

- GIVEN a recipient with notification_channel="both"
- WHEN the booking reaches the eligible confirmed state
- THEN available email and SMS confirmations are attempted independently

#### Scenario: Paid guest confirmation waits for webhook

- GIVEN a payment-required guest booking is created pending payment
- WHEN the pending booking is created before webhook payment success
- THEN no confirmation notification is sent

#### Scenario: Guest no-payment confirmation is sent after creation

- GIVEN a no-payment guest booking is created confirmed
- WHEN booking creation succeeds
- THEN confirmation is sent to available guest contact channels

#### Scenario: Duplicate webhook sends no duplicate confirmation

- GIVEN a paid guest booking was already confirmed by a successful webhook
- WHEN the same payment success is processed again
- THEN no second confirmation notification is dispatched

### Requirement: Booking Reminder Notification

The system SHALL send a booking reminder notification 24 hours before appointment time to registered clients and guest recipients with usable contact details.
(Previously: Reminder recipients were described only as clients/users.)

#### Scenario: Reminder sent 24 hours before appointment

- GIVEN a booking scheduled for tomorrow at 10:00 AM
- WHEN the reminder scheduler runs at 10:00 AM today
- THEN a reminder notification is sent to the recipient
- AND it includes appointment details

#### Scenario: Reminder respects user notification preference

- GIVEN a registered user with notification_channel="sms"
- WHEN a reminder is triggered
- THEN the reminder is sent via SMS only
- AND no email is sent

#### Scenario: Reminder for past-due booking

- GIVEN a booking with appointment time in the past
- WHEN the reminder scheduler runs
- THEN no reminder is sent for that booking

#### Scenario: Guest reminder uses booking contact fields

- GIVEN a guest booking with client_email and notification_channel="email"
- WHEN a reminder is triggered
- THEN the reminder is sent to client_email

#### Scenario: Guest reminder with missing selected contact

- GIVEN a guest booking with notification_channel="sms" and no client_phone
- WHEN a reminder is triggered
- THEN no reminder notification is sent
- AND the scheduler continues processing other bookings

### Requirement: Booking Cancellation Notification

The system SHALL send cancellation notifications to registered clients and guest recipients when a business cancels a booking. Missing guest contact details MUST NOT block cancellation, audit, or refund handling.
(Previously: Cancellation allowed no resolvable client recipient and did not require guest delivery.)

#### Scenario: Cancellation notification sent to client

- GIVEN a business cancels a client's booking
- WHEN cancellation is processed
- THEN a cancellation notification is sent to the recipient
- AND it includes reason and refund information when available

#### Scenario: Cancellation with refund

- GIVEN a booking qualifies for a refund
- WHEN the business cancels the booking
- THEN the notification includes refund amount and estimated processing time
- AND refund processing is initiated

#### Scenario: Cancellation without refund

- GIVEN a booking does not qualify for a refund
- WHEN the business cancels the booking
- THEN the notification explains no refund is available
- AND no refund process is initiated

#### Scenario: Cancellation without notifiable client

- GIVEN a booking has no usable registered or guest recipient
- WHEN business cancellation is processed
- THEN cancellation still succeeds
- AND notification failure does not block audit or refund handling

#### Scenario: Duplicate cancellation sends no duplicate notification

- GIVEN a booking is already cancelled
- WHEN cancellation is requested again
- THEN no additional BookingCancelled notification is dispatched

### Requirement: Booking Reschedule Notification

The system SHALL send a BookingRescheduled notification to registered clients and guest recipients when an authorized business user reschedules a booking. Customer self-reschedule MUST NOT be introduced in this slice.
(Previously: Reschedule notification targeted the client and allowed missing resolvable client recipients.)

#### Scenario: Reschedule notification sent to client

- GIVEN a business admin reschedules a booking
- WHEN the reschedule is committed
- THEN a BookingRescheduled notification is dispatched to the recipient
- AND it includes original and new date/time

#### Scenario: Notification failure does not corrupt reschedule

- GIVEN the booking has no usable registered or guest recipient
- WHEN business reschedule is committed
- THEN the booking remains rescheduled with audit fields
- AND notification failure causes no cancellation, refund, or payment changes
