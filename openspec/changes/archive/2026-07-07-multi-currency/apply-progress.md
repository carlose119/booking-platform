# Apply Progress: Multi-currency

## Mode

Strict TDD

## Workload / PR Boundary

- Mode: stacked PR slice
- Current work unit: PR 3 — admin/dashboard/tests
- Boundary: starts after PR 2 payment/public booking wiring and ends with admin currency controls, service currency display/input, dashboard currency-safe grouping, widgets, and full verification prep.
- Estimated review budget impact: PR 3 touched admin/dashboard/test files plus SDD artifacts. Workspace is not a git repository, so exact changed-line count could not be measured with `git diff --stat`.

## Completed Tasks

- [x] 1.1 Created `database/migrations/2026_07_07_000002_add_currency_to_tenants_and_payment_snapshot_to_bookings.php` with tenant `default_currency`, booking payment snapshot columns, and USD backfill for nullable tenant/Stripe rows.
- [x] 1.2 Created `config/currencies.php` with supported lowercase ISO codes, labels, symbols, minor units, and Stripe support flags.
- [x] 1.3 Created `app/Support/Currency.php` with normalization, supported-code options, support checks, Stripe rejection, and minor-unit formatting.
- [x] 1.4 Updated `app/Models/Tenant.php` fillable/casts and added normalized `currency()` fallback helper.
- [x] 1.5 Updated `app/Models/Booking.php` fillable/casts and added resolved payment amount/currency helpers using snapshot → tenant/service → USD fallback.
- [x] 2.1 Added RED coverage for full/deposit Stripe amount+currency propagation, booking payment snapshots, unsupported Stripe currency rejection, and public tenant-currency display.
- [x] 2.2 Updated `app/Services/BookingService.php` with `snapshotPaymentForStripe(...)` to calculate tenant-scoped payment amount/currency and persist snapshots before Stripe creation.
- [x] 2.3 Updated `app/Services/StripeService.php` to normalize/validate currency through `Currency::ensureSupportedForStripe()` before creating PaymentIntents.
- [x] 2.4 Updated `app/Livewire/BookingCalendar.php` and `resources/views/livewire/booking-calendar.blade.php` to format service/payment amounts with the tenant currency helper and preserve USD fallback behavior.
- [x] 3.1 Updated `app/Filament/Resources/TenantResource.php` with default-currency select options, validation rules, and table display helper.
- [x] 3.2 Updated `app/Filament/Resources/ServiceResource.php` to use active tenant currency labels/prefixes/formatting, store minor units, and register `ServiceResource` in the tenant panel.
- [x] 3.3 Updated `app/Services/DashboardMetricsService.php` to return `revenue_today_by_currency` and currency-keyed trend `series`, preserving legacy `data`/`revenue_today_cents` only when unambiguous.
- [x] 4.1 Verified migration/model defaults and legacy USD behavior through existing schema/model tests.
- [x] 4.2 Added admin/service currency UI tests for supported currency labels, unsupported validation rules, tenant currency display, minor-unit storage, and no conversion.
- [x] 4.3 Added dashboard tests for single-currency totals, mixed-currency grouping, tenant isolation, and no FX conversion.
- [x] 4.4 Ran targeted multi-currency suites, full test suite, and Pint dirty check.

## TDD Cycle Evidence

