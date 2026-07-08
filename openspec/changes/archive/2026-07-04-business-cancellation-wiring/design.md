# Design: Business Cancellation Wiring

## Technical Approach

Add business cancellation as a tenant-scoped `BookingService` lifecycle operation, then expose it through a Filament tenant `BookingResource`. The UI will collect a required reason, call the service with the active tenant and actor, and never call Stripe directly. Existing `SendBookingNotification($booking, 'cancelled', $reason)` and `booking:auto-refund` primitives remain the integration points.

## Architecture Decisions

| Decision | Choice | Alternatives considered | Rationale |
|---|---|---|---|
| Cancellation boundary | `BookingService::cancelBooking(...)` owns state transition, audit, notification dispatch, and refund trigger | Put logic directly in Filament action | Centralizes idempotency and tenant guard so future callers cannot bypass lifecycle rules. |
| Tenant isolation | Service accepts `tenantId` and queries `Booking::where('tenant_id', $tenantId)->lockForUpdate()` | Trust Filament query scoping only | Tenant isolation is non-negotiable; enforce it at UI query and service mutation boundaries. |
| Refund handling | Leave Stripe work to existing async/scheduled command; optionally queue `booking:auto-refund` after eligible cancellation | Synchronous Stripe refund in modal | Keeps UI responsive and reuses existing idempotency via `payment_status`/`stripe_payment_intent_id`. |
| Refund tracking | No new refund column in this slice; update command to include `paid` and `partial` and set `payment_status=refunded` after success | Add `refund_requested_at`/`refund_status` now | Existing command already tracks completion through `payment_status`; extra fields can be added later if concurrent scheduler locking becomes a problem. |

## Data Flow

```text
Tenant BookingResource action
  └─ validates reason + active Filament tenant
     └─ BookingService::cancelBooking(bookingId, tenantId, actorId, reason)
        ├─ transaction + row lock + tenant-scoped lookup
        ├─ idempotent skip if already cancelled
        ├─ update status/cancel audit fields
        ├─ dispatch SendBookingNotification(..., 'cancelled', reason)
        └─ queue/schedule booking:auto-refund for paid/partial bookings
```

## File Changes

| File | Action | Description |
|---|---|---|
| `database/migrations/*_add_cancellation_audit_to_bookings.php` | Create | Add nullable `cancellation_reason` and `cancelled_by_user_id` after existing `cancelled_at`; FK to users with null-on-delete. |
| `app/Models/Booking.php` | Modify | Add fillable audit fields and `cancelledBy()` relationship. |
| `app/Services/BookingService.php` | Modify | Add `cancelBooking()` transaction, idempotency guard, notification dispatch, and async refund trigger. |
| `app/Console/Commands/ProcessAutoRefunds.php` | Modify | Include `partial` alongside `paid`; keep tenant refund-window guard and idempotent `refunded` status. |
| `app/Filament/Resources/BookingResource.php` | Create | Tenant-scoped table/detail with cancel action modal requiring a reason. |
| `app/Filament/Resources/BookingResource/Pages/ListBookings.php` | Create | List page using resource table actions. |
| `app/Filament/Resources/BookingResource/Pages/ViewBooking.php` | Create | Read-only detail surface with cancel action. |
| `app/Providers/Filament/TenantPanelProvider.php` | Modify | Register `BookingResource` in tenant panel resources. |
| `resources/views/filament/widgets/quick-actions-widget.blade.php` | Modify | Use `BookingResource::getUrl('index')` instead of hard-coded `/tenant/{id}/bookings`. |
| `tests/Unit/BookingServiceTest.php` | Modify | Cover successful, duplicate, and cross-tenant cancellation. |
| `tests/Feature/Filament/BookingResourceTest.php` | Create | Cover list visibility, tenant scoping, and cancel modal action. |
| `tests/Unit/ProcessAutoRefundsTest.php` | Modify | Cover paid/partial eligibility and duplicate refund skip. |
| `tests/Feature/NotificationDispatchTest.php` | Modify | Assert single cancelled notification dispatch with reason. |

## Interfaces / Contracts

```php
public function cancelBooking(
    int $bookingId,
    int $tenantId,
    int $actorUserId,
    string $reason,
): Booking
```

`reason` is trimmed and non-empty. Already-cancelled bookings return unchanged and MUST NOT dispatch notification or refund work again.

## Testing Strategy

| Layer | What to Test | Approach |
|---|---|---|
| Unit | Service transition, audit fields, idempotency, cross-tenant denial | `BookingServiceTest` with `Queue::fake()` and database assertions. |
| Feature | Filament list/detail scoping and cancel action/modal validation | Livewire/Filament tests with `Filament::setTenant($tenant)`. |
| Integration | Notification/refund idempotency | Fake queued jobs; command tests for paid/partial and already-refunded bookings. |
| E2E | Not required in this slice | Feature tests cover tenant UI workflow. |

## Migration / Rollout

Add nullable columns only; no data backfill required. Register `BookingResource` after service/tests are in place. This likely exceeds the 400-line budget; split PRs into: data/service, tenant UI/link, then notification/refund/test hardening.

## Open Questions

- [ ] Should employees be allowed to cancel their own assigned bookings, or only `business_admin`? Default design gates the resource/action to business admins until product confirms.
