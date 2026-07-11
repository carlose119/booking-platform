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

## Final Verification Remediation — Cancellation Refund Copy

**Status**: Complete
**Mode**: Strict TDD
**Delivery**: Surgical stacked-to-main remediation
**Work unit boundary**: `BookingCancelled` email/SMS cancellation copy and its feature coverage only.
**Review budget impact**: Two production copy helpers and three focused tests; well below the 400-line guideline.

### Completed Tasks

- [x] R9 Add paid and unpaid cancellation refund copy for guest email/SMS notifications using payment snapshots and existing currency formatting.

### TDD Cycle Evidence

| Task | Test File | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|------|-----------|-------|------------|-----|-------|-------------|----------|
| R9 | `tests/Feature/NotificationDispatchTest.php` | Feature notification rendering | ✅ `php artisan test tests/Feature/NotificationDispatchTest.php` → 15 passed, 64 assertions | ✅ Paid guest email/SMS snapshot amount + 5-10 business-day copy and unpaid guest no-refund copy: `--filter=cancelled_notification` → 2 failed, 2 passed, 7 assertions | ✅ `--filter=cancelled_notification` → 5 passed, 12 assertions | ✅ Paid guest with `both` channels, unpaid guest with `both` channels, and a registered paid booking without a snapshot that retains generic non-invented copy | ✅ Extracted refund eligibility, snapshot formatting, and channel-specific copy helpers; focused tests remained green |

### Work Unit Evidence

| Evidence | Required value |
|---|---|
| Focused test command and exact result | `php artisan test tests/Feature/NotificationDispatchTest.php --filter=cancelled_notification` → 5 passed, 12 assertions |
| Runtime harness command/scenario and exact result | `php artisan test tests/Unit/NotificationServiceTest.php tests/Feature/NotificationDispatchTest.php tests/Feature/SendRemindersTest.php tests/Unit/ProcessWebhookTest.php tests/Feature/BookingWithPaymentTest.php tests/Feature/WebhookControllerTest.php` → 70 passed, 202 assertions; direct job/service notification rendering covers guest and registered recipients through both mail and SMS representations |
| Rollback boundary | Revert `app/Notifications/BookingCancelled.php` refund-copy helpers and the three cancellation-copy tests in `tests/Feature/NotificationDispatchTest.php`; routing, refund initiation, and unrelated notification behavior remain unchanged |

### Verification

- Safety baseline: `php artisan test tests/Feature/NotificationDispatchTest.php` → 15 passed, 64 assertions.
- RED: `php artisan test tests/Feature/NotificationDispatchTest.php --filter=cancelled_notification` → 2 failed, 2 passed, 7 assertions; paid copy lacked snapshot amount/time and unpaid copy lacked an explicit no-refund explanation.
- GREEN/triangulation: `php artisan test tests/Feature/NotificationDispatchTest.php --filter=cancelled_notification` → 5 passed, 12 assertions.
- Relevant notification suite: `php artisan test tests/Unit/NotificationServiceTest.php tests/Feature/NotificationDispatchTest.php tests/Feature/SendRemindersTest.php tests/Unit/ProcessWebhookTest.php tests/Feature/BookingWithPaymentTest.php tests/Feature/WebhookControllerTest.php` → 70 passed, 202 assertions.
- Full suite: `composer test` → 253 passed, 921 assertions.
- Remediation RED: `php artisan test tests/Unit/NotificationServiceTest.php tests/Unit/SmsChannelTest.php tests/Unit/BookingServiceTest.php` → 5 failed, 40 passed; invalid channels still fell back to mail, valid channel was not normalized, and generic SMS route was not covered.
- Remediation GREEN: `php artisan test tests/Unit/NotificationServiceTest.php tests/Unit/SmsChannelTest.php tests/Unit/BookingServiceTest.php` → 45 passed, 132 assertions.
- Remediation focused suite: `php artisan test tests/Unit/NotificationServiceTest.php tests/Unit/SmsChannelTest.php tests/Unit/BookingConfirmationTest.php tests/Unit/BookingServiceTest.php tests/Feature/NotificationDispatchTest.php tests/Feature/SendRemindersTest.php` → 63 passed, 163 assertions.
- Remediation full suite: `php artisan test` → 237 passed, 838 assertions.
- Remediation style: `vendor/bin/pint --dirty --test` → PASS, 12 files.
- Remediation whitespace: `git diff --check` → PASS.

