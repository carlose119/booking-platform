# Reminder Scheduler Specification

## Purpose

Define the scheduled command that scans upcoming bookings and dispatches reminder notifications 24 hours before appointment time.

## Requirements

### Requirement: Reminder Scheduler Command

The system SHALL provide a scheduled command that runs daily to scan for bookings requiring reminders.

#### Scenario: Scheduler runs daily

- GIVEN the Laravel Scheduler is configured
- WHEN the scheduler runs at the configured time
- THEN the reminder command is executed
- AND the command scans all active bookings

#### Scenario: Scheduler finds bookings needing reminders

- GIVEN bookings scheduled for tomorrow between 8:00 AM and 8:00 PM
- WHEN the reminder command runs
- THEN reminders are dispatched for all qualifying bookings
- AND each reminder is sent to the respective client

#### Scenario: Scheduler skips past-due bookings

- GIVEN a booking with appointment time in the past
- WHEN the reminder command runs
- THEN that booking is skipped
- AND no reminder is sent

### Requirement: Reminder Timing Precision

The system SHALL dispatch reminders within a 5-minute window of the 24-hour mark before appointment time.

#### Scenario: Reminder dispatched at correct time

- GIVEN a booking scheduled for July 5, 2026 at 10:00 AM
- WHEN the reminder command runs on July 4, 2026 between 9:55 AM and 10:05 AM
- THEN a reminder is dispatched for that booking
- AND the reminder is not dispatched before or after the window

#### Scenario: Multiple reminders for same booking

- GIVEN a booking that already received a reminder
- WHEN the reminder command runs again
- THEN no duplicate reminder is sent
- AND the booking is marked as reminded

### Requirement: Reminder Failure Handling

The system SHALL handle reminder failures gracefully without blocking other reminders.

#### Scenario: Single reminder failure

- GIVEN a booking requiring a reminder
- WHEN the reminder dispatch fails (e.g., invalid phone number)
- THEN the failure is logged with booking and tenant context
- AND other reminders continue processing
- AND the failed reminder is retried up to 3 times

#### Scenario: Tenant configuration missing

- GIVEN a tenant without notification configuration
- WHEN the reminder command processes bookings for that tenant
- THEN reminders for that tenant are skipped
- AND a warning is logged for the tenant
- AND other tenants' reminders continue processing

### Requirement: Reminder Idempotency

The system SHALL ensure reminder commands are idempotent — running multiple times for the same period does not produce duplicate notifications.

#### Scenario: Scheduler runs twice in same period

- GIVEN the reminder command ran successfully at 10:00 AM
- WHEN the command runs again at 10:05 AM (manual trigger or retry)
- THEN no duplicate reminders are sent for bookings already reminded
- AND only new qualifying bookings receive reminders

#### Scenario: Queue job failure and retry

- GIVEN a reminder job queued but not yet processed
- WHEN the job fails and is retried
- THEN the retry does not duplicate notifications
- AND the job is marked as processed after successful completion

### Requirement: Reminder Command Configuration

The system SHALL allow configuring the reminder window (hours before appointment) and scan interval.

#### Scenario: Default reminder window

- GIVEN no custom configuration
- WHEN the reminder command runs
- THEN reminders are sent for bookings 24 hours before appointment
- AND the window is ±5 minutes

#### Scenario: Custom reminder window

- GIVEN a configuration setting reminder_hours to 48
- WHEN the reminder command runs
- THEN reminders are sent for bookings 48 hours before appointment
- AND the window is ±5 minutes
