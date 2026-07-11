# Delta for Notification Channels

## MODIFIED Requirements

### Requirement: Notification Channel Routing

The system SHALL route notifications to channels from either a registered user's `notification_channel` or a guest booking's `notification_channel`. Guest recipients MUST use `client_email` for email and `client_phone` for SMS without requiring a user account. For `both`, the system MUST attempt every available guest contact method and MUST NOT suppress all delivery because one contact method is missing.
(Previously: Routing was based only on the user's `notification_channel` preference.)

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

#### Scenario: Guest prefers both with one contact missing

- GIVEN a guest booking with notification_channel="both" and only client_email present
- WHEN a booking notification is triggered
- THEN email delivery is attempted
- AND SMS delivery is skipped without blocking the notification

#### Scenario: Guest has no usable contact for selected channel

- GIVEN a guest booking with notification_channel="sms" and no client_phone
- WHEN a booking notification is triggered
- THEN no notification is sent
- AND the booking workflow still succeeds