## Remaining Tasks

- [x] None for `guest-notification-delivery` apply. Ready for SDD verify; do not archive until verify passes.

## Slice 2 — Webhook/Event Integration

**Status**: Complete with surgical remediation
**Mode**: Strict TDD
**Delivery**: Two slices / stacked-to-main
**Current slice boundary**: Slice 2 starts from committed Slice 1 `d33e224`; current working tree includes webhook/event integration tests, retry-safe payment/notification remediation, exhausted-notification failure logging, and safe Stripe event retrieval failure logging in `ProcessWebhook`. `.atl/*` registry changes remain outside this slice.
**Review budget impact**: Existing Slice 2 diff was already above the nominal 400-line guideline from preserved prior work; this pass added only a surgical `ProcessWebhook` log-context replacement and two focused retrieval-failure tests.

### Completed Tasks

- [x] 3.1 In `tests/Unit/ProcessWebhookTest.php`, assert paid guest booking sends no confirmation before webhook, sends one after success, and duplicate success sends none.
- [x] 3.2 In `tests/Feature/NotificationDispatchTest.php`, add guest cancellation, cancellation-without-recipient success, guest reschedule, and no-recipient reschedule integrity scenarios.
- [x] 3.3 In `tests/Feature/SendRemindersTest.php`, add guest reminder to `client_email` and missing selected `client_phone` continues scheduler scenarios.
- [x] 4.1 Modify `app/Jobs/ProcessWebhook.php` to dispatch `SendBookingNotification(..., 'confirmed')` only after unpaid guest booking transitions to paid/partial confirmed.
- [x] 4.2 Preserve duplicate webhook idempotency via atomic conditional transition guard plus existing paid/partial early return.
- [x] 4.3 Verify `SendBookingNotification` paths still cover confirmation, reminder, cancellation, and reschedule through `NotificationService`.
- [x] 5.1 Run focused notification/webhook event suite.
- [x] 5.2 Run full suite, Pint, and whitespace checks.
- [x] R3 Make guest payment success transition plus confirmation enqueue retry-safe when queue dispatch fails.
- [x] R4 Make duplicate webhook idempotency atomic enough through a conditional payment-status transition guard.
- [x] R5 Cover already `partial` guest booking duplicate webhook behavior without duplicate confirmation dispatch.
- [x] R6 Add exhausted `SendBookingNotification` failure logging with safe booking/tenant/event/channel/exception class and generic failure-code context.
- [x] R7 Remove raw exception messages from exhausted notification failure logs and prove provider PII/secrets are not logged.
- [x] R8 Replace raw `ProcessWebhook` Stripe event retrieval exception logging with safe event/tenant/account/exception-class/failure-code context and prove email/phone/secret-like exception text is not logged.

### TDD Cycle Evidence

