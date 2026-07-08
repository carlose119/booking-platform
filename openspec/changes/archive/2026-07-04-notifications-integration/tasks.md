# Tasks: Notifications Integration

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 450–550 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 → PR 2 → PR 3 |
| Delivery strategy | ask-on-risk |
| Chain strategy | stacked-to-main |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Tenant notification config + migration | PR 1 | base: main; ~130 lines; tests included |
| 2 | NotificationService, SmsChannel, notifications, job, command | PR 2 | base: main; ~270 lines; depends on PR 1 |
| 3 | Booking flow integration, scheduler wiring, tests | PR 3 | base: main; ~100 lines; depends on PR 2 |

## Phase 1: Foundation / Infrastructure

- [x] 1.1 Create `database/migrations/xxxx_add_notification_config_to_tenants.php` — add `twilio_sid` (nullable), `twilio_auth_token` (nullable, encrypted), `twilio_phone_number` (nullable), `mailgun_domain` (nullable), `mailgun_secret` (nullable, encrypted) to `tenants`
- [x] 1.2 Create `database/migrations/xxxx_add_reminded_at_to_bookings.php` — add `reminded_at` (timestamp, nullable) to `bookings`
- [x] 1.3 Modify `app/Models/Tenant.php` — add notification fields to `$fillable`, add encrypted casts for `twilio_auth_token` and `mailgun_secret`
- [x] 1.4 Modify `app/Models/Booking.php` — add `reminded_at` to `$fillable`, add `'reminded_at' => 'datetime'` cast
- [x] 1.5 Modify `config/services.php` — add `twilio` and `mailgun` config keys (env-driven)
- [x] 1.6 Modify `composer.json` — add `"twilio/sdk": "^8.0"` to `require`
- [x] 1.7 Modify `app/Filament/Resources/TenantResource.php` — add "Notification Configuration" section with Twilio/Mailgun fields (password/revealable for secrets)

## Phase 2: Core Notification Infrastructure

- [x] 2.1 Create `app/Services/NotificationService.php` — channel routing based on `user.notification_channel`, methods: `sendBookingConfirmed`, `sendBookingReminder`, `sendBookingCancelled`, `sendBookingRescheduled`
- [x] 2.2 Create `app/Channels/SmsChannel.php` — custom channel wrapping Twilio SDK, reads tenant Twilio config, sends SMS via `Client`
- [x] 2.3 Create `app/Notifications/BookingConfirmed.php` — plain-text notification with booking details (date, time, service, business)
- [x] 2.4 Create `app/Notifications/BookingReminder.php` — plain-text reminder with appointment details
- [x] 2.5 Create `app/Notifications/BookingCancelled.php` — cancellation notification with reason and refund info (conditional)
- [x] 2.6 Create `app/Notifications/BookingRescheduled.php` — reschedule notification with original and new details
- [x] 2.7 Create `app/Jobs/SendBookingNotification.php` — queue job wrapping `NotificationService`, `$tries = 3`, `$backoff = [30, 120, 300]`
- [x] 2.8 Create `app/Console/Commands/SendReminders.php` — scan bookings WHERE `date = tomorrow` AND `reminded_at IS NULL`, dispatch reminders, set `reminded_at`
- [x] 2.9 Create `app/Notifications/Messages/SmsMessage.php` — simple SMS message class for custom channel
- [x] 2.10 Write test: `tests/Unit/NotificationServiceTest.php` — channel routing tests (8 tests)
- [x] 2.11 Write test: `tests/Unit/SmsChannelTest.php` — SMS channel tests (3 tests)
- [x] 2.12 Write test: `tests/Feature/NotificationDispatchTest.php` — job dispatch and execution tests (6 tests)

## Phase 3: Integration / Wiring

- [x] 3.1 Modify `app/Services/BookingService.php` — after `confirmBooking()` creates booking, dispatch `NotificationService::sendBookingConfirmed()` via `SendBookingNotification` job
- [x] 3.2 Modify `routes/console.php` — register `Schedule::command('booking:send-reminders')->dailyAt('08:00')`
- [x] 3.3 Write test: `tests/Feature/NotificationServiceTest.php` — channel routing respects user preference (email, sms, both)
- [x] 3.4 Write test: `tests/Feature/SendRemindersTest.php` — command finds bookings due tomorrow, sets `reminded_at`, skips past-due and already-reminded
- [x] 3.5 Write test: `tests/Unit/BookingConfirmationTest.php` — booking creation dispatches `SendBookingNotification` job
