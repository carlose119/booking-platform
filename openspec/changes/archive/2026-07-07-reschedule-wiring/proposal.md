# Proposal: Reschedule Wiring

## Intent

Let tenant Business Admins move an existing booking to a valid time slot without cancelling/recreating it, while preserving tenant isolation, booking status, payment status, cancellation/payment behavior, and customer notification.

## Scope

### In Scope
- Add `BookingService::rescheduleBooking(...)` as the lifecycle method for business/admin reschedules.
- Validate target availability for the same service and same employee, excluding the booking being moved.
- Add reschedule audit fields: previous date/start/end, actor, and optional reason.
- Add tenant `BookingResource` reschedule action with date/time/reason modal.
- Dispatch existing `BookingRescheduled` path through `SendBookingNotification`.

### Out of Scope
- Customer self-reschedule.
- Employee/service reassignment in this first slice.
- Payment adjustments, refunds, or price recalculation.

## Capabilities

### New Capabilities
- None

### Modified Capabilities
- `business-booking-management`: add tenant-scoped business reschedule rules and UI action.
- `data-model`: add nullable reschedule audit fields to bookings.
- `notification-events`: require business reschedule to dispatch the existing client notification.

## Proposal question round

Blocked from asking interactively; assumptions needing product review: same-employee only in slice one; reschedule is allowed for active non-cancelled/non-completed bookings; reason is optional; payments are preserved unchanged.

## Approach

Implement service-first, transactional rescheduling. Lock the tenant booking, reject cancelled/completed bookings, validate actor belongs to tenant and is not Client, verify target slot availability excluding the current booking, update date/time plus audit fields, then dispatch `SendBookingNotification($booking, 'rescheduled', original...)`. Filament action delegates only to the service.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `app/Services/BookingService.php` | Modified | Add reschedule lifecycle method. |
| `app/Services/AvailabilityService.php` | Modified | Support excluding current booking from conflicts. |
| `app/Filament/Resources/BookingResource.php` | Modified | Add admin reschedule modal/action. |
| `database/migrations/*bookings*` | New | Add reschedule audit columns. |
| `app/Models/Booking.php` | Modified | Fillable/casts/relationship for audit fields. |
| `app/Jobs/SendBookingNotification.php` | Modified | Ensure rescheduled event arguments work. |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| False slot conflict with current booking | Med | Add explicit exclusion tests. |
| Payment/cancellation side effects | Low | Preserve statuses and avoid refund paths. |
| Employee change scope creep | Med | Same employee only for first slice. |

## Rollback Plan

Remove the Filament action and service method; keep nullable audit columns or revert the migration before release if no production data exists.

## Dependencies

- Existing notification job/service must support the `rescheduled` event payload.

## Success Criteria

- [ ] Business Admin can reschedule own-tenant active bookings only.
- [ ] Target slot validation excludes the booking being moved.
- [ ] Status/payment values remain unchanged.
- [ ] Reschedule audit fields and notification are recorded/dispatched.