| Task | Test File | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|------|-----------|-------|------------|-----|-------|-------------|----------|
| 3.1/4.1/4.2 | `tests/Unit/ProcessWebhookTest.php` | Unit/integration job | ✅ Baseline before remediation: `php artisan test tests/Unit/ProcessWebhookTest.php` → 10 passed, 32 assertions | ✅ Existing Slice 2 RED tests covered guest success and paid duplicate behavior before production dispatch | ✅ Previous GREEN: `php artisan test tests/Unit/ProcessWebhookTest.php` → 10 passed, 32 assertions | ✅ 100% upfront guest dispatch, fraction/partial guest dispatch, paid duplicate no dispatch, registered-client no dispatch | ✅ No broad refactor; remediation kept within `ProcessWebhook` |
| R3 | `tests/Unit/ProcessWebhookTest.php` | Unit/integration job | ✅ `php artisan test tests/Unit/ProcessWebhookTest.php` → 10 passed, 32 assertions before new remediation tests | ✅ Enqueue failure test failed because booking stayed `paid` after queue exception | ✅ `php artisan test tests/Unit/ProcessWebhookTest.php` → 13 passed, 44 assertions | ✅ Failure path rolls back to unpaid/pending; retry succeeds and dispatches one confirmation | ✅ Wrapped conditional transition and dispatch in `DB::transaction()` |
| R4 | `tests/Unit/ProcessWebhookTest.php` | Unit guard | ✅ Same ProcessWebhook baseline | ✅ Transition guard test failed because guard method did not exist | ✅ `php artisan test tests/Unit/ProcessWebhookTest.php` → 13 passed, 44 assertions | ✅ Two stale booking instances: first transition true, second false | ✅ Extracted `confirmPendingBookingPayment()` conditional update guard |
| R5 | `tests/Unit/ProcessWebhookTest.php` | Unit/integration job | ✅ Same ProcessWebhook baseline | ✅ Partial duplicate coverage was missing before test was added | ✅ `php artisan test tests/Unit/ProcessWebhookTest.php` → 13 passed, 44 assertions | ✅ Paid duplicate and partial duplicate guest bookings both dispatch nothing | ✅ No production refactor needed beyond R3/R4 guard |
| R6 | `tests/Feature/NotificationDispatchTest.php` | Feature/job failure hook | ✅ Historical baseline before adding failure hook: `php artisan test tests/Feature/NotificationDispatchTest.php` → 13 passed, 37 assertions | ✅ Historical RED failed because `SendBookingNotification::failed()` was undefined, then triangulation RED failed on missing `notification_channel` | ✅ Historical GREEN: `php artisan test tests/Feature/NotificationDispatchTest.php` → 15 passed, 55 assertions | ✅ Confirmation/email RuntimeException and cancellation/sms LogicException both log safe context without email/phone fields | ✅ Minimal `failed(Throwable)` hook only; retry `$tries`/`$backoff` unchanged |
| R7 | `tests/Feature/NotificationDispatchTest.php` | Feature/job failure hook | ✅ `php artisan test tests/Feature/NotificationDispatchTest.php --filter=failed_job_logs` → 2 passed, 18 assertions | ✅ `php artisan test tests/Feature/NotificationDispatchTest.php --filter=failed_job_logs` → 2 failed, 12 assertions; missing `failure_code` and existing context still exposed raw `exception_message` | ✅ `php artisan test tests/Feature/NotificationDispatchTest.php --filter=failed_job_logs` → 2 passed, 27 assertions | ✅ Email-like, phone-like, and secret-like provider exception messages are absent from encoded log context for email and SMS failures; `exception_message` key is absent | ✅ Reused encoded context per assertion block; production hook still only emits safe IDs/event/channel/exception class/failure code |
| R8 | `tests/Unit/ProcessWebhookTest.php` | Unit/integration job | ✅ `php artisan test tests/Unit/ProcessWebhookTest.php` → 13 passed, 44 assertions | ✅ `php artisan test tests/Unit/ProcessWebhookTest.php --filter=stripe_event_retrieval_failure` → 2 failed; existing `ProcessWebhook` logged raw retrieval exception text instead of safe structured context | ✅ `php artisan test tests/Unit/ProcessWebhookTest.php --filter=stripe_event_retrieval_failure` → 2 passed, 2 assertions; `php artisan test tests/Unit/ProcessWebhookTest.php` → 15 passed, 46 assertions | ✅ Direct tenant failure and connected-account failure both prove raw email/phone/secret-like/provider-body text is absent while safe event/tenant/account/exception/failure-code context is logged | ✅ Minimal replacement of the retrieval failure `Log::error()` call; retries and exception rethrow behavior unchanged |
| 3.2/4.3 | `tests/Feature/NotificationDispatchTest.php` | Feature/job-service | ✅ Focused feature suite stayed green | ✅ Guest cancellation/reschedule expectations were added before preserving event/service integration | ✅ Focused suite passed | ✅ Guest cancellation delivery, no-recipient cancellation audit, guest reschedule delivery, no-recipient reschedule integrity | ✅ No production refactor needed |
| 3.3/4.3 | `tests/Feature/SendRemindersTest.php` | Feature/command+job-service | ✅ Focused feature suite stayed green | ✅ Guest reminder/missing-phone scheduler scenarios were added before finalizing reminder evidence | ✅ Focused suite passed | ✅ Missing selected phone skips notification while scheduler continues and email guest reminder is delivered via the second booking | ✅ No production refactor needed |

