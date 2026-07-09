# Apply Progress: Stripe Connect MVP

## Change

stripe-connect

## Mode

Strict TDD

## Delivery / PR Boundary

- Strategy: stacked-to-main
- Current work unit: PR 2 — payments/refunds/webhooks
- Boundary: starts after PR 1 data/resolver foundation; stops before Standard Connect onboarding controller/OAuth callback and TenantResource Connect UI/status actions.
- Review budget: payment/refund/webhook slice only. Stacked diff includes PR 1 foundation files in the working tree, but this batch focused on service wiring, booking snapshot usage, refunds, webhooks, routes/config needed for Connect webhook handling, and tests.

## Completed Tasks

- [x] 1.1 Create `database/migrations/*_add_stripe_connect_to_tenants_and_bookings.php` with tenant Connect fields and booking payment account snapshots.
- [x] 1.2 Update `app/Models/Tenant.php` fill/casts plus `usesDirectStripe()`, `usesStripeConnect()`, `hasActiveConnectCharges()`, and readiness helpers.
- [x] 1.3 Update `app/Models/Booking.php` fill/casts plus legacy direct fallback helpers for refund/webhook account context.
- [x] 1.4 Add `tests/Unit/TenantPaymentAccountTest.php` and `tests/Unit/BookingPaymentAccountSnapshotTest.php` for defaults, readiness, and legacy fallbacks.
- [x] 2.1 Create `app/Services/Stripe/StripeAccountContext.php` with mode, API key, connected account ID, webhook secret, readiness, and `stripeOptions()`.
- [x] 2.2 Create `app/Services/Stripe/StripeAccountResolver.php` for direct, Connect charge, refund-from-booking, and webhook tenant/account resolution.
- [x] 2.3 Modify `app/Services/StripeService.php` to pass optional Stripe request options for PaymentIntent, refund, and event retrieval.
- [x] 2.4 Add unit tests for resolver tenant isolation and `StripeService` direct vs `stripe_account` option passing.
- [x] 3.1 Update `app/Livewire/BookingCalendar.php` to resolve account, block unready Connect charges, and snapshot booking account context.
- [x] 3.2 Update `app/Console/Commands/ProcessAutoRefunds.php` to refund through the booking's original account context.
- [x] 3.3 Update `app/Http/Controllers/WebhookController.php` and `app/Jobs/ProcessWebhook.php` for direct and Connect webhook verification, idempotency, and scoped booking lookup.
- [x] 3.4 Add feature tests covering direct regression and Connect PaymentIntent, refund, webhook success/failure, invalid signature, and ambiguous account scenarios.

## TDD Cycle Evidence

