# Apply Progress: Stripe Connect MVP

## Change

stripe-connect

## Mode

Strict TDD

## Delivery / PR Boundary

- Strategy: stacked-to-main
- Current work unit: PR 3 — onboarding/admin UI
- Boundary: starts after committed PR 1+PR 2 payment routing (`3acb407 feat(stripe): add connect payment routing`); completes Standard Connect OAuth routes/controller, tenant dashboard onboarding link, Super Admin TenantResource payment-mode/status fields, and PR3 tests. No archive/commit performed.
- Review budget: PR 3 UI/onboarding slice only. Preserved existing PR 1+PR 2 payment routing except for additive OAuth helpers on `StripeService` required by onboarding.

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
- [x] 4.1 Update `config/services.php` and `routes/web.php` for platform Stripe keys, OAuth start/callback, and Connect webhook routes.
- [x] 4.2 Create `app/Http/Controllers/StripeConnectController.php` for Standard OAuth start/callback, state validation, tenant-only persistence, and non-admin denial.
- [x] 4.3 Update `app/Filament/Resources/TenantResource.php` with payment mode fields, conditional direct credentials, Connect status display, and onboarding action/link.
- [x] 4.4 Add feature/Filament tests for onboarding authorization, state mismatch, TenantResource validation, status display, and unsupported currency rejection.

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
| 4.1 Config/routes | `tests/Feature/StripeConnectControllerTest.php` | Feature | ✅ 17 existing Filament/Tenant/Stripe tests passed before edits | ✅ Route tests failed on missing `stripe.connect.start` / `stripe.connect.callback` | ✅ PR3 focused tests passed | ✅ OAuth start and callback routes plus existing Connect webhook route coexistence | ✅ Kept route additions isolated under auth middleware |
| 4.2 Standard OAuth controller | `tests/Feature/StripeConnectControllerTest.php` | Feature | ✅ 17 existing Filament/Tenant/Stripe tests passed before edits | ✅ Authorization/state/callback tests written before controller existed | ✅ PR3 focused tests passed | ✅ Business admin start, employee denial, state mismatch, callback persistence | ✅ Controller persists only authenticated user's tenant and clears OAuth state |
| 4.3 TenantResource / tenant admin UI | `tests/Feature/Filament/MultiCurrencyResourceTest.php`, `tests/Feature/Filament/DashboardPageTest.php` | Feature/Filament | ✅ Existing Filament resource/dashboard tests passed before edits | ✅ Helper/status/dashboard link tests failed before UI helpers/link existed | ✅ PR3 focused tests passed | ✅ Direct paid credential requirement, Connect mode exemption, ready/incomplete statuses, dashboard onboarding link | ✅ Extracted static helpers for validation/status labels |
| 4.4 PR3 coverage | `tests/Feature/StripeConnectControllerTest.php`, `tests/Feature/Filament/MultiCurrencyResourceTest.php`, `tests/Feature/Filament/DashboardPageTest.php` | Feature/Filament | ✅ 17 existing tests passed before PR3 edits | ✅ New onboarding/auth/state/resource/dashboard tests written first | ✅ 20 PR3 tests passed; full suite passed | ✅ Authorization, state validation, tenant-only persistence, TenantResource validation/status, onboarding link, existing unsupported currency rejection | ✅ Pint clean |

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

## PR 3 Surgical Remediation

- [x] R7 Protected Stripe Connect ownership/status fields from admin or mass-assignment mutation by removing them from Tenant mass assignment, marking the Filament connected-account/status fields read-only/non-dehydrated, and adding controlled `Tenant::syncStripeConnectAccount()` persistence for OAuth callbacks.
- [x] R8 Made OAuth start/callback resilient to missing config, token exchange failures, and account retrieval failures with safe logging, generic admin-visible retry messages, and no secret leakage.
- [x] R9 Hid the tenant dashboard Stripe Connect action unless the current user is BusinessAdmin and Stripe Connect secret/client ID are configured; documented required Stripe env vars in `.env.example`.
- [x] R10 Updated Connect-related tests and regression fixtures to use explicit controlled Connect sync instead of mass-assigning protected ownership/status fields.
- [x] R11 Required `STRIPE_CONNECT_WEBHOOK_SECRET` for Connect onboarding readiness in dashboard visibility and OAuth start, and added safe operational logging when Connect webhooks hit the endpoint without a configured secret.

## Post-PR3 Resilience Blocker TDD Cycle Evidence