### Work Unit Evidence

| Evidence | Required value |
|---|---|
| Focused test command and exact result | `php artisan test tests/Unit/ProcessWebhookTest.php --filter=stripe_event_retrieval_failure` → 2 passed, 2 assertions; `php artisan test tests/Unit/ProcessWebhookTest.php` → 15 passed, 46 assertions |
| Runtime harness command/scenario and exact result | Notification/webhook focused runtime: `php artisan test tests/Unit/ProcessWebhookTest.php tests/Feature/NotificationDispatchTest.php tests/Feature/SendRemindersTest.php tests/Feature/BookingWithPaymentTest.php tests/Feature/WebhookControllerTest.php` → 54 passed, 184 assertions. Full executable harness: `php artisan test` → 251 passed, 917 assertions. |
| Rollback boundary | Revert the `ProcessWebhook` retrieval-failure `Log::error()` structured context replacement plus the two retrieval-failure tests/import in `tests/Unit/ProcessWebhookTest.php`; existing Slice 2 webhook/reminder/notification failure work remains independently revertible as previously documented. |

### Verification

- Safety baseline: `php artisan test tests/Feature/NotificationDispatchTest.php` → 13 passed, 37 assertions.
- R6 RED: `php artisan test tests/Feature/NotificationDispatchTest.php --filter=failed_job_logs_safe_context` → 1 failed; `SendBookingNotification::failed()` undefined.
- R6 triangulation RED: `php artisan test tests/Feature/NotificationDispatchTest.php --filter=failed_job_logs` → 2 failed; missing `notification_channel` in log context.
- R6 GREEN/focused SendBookingNotification suite: `php artisan test tests/Feature/NotificationDispatchTest.php` → 15 passed, 55 assertions.
- R7 safety baseline: `php artisan test tests/Feature/NotificationDispatchTest.php --filter=failed_job_logs` → 2 passed, 18 assertions.
- R7 RED: `php artisan test tests/Feature/NotificationDispatchTest.php --filter=failed_job_logs` → 2 failed, 12 assertions; missing `failure_code` and still relying on raw `exception_message` context.
- R7 GREEN/refactor: `php artisan test tests/Feature/NotificationDispatchTest.php --filter=failed_job_logs` → 2 passed, 27 assertions.
- R7 focused SendBookingNotification suite: `php artisan test tests/Feature/NotificationDispatchTest.php` → 15 passed, 64 assertions.
- R8 safety baseline: `php artisan test tests/Unit/ProcessWebhookTest.php` → 13 passed, 44 assertions.
- R8 RED: `php artisan test tests/Unit/ProcessWebhookTest.php --filter=stripe_event_retrieval_failure` → 2 failed; existing retrieval failure log used the raw exception message instead of safe structured context.
- R8 GREEN/refactor: `php artisan test tests/Unit/ProcessWebhookTest.php --filter=stripe_event_retrieval_failure` → 2 passed, 2 assertions.
- R8 focused ProcessWebhook suite: `php artisan test tests/Unit/ProcessWebhookTest.php` → 15 passed, 46 assertions.
- Notification/webhook focused suite: `php artisan test tests/Unit/ProcessWebhookTest.php tests/Feature/NotificationDispatchTest.php tests/Feature/SendRemindersTest.php tests/Feature/BookingWithPaymentTest.php tests/Feature/WebhookControllerTest.php` → 54 passed, 184 assertions.
- Full suite: `php artisan test` → 251 passed, 917 assertions.
- Style: `vendor/bin/pint --dirty --test` → PASS, 5 files.
- Whitespace: `git diff --check` → PASS.
