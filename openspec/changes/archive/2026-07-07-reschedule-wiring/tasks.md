# Tasks: Reschedule Wiring

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 450-650 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 backend lifecycle/tests → PR 2 Filament action/tests |
| Delivery strategy | ask-on-risk |
| Chain strategy | stacked-to-main |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Add migration/model audit, availability exclusion, service lifecycle, unit tests | PR 1 | Base main; proves tenant/state/conflict/notification rules. |
| 2 | Add Filament reschedule action/modal and feature tests | PR 2 | Base PR 1 branch or merge after PR 1; UI delegates to service. |

## Phase 1: Data and Model Foundation

- [x] 1.1 Create `database/migrations/*_add_reschedule_audit_to_bookings.php` with nullable previous date/start/end, `rescheduled_by`, and `reschedule_reason` columns.
- [x] 1.2 Update `app/Models/Booking.php` fillable/casts and add `rescheduledBy()` user relationship.

## Phase 2: Availability and Service Lifecycle

- [x] 2.1 Extend `app/Services/AvailabilityService.php` to accept `?int $excludeBookingId` and exclude only that booking from booking conflicts.
- [x] 2.2 Add `BookingService::rescheduleBooking(...)` in `app/Services/BookingService.php` with transaction, tenant/role/state checks, and same service/employee enforcement.
- [x] 2.3 In `BookingService::rescheduleBooking(...)`, validate target slot through `AvailabilityService`, preserve status/payment fields, save audit fields, and dispatch `SendBookingNotification` as `rescheduled`.

## Phase 3: Filament Wiring

- [x] 3.1 Add a `reschedule` table action to `app/Filament/Resources/BookingResource.php`, visible only to Business Admins for active own-tenant bookings.
- [x] 3.2 Build the action modal fields for date, start/end time, and optional reason; submit by calling `BookingService::rescheduleBooking(...)`.

## Phase 4: Tests and Verification

- [x] 4.1 Extend `tests/Unit/BookingServiceTest.php` for successful reschedule, cancelled/completed rejection, client actor denial, cross-tenant denial, and conflicting slot denial.
- [x] 4.2 Add availability self-exclusion coverage in `tests/Unit/BookingServiceTest.php`: excluded booking passes, other booking/hold still blocks.
- [x] 4.3 Assert `SendBookingNotification` dispatch in `tests/Unit/BookingServiceTest.php` includes `rescheduled`, original date, and original time without payment/refund changes.
- [x] 4.4 Extend `tests/Feature/Filament/BookingResourceTest.php` for action visibility, modal validation, and successful service-backed update.