| Task | Test File | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|------|-----------|-------|------------|-----|-------|-------------|----------|
| R11 Connect webhook secret readiness + observability | `tests/Feature/StripeConnectControllerTest.php`, `tests/Feature/Filament/DashboardPageTest.php`, `tests/Feature/WebhookControllerTest.php` | Feature + Feature/Livewire | ✅ 25 existing targeted tests passed before edits | ✅ Missing webhook-secret readiness and missing-secret log assertions failed first | ✅ 28 targeted tests passed after surgical implementation | ✅ Start path blocks when only webhook secret is missing, dashboard hides CTA when only webhook secret is missing, full config still shows/starts onboarding, and webhook missing-secret response logs safe context while remaining 400 | ✅ Kept config predicate changes local; no secrets are logged |

## Post-PR3 Resilience Blocker Verification

Safety net before blocker edits:

`php artisan test tests/Feature/StripeConnectControllerTest.php tests/Feature/Filament/DashboardPageTest.php tests/Feature/WebhookControllerTest.php`

Result: 25 passed, 90 assertions.

RED run after blocker tests were written first:

`php artisan test tests/Feature/StripeConnectControllerTest.php tests/Feature/Filament/DashboardPageTest.php tests/Feature/WebhookControllerTest.php`

Result: Failed as expected on missing webhook-secret readiness for controller/dashboard and missing missing-secret webhook log.

Targeted blocker GREEN run:

`php artisan test tests/Feature/StripeConnectControllerTest.php tests/Feature/Filament/DashboardPageTest.php tests/Feature/WebhookControllerTest.php`

Result: 28 passed, 101 assertions.

Relevant Stripe/payment regression run:

`php artisan test tests/Unit/StripeServiceTest.php tests/Unit/StripeAccountResolverTest.php tests/Feature/BookingWithPaymentTest.php tests/Unit/ProcessAutoRefundsTest.php tests/Feature/WebhookControllerTest.php tests/Unit/ProcessWebhookTest.php tests/Feature/StripeConnectControllerTest.php tests/Feature/Filament/DashboardPageTest.php tests/Feature/Filament/MultiCurrencyResourceTest.php tests/Unit/TenantPaymentAccountTest.php tests/Unit/BookingPaymentAccountSnapshotTest.php`

Result: 85 passed, 257 assertions.

Full suite:

`php artisan test`

Result: 206 passed, 687 assertions.

Formatting:

`vendor/bin/pint --dirty --test`

Result: PASS, 17 files.

Whitespace:

`git diff --check`

Result: PASS.

## PR 3 Surgical Remediation TDD Cycle Evidence

| Task | Test File | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|------|-----------|-------|------------|-----|-------|-------------|----------|
| R7 Connect field protection | `tests/Unit/TenantPaymentAccountTest.php`, `tests/Feature/Filament/MultiCurrencyResourceTest.php` | Unit + Feature/Filament | ✅ 32 targeted existing tests passed before edits | ✅ Missing `syncStripeConnectAccount()`, `sensitiveStripeConnectFields()`, and read-only resource helper failed first | ✅ Targeted remediation tests passed | ✅ Non-fillable field list, forged mass update rejection, controlled OAuth sync, and TenantResource read-only field contract | ✅ Controller now uses controlled model method; tests no longer rely on protected mass assignment |
| R8 OAuth failure recovery | `tests/Feature/StripeConnectControllerTest.php` | Feature | ✅ Existing controller tests passed before edits | ✅ Missing config and thrown token/account failures produced redirect/log assertions that failed first | ✅ Controller tests passed | ✅ Config missing, token exchange failure, account retrieval failure, and existing success/state/role paths | ✅ Extracted state length and user-facing failure message constants; log context excludes exception messages/secrets |
| R9 Dashboard/config/role gate | `tests/Feature/Filament/DashboardPageTest.php` | Feature/Livewire | ✅ Existing dashboard tests passed before edits | ✅ Missing-config and employee hidden-action assertions failed while action rendered unconditionally | ✅ Dashboard tests passed | ✅ BusinessAdmin with config sees action; missing config and Employee do not | ✅ Gate kept in widget view; env documentation added |
| R10 Payment regression fixture hardening | `tests/Unit/StripeAccountResolverTest.php`, `tests/Unit/BookingPaymentAccountSnapshotTest.php`, `tests/Unit/ProcessAutoRefundsTest.php`, `tests/Unit/ProcessWebhookTest.php`, `tests/Feature/BookingWithPaymentTest.php`, `tests/Feature/WebhookControllerTest.php` | Unit + Feature | ✅ Relevant Stripe/payment regression set exposed protected-field fixture failures after R7 | ✅ Regression fixtures failed after protected fields stopped being fillable | ✅ Relevant Stripe/payment regression passed | ✅ Resolver, booking payment, auto-refund, webhook controller/job, and booking snapshot flows | ✅ Shared test helpers now sync Connect state through controlled paths |

