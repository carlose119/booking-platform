# Apply Progress: Guest Notification Delivery

## Slice 1 — Recipient/Channel Foundation

**Status**: Complete  
**Mode**: Strict TDD  
**Delivery**: Two slices / stacked-to-main  
**Review budget impact**: 9 tracked source/test files changed plus untracked OpenSpec artifacts; authored code/test diff remains under the 400-line slice budget.

## Completed Tasks

- [x] 1.1 Replace wrong guest `assertNothingSent` with guest email, SMS, both-with-missing-phone, both-with-both-contacts, and no-usable-contact scenarios.
- [x] 1.2 Preserve registered-client regression coverage for email, SMS, and both preferences.
- [x] 2.1 Create `app/Notifications/BookingRecipient.php` with `Notifiable`, tenant, email, phone, preference, `routeNotificationForMail()`, and `routeNotificationForSms()`.
- [x] 2.2 Modify `NotificationService` to resolve `User|BookingRecipient|null` and filter unavailable guest channels without blocking workflows.
- [x] 2.3 Modify `SmsChannel` to support generic notifiables using SMS routes/fallback phone and tenant config.
- [x] 2.4 Loosen notifiable assumptions in booking notification classes.
- [x] R1 Reject/normalize invalid booking `notification_channel` at `BookingService::confirmBooking()` and fail closed in `NotificationService`/notification channel mapping.
- [x] R2 Add explicit `SmsChannel` coverage for generic notifiables that expose `routeNotificationForSms()`.

## TDD Cycle Evidence

| Task | Test File | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|------|-----------|-------|------------|-----|-------|-------------|----------|
| 1.1/2.1/2.2 | `tests/Unit/NotificationServiceTest.php` | Unit | ✅ `php artisan test tests/Unit/NotificationServiceTest.php tests/Unit/SmsChannelTest.php` → 11 passed | ✅ Guest tests failed on missing `BookingRecipient` | ✅ `php artisan test tests/Unit/NotificationServiceTest.php` → 12 passed | ✅ Email, SMS, both missing phone, both with both contacts, no usable contact | ✅ `vendor/bin/pint --dirty`; tests remained green |
| 1.2/2.2 | `tests/Unit/NotificationServiceTest.php` | Unit | ✅ Same safety net | ✅ Existing registered-client tests preserved while guest routing changed | ✅ Registered email/SMS/both cases stayed green | ✅ Email, SMS, both, unknown/default channels covered | ✅ `vendor/bin/pint --dirty`; tests remained green |
| 2.3 | `tests/Unit/SmsChannelTest.php`, `tests/Unit/NotificationServiceTest.php` | Unit | ✅ `tests/Unit/SmsChannelTest.php` baseline passed | ✅ Guest SMS route covered through `NotificationServiceTest` before generic channel support | ✅ `php artisan test tests/Unit/NotificationServiceTest.php tests/Unit/SmsChannelTest.php` → 15 passed | ✅ User fallback phone and guest route phone paths covered | ✅ `vendor/bin/pint --dirty`; tests remained green |
| 2.4 | `tests/Unit/NotificationServiceTest.php`, `tests/Feature/NotificationDispatchTest.php`, `tests/Unit/BookingServiceTest.php` | Unit/Feature | ✅ Existing notification tests baseline passed | ✅ Full suite exposed stale guest-skip expectations in cancellation/reschedule tests | ✅ Focused filters passed after updating expectations | ✅ Confirmation, cancellation, and reschedule job paths exercise guest recipient notifiables | ✅ Full suite and Pint green |
| R1 | `tests/Unit/NotificationServiceTest.php`, `tests/Unit/BookingServiceTest.php` | Unit | ✅ `php artisan test tests/Unit/NotificationServiceTest.php tests/Unit/SmsChannelTest.php tests/Unit/BookingServiceTest.php` → 40 passed, 124 assertions | ✅ Invalid registered/guest channel and booking confirmation validation tests failed before production changes | ✅ `php artisan test tests/Unit/NotificationServiceTest.php tests/Unit/SmsChannelTest.php tests/Unit/BookingServiceTest.php` → 45 passed, 132 assertions | ✅ Fail-closed registered/guest invalid values plus booking rejection and valid ` SMS ` normalization | ✅ `vendor/bin/pint --dirty --test` passed after formatting |
| R2 | `tests/Unit/SmsChannelTest.php` | Unit | ✅ Same safety net | ✅ Generic notifiable route test failed before route-aware/send hook support | ✅ `php artisan test tests/Unit/NotificationServiceTest.php tests/Unit/SmsChannelTest.php tests/Unit/BookingServiceTest.php` → 45 passed, 132 assertions | ✅ Route phone differs from fallback phone, proving `routeNotificationForSms()` wins | ✅ `vendor/bin/pint --dirty --test` passed after formatting |

