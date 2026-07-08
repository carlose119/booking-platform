# Notification Events Specification

## Purpose

Define notification classes for booking confirmation, reminder, cancellation, and reschedule events with message templates.

## Requirements

### Requirement: Booking Confirmation Notification

The system SHALL send a booking confirmation notification immediately after a successful booking is created.

#### Scenario: Booking confirmation sent via email

- GIVEN a user with notification_channel set to "email"
- WHEN a booking is successfully created
- THEN a booking confirmation email is sent within 30 seconds
- AND the email includes booking details (date, time, service, business name)

#### Scenario: Booking confirmation sent via SMS

- GIVEN a user with notification_channel set to "sms"
- WHEN a booking is successfully created
- THEN a booking confirmation SMS is sent within 30 seconds
- AND the SMS includes booking details (date, time, service)

#### Scenario: Booking confirmation sent via both channels

- GIVEN a user with notification_channel set to "both"
- WHEN a booking is successfully created
- THEN both email and SMS confirmations are sent within 30 seconds
- AND both messages include booking details

### Requirement: Booking Reminder Notification

The system SHALL send a booking reminder notification 24 hours before the scheduled appointment time.

#### Scenario: Reminder sent 24 hours before appointment

- GIVEN a booking scheduled for tomorrow at 10:00 AM
- WHEN the reminder scheduler runs at 10:00 AM today
- THEN a reminder notification is sent to the client
- AND the reminder includes appointment details (date, time, service, business name)

#### Scenario: Reminder respects user notification preference

- GIVEN a user with notification_channel set to "sms"
- WHEN a reminder is triggered
- THEN the reminder is sent via SMS only
- AND no email is sent

#### Scenario: Reminder for past-due booking

- GIVEN a booking with appointment time in the past
- WHEN the reminder scheduler runs
- THEN no reminder is sent for that booking
- AND the booking is skipped in the scheduler scan

### Requirement: Booking Cancellation Notification

The system SHALL send a cancellation notification when a business cancels a booking.

#### Scenario: Cancellation notification sent to client

- GIVEN a business cancels a client's booking
- WHEN the cancellation is processed
- THEN a cancellation notification is sent to the client
- AND the notification includes cancellation reason (if provided) and refund information (if applicable)

#### Scenario: Cancellation with refund

- GIVEN a booking that qualifies for a refund (within refund_window_hours)
- WHEN the business cancels the booking
- THEN the cancellation notification includes refund amount and estimated processing time
- AND the refund process is initiated automatically

#### Scenario: Cancellation without refund

- GIVEN a booking that does not qualify for a refund (outside refund_window_hours)
- WHEN the business cancels the booking
- THEN the cancellation notification includes explanation that no refund is available
- AND no refund process is initiated

### Requirement: Booking Reschedule Notification

The system SHALL send a reschedule notification when a booking is rescheduled by either the business or client.

#### Scenario: Reschedule notification sent to client

- GIVEN a business reschedules a client's booking to a new date/time
- WHEN the reschedule is processed
- THEN a reschedule notification is sent to the client
- AND the notification includes both original and new appointment details

#### Scenario: Reschedule notification sent to business

- GIVEN a client reschedules their booking to a new date/time
- WHEN the reschedule is processed
- THEN a reschedule notification is sent to the business
- AND the notification includes both original and new appointment details

### Requirement: Notification Message Templates

The system SHALL use plain-text message templates for all notifications, with dynamic content insertion for booking details.

#### Scenario: Template includes required booking details

- GIVEN a notification is triggered for a booking
- WHEN the notification message is generated
- THEN the message includes: client name, business name, service name, date, time
- AND the message is formatted in plain text (no HTML)

#### Scenario: Template handles missing optional data

- GIVEN a booking without a cancellation reason
- WHEN a cancellation notification is generated
- THEN the message excludes the cancellation reason section
- AND the message remains valid and readable
