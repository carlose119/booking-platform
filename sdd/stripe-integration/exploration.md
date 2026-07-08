# Exploration: Stripe Integration

## Current State

The booking-platform has a **skeleton for payment integration** but **zero Stripe implementation**:

### Existing Payment Infrastructure
- **Booking model** (`app/Models/Booking.php`): Has `payment_status` (string) and `stripe_payment_intent_id` (nullable string) fields already in the schema
- **PaymentStatus enum** (`app/Enums/PaymentStatus.php`): Defines Unpaid, Paid, Refunded, Partial states
- **BookingService** (`app/Services/BookingService.php`): Creates bookings with `status=pending`, `payment_status=unpaid` — no payment processing logic
- **BookingCalendar Livewire component** (`app/Livewire/BookingCalendar.php`): 3-step flow (slot selection → guest form → confirmation). No payment step exists yet
- **Migrations**: `bookings` table already has `payment_status` and `stripe_payment_intent_id` columns

### What's Missing
- **Stripe PHP SDK**: Not installed (`composer.json` shows no `stripe/stripe-php` dependency)
- **Payment configuration**: No per-tenant Stripe settings (API keys, payment policy, refund rules)
- **Payment processing service**: No `PaymentService` or similar exists
- **Webhook endpoint**: No Stripe webhook route or controller
- **Refund logic**: No refund processing code
- **Frontend payment UI**: No Stripe Elements or payment form integration

### Multi-Tenant Context
- Tenant model (`app\Models\Tenant.php`): Basic fields only (`name`, `slug`). No payment configuration columns
- Single-database multi-tenancy with `tenant_id` foreign keys
- FilamentPHP v5 with native multi-tenancy support

## Affected Areas

- `app/Models/Tenant.php` — Add payment configuration (Stripe keys, payment policy, refund rules)
- `app/Services/BookingService.php` — Extend to handle payment flow orchestration
- `app/Livewire/BookingCalendar.php` — Add payment step to booking flow
- `composer.json` — Install `stripe/stripe-php` SDK
- `config/booking.php` — Add Stripe configuration (webhook secret, API version)
- `routes/web.php` — Add webhook endpoint route
- `app/Http/Controllers/` — New webhook controller
- `database/migrations/` — New migration for tenant payment settings
- `app/Filament/Resources/TenantResource.php` — Admin UI for payment configuration

## Approaches

### 1. Platform-Level Stripe Account (Single Account, Multi-Tenant Routing)

Each tenant configures their own Stripe API keys in the admin panel. The platform uses those keys to create PaymentIntents directly.

| Aspect | Details |
|--------|---------|
| **Pros** | Simplest implementation; each tenant controls their own Stripe account; no connected account complexity; direct refund capability |
| **Cons** | Requires each tenant to have their own Stripe account; platform can't aggregate payments; more configuration per tenant |
| **Effort** | Low |

### 2. Stripe Connect (Platform as Intermediary)

Platform has a master Stripe account. Tenants onboard as connected accounts. Platform creates PaymentIntents on behalf of tenants, with automatic routing.

| Aspect | Details |
|--------|---------|
| **Pros** | Unified platform billing; easier onboarding for tenants; automatic payment splitting; platform can take commission |
| **Cons** | Much higher complexity (Connect onboarding, account links, compliance); requires platform to handle KYC; more Stripe API surface |
| **Effort** | High |

### 3. Hybrid (Configurable Per-Tenant with Platform Fallback)

Tenants can choose: use their own Stripe account (direct integration) OR use the platform's Stripe account (with Connect). Admin panel lets tenant choose their preferred mode.

| Aspect | Details |
|--------|---------|
| **Pros** | Maximum flexibility; tenants who want control get it; tenants who want simplicity get it; future-proof |
| **Cons** | Highest implementation complexity; two code paths to maintain; more testing surface |
| **Effort** | High |

## Recommendation

**Approach 1: Platform-Level Stripe Account (Direct Integration)**

For an MVP of a booking SaaS, this is the right starting point:

1. **Simplicity**: Each tenant gets their own Stripe account. They configure their keys in the admin panel. No Connect complexity.
2. **Control**: Tenants have full control over their payments, refunds, and Stripe dashboard.
3. **Compliance**: No KYC or Connect compliance burden on the platform.
4. **Refund capability**: Direct API calls to Stripe with tenant's own keys — straightforward.
5. **Future migration path**: If Connect becomes needed later, the PaymentService abstraction makes it swappable.

The PRD's payment policies (100% upfront, fraction/deposit, no payment) map cleanly to PaymentIntent amount calculation:
- 100%: `amount = service.price`
- Fraction: `amount = service.price * (deposit_percentage / 100)`
- No payment: Skip PaymentIntent creation entirely

## Risks

- **Webhook reliability**: Stripe webhooks can fail or be delayed. Need idempotent handling and retry logic. Consider using Laravel queues for webhook processing.
- **Tenant API key security**: Storing Stripe API keys per-tenant requires encryption at rest. Never store in plain text. Use Laravel's encrypted model attributes or a separate `tenant_payment_settings` table with encrypted columns.
- **Double-booking during payment**: The current hold system (10-minute TTL) may not align with payment processing time. Payment intent creation should extend or replace the hold mechanism.
- **Refund timing**: Auto-refund logic needs a scheduled job to check upcoming bookings and trigger refunds before the deadline. This is a new queue/scheduler concern.
- **Multi-currency**: Not in PRD but tenants may operate in different currencies. PaymentIntent `currency` parameter must be configurable per-tenant.

## Ready for Proposal

**Yes** — the exploration is complete. The orchestrator should proceed to `sdd-propose` with the recommendation to use **Approach 1 (Direct Stripe Integration)** unless the user specifically requests Connect functionality.
