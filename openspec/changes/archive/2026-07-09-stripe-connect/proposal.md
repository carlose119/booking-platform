# Proposal: Stripe Connect MVP

## Intent

Enable tenants to opt into Stripe Standard Connect while preserving the existing direct tenant API key mode. The MVP reduces tenant key handling risk and prepares platform-managed payment operations without forcing migration, fees, or account-type complexity.

## Scope

### In Scope
- Add tenant payment mode selection: direct API keys or Stripe Connect.
- Store connected account ID plus onboarding/capability status on tenants.
- Provide a Business Admin onboarding/connect flow using Stripe account links.
- Resolve Stripe API key and `stripe_account` context for PaymentIntents, refunds, and webhooks.
- Preserve current direct API key behavior and tenant webhook-secret support.

### Out of Scope
- Express or Custom Connect accounts.
- Application/platform fees.
- Connect-only cutover or tenant migration.
- FX conversion.

## Capabilities

### New Capabilities
- None

### Modified Capabilities
- `payment-processing`: Add Connect-aware account resolution for PaymentIntent creation, refunds, and webhook processing while preserving direct tenant API key mode.
- `tenant-management`: Add opt-in Connect configuration, onboarding status, connected account ID, and admin onboarding entry point.
- `data-model`: Extend tenant persistence with payment mode and Connect account/status fields.

## Approach

Introduce a tenant payment-account resolver that returns provider mode, API credential source, and optional connected account context. Use the platform Stripe API key with `stripe_account` for Connect direct charges; use tenant keys unchanged for direct mode. Add migrations/model casts, Filament tenant settings, a Business Admin onboarding action, and status refresh/capability checks before payment use.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `app/Models/Tenant.php` | Modified | Add payment mode and Connect account/status fields. |
| `app/Filament/Resources/TenantResource.php` | Modified | Manage payment mode and Connect status/configuration. |
| `app/Services/StripeService.php` | Modified | Support account-scoped requests and onboarding links. |
| `app/Livewire/BookingCalendar.php` | Modified | Create PaymentIntents through resolved payment account context. |
| `app/Http/Controllers/WebhookController.php` | Modified | Verify and dispatch direct/Connect webhook events safely. |
| `app/Jobs/ProcessWebhook.php` | Modified | Resolve booking/tenant under the correct Stripe account context. |
| `app/Console/Commands/ProcessAutoRefunds.php` | Modified | Refund through the original payment account context. |
| `tests/*Stripe*`, `tests/*Webhook*`, `tests/*Payment*` | Modified | Cover both modes and fallback behavior. |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Cross-account payment/refund mismatch | Med | Persist/resolve tenant mode and account context; test both modes. |
| Webhook ambiguity between direct and Connect | Med | Verify signatures per endpoint/secret and use metadata/account context. |
| Incomplete onboarding capabilities | Med | Block Connect payments until required capabilities are active. |

## Rollback Plan

Disable Connect mode in tenant settings, keep direct mode active, and revert routing to tenant API keys. Schema additions are nullable and can remain unused until a later cleanup migration.

## Dependencies

- Platform Stripe secret key configured for Connect.
- Stripe Standard Connect onboarding/account-link APIs.

## Success Criteria

- [ ] Direct tenant API key payments, refunds, and webhooks still pass existing tests.
- [ ] Connect tenants can onboard, create PaymentIntents, receive webhooks, and refund using `stripe_account`.
- [ ] Tenants without active Connect capabilities cannot accept Connect payments.