| Task | Test File | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|------|-----------|-------|------------|-----|-------|-------------|----------|
| 1.1 | `tests/Feature/Database/MultiCurrencySchemaTest.php` | Feature/DB | N/A (new migration); existing `BookingServiceTest` baseline ✅ 25/25 | ✅ Written first; failed on missing `default_currency` and payment snapshot columns | ✅ Passed in targeted run | ✅ 2 schema/default/snapshot cases | ✅ Pint passed |
| 1.2 | `tests/Unit/CurrencyTest.php` | Unit | N/A (new config) | ✅ Written first; failed because `App\Support\Currency` did not exist | ✅ Passed in targeted run | ✅ Options assertions cover `usd` and `eur` | ✅ Pint passed |
| 1.3 | `tests/Unit/CurrencyTest.php` | Unit | N/A (new helper) | ✅ Written first; failed because helper did not exist | ✅ Passed in targeted run | ✅ Missing/uppercase normalization, format for USD/EUR, unsupported Stripe rejection | ✅ Pint passed |
| 1.4 | `tests/Unit/TenantCurrencyTest.php` | Unit/model | ✅ `BookingServiceTest` baseline 25/25 before model edits | ✅ Written first; failed on missing `Tenant::currency()` | ✅ Passed in targeted run | ✅ Missing/default USD and uppercase EUR normalization cases | ✅ Pint passed |
| 1.5 | `tests/Unit/BookingPaymentSnapshotTest.php` | Unit/model | ✅ `BookingServiceTest` baseline 25/25 before model edits | ✅ Written first; failed on missing booking helper methods/columns | ✅ Passed in targeted run | ✅ Snapshot-first, service/tenant fallback, and legacy USD fallback cases | ✅ Pint passed |
| 2.1 | `tests/Feature/BookingWithPaymentTest.php`, `tests/Unit/StripeServiceTest.php` | Feature + Unit | ✅ Existing payment/Stripe tests 10/10 passing before production edits | ✅ Written/enhanced first; failed on hardcoded USD, missing snapshots, unsupported currency reaching Stripe, and hardcoded public display | ✅ 13/13 passed after implementation | ✅ Full EUR payment, GBP deposit, unsupported BRL, and public EUR display cases | ✅ Pint passed |
| 2.2 | `tests/Feature/BookingWithPaymentTest.php` | Feature | ✅ Existing payment flow tests 7/7 passing before production edits | ✅ Snapshot assertions failed because bookings did not persist `payment_amount_cents`/`payment_currency` | ✅ Passed in targeted run | ✅ Full upfront and deposit snapshots cover different policy branches | ✅ Extracted service-owned snapshot helper with tenant/service/booking guard |
| 2.3 | `tests/Unit/StripeServiceTest.php` | Unit | ✅ Existing Stripe tests 3/3 passing before production edits | ✅ Uppercase EUR and unsupported BRL tests failed against raw currency passthrough | ✅ Passed in targeted run | ✅ Supported normalization and unsupported pre-Stripe rejection cases | ✅ Reused `Currency::ensureSupportedForStripe()` |
| 2.4 | `tests/Feature/BookingWithPaymentTest.php` | Feature/Livewire | ✅ Existing public payment tests 7/7 passing before production edits | ✅ EUR/GBP display assertions failed because Blade prepended hardcoded `$` and service list omitted prices | ✅ Passed in targeted run | ✅ Service list, full payment, and deposit copy render non-USD symbols | ✅ Formatting centralized through `Currency::format()` |
| 3.1 | `tests/Feature/Filament/MultiCurrencyResourceTest.php` | Feature/Filament | ✅ Targeted baseline 20/20 before PR 3 edits | ✅ Tenant currency resource tests failed on missing field/display helpers and Filament v5 resource compatibility issues | ✅ Targeted admin/dashboard suite passed 23/23 | ✅ Supported EUR option, unsupported BRL validation, and USD fallback display | ✅ Extracted `TenantResource` helper methods and updated v5 schema/action APIs |
| 3.2 | `tests/Feature/Filament/MultiCurrencyResourceTest.php` | Feature/Filament | ✅ Targeted baseline 20/20 before PR 3 edits | ✅ Service table/create tests failed on hardcoded/blank price display and missing tenant assignment | ✅ Targeted admin/dashboard suite passed 23/23 | ✅ EUR table display and GBP create/minor-unit storage cover different currencies | ✅ Added `Currency::symbol()` and hidden tenant assignment; registered `ServiceResource` in tenant panel |
| 3.3 | `tests/Unit/Services/DashboardMetricsServiceTest.php`, `tests/Feature/Filament/DashboardPageTest.php` | Unit + Feature/Widget | ✅ Existing dashboard tests 13/13 passing before edits | ✅ Grouped revenue tests failed because dashboard returned mixed legacy totals only | ✅ Targeted admin/dashboard suite passed 23/23 | ✅ Single-currency, mixed-currency, legacy fallback, trend series, and tenant isolation cases | ✅ Centralized paid booking lookup and widget formatting |
| 4.1 | `tests/Feature/Database/MultiCurrencySchemaTest.php`, `tests/Unit/TenantCurrencyTest.php`, `tests/Unit/BookingPaymentSnapshotTest.php` | Feature + Unit | ✅ PR 1 verify report PASS | ✅ Existing PR 1 RED evidence retained | ✅ Included in expanded target run 68/68 | ✅ Defaults, fallback, snapshot-first, service fallback | ✅ No PR 3 code changes required |
| 4.2 | `tests/Feature/Filament/MultiCurrencyResourceTest.php` | Feature/Filament | ✅ Targeted baseline 20/20 before PR 3 edits | ✅ Written before admin/service resource implementation | ✅ 5/5 passed | ✅ Tenant options/validation, service display/create | ✅ Pint passed |
| 4.3 | `tests/Unit/Services/DashboardMetricsServiceTest.php`, `tests/Feature/Filament/DashboardPageTest.php` | Unit + Feature/Widget | ✅ Existing dashboard tests 13/13 before edits | ✅ Written before dashboard implementation | ✅ 18/18 dashboard/admin tests passed | ✅ Single, mixed, legacy, tenant isolation, and widget display | ✅ Pint passed |
| 4.4 | Full suite | Verification prep | ✅ PR 1/PR 2 verify reports PASS | ✅ N/A command task; verification targets established before final run | ✅ `php artisan test` passed 161/161; `vendor/bin/pint --dirty --test` PASS | ✅ Targeted and full suite both run | ✅ 0 dirty Pint issues |

## Test Summary

- **Total tests written/enhanced this slice**: 8 behavior cases (5 admin/resource cases, 3 dashboard service cases, 2 dashboard widget cases; some existing tests enhanced for grouped outputs).
- **Total tests passing**: 161 full-suite tests / 533 assertions.
- **Layers used**: Unit, Feature/Filament, Feature/Livewire/widget.
- **Approval tests**: Existing dashboard/admin/model/payment tests passed before PR 3 edits: 20 tests / 60 assertions.
- **Pure functions created**: 3 small formatting/option helper methods on `TenantResource` and `Currency::symbol()`.

