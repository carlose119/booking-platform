# Notification Channels Specification

## Purpose

Define email and SMS notification channel implementations, routing logic based on user preference, and queue processing for async delivery.

## Requirements

### Requirement: Notification Channel Routing

The system SHALL route notifications to the appropriate channel(s) based on the user's `notification_channel` preference (email, sms, or both).

#### Scenario: User prefers email only

- GIVEN a user with notification_channel set to "email"
- WHEN a notification is triggered for that user
- THEN the notification is sent via email only
- AND no SMS is sent

#### Scenario: User prefers SMS only

- GIVEN a user with notification_channel set to "sms"
- WHEN a notification is triggered for that user
- THEN the notification is sent via SMS only
- AND no email is sent

#### Scenario: User prefers both channels

- GIVEN a user with notification_channel set to "both"
- WHEN a notification is triggered for that user
- THEN the notification is sent via both email and SMS
- AND both channels are attempted independently

### Requirement: Email Channel Implementation

The system SHALL send email notifications using Laravel Mail with SMTP or Mailgun transport, configured per tenant.

#### Scenario: Email sent via configured transport

- GIVEN a tenant with valid Mailgun configuration (domain, secret)
- WHEN an email notification is triggered for a user in that tenant
- THEN the email is sent via Mailgun API
- AND the email contains the notification content in plain text format

#### Scenario: Email fallback to SMTP

- GIVEN a tenant without Mailgun configuration but with SMTP settings
- WHEN an email notification is triggered
- THEN the email is sent via SMTP transport
- AND the email contains the notification content in plain text format

### Requirement: SMS Channel Implementation

The system SHALL send SMS notifications using Twilio API, configured per tenant.

#### Scenario: SMS sent via Twilio

- GIVEN a tenant with valid Twilio configuration (SID, auth token, phone number)
- WHEN an SMS notification is triggered for a user in that tenant
- THEN the SMS is sent via Twilio API
- AND the SMS contains the notification content in plain text format

#### Scenario: SMS fails due to invalid configuration

- GIVEN a tenant with invalid Twilio credentials
- WHEN an SMS notification is triggered
- THEN the notification fails with a configuration error
- AND the error is logged with tenant context
- AND the notification is not retried

### Requirement: Async Queue Processing

The system SHALL process all notifications asynchronously via Laravel Queues to avoid blocking the request cycle.

#### Scenario: Notification queued for processing

- GIVEN a notification is triggered
- WHEN the notification is dispatched
- THEN a queue job is created
- AND the job is processed by a queue worker
- AND the user receives the notification within 30 seconds under normal load

#### Scenario: Queue worker failure

- GIVEN a notification job is queued
- WHEN the queue worker fails to process the job
- THEN the job is retried up to 3 times with exponential backoff
- AND after 3 failures, the job is moved to a dead-letter queue
- AND the failure is logged with error details

### Requirement: Tenant Notification Configuration

The system SHALL store notification configuration per tenant, with sensitive fields encrypted at rest.

#### Scenario: Tenant has notification configuration

- GIVEN a tenant with notification settings configured
- WHEN the tenant's notification configuration is accessed
- THEN Twilio SID, auth token, and Mailgun secret are decrypted
- AND Twilio phone number and Mailgun domain are accessible in plain text

#### Scenario: Tenant missing notification configuration

- GIVEN a tenant without notification configuration
- WHEN a notification is triggered for that tenant
- THEN the notification fails with a configuration error
- AND the error is logged indicating missing tenant configuration