## Work Unit Evidence

| Evidence | Required value |
|---|---|
| Focused test command and exact result | `php artisan test tests/Unit/NotificationServiceTest.php tests/Unit/SmsChannelTest.php tests/Unit/BookingConfirmationTest.php tests/Unit/BookingServiceTest.php tests/Feature/NotificationDispatchTest.php tests/Feature/SendRemindersTest.php` → 63 passed, 163 assertions |
| Runtime harness command/scenario and exact result | `php artisan test` used as the executable runtime harness for queued job/service paths → 237 passed, 838 assertions. `queue:work --once` was not run because the slice is proven through synchronous job handle/service tests and no external queue worker boundary was required. |
| Rollback boundary | Revert `app/Services/BookingService.php` channel validation, `app/Services/NotificationService.php` fail-closed channel mapping, notification `via()` fail-closed mapping edits, `app/Channels/SmsChannel.php` generic route/send-hook edits, and related notification/booking tests. |

## Verification

- RED: `php artisan test tests/Unit/NotificationServiceTest.php` → 3 failed, 8 passed; missing `App\Notifications\BookingRecipient`.
- GREEN: `php artisan test tests/Unit/NotificationServiceTest.php tests/Unit/SmsChannelTest.php` → 14 passed, 16 assertions.
- Focused suite: `php artisan test tests/Unit/NotificationServiceTest.php tests/Unit/SmsChannelTest.php tests/Unit/BookingConfirmationTest.php tests/Unit/BookingServiceTest.php tests/Feature/NotificationDispatchTest.php tests/Feature/SendRemindersTest.php` → 58 passed, 155 assertions.
- Full suite: `php artisan test` → 232 passed, 830 assertions.
- Style: `vendor/bin/pint --dirty --test` → PASS, 10 files.
- Whitespace: `git diff --check` → PASS.
- Remediation RED: `php artisan test tests/Unit/NotificationServiceTest.php tests/Unit/SmsChannelTest.php tests/Unit/BookingServiceTest.php` → 5 failed, 40 passed; invalid channels still fell back to mail, valid channel was not normalized, and generic SMS route was not covered.
- Remediation GREEN: `php artisan test tests/Unit/NotificationServiceTest.php tests/Unit/SmsChannelTest.php tests/Unit/BookingServiceTest.php` → 45 passed, 132 assertions.
- Remediation focused suite: `php artisan test tests/Unit/NotificationServiceTest.php tests/Unit/SmsChannelTest.php tests/Unit/BookingConfirmationTest.php tests/Unit/BookingServiceTest.php tests/Feature/NotificationDispatchTest.php tests/Feature/SendRemindersTest.php` → 63 passed, 163 assertions.
- Remediation full suite: `php artisan test` → 237 passed, 838 assertions.
- Remediation style: `vendor/bin/pint --dirty --test` → PASS, 12 files.
- Remediation whitespace: `git diff --check` → PASS.

## Remaining Tasks

- [ ] Slice 2 webhook/event integration: paid guest confirmation after payment-success webhook, duplicate webhook confirmation idempotency, reminder/cancellation/reschedule event expansion.
