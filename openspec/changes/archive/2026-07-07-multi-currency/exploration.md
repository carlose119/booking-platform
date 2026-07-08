## Exploration: Multi-currency Support

### Current State
- `Service.price_cents` is stored as an integer with no currency column.
- `Booking` captures payment status and Stripe PaymentIntent ID, but not amount/currency.
- `StripeService::createPaymentIntent()` already accepts a currency, but `BookingCalendar` hardcodes `usd`.
- `DashboardMetricsService` sums `services.price_cents` across paid bookings without currency checks.
- `Tenant` and `TenantResource` have payment settings, but no tenant default currency.
- `ServiceResource` and dashboard widgets format money with `$`, so UI is USD-biased.

### Affected Areas
- `app/Models/Service.php` — add currency awareness for service pricing.
- `app/Models/Booking.php` — snapshot booking/payment currency and charged amount.
- `app/Models/Tenant.php` — tenant default currency setting.
- `app/Filament/Resources/TenantResource.php` — tenant currency field in settings.
- `app/Filament/Resources/ServiceResource.php` — price input/display formatting.
- `app/Livewire/BookingCalendar.php` — pass tenant/service currency into Stripe.
- `app/Services/StripeService.php` — keep currency parameter, no FX conversion.
- `app/Services/DashboardMetricsService.php` — stop mixing currencies in totals.
- `app/Jobs/ProcessWebhook.php` — payment status stays currency-agnostic, but should align to stored booking payment context.

### Approaches
1. **Tenant default currency + booking snapshot** — add tenant currency, let services inherit it, store booking/payment currency at charge time, and use that currency for Stripe.
   - Pros: lowest-risk path, preserves current USD behavior, no FX layer required.
   - Cons: reporting must guard against mixed-currency aggregation.
   - Effort: Medium

2. **Full money value-object refactor** — replace raw cent integers with a currency-aware money abstraction everywhere.
   - Pros: clean long-term model, fewer hidden currency bugs.
   - Cons: too large for the first slice, wide review blast radius.
   - Effort: High

### Recommendation
Take **Approach 1**. Introduce `tenant.default_currency` with `usd` fallback, snapshot currency on the booking/payment record, thread it through Stripe PaymentIntent creation, and update UI labels/prefixes to use the active currency. For reporting, only aggregate within a single currency or group by currency; do not convert FX in this slice.

### Risks
- Existing dashboard revenue metrics will be misleading if mixed currencies are still summed.
- Historical records have no currency metadata, so migration/backfill rules must be explicit.
- Stripe refunds follow the original PaymentIntent currency, so stored booking/payment currency must match the charged intent.

### Ready for Proposal
Yes — propose a first slice focused on tenant currency config, currency propagation to Stripe, and currency-safe reporting guards. Expect a medium review set (~8 files, roughly 250–400 changed lines).
