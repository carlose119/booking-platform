# Tasks: Multi-currency

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 550-750 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 data/helpers → PR 2 payment/public UI → PR 3 dashboard/admin/tests |
| Delivery strategy | ask-on-risk |
| Chain strategy | stacked-to-main |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Data model, currency config/helper, fallback tests | PR 1 | Base main; preserves USD defaults and legacy reads. |
| 2 | Booking payment snapshot, Stripe currency, public display | PR 2 | Base PR 1; verifies tenant isolation and no FX conversion. |
| 3 | Admin resource display and currency-safe dashboard metrics | PR 3 | Base PR 2; verifies grouped revenue series. |

## Phase 1: Foundation / Data

- [x] 1.1 Create `database/migrations/*_add_currency_to_tenants_and_payment_snapshot_to_bookings.php` with tenant `default_currency` default `usd`, booking snapshot columns, and USD backfill.
- [x] 1.2 Create `config/currencies.php` with supported lowercase ISO codes, labels, and symbols.
- [x] 1.3 Create `app/Support/Currency.php` with `normalize()`, `options()`, `isSupported()`, and minor-unit `format()`; reject unsupported Stripe currencies.
- [x] 1.4 Update `app/Models/Tenant.php` fillable/casts plus currency fallback helper returning normalized `usd`.
- [x] 1.5 Update `app/Models/Booking.php` fillable/casts plus resolved payment amount/currency helpers using snapshot → tenant/service → USD fallback.

## Phase 2: Payment / Public Booking

- [x] 2.1 Add RED tests for PaymentIntent full/deposit amount+currency snapshots and unsupported currency rejection in `tests/Feature` or `tests/Unit`.
- [x] 2.2 Update `app/Services/BookingService.php` to calculate and persist tenant-scoped payment snapshots before Stripe creation.
- [x] 2.3 Update `app/Services/StripeService.php` to validate normalized currency and pass it to PaymentIntent params without FX conversion.
- [x] 2.4 Update `app/Livewire/BookingCalendar.php` and `resources/views/livewire/booking-calendar.blade.php` to use tenant currency formatting and legacy USD fallback.

## Phase 3: Admin / Dashboard

- [x] 3.1 Update `app/Filament/Resources/TenantResource.php` with validated `default_currency` select and table display.
- [x] 3.2 Update `app/Filament/Resources/ServiceResource.php` to replace hardcoded `$` with active tenant currency labels; services inherit tenant currency only.
- [x] 3.3 Update `app/Services/DashboardMetricsService.php` to return revenue totals/trends grouped by snapshot currency and avoid mixed-currency totals.

## Phase 4: Testing / Verification

- [x] 4.1 Test migration defaults/backfill: new tenants default USD, existing tenants resolve USD, legacy bookings preserve USD behavior.
- [x] 4.2 Test service CRUD/display: tenant currency labels, integer minor-unit storage, tenant isolation, and no conversion after currency changes.
- [x] 4.3 Test dashboard scenarios: single-currency totals, mixed-currency grouping/filtering, and no FX conversion.
- [x] 4.4 Run targeted Pest/PHPUnit suites for models, booking payment flow, Filament resources, Livewire public booking, and dashboard metrics.