## Verification Commands

- `php artisan test tests/Unit/Services/DashboardMetricsServiceTest.php tests/Feature/Filament/DashboardPageTest.php tests/Feature/Database/MultiCurrencySchemaTest.php tests/Unit/TenantCurrencyTest.php tests/Unit/BookingPaymentSnapshotTest.php` before production edits → ✅ 20 passed / 60 assertions.
- `php artisan test tests/Feature/Filament/MultiCurrencyResourceTest.php tests/Unit/Services/DashboardMetricsServiceTest.php` after RED tests → ❌ expected RED failures for missing admin currency UI/helper behavior and dashboard grouped revenue contracts.
- `php artisan test tests/Feature/Filament/MultiCurrencyResourceTest.php tests/Unit/Services/DashboardMetricsServiceTest.php tests/Feature/Filament/DashboardPageTest.php` after implementation → ✅ 23 passed / 81 assertions.
- `vendor/bin/pint app/Support/Currency.php app/Filament/Resources/TenantResource.php app/Filament/Resources/TenantResource/Pages/ListTenants.php app/Filament/Resources/ServiceResource.php app/Providers/Filament/TenantPanelProvider.php app/Services/DashboardMetricsService.php app/Filament/Widgets/StatsOverviewWidget.php app/Filament/Widgets/RevenueChartWidget.php tests/Feature/Filament/MultiCurrencyResourceTest.php tests/Feature/Filament/DashboardPageTest.php tests/Unit/Services/DashboardMetricsServiceTest.php` → ✅ 11 files formatted / checked.
- `php artisan test tests/Feature/Filament/MultiCurrencyResourceTest.php tests/Unit/Services/DashboardMetricsServiceTest.php tests/Feature/Filament/DashboardPageTest.php tests/Feature/Database/MultiCurrencySchemaTest.php tests/Unit/TenantCurrencyTest.php tests/Unit/BookingPaymentSnapshotTest.php tests/Feature/BookingWithPaymentTest.php tests/Unit/StripeServiceTest.php tests/Unit/BookingServiceTest.php` → ✅ 68 passed / 242 assertions.
- `vendor/bin/pint --dirty --test` → ✅ PASS 0 files.
- `php artisan test` → ✅ 161 passed / 533 assertions.

## Files Changed

| File | Action | What Was Done |
|------|--------|---------------|
| `app/Support/Currency.php` | Modified | Added `symbol()` helper for currency-aware Filament input prefixes. |
| `app/Filament/Resources/TenantResource.php` | Modified | Added default currency select, validation, display formatting helpers, and Filament v5 schema/action compatibility updates. |
| `app/Filament/Resources/TenantResource/Pages/ListTenants.php` | Modified | Updated tab import to Filament v5 namespace. |
| `app/Filament/Resources/ServiceResource.php` | Modified | Replaced hardcoded USD display with active tenant currency formatting, stored price input as minor units, and assigned tenant context on create. |
| `app/Providers/Filament/TenantPanelProvider.php` | Modified | Registered `ServiceResource` so tenant admins can access service currency UI. |
| `app/Services/DashboardMetricsService.php` | Modified | Added currency-keyed revenue totals/trends and removed mixed-currency aggregate totals. |
| `app/Filament/Widgets/StatsOverviewWidget.php` | Modified | Displays revenue by formatted currency group instead of a raw unscoped total. |
| `app/Filament/Widgets/RevenueChartWidget.php` | Modified | Emits one chart dataset per currency series. |
| `tests/Feature/Filament/MultiCurrencyResourceTest.php` | Created | Covers tenant currency options/validation/display and service currency display/minor-unit storage. |
| `tests/Feature/Filament/DashboardPageTest.php` | Modified | Covers single and mixed currency revenue display in stats widget. |
| `tests/Unit/Services/DashboardMetricsServiceTest.php` | Modified | Covers grouped revenue totals/trends, legacy fallback, and tenant isolation. |
| `openspec/changes/multi-currency/tasks.md` | Modified | Marked Phase 3/4 tasks complete. |
| `openspec/changes/multi-currency/apply-progress.md` | Modified | Merged PR 1/PR 2 progress with PR 3 evidence. |

## Deviations from Design

None — implementation matches the PR 3 subset of the design. `revenue_today_cents` and trend `data` are preserved only for zero/single-currency compatibility and become `null` for mixed-currency data.

## Issues Found

- The workspace is not a git repository, so changed-line budget could not be measured with `git diff --stat`.
- Rendering `TenantResource` surfaced pre-existing Filament v5 compatibility issues (`Form` vs `Schema`, old action namespaces, old tab namespace, and `liveOnBlur()`); the touched TenantResource/ListTenants paths were updated while broader resources were left unchanged.
- `ServiceResource` was not registered in the tenant panel and did not assign `tenant_id` during create; registering it was required to make service currency UI reachable.

## Remaining Tasks

None.
