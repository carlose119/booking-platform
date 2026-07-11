# Tasks: Guest Notification Delivery

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 520-700 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 recipient/channel foundation → PR 2 webhook/event integration |
| Delivery strategy | two slices |
| Chain strategy | stacked-to-main |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | Recipient/channel foundation | PR 1 | `php artisan test tests/Unit/NotificationServiceTest.php` | `php artisan queue:work --once` against a guest no-payment booking | `app/Notifications/BookingRecipient.php`, `app/Services/NotificationService.php`, `app/Channels/SmsChannel.php`, notification channel edits |
| 2 | Webhook/event integration | PR 2 | `php artisan test tests/Unit/ProcessWebhookTest.php tests/Feature/NotificationDispatchTest.php tests/Feature/SendRemindersTest.php` | Stripe payment success webhook fixture for a guest booking | `app/Jobs/ProcessWebhook.php` plus event/reminder/cancel/reschedule tests |

## Phase 1: RED Recipient and Channel Tests

- [x] 1.1 In `tests/Unit/NotificationServiceTest.php`, replace wrong guest `assertNothingSent` with guest email, SMS, both-with-missing-phone, and no-usable-contact scenarios.
- [x] 1.2 In `tests/Unit/NotificationServiceTest.php`, add registered-client regression cases for email, SMS, and both preferences.

## Phase 2: GREEN Recipient and Channel Foundation

- [x] 2.1 Create `app/Notifications/BookingRecipient.php` with `Notifiable`, tenant, email, phone, preference, `routeNotificationForMail()`, and `routeNotificationForSms()`.
- [x] 2.2 Modify `app/Services/NotificationService.php` to resolve `User|BookingRecipient|null` and filter unavailable guest channels without blocking workflows.
- [x] 2.3 Modify `app/Channels/SmsChannel.php` to support generic notifiables using SMS routes/fallback phone and tenant config.
- [x] 2.4 Loosen notifiable assumptions in `app/Notifications/BookingConfirmed.php`, `BookingReminder.php`, `BookingCancelled.php`, and `BookingRescheduled.php`.

## Surgical Remediation: Slice 1 Blockers

- [x] R1 Invalid `notification_channel` values fail closed in notification routing and are rejected/normalized during booking confirmation.
- [x] R2 `SmsChannel` has explicit coverage for generic notifiables using `routeNotificationForSms()`.

## Phase 3: RED Event and Webhook Tests

- [x] 3.1 In `tests/Unit/ProcessWebhookTest.php`, assert paid guest booking sends no confirmation before webhook, sends one after success, and duplicate success sends none.
- [x] 3.2 In `tests/Feature/NotificationDispatchTest.php`, add guest cancellation, cancellation-without-recipient success, guest reschedule, and no-recipient reschedule integrity scenarios.
- [x] 3.3 In `tests/Feature/SendRemindersTest.php`, add guest reminder to `client_email` and missing selected `client_phone` continues scheduler scenarios.

## Phase 4: GREEN Event and Webhook Integration

- [x] 4.1 Modify `app/Jobs/ProcessWebhook.php` to dispatch `SendBookingNotification(..., 'confirmed')` only after unpaid guest booking transitions to paid/partial confirmed.
- [x] 4.2 Preserve duplicate webhook idempotency through the existing paid/partial early return in `app/Jobs/ProcessWebhook.php`.
- [x] 4.3 Verify `SendBookingNotification` paths still cover confirmation, reminder, cancellation, and reschedule through `NotificationService`.

## Phase 5: Verification and Refactor

- [x] 5.1 Run `php artisan test tests/Unit/NotificationServiceTest.php tests/Unit/ProcessWebhookTest.php tests/Feature/NotificationDispatchTest.php tests/Feature/SendRemindersTest.php`.
- [x] 5.2 Run `php artisan test` and `./vendor/bin/pint --dirty`; refactor only duplication introduced in touched notification routing code.

## Surgical Remediation: Slice 2 Blockers

- [x] R3 Make guest payment success transition plus confirmation enqueue retry-safe when queue dispatch fails.
- [x] R4 Make duplicate webhook idempotency atomic enough through a conditional payment-status transition guard.
- [x] R5 Cover already `partial` guest booking duplicate webhook behavior without duplicate confirmation dispatch.
- [x] R6 Add exhausted `SendBookingNotification` failure logging with safe booking/tenant/event/channel/exception context.
- [x] R7 Remove raw exception messages from exhausted notification failure logs and prove provider PII/secrets are not logged.
- [x] R8 Replace raw `ProcessWebhook` Stripe event retrieval exception logging with safe structured context and prove email/phone/secret-like exception text is not logged.
