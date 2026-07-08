# Design: Reschedule Wiring

## Technical Approach

Implement rescheduling as a service-owned lifecycle transition, mirroring the existing cancellation pattern. `BookingResource` only collects date/time/reason and delegates to `BookingService::rescheduleBooking(...)`. The service performs tenant/role/state checks inside a transaction, validates the target slot through `AvailabilityService` with current-booking exclusion, updates booking date/time and audit fields, then queues the existing reschedule notification job.

## Architecture Decisions

| Option | Tradeoff | Decision |
|---|---|---|
| Put reschedule rules in Filament action | Fast UI work but duplicates domain rules and weakens testability | Use `BookingService::rescheduleBooking(...)` as the single lifecycle API, matching `cancelBooking`. |
| Add a one-off availability query in `BookingService` | Avoids changing availability API but risks divergent conflict behavior | Extend `AvailabilityService::getAvailableSlots(..., ?int $excludeBookingId = null)` and filter conflicts centrally. |
| Dispatch notification in resource | Simple but notification can be skipped by non-UI callers | Dispatch `SendBookingNotification` after service update so every reschedule path behaves consistently. |
| Allow employee/service changes | More flexible but creates pricing, duration, and payment recalculation scope | First slice keeps same service and employee only. |

## Data Flow

    BookingResource action
        -> BookingService::rescheduleBooking
            -> lock tenant booking + authorize actor
            -> AvailabilityService::getAvailableSlots(excludeBookingId)
            -> update booking + audit fields
            -> SendBookingNotification(rescheduled)

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `database/migrations/*_add_reschedule_audit_to_bookings.php` | Create | Add nullable previous date/start/end, actor FK, optional reason. |
| `app/Models/Booking.php` | Modify | Add fillable fields, date/time casts, and `rescheduledBy()` relationship. |
| `app/Services/AvailabilityService.php` | Modify | Accept optional `excludeBookingId` and omit it from booking conflicts while keeping holds and other bookings blocking. |
| `app/Services/BookingService.php` | Modify | Add transactional `rescheduleBooking(...)` with tenant, role, state, same-employee, same-service, availability, audit, and notification logic. |
| `app/Filament/Resources/BookingResource.php` | Modify | Add table `reschedule` action visible only to Business Admins for non-cancelled/non-completed bookings. |
| `tests/Unit/BookingServiceTest.php` | Modify | Cover successful reschedule, denied client actor, denied cancelled/completed, cross-tenant, conflict, and notification payload. |
| `tests/Feature/Filament/BookingResourceTest.php` | Modify | Cover resource action visibility, modal validation, and successful service-backed update. |

## Interfaces / Contracts

```php
BookingService::rescheduleBooking(
    int $bookingId,
    int $tenantId,
    int $actorUserId,
    string $date,
    string $startTime,
    string $endTime,
    ?string $reason = null,
): Booking

AvailabilityService::getAvailableSlots(
    int $serviceId,
    string $date,
    ?int $tenantId = null,
    ?int $excludeBookingId = null,
): array
```

Notification dispatch must use the current job constructor order:

```php
SendBookingNotification::dispatch($booking, 'rescheduled', null, $originalDate, $originalTime);
```

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Unit | Service lifecycle rules and audit fields | Extend `tests/Unit/BookingServiceTest.php` with `Queue::fake()` and database assertions. |
| Unit | Availability self-exclusion | Add coverage that excluded booking does not block itself but another booking/hold still blocks. |
| Feature | Filament resource action | Use existing Livewire `TestAction` pattern for visibility, validation, and successful action call. |
| Integration | Notification dispatch | Assert queued `SendBookingNotification` has `event=rescheduled`, `originalDate`, and `originalTime`. |

## Migration / Rollout

Add nullable columns only; no data backfill required. If implementation exceeds the 400-line review budget, split PR 1 for migration/model/service/availability/tests and PR 2 for Filament resource action/tests.

## Open Questions

None.
