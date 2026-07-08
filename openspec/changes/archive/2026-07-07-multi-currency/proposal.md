# Proposal: Multi-currency

## Intent

Make booking payments currency-aware without FX conversion. First slice preserves USD defaults, gives each tenant a default currency, snapshots charged booking amount/currency, and prevents revenue metrics from mixing currencies.

## Scope

### In Scope
- Add tenant default currency with `usd` fallback and Filament configuration/display.
- Let services inherit tenant currency in this slice; do not add per-service currency overrides.
- Snapshot booking charged amount and currency when payment is required.
- Pass selected currency into Stripe PaymentIntent creation instead of hardcoded USD.
- Show currency code/symbol safely in service, booking, public booking, and payment displays.
- Update dashboard revenue metrics to group/scope by tenant currency; no FX conversion.

### Out of Scope
- FX conversion, historical exchange rates, and exchange-rate providers.
- Stripe Connect or multi-account settlement changes.
- Per-service multi-currency pricing beyond inherited tenant currency.

## Capabilities

### New Capabilities
- None

### Modified Capabilities
- `tenant-management`: tenant records gain a default currency setting.
- `service-management`: service price display/input becomes tenant-currency-aware while prices inherit tenant currency.
- `payment-processing`: PaymentIntents and booking payment records use/snapshot tenant currency and charged amount.
- `public-booking-calendar`: public booking/payment UI shows currency-aware service/payment amounts.
- `admin-dashboard`: revenue metrics avoid mixed-currency totals.
- `data-model`: tenant and booking schemas gain currency/amount fields.

## Approach

Add `tenants.default_currency` (`usd` default) and nullable booking payment snapshot fields. Resolve booking currency from tenant default at charge time, calculate amount as today, update booking snapshot before/with PaymentIntent creation, and pass currency to `StripeService`. Update money formatting through a small shared helper/formatter. Dashboard metrics should aggregate from booking snapshots and include currency in cache keys/results.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `database/migrations` | Modified | Tenant currency and booking snapshot columns/backfill. |
| `app/Models/{Tenant,Booking,Service}.php` | Modified | Casts/fillables/accessors for currency-aware money. |
| `app/Filament/Resources/{TenantResource,ServiceResource,BookingResource}.php` | Modified | Currency config and safe display. |
| `app/Livewire/BookingCalendar.php` | Modified | Resolve currency, snapshot payment, pass to Stripe, display amount. |
| `app/Services/{StripeService,DashboardMetricsService}.php` | Modified | Currency passthrough and currency-safe revenue aggregation. |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Historical paid bookings lack snapshots | Med | Backfill USD and keep nullable-safe reads. |
| Dashboard totals become misleading | Med | Group/scope by currency; never convert. |
| Unsupported Stripe currency code | Low | Validate against allowed lowercase ISO list. |

## Proposal question round

Assumption needing review: first slice uses tenant-wide currency only; services inherit it, including existing services.

## Rollback Plan

Revert code and migrations. Keep USD fallback reads so existing rows remain operable; if columns were deployed, leave them unused until a cleanup migration is planned.

## Dependencies

- Stripe must support configured tenant currency.

## Success Criteria

- [ ] Existing tenants/bookings continue behaving as USD by default.
- [ ] PaymentIntent currency matches tenant default and booking snapshot.
- [ ] Dashboard revenue never sums different currencies together.
