# Design: Multi-currency

## Technical Approach

Add tenant-level currency as the only pricing currency for this slice, snapshot charged booking amount/currency at PaymentIntent creation, and replace hardcoded USD display/Stripe calls with a small shared currency helper. Dashboard revenue will read booking payment snapshots first and return currency-keyed totals/series so no mixed-currency sum is exposed. This implements the `tenant-management`, `payment-processing`, `public-booking-calendar`, `admin-dashboard`, and `data-model` specs without FX conversion or per-service currency overrides.

## Architecture Decisions

| Decision | Choice | Alternatives considered | Rationale |
|---|---|---|---|
| Currency source | `tenants.default_currency`, lowercase ISO, default `usd` | Per-service currency | The spec requires services to inherit tenant currency in the first slice, preserving existing service prices. |
| Currency catalog | `config/currencies.php` plus `App\Support\Currency` formatter/validator | Enum only; database table | A small explicit config is easy to validate in Filament/tests and avoids premature admin-managed currency metadata. |
| Booking payment snapshot | Nullable `bookings.payment_amount_cents` and `bookings.payment_currency` populated when payment is required | Recalculate from service price forever | Snapshots preserve historical charge intent when tenant currency or service price later changes. |
| Dashboard revenue | Return `revenue_today_by_currency` and trend `series` keyed by currency; keep legacy USD fallback fields only when unambiguous | Convert everything to tenant currency | No FX conversion is allowed, and grouped results prevent misleading totals. |

## Data Flow

    Tenant.default_currency ──→ ServiceResource display/input label
             │
             └──→ BookingCalendar ──→ BookingService amount calculation
                         │
                         ├──→ Booking snapshot: payment_amount_cents/payment_currency
                         └──→ StripeService::createPaymentIntent(amount, currency)

    Paid bookings ──→ DashboardMetricsService ──→ revenue grouped by payment_currency

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `database/migrations/*_add_currency_to_tenants_and_payment_snapshot_to_bookings.php` | Create | Add `tenants.default_currency` default `usd`; add nullable booking snapshot columns; backfill existing tenants and paid/Stripe bookings to USD where needed. |
| `config/currencies.php` | Create | Supported lowercase ISO list and labels/symbols, e.g. `usd`, `eur`, `gbp`, `cad`, `aud`. |
| `app/Support/Currency.php` | Create | Normalize/validate currency and format minor-unit amounts without FX conversion. |
| `app/Models/Tenant.php` | Modify | Add `default_currency` fillable/cast and `currency()` fallback accessor/helper returning normalized `usd` when missing. |
| `app/Models/Booking.php` | Modify | Add payment snapshot fillable/casts and helpers for resolved payment amount/currency with USD fallback for legacy rows. |
| `app/Services/BookingService.php` | Modify | Add reusable payment snapshot helper used by public booking before Stripe creation; preserve tenant-scoped lookups. |
| `app/Livewire/BookingCalendar.php` | Modify | Resolve tenant currency on mount/payment, snapshot amount/currency, pass currency to Stripe, and expose formatted amount. |
| `app/Services/StripeService.php` | Modify | Normalize/validate currency before sending PaymentIntent params; keep current method signature. |
| `app/Services/DashboardMetricsService.php` | Modify | Aggregate snapshot revenue grouped by currency and include currency in cache-safe result shape. |
| `app/Filament/Resources/TenantResource.php` | Modify | Add validated `default_currency` select and table display. |
| `app/Filament/Resources/ServiceResource.php` | Modify | Replace `$` prefix/formatting with tenant currency-aware labels using active tenant/auth tenant. |
| `resources/views/livewire/booking-calendar.blade.php` | Modify | Show service/payment amounts using formatted currency, not hardcoded `$`. |
| `tests/*` | Modify/Create | Cover defaults/backfill, Stripe currency, dashboard grouping, tenant isolation, and public display. |

## Interfaces / Contracts

```php
Currency::normalize(?string $currency): string; // lowercase, fallback usd
Currency::options(): array; // code => label for Filament Select
Currency::format(int $amountCents, ?string $currency): string;

Booking::resolvedPaymentCurrency(): string; // snapshot -> tenant -> usd
Booking::resolvedPaymentAmountCents(): ?int; // snapshot -> service price fallback
```

`DashboardMetricsService::getTodayMetrics()` should return `revenue_today_by_currency: ['usd' => 6000]`. `getRevenueTrend()` should return `series: ['usd' => [0, 1000, ...]]` alongside existing labels.

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Unit | Currency normalization/formatting; Booking/Tenant fallbacks | PHPUnit model/support tests with legacy null currency rows. |
| Integration | Migration defaults/backfill; PaymentIntent currency/snapshot; dashboard grouping | Feature/unit service tests using RefreshDatabase and StripeService mock expectations. |
| E2E | Public booking display and Filament service/tenant display | Livewire/Filament feature tests asserting non-USD labels and tenant isolation. |

## Migration / Rollout

Deploy additive nullable/defaulted columns first. Existing tenants default to `usd`; existing booking snapshot reads fall back to USD and service price. No data conversion, no FX, and no Stripe Connect changes. If implementation exceeds the 400-line review budget, split PR 1 as data/model/helper/tests and PR 2 as UI/dashboard/payment wiring/tests.

## Open Questions

- [ ] None.