## PR 3 Surgical Remediation Verification

Safety net before remediation edits:

`php artisan test tests/Feature/StripeConnectControllerTest.php tests/Feature/Filament/DashboardPageTest.php tests/Feature/Filament/MultiCurrencyResourceTest.php tests/Unit/TenantPaymentAccountTest.php tests/Unit/StripeServiceTest.php`

Result: 32 passed, 91 assertions.

RED run after remediation tests were written first:

`php artisan test tests/Unit/TenantPaymentAccountTest.php tests/Feature/StripeConnectControllerTest.php tests/Feature/Filament/DashboardPageTest.php tests/Feature/Filament/MultiCurrencyResourceTest.php`

Result: Failed as expected on missing controlled sync helpers, missing config/failure handling, and unconditional dashboard action.

Targeted remediation GREEN run:

`php artisan test tests/Unit/TenantPaymentAccountTest.php tests/Feature/StripeConnectControllerTest.php tests/Feature/Filament/DashboardPageTest.php tests/Feature/Filament/MultiCurrencyResourceTest.php`

Result: 33 passed, 112 assertions.

Relevant Stripe/payment regression run:

`php artisan test tests/Unit/StripeServiceTest.php tests/Unit/StripeAccountResolverTest.php tests/Feature/BookingWithPaymentTest.php tests/Unit/ProcessAutoRefundsTest.php tests/Feature/WebhookControllerTest.php tests/Unit/ProcessWebhookTest.php tests/Feature/StripeConnectControllerTest.php tests/Feature/Filament/DashboardPageTest.php tests/Feature/Filament/MultiCurrencyResourceTest.php tests/Unit/TenantPaymentAccountTest.php tests/Unit/BookingPaymentAccountSnapshotTest.php`

Result: 82 passed, 246 assertions.

Full suite:

`php artisan test`

Result: 203 passed, 676 assertions.

Formatting:

`vendor/bin/pint --dirty --test`

Result: PASS, 16 files.

Whitespace:

`git diff --check`

Result: PASS.

## Deviations

- Added the Connect webhook route and minimal Stripe platform config keys in PR 2 because webhook handling cannot function without them.
- PR 3 completes onboarding routes/controller and TenantResource/tenant dashboard UI. No platform fees, FX conversion, Express/Custom accounts, archive, commit, or PR creation were performed.

## Remaining Tasks

- [x] 4.1 Complete PR 3 config/routes for publishable key/client ID, OAuth start/callback, and any remaining onboarding route wiring.
- [x] 4.2 Create `app/Http/Controllers/StripeConnectController.php` for Standard OAuth start/callback, state validation, tenant-only persistence, and non-admin denial.
- [x] 4.3 Update `app/Filament/Resources/TenantResource.php` with payment mode fields, conditional direct credentials, Connect status display, and onboarding action/link.
- [x] 4.4 Add feature/Filament tests for onboarding authorization, state mismatch, TenantResource validation, status display, and unsupported currency rejection.

## PR 3 Verification

Safety net before PR 3 edits:

`php artisan test tests/Feature/Filament/MultiCurrencyResourceTest.php tests/Unit/TenantPaymentAccountTest.php tests/Unit/StripeServiceTest.php`

Result: 17 passed, 39 assertions.

PR 3 focused run:

`php artisan test tests/Feature/StripeConnectControllerTest.php tests/Feature/Filament/MultiCurrencyResourceTest.php tests/Feature/Filament/DashboardPageTest.php`

Result: 20 passed, 65 assertions.

Relevant Stripe/payment regression run:

`php artisan test tests/Unit/StripeServiceTest.php tests/Unit/StripeAccountResolverTest.php tests/Feature/BookingWithPaymentTest.php tests/Unit/ProcessAutoRefundsTest.php tests/Feature/WebhookControllerTest.php tests/Unit/ProcessWebhookTest.php tests/Feature/StripeConnectControllerTest.php tests/Feature/Filament/MultiCurrencyResourceTest.php tests/Feature/Filament/DashboardPageTest.php`

Result: 66 passed, 193 assertions.

Full suite:

`php artisan test`

Result: 194 passed, 639 assertions.

Formatting:

`vendor/bin/pint --dirty --test`

Result: PASS, 8 files.

Whitespace:

`git diff --check`

Result: PASS.