| Task | Test File | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|------|-----------|-------|------------|-----|-------|-------------|----------|
| 1.1 | `tests/Unit/TenantPaymentAccountTest.php`, `tests/Unit/BookingPaymentAccountSnapshotTest.php` | Unit | ✅ 8/8 existing tests passed | ✅ Tests failed on missing helpers/resolver | ✅ Relevant tests passed | ✅ Defaults, direct, Connect, legacy fallback | ✅ Clean migration only |
| 1.2 | `tests/Unit/TenantPaymentAccountTest.php` | Unit | ✅ 8/8 existing tests passed | ✅ Tests failed on missing Tenant helpers | ✅ 4/4 passed | ✅ Direct ready, Connect ready, Connect unready | ✅ Constants/default attributes added |
| 1.3 | `tests/Unit/BookingPaymentAccountSnapshotTest.php` | Unit | ✅ 3/3 existing booking snapshot tests passed | ✅ Tests failed on missing Booking helpers | ✅ 2/2 passed | ✅ Connect snapshot and legacy direct fallback | ✅ Helper methods kept focused |
| 1.4 | `tests/Unit/TenantPaymentAccountTest.php`, `tests/Unit/BookingPaymentAccountSnapshotTest.php` | Unit | N/A (new tests) | ✅ Written before implementation | ✅ 6/6 passed | ✅ 6 behavior cases | ➖ None needed |
| 2.1 | `tests/Unit/StripeAccountResolverTest.php` | Unit | ✅ 5/5 existing StripeService tests passed | ✅ Tests failed because DTO/resolver did not exist | ✅ 4/4 resolver tests passed | ✅ Direct, Connect, refund snapshot, tenant lookup | ✅ DTO kept immutable/read-only |
| 2.2 | `tests/Unit/StripeAccountResolverTest.php` | Unit | ✅ 5/5 existing StripeService tests passed | ✅ Tests failed because resolver did not exist | ✅ 4/4 resolver tests passed | ✅ Direct, Connect, original snapshot, tenant isolation | ✅ Resolver centralizes account selection |
| 2.3 | `tests/Unit/StripeServiceTest.php` | Unit | ✅ 31/31 baseline PR 2 focused tests passed | ✅ Option-passing tests failed before `StripeService` accepted request options | ✅ 8/8 StripeService tests passed | ✅ PaymentIntent, refund, retrieve event; direct no-options regressions still pass | ✅ Conditional one-arg calls preserve direct mode |
| 2.4 | `tests/Unit/StripeServiceTest.php`, `tests/Unit/StripeAccountResolverTest.php` | Unit | ✅ 31/31 baseline PR 2 focused tests passed | ✅ Tests written for `stripe_account` option passing and resolver isolation | ✅ Focused suite passed | ✅ Direct and Connect paths covered | ✅ Ambiguous connected-account lookup rejects non-unique mapping |
| 3.1 | `tests/Feature/BookingWithPaymentTest.php` | Feature | ✅ 31/31 baseline PR 2 focused tests passed | ✅ Connect booking/unready tests failed before account resolution and snapshot wiring | ✅ 10/10 booking payment tests passed | ✅ Direct upfront/deposit regressions, ready Connect, unready Connect | ✅ Direct call shape preserved when Stripe options are empty |
| 3.2 | `tests/Unit/ProcessAutoRefundsTest.php` | Unit/Command | ✅ 31/31 baseline PR 2 focused tests passed | ✅ Connect refund test failed before refund context options were passed | ✅ 7/7 auto-refund tests passed | ✅ Direct paid/partial/idempotent/unpaid and Connect original snapshot | ✅ Command now resolves context per booking |
| 3.3 | `tests/Feature/WebhookControllerTest.php`, `tests/Unit/ProcessWebhookTest.php` | Feature + Unit/Job | ✅ 31/31 baseline PR 2 focused tests passed | ✅ Connect route/job tests failed before account dispatch/retrieve/scoped lookup | ✅ Webhook and job focused tests passed | ✅ Direct valid/invalid/unknown, Connect success, unknown account, ambiguous account, scoped booking lookup | ✅ Booking lookup centralized in job helper |
| 3.4 | `tests/Feature/BookingWithPaymentTest.php`, `tests/Unit/ProcessAutoRefundsTest.php`, `tests/Feature/WebhookControllerTest.php`, `tests/Unit/ProcessWebhookTest.php`, `tests/Unit/StripeServiceTest.php` | Feature + Unit | ✅ 31/31 baseline PR 2 focused tests passed | ✅ New regression and Connect tests written first | ✅ 40/40 focused tests passed, full suite passed | ✅ PaymentIntent options, refund options, direct regressions, Connect webhook unknown/ambiguous/scoped success | ✅ Pint clean |

## Test Summary

- Total tests written in PR 1: 10
- Total tests written in PR 2: 10
- Total focused tests passing for PR 2 run: 40 tests, 107 assertions
- Full suite: 181 passed, 587 assertions
- Layers used: Unit, Feature, Command-level Laravel tests
- Approval tests: None — changes are additive behavior wiring, with direct regressions preserved by existing tests.
- Pure functions/helpers created: `ProcessWebhook::bookingQuery()` helper; resolver ambiguity logic generalized.

## Verification Run

Focused PR 2 run:

`php artisan test tests/Unit/StripeServiceTest.php tests/Feature/BookingWithPaymentTest.php tests/Unit/ProcessAutoRefundsTest.php tests/Feature/WebhookControllerTest.php tests/Unit/ProcessWebhookTest.php tests/Unit/StripeAccountResolverTest.php`

Result: 40 passed, 107 assertions.

Formatting:

`vendor/bin/pint --dirty --test`

Result: PASS, 21 files.

Full suite:

`php artisan test`

Result: 181 passed, 587 assertions.

Remediation run after pre-commit blockers:

`php artisan test tests/Unit/BookingPaymentAccountSnapshotTest.php tests/Unit/StripeAccountResolverTest.php tests/Unit/ProcessWebhookTest.php tests/Unit/ProcessAutoRefundsTest.php tests/Feature/WebhookControllerTest.php tests/Unit/StripeServiceTest.php tests/Feature/BookingWithPaymentTest.php`

Result: 46 passed, 125 assertions.

`php artisan test`

Result: 184 passed, 598 assertions.

`vendor/bin/pint --dirty --test`

Result: PASS, 21 files.

## Remediation Progress

- [x] R1 Added stable Stripe refund idempotency keys for scheduled auto-refunds, derived from booking ID, refund purpose, and PaymentIntent ID; covered retry-safety behavior.
- [x] R2 Made queued Connect webhook event retrieval deterministic by using the webhook/job connected account ID instead of the tenant's mutable current Connect account; covered tenant-account drift before job execution.
- [x] R3 Changed legacy booking account fallback so null payment account snapshots remain direct and do not switch to Connect after tenant migration; covered migrated-tenant regression.
- [x] R4 Added nullable unique protection for tenant `stripe_connected_account_id` plus safe logging for missing, unknown, and ambiguous Connect webhook account routing failures.
- [x] R5 Made refund account resolution bypass mutable tenant Connect mode for legacy/null/direct booking snapshots by adding an explicit direct tenant context and using it from `forBookingRefund()`.
- [x] R6 Made direct webhook jobs carry explicit direct account mode from dispatch through processing so queued direct events do not switch to Connect event retrieval after tenant migration.

