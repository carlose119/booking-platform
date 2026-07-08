# Design: Stripe Integration

## Technical Approach

Add Stripe payment processing to the booking platform via a `StripeService` abstraction, webhook controller with signature verification, tenant-level payment configuration, and a payment step in the Livewire booking flow. The approach follows the existing service-layer pattern (`BookingService`, `AvailabilityService`) and integrates with the current 3-step booking flow by inserting a payment step between guest form and confirmation.

## Architecture Decisions

| Decision | Options | Tradeoff | Choice |
|----------|---------|----------|--------|
| Stripe SDK usage | `StripeClient` (v7.33+) vs legacy static methods | `StripeClient` enables per-tenant keys via constructor injection; legacy is simpler but locks to single key | `StripeClient` — required for multi-tenant key isolation |
| API key storage | Laravel encrypted casts vs custom encryptor | Encrypted casts are declarative, work with Filament forms natively; custom gives more control | Encrypted casts — simpler, idiomatic Laravel |
| Payment UI | Stripe Elements (JS embed) vs Stripe Checkout (redirect) | Elements keeps user in-app, more control; Checkout is faster to implement but breaks flow | Stripe Elements — better UX within Livewire step flow |
| Webhook handling | Controller + job dispatch vs dedicated webhook middleware | Controller is explicit, easier to debug; middleware is cleaner but less visible | Controller — follows existing route patterns, explicit |
| Refund logic | Manual admin action + scheduled command | Manual covers ad-hoc; scheduled handles auto-refund within window | Both — required by spec |
| Hold TTL for payment | Configurable via tenant setting vs hardcoded 15min | Configurable adds complexity; hardcoded is simpler for v1 | Hardcoded 15min when payment required — simple, predictable |

## Data Flow

### Booking with Payment (100upfront / fraction)

```
Guest selects slot → createHold(ttl=15min) → Guest fills form
  → confirmBooking() → creates Booking(status=pending, payment_status=unpaid)
  → StripeService::createPaymentIntent() → returns client_secret
  → Guest enters card via Stripe Elements → JS confirms payment
  → Webhook: payment_intent.succeeded → ProcessWebhook job
  → Updates booking: payment_status=paid (or partial), status=confirmed
```

### Webhook Processing

```
Stripe sends POST /webhooks/stripe/{tenant}
  → WebhookController verifies signature using tenant's webhook_secret
  → Dispatches ProcessWebhook job to queue
  → Job: idempotent check (stripe_payment_intent_id lookup)
  → payment_intent.succeeded → mark paid/partial + confirm booking
  → payment_intent.payment_failed → leave unpaid, log failure
```

### Auto-Refund

```
Schedule::command('booking:auto-refund')->hourly
  → Finds cancelled bookings where:
      cancelled_at >= (now - refund_window_hours)
      AND payment_status = paid
      AND stripe_payment_intent_id IS NOT NULL
  → StripeService::createRefund() per booking
  → Updates payment_status = refunded
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `app/Services/StripeService.php` | Create | PaymentIntent creation, refund processing, tenant-aware via StripeClient |
| `app/Http/Controllers/WebhookController.php` | Create | Stripe webhook endpoint with signature verification |
| `app/Jobs/ProcessWebhook.php` | Create | Idempotent webhook event handler (succeeded/failed) |
| `app/Console/Commands/ProcessAutoRefunds.php` | Create | Scheduled command for auto-refund checks |
| `app/Models/Tenant.php` | Modify | Add payment config fields to fillable, add encrypted casts |
| `app/Services/BookingService.php` | Modify | Branch `confirmBooking()` by payment_policy; extend hold TTL when payment required |
| `app/Livewire/BookingCalendar.php` | Modify | Add payment step (step 3), shift confirmation to step 4 |
| `resources/views/livewire/booking-calendar.blade.php` | Modify | Add Stripe Elements payment step view |
| `routes/web.php` | Modify | Add webhook route |
| `routes/console.php` | Modify | Schedule auto-refund command |
| `database/migrations/XXXX_add_payment_config_to_tenants.php` | Create | Add payment_policy, deposit_percentage, refund_window_hours, stripe_api_key, stripe_webhook_secret |
| `app/Filament/Resources/TenantResource.php` | Modify | Add payment configuration fields to form |
| `composer.json` | Modify | Add `stripe/stripe-php` dependency |

## Interfaces / Contracts

```php
// StripeService — called by BookingService and webhook job
class StripeService
{
    public function __construct(string $apiKey) {} // tenant's Stripe key
    public function createPaymentIntent(int $amountCents, string $currency, array $metadata): PaymentIntentResult
    public function createRefund(string $paymentIntentId, ?int $amountCents = null): RefundResult
}

// Webhook result DTOs
readonly class PaymentIntentResult {
    public function __construct(
        public string $id,
        public string $clientSecret,
        public int $amount,
        public string $status,
    ) {}
}

readonly class RefundResult {
    public function __construct(
        public string $id,
        public string $status,
        public int $amount,
    ) {}
}
```

```php
// Tenant payment config fields (migration)
$table->string('payment_policy')->default('nopayment'); // 100upfront | fraction | nopayment
$table->unsignedInteger('deposit_percentage')->nullable();
$table->unsignedInteger('refund_window_hours')->default(24);
$table->text('stripe_api_key')->nullable();       // encrypted
$table->text('stripe_webhook_secret')->nullable(); // encrypted
```

```php
// Encrypted cast on Tenant model
protected function casts(): array {
    return [
        'stripe_api_key' => 'encrypted',
        'stripe_webhook_secret' => 'encrypted',
    ];
}
```

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Unit | `StripeService::createPaymentIntent()` with mocked StripeClient | Mock StripeClient, verify amount/metadata, assert PaymentIntentResult |
| Unit | `StripeService::createRefund()` with mocked StripeClient | Mock StripeClient, verify refund call, assert RefundResult |
| Unit | `ProcessWebhook` job idempotency | Dispatch same event twice, verify booking updated once |
| Unit | `ProcessAutoRefunds` command logic | Create test bookings, run command, verify refund calls |
| Integration | Webhook signature verification | Send valid/invalid signatures, assert 200/400 |
| Integration | `BookingService::confirmBooking()` payment branching | Mock StripeService, verify different paths for each payment_policy |
| Feature | Full booking flow with payment | Livewire test: slot → guest → payment → confirmation |

## Migration / Rollout

1. **Migration**: Add payment config columns to `tenants` table with defaults (`nopayment`, 24h refund window)
2. **Dependency**: `composer require stripe/stripe-php`
3. **Queue**: Ensure queue worker is running (database driver, already configured)
4. **Webhook**: Stripe dashboard — register webhook URL, subscribe to `payment_intent.succeeded` and `payment_intent.payment_failed`
5. **No feature flag needed** — `payment_policy=nopayment` (default) means existing behavior is unchanged

## Open Questions

- [ ] Should the webhook route be outside middleware (Stripe requires raw body)? → Yes, use `VerifyCsrfToken` exclusion.
- [ ] Should refund be partial (deposit only) or full (total amount) for `fraction` policy? → Spec says deposit-only refund for fraction.
- [ ] What happens if webhook fails after booking is created but before payment? → Booking stays pending, slot expires via existing `expireHolds` scheduler.
