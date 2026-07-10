## Exploration: Stripe Connect for booking-platform

### Current State
- Stripe is tenant-owned today: `Tenant` stores `stripe_api_key` and `stripe_webhook_secret`, both encrypted.
- `BookingCalendar` creates PaymentIntents with the tenant key, and `StripeService` only supports a plain Stripe client.
- Webhooks are tenant-scoped (`/webhooks/stripe/{tenant}`) and verified with the tenant webhook secret.
- Refunds also run through the tenant key.
- There is no Connect-specific state anywhere: no connected account id, no capability/status tracking, and no platform webhook path.

### Affected Areas
- `app/Models/Tenant.php` — needs connect/account status fields and mode selection.
- `app/Filament/Resources/TenantResource.php` — needs onboarding/status UI and fallback/legacy Stripe fields clarified.
- `app/Services/StripeService.php` — needs account-context support (`stripe_account`) if Connect is used.
- `app/Livewire/BookingCalendar.php` — payment creation must branch by tenant Stripe mode.
- `app/Http/Controllers/WebhookController.php` — current tenant-slug webhook model does not fit Connect webhooks.
- `app/Jobs/ProcessWebhook.php` — event handling must resolve the tenant from connected account id for Connect events.
- `app/Console/Commands/ProcessAutoRefunds.php` — refunds need account-context awareness.
- `database/migrations/*` — tenant/account schema additions.
- `tests/*Stripe*`, `tests/*Webhook*`, `tests/*BookingWithPayment*` — current tests assume direct tenant keys.

### Approaches
1. **Connect as an opt-in coexistence layer** — keep direct tenant API keys working, add Connect as a separate mode.
   - Pros: safest migration path, preserves current tenants, lowest regression risk, lets us phase in onboarding/webhooks/refunds.
   - Cons: two payment paths to maintain, more branching in service/job code, more tests.
   - Effort: Medium

2. **Full migration to Connect-only** — replace tenant API keys with platform key + connected accounts.
   - Pros: one model long-term, simpler future mental model, no per-tenant secret handling.
   - Cons: breaking change for existing tenants, larger webhook/refund rewrite, higher support/compliance risk.
   - Effort: High

### Recommendation
Use **coexistence**. Keep direct tenant Stripe behavior as the default/legacy path and add Connect as an opt-in tenant mode.
For MVP onboarding, choose **Standard Connect** first: it best matches the current tenant-owned account model and minimizes platform responsibility. Keep Express as a later UX upgrade if needed.

For charges, use **platform secret + connected account context** (`stripe_account`) and **direct charges**. Do not introduce platform fees/application fees now.

### Risks
- Current webhook and refund code assume a tenant-owned secret, so Connect needs a new account-resolution path.
- `StripeService` currently has no way to pass per-request account context; that abstraction must change cleanly.
- Existing tenants may need a migration story; do not flip everyone at once.
- Account status must be stored locally (`charges_enabled`, `payouts_enabled`, `details_submitted`) or the admin UI will be blind.

### Ready for Proposal
Yes — but the proposal should explicitly preserve direct Stripe keys while adding Connect as an opt-in mode.
Low-risk first slice: tenant schema + connect status display + onboarding link generation + status sync, without switching booking payments yet. That slice should stay under the 400-line review budget.
