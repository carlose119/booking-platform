# Design: Stripe Connect MVP

## Technical Approach

Add an opt-in Stripe account layer without replacing direct tenant keys. Tenants default to `direct`; Connect tenants use the platform secret key plus `stripe_account` request options. Payment creation, refunds, and webhooks flow through one resolver so tenant isolation is enforced consistently. Standard Connect onboarding uses OAuth connect/callback rather than Express/Custom account links.

## Architecture Decisions

| Decision | Choice | Alternatives considered | Rationale |
|---|---|---|---|
| Account resolution | Create `App\Services\Stripe\StripeAccountResolver` returning a `StripeAccountContext` DTO | Inline conditionals in Livewire/jobs | Centralizes direct vs Connect rules and avoids cross-tenant drift. |
| Standard onboarding | Add OAuth redirect/callback for Standard Connect and store `stripe_user_id` as connected account ID | Account Links, Express/Custom | User constraint is Standard only; OAuth is the normal Standard Connect flow. |
| Request scoping | Extend `StripeService` methods with optional `$stripeOptions = []` and pass `['stripe_account' => acct_*]` for Connect | Separate Connect service | Preserves existing tests and call shape while allowing account-scoped operations. |
| Original account context | Snapshot payment account mode and connected account ID on `bookings` | Resolve only from current tenant state | Refunds/webhooks must target the original charge account even if tenant settings change later. |

## Data Flow

    Tenant ──→ StripeAccountResolver ──→ StripeService
      │                 │                     │
      │                 └─ direct: tenant key ┘
      │                 └─ connect: platform key + stripe_account
      └─ Booking snapshots account context for refund/webhook lookup

    Stripe OAuth ──→ ConnectController callback ──→ Tenant connect fields
    Stripe webhook ──→ WebhookController ──→ ProcessWebhook ──→ Booking by tenant/account/PI

## File Changes

| File | Action | Description |
|---|---|---|
| `database/migrations/*_add_stripe_connect_to_tenants_and_bookings.php` | Create | Add tenant `payment_account_mode`, `stripe_connected_account_id`, onboarding/capability/status fields; add booking payment account snapshot fields. |
| `app/Models/Tenant.php` | Modify | Fill/cast new fields; add helpers: `usesDirectStripe()`, `usesStripeConnect()`, `hasDirectStripeCredentials()`, `hasActiveConnectCharges()`, `isPaymentAccountReady()`. |
| `app/Models/Booking.php` | Modify | Fill booking account snapshot fields and provide legacy direct fallback helpers. |
| `app/Services/Stripe/StripeAccountContext.php` | Create | Immutable DTO: mode, api key, connected account ID, webhook secret, readiness flags. |
| `app/Services/Stripe/StripeAccountResolver.php` | Create | Resolve direct, Connect payment, refund, and webhook contexts with tenant scoping. |
| `app/Services/StripeService.php` | Modify | Accept Stripe options for `createPaymentIntent`, `createRefund`, `retrieveEvent`; add OAuth token/account retrieval helpers if kept here. |
| `config/services.php` | Modify | Add `stripe.secret`, `stripe.publishable_key`, `stripe.client_id`, `stripe.connect_webhook_secret`. |
| `routes/web.php` | Modify | Add Connect OAuth start/callback and platform Connect webhook routes; keep existing tenant webhook route. |
| `app/Http/Controllers/StripeConnectController.php` | Create | Business Admin OAuth start/callback, state validation, tenant-only persistence. |
| `app/Http/Controllers/WebhookController.php` | Modify | Verify direct tenant webhooks and Connect platform webhooks; dispatch tenant/account context. |
| `app/Jobs/ProcessWebhook.php` | Modify | Retrieve/process events with resolved context and query booking by `tenant_id`, `stripe_payment_intent_id`, and saved account context. |
| `app/Livewire/BookingCalendar.php` | Modify | Resolve account before PaymentIntent, block if not ready, snapshot payment context. |
| `app/Console/Commands/ProcessAutoRefunds.php` | Modify | Resolve refund context from booking snapshot; pass connected account options. |
| `app/Filament/Resources/TenantResource.php` | Modify | Add mode/status fields, direct credential conditional validation, Connect status display/action link. |

## Interfaces / Contracts

```php
final readonly class StripeAccountContext {
    public function stripeOptions(): array; // [] or ['stripe_account' => $connectedAccountId]
    public function isReadyForCharges(): bool;
}
```

Tenant modes: `direct`, `connect`. Connect charge readiness requires a connected account ID and active charge capability; direct readiness requires tenant API key when payment is required.

## Testing Strategy

| Layer | What to Test | Approach |
|---|---|---|
| Unit | Tenant helpers and resolver direct/Connect readiness | Pest/PHPUnit model + resolver tests. |
| Unit | `StripeService` passes `stripe_account` for PI/refund/retrieve | Mock Stripe client resources. |
| Feature | Direct mode payment/refund/webhook unchanged | Preserve existing tests, add regression assertions. |
| Feature | Connect PI/refund/webhook routing | Booking flow and job/controller tests with saved account context. |
| Feature | Standard OAuth onboarding and authorization | Business Admin allowed, non-admin denied, state mismatch rejected. |
| Feature | TenantResource fields/status | Filament resource tests for visibility and validation. |

## Migration / Rollout

Add nullable/defaulted columns. Backfill all tenants to `direct`; booking account fields stay nullable and use legacy direct fallback. No data migration to Connect. Because scope likely exceeds 400 changed lines, split PRs: data/resolver, payment/refund/webhooks, onboarding/admin UI/tests.

## Open Questions

- [ ] Exact Connect status refresh timing: OAuth callback only, manual admin action, or scheduled refresh.
