# Tasks: Stripe Connect MVP

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 900-1300 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 data/resolver → PR 2 payments/refunds/webhooks → PR 3 onboarding/admin UI |
| Delivery strategy | stacked-to-main |
| Chain strategy | stacked-to-main |

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Schema, model helpers, resolver DTO/service | PR 1 | Base main; include resolver/model tests. |
| 2 | PaymentIntent, refund, webhook account context | PR 2 | Depends on PR 1; include direct regressions and Connect context tests. |
| 3 | Standard Connect onboarding and TenantResource UI | PR 3 | Depends on PR 1; include auth, callback, and Filament tests. |

## Phase 1: Foundation / Data Model

- [x] 1.1 Create `database/migrations/*_add_stripe_connect_to_tenants_and_bookings.php` with tenant Connect fields and booking payment account snapshots.
- [x] 1.2 Update `app/Models/Tenant.php` fill/casts plus `usesDirectStripe()`, `usesStripeConnect()`, `hasActiveConnectCharges()`, and readiness helpers.
- [x] 1.3 Update `app/Models/Booking.php` fill/casts plus legacy direct fallback helpers for refund/webhook account context.
- [x] 1.4 Add `tests/Unit/TenantPaymentAccountTest.php` and `tests/Unit/BookingPaymentAccountSnapshotTest.php` for defaults, readiness, and legacy fallbacks.

## Phase 2: Account Resolution / Stripe Service

- [x] 2.1 Create `app/Services/Stripe/StripeAccountContext.php` with mode, API key, connected account ID, webhook secret, readiness, and `stripeOptions()`.
- [x] 2.2 Create `app/Services/Stripe/StripeAccountResolver.php` for direct, Connect charge, refund-from-booking, and webhook tenant/account resolution.
- [x] 2.3 Modify `app/Services/StripeService.php` to pass optional Stripe request options for PaymentIntent, refund, and event retrieval.
- [x] 2.4 Add unit tests for resolver tenant isolation and `StripeService` direct vs `stripe_account` option passing.

## Phase 3: Payment / Refund / Webhook Wiring

- [x] 3.1 Update `app/Livewire/BookingCalendar.php` to resolve account, block unready Connect charges, and snapshot booking account context.
- [x] 3.2 Update `app/Console/Commands/ProcessAutoRefunds.php` to refund through the booking's original account context.
- [x] 3.3 Update `app/Http/Controllers/WebhookController.php` and `app/Jobs/ProcessWebhook.php` for direct and Connect webhook verification, idempotency, and scoped booking lookup.
- [x] 3.4 Add feature tests covering direct regression and Connect PaymentIntent, refund, webhook success/failure, invalid signature, and ambiguous account scenarios.

## Phase 4: Onboarding / Admin UI

- [x] 4.1 Update `config/services.php` and `routes/web.php` for platform Stripe keys, OAuth start/callback, and Connect webhook routes.
- [x] 4.2 Create `app/Http/Controllers/StripeConnectController.php` for Standard OAuth start/callback, state validation, tenant-only persistence, and non-admin denial.
- [x] 4.3 Update `app/Filament/Resources/TenantResource.php` with payment mode fields, conditional direct credentials, Connect status display, and onboarding action/link.
- [x] 4.4 Add feature/Filament tests for onboarding authorization, state mismatch, TenantResource validation, status display, and unsupported currency rejection.

## Post-PR3 Resilience Remediation

- [x] R11 Require `STRIPE_CONNECT_WEBHOOK_SECRET` for Connect onboarding readiness in dashboard visibility and OAuth start, and log safe operational context when Connect webhooks arrive without a configured secret.
