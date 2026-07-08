# Proposal: Business Cancellation Wiring

## Intent

Give tenant business/admin users a safe, service-backed way to cancel bookings. Today cancellation primitives exist, but no tenant booking-management surface wires status updates, audit data, cancellation notifications, and refund handling together.

## Proposal Question Round

Questions needing product review before spec finalization:
- Which roles may cancel: Business Admin only, or Employees for their own bookings?
- Should cancellation reasons be free text, presets, or both?
- Should refunds start immediately in queue, or wait for the existing scheduled refund command?

Assumptions: audit fields are required (`cancellation_reason`, `cancelled_by`); refund triggering should be async/queued rather than blocking the UI; customer self-cancellation is deferred.

## Scope

### In Scope
- Tenant-scoped BookingResource or equivalent booking management UI.
- Cancel action requiring a reason and actor.
- Service method sets `status=cancelled`, `cancelled_at`, `cancellation_reason`, `cancelled_by`.
- Dispatch `SendBookingNotification($booking, 'cancelled', $reason)`.
- Trigger existing refund primitives for `paid`/`partial` bookings asynchronously.

### Out of Scope
- Customer self-cancellation.
- Rescheduling flow.
- New refund provider or payment policy redesign.

## Capabilities

### New Capabilities
- `business-booking-management`: Tenant-admin booking list/detail actions, including audited cancellation.

### Modified Capabilities
- `data-model`: Add booking cancellation audit fields.
- `payment-processing`: Wire cancellation to async refund initiation for paid/partial bookings.
- `notification-events`: Ensure business cancellation uses the existing cancellation notification path.
- `admin-dashboard`: Make “View Bookings” target a registered tenant booking surface.

## Approach

Add cancellation as a BookingService operation used by Filament UI. Keep tenant scoping at query/action level using the active Filament tenant. Queue refund work or mark for the scheduled refund command rather than calling Stripe synchronously from the UI. Preserve public booking creation, holds, and availability semantics.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `app/Filament/Resources/BookingResource.php` | New | Tenant booking management and cancel action |
| `app/Providers/Filament/TenantPanelProvider.php` | Modified | Register booking resource |
| `app/Services/BookingService.php` | Modified | Central cancellation workflow |
| `app/Models/Booking.php` | Modified | Cast/fill audit fields |
| `database/migrations/*bookings*` | Modified | Add cancellation audit columns |
| `app/Console/Commands/ProcessAutoRefunds.php` | Modified | Reuse/queue refund path for cancellations |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Cross-tenant cancellation | Med | Scope resource queries/actions by active tenant; test isolation |
| Duplicate refunds | Med | Idempotent refund guard on payment status/intent |
| UI blocks on Stripe | Med | Use queued/scheduled refund processing |

## Rollback Plan

Disable/unregister BookingResource and cancel action, revert the service method and migration, and keep existing public booking/notification/refund behavior unchanged.

## Dependencies

- Existing `SendBookingNotification`, `BookingCancelled`, Stripe refund service, and auto-refund command.

## Success Criteria

- [ ] Tenant admin can cancel only own-tenant bookings with reason and audit actor.
- [ ] Cancellation sends client notification and never changes public booking flow.
- [ ] Paid/partial cancellations initiate refund handling asynchronously and idempotently.
