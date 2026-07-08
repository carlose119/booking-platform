# Tasks: Stripe Integration

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 550–650 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 (Stripe core) → PR 2 (Booking integration + tests) |
| Delivery strategy | ask-on-risk |
| Chain strategy | stacked-to-main |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Stripe core infrastructure (migration, model, service, webhooks, scheduled command) | PR 1 | base: main; ~380 lines; standalone deployable with nopayment default |
| 2 | Booking flow integration + Filament form + tests | PR 2 | base: main; ~200 lines; depends on PR 1 being merged |

---

## Phase 1: Foundation / Infrastructure

- [x] 1.1 Run `composer require stripe/stripe-php` to add the Stripe SDK dependency
- [x] 1.2 Create `database/migrations/2026_07_04_000002_add_payment_config_to_tenants.php` — add columns: `payment_policy` (enum: 100upfront, fraction, nopayment; default nopayment), `deposit_percentage` (nullable unsigned tinyInteger), `refund_window_hours` (unsigned smallInteger, default 24), `stripe_api_key` (text, nullable), `stripe_webhook_secret` (text, nullable)
- [x] 1.3 Update `app/Models/Tenant.php` — add new fields to `$fillable`, add encrypted casts for `stripe_api_key` and `stripe_webhook_secret`, add `$casts` method
- [x] 1.4 Create `app/Services/StripeService.php` — inject `string|StripeClient $apiKeyOrClient` in constructor, implement `createPaymentIntent()`, `createRefund()`, `retrieveEvent()`
- [x] 1.5 Create `app/Services/DTOs/PaymentIntentResult.php` — readonly class with `id`, `clientSecret`, `amount`, `status`
- [x] 1.6 Create `app/Services/DTOs/RefundResult.php` — readonly class with `id`, `status`, `amount`

## Phase 2: Webhook & Job Infrastructure

- [x] 2.1 Create `app/Http/Controllers/WebhookController.php` — accept POST `{tenant}`, resolve tenant, use `stripe_webhook_secret` to verify signature via `Stripe\Webhook::constructEvent()`, dispatch `ProcessWebhook` job, return 200 on success / 400 on invalid signature
- [x] 2.2 Create `app/Jobs/ProcessWebhook.php` — accept `$eventId` + `$tenantId`, lookup booking by `stripe_payment_intent_id`, on `payment_intent.succeeded` update `payment_status=paid` (or `partial` for fraction) and `status=confirmed`, on `payment_intent.payment_failed` log failure, implement idempotency guard (skip if already paid)
- [x] 2.3 Create `app/Console/Commands/ProcessAutoRefunds.php` — artisan command `booking:auto-refund`, find cancelled bookings within `refund_window_hours` with `payment_status=paid` and non-null `stripe_payment_intent_id`, call `StripeService::createRefund()` per booking, update `payment_status=refunded`
- [x] 2.4 Add route `POST /webhooks/stripe/{tenant}` to `routes/web.php` (exclude from CSRF), add schedule `Schedule::command('booking:auto-refund')->hourly()` to `routes/console.php`

## Phase 3: Booking Flow Integration

- [x] 3.1 Modify `app/Services/BookingService.php` — branch `confirmBooking()` by payment_policy; extend hold TTL when payment required; add `calculatePaymentAmount()` helper
- [x] 3.2 Update `app/Filament/Resources/TenantResource.php` — add payment configuration fields (payment_policy select, deposit_percentage, refund_window_hours, stripe_api_key, stripe_webhook_secret)
- [x] 3.3 Update `app/Livewire/BookingCalendar.php` — add payment step (step 3), shift confirmation to step 4, add StripeService integration, add `tenantPaymentPolicy` and `requiresPayment` properties
- [x] 3.4 Update `resources/views/livewire/booking-calendar.blade.php` — add Stripe Elements payment step view, dynamic step indicator, payment error handling

## Phase 4: Testing

- [x] 4.1 Write `tests/Unit/StripeServiceTest.php` — 3 tests: createPaymentIntent DTO, createRefund DTO, createRefund with partial amount
- [x] 4.2 Write `tests/Unit/ProcessWebhookTest.php` — 4 tests: payment succeeded, payment failed, idempotency, unknown booking
- [x] 4.3 Write `tests/Unit/ProcessAutoRefundsTest.php` — 3 tests: eligible refund, outside window, already refunded
- [x] 4.4 Write `tests/Feature/WebhookControllerTest.php` — 4 tests: valid signature, invalid signature, missing secret, unknown tenant
- [x] 4.5 Write `tests/Feature/BookingWithPaymentTest.php` — 6 tests: nopayment flow, 100upfront flow, fraction flow, hold TTL extended/standard, payment amount calculation
- [x] 4.6 Update existing `tests/Unit/BookingServiceTest.php` — updated for nopayment behavior, added payment amount tests, added extended TTL test

## Phase 5: Cleanup

- [x] 5.1 Run full test suite: `php artisan test` — 57 tests passing, 0 failures
- [x] 5.2 Verify encrypted cast on `stripe_api_key` — confirmed in Tenant model
- [x] 5.3 Verify no regression — all existing tests pass with updated expectations