## Remediation TDD Cycle Evidence

| Task | Test File | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|------|-----------|-------|------------|-----|-------|-------------|----------|
| R1 Refund idempotency | `tests/Unit/ProcessAutoRefundsTest.php` | Unit/Command | ✅ Existing targeted tests run; new idempotency expectation failed before implementation | ✅ Written first | ✅ Targeted tests passed | ✅ Direct retry key plus existing direct/Connect refund paths | ✅ Kept command change surgical; reused Stripe request options |
| R2 Webhook account determinism | `tests/Unit/ProcessWebhookTest.php` | Unit/Job | ✅ Existing webhook tests run; drift test failed with current account | ✅ Written first | ✅ Targeted tests passed | ✅ Existing scoped lookup test plus tenant drift regression | ✅ Resolver provides explicit webhook context |
| R3 Legacy snapshot fallback | `tests/Unit/BookingPaymentAccountSnapshotTest.php` | Unit | ✅ Existing snapshot tests run; migrated-tenant legacy fallback failed | ✅ Written first | ✅ Targeted tests passed | ✅ Direct tenant fallback plus migrated Connect tenant fallback | ✅ Simplified `Booking::resolvedPaymentAccountMode()` to snapshot-or-direct |
| R4 Connect ambiguity/observability/uniqueness | `tests/Feature/WebhookControllerTest.php` | Feature | ✅ Existing webhook controller tests run; logging expectations failed before implementation | ✅ Written first | ✅ Targeted tests passed | ✅ Unknown account and ambiguous account logging paths | ✅ Kept resolver compatibility while exposing count for observability |
| R5 Legacy direct/null refund context | `tests/Unit/StripeAccountResolverTest.php`, `tests/Unit/ProcessAutoRefundsTest.php` | Unit/Command | ✅ Existing R3 tests showed model fallback only; new resolver/command tests protect Stripe options | ✅ Tests written before implementation | ✅ Focused tests passed | ✅ Resolver direct context plus command-level no-`stripe_account` assertion | ✅ Added `forTenantDirect()` to make direct context explicit and reusable |
| R6 Direct queued webhook context | `tests/Unit/ProcessWebhookTest.php`, `tests/Feature/WebhookControllerTest.php` | Unit/Feature | ✅ Existing R2 tests protected Connect drift only; new direct drift test protects direct dispatch | ✅ Tests written before implementation | ✅ Focused tests passed | ✅ Controller dispatch assertion plus tenant migration before job handle | ✅ Added explicit queued `accountMode` and direct booking-snapshot filter |

## Second Surgical Remediation Verification

Focused blocker run:

`php artisan test tests/Unit/StripeAccountResolverTest.php tests/Unit/ProcessAutoRefundsTest.php tests/Unit/ProcessWebhookTest.php tests/Feature/WebhookControllerTest.php`

Result: 28 passed, 77 assertions.

Expanded focused Stripe/booking run:

`php artisan test tests/Unit/BookingPaymentAccountSnapshotTest.php tests/Unit/StripeAccountResolverTest.php tests/Unit/ProcessAutoRefundsTest.php tests/Unit/ProcessWebhookTest.php tests/Feature/WebhookControllerTest.php tests/Unit/StripeServiceTest.php tests/Feature/BookingWithPaymentTest.php`

Result: 49 passed, 134 assertions.

Full suite:

`php artisan test`

Result: 187 passed, 607 assertions.

Formatting:

`vendor/bin/pint --dirty --test`

Result: PASS, 21 files.

Whitespace:

`git diff --check`

Result: PASS.

## Deviations

- Added the Connect webhook route and minimal Stripe platform config keys in PR 2 because webhook handling cannot function without them. Onboarding/OAuth routes and TenantResource UI/status remain deferred to PR 3.
- No platform fees, FX conversion, onboarding controller, OAuth callback, or TenantResource Connect UI were implemented.

## Remaining Tasks

- [ ] 4.1 Complete PR 3 config/routes for publishable key/client ID, OAuth start/callback, and any remaining onboarding route wiring.
- [ ] 4.2 Create `app/Http/Controllers/StripeConnectController.php` for Standard OAuth start/callback, state validation, tenant-only persistence, and non-admin denial.
- [ ] 4.3 Update `app/Filament/Resources/TenantResource.php` with payment mode fields, conditional direct credentials, Connect status display, and onboarding action/link.
- [ ] 4.4 Add feature/Filament tests for onboarding authorization, state mismatch, TenantResource validation, status display, and unsupported currency rejection.
