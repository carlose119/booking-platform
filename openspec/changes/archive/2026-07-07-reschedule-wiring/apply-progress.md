# Apply Progress: Reschedule Wiring

## Mode

Strict TDD

## Workload / PR Boundary

- Mode: stacked PR slice
- Current work unit: PR 2 — Filament action/modal + feature tests
- Boundary: starts after PR 1 backend lifecycle/tests and ends with tenant Filament table reschedule action, modal validation, service-backed update coverage, and supplemental no-client notification preservation coverage.
- Estimated review budget impact: PR 2 stayed focused on `BookingResource` UI wiring and targeted tests; no service/payment/public booking behavior changes were introduced.
- Delivery decision source: User/orchestrator prompt resolved stacked-to-main, split into 2 PRs.

## Completed Tasks

- [x] 1.1 Create `database/migrations/*_add_reschedule_audit_to_bookings.php` with nullable previous date/start/end, `rescheduled_by`, and `reschedule_reason` columns.
- [x] 1.2 Update `app/Models/Booking.php` fillable/casts and add `rescheduledBy()` user relationship.
- [x] 2.1 Extend `app/Services/AvailabilityService.php` to accept `?int $excludeBookingId` and exclude only that booking from booking conflicts.
- [x] 2.2 Add `BookingService::rescheduleBooking(...)` in `app/Services/BookingService.php` with transaction, tenant/role/state checks, and same service/employee enforcement.
- [x] 2.3 In `BookingService::rescheduleBooking(...)`, validate target slot through `AvailabilityService`, preserve status/payment fields, save audit fields, and dispatch `SendBookingNotification` as `rescheduled`.
- [x] 3.1 Add a `reschedule` table action to `app/Filament/Resources/BookingResource.php`, visible only to Business Admins for active own-tenant bookings.
- [x] 3.2 Build the action modal fields for date, start/end time, and optional reason; submit by calling `BookingService::rescheduleBooking(...)`.
- [x] 4.1 Extend `tests/Unit/BookingServiceTest.php` for successful reschedule, cancelled/completed rejection, client actor denial, cross-tenant denial, and conflicting slot denial.
- [x] 4.2 Add availability self-exclusion coverage in `tests/Unit/BookingServiceTest.php`: excluded booking passes, other booking/hold still blocks.
- [x] 4.3 Assert `SendBookingNotification` dispatch in `tests/Unit/BookingServiceTest.php` includes `rescheduled`, original date, and original time without payment/refund changes.
- [x] 4.4 Extend `tests/Feature/Filament/BookingResourceTest.php` for action visibility, modal validation, and successful service-backed update.

## TDD Cycle Evidence

| Task | Test File | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|------|-----------|-------|------------|-----|-------|-------------|----------|
| 1.1 | `tests/Unit/BookingServiceTest.php` | Unit/database | ✅ 18/18 baseline | ✅ Written first via service audit assertions | ✅ 24/24 file passed | ✅ Audit fields asserted through happy path | ✅ Nullable migration only |
| 1.2 | `tests/Unit/BookingServiceTest.php` | Unit/model | ✅ 18/18 baseline | ✅ Written first via `rescheduledBy` relation assertions | ✅ 24/24 file passed | ✅ Fillable/casts exercised through persisted audit fields | ✅ Matched existing model style |
| 2.1 | `tests/Unit/BookingServiceTest.php` | Unit/service | ✅ 18/18 baseline | ✅ Written first with `excludeBookingId` named argument | ✅ 24/24 file passed | ✅ Current booking passes while other booking and hold block | ✅ Existing conflict path reused |
| 2.2 | `tests/Unit/BookingServiceTest.php` | Unit/service | ✅ 18/18 baseline | ✅ Written first against missing `rescheduleBooking` API | ✅ 24/24 file passed | ✅ Success, state rejection, client denial, and cross-tenant denial | ✅ Kept lifecycle in service transaction |
| 2.3 | `tests/Unit/BookingServiceTest.php` | Unit/service | ✅ 18/18 baseline | ✅ Written first for slot validation/audit/notification behavior | ✅ 24/24 file passed | ✅ Conflict rejection plus successful queued notification | ✅ Preserved payment/status fields |
| 3.1 | `tests/Feature/Filament/BookingResourceTest.php` | Feature/Filament | ✅ 30/30 relevant baseline | ✅ Written first; failed because table action `reschedule` did not exist | ✅ 9/9 reschedule-filter tests passed | ✅ Admin visible, employee hidden, cancelled hidden | ✅ Reused existing `Action` pattern |
| 3.2 | `tests/Feature/Filament/BookingResourceTest.php` | Feature/Filament | ✅ 30/30 relevant baseline | ✅ Written first; failed because table action `reschedule` did not exist | ✅ 9/9 reschedule-filter tests passed | ✅ Successful update plus required date/start/end validation | ✅ Normalized date/time before service call |
| 4.1 | `tests/Unit/BookingServiceTest.php` | Unit | ✅ 18/18 baseline | ✅ Written first | ✅ 24/24 file passed | ✅ Covered happy path plus cancelled/completed/client/cross-tenant/conflict | ➖ None needed |
| 4.2 | `tests/Unit/BookingServiceTest.php` | Unit | ✅ 18/18 baseline | ✅ Written first | ✅ 24/24 file passed | ✅ Excluded booking, other booking, and active hold cases | ➖ None needed |
| 4.3 | `tests/Unit/BookingServiceTest.php` | Unit | ✅ 18/18 baseline | ✅ Written first | ✅ 24/24 file passed | ✅ Asserts event/original date/original time and no refund queue | ➖ None needed |
| 4.4 | `tests/Feature/Filament/BookingResourceTest.php` | Feature/Filament | ✅ 30/30 relevant baseline | ✅ Written first; failed because table action `reschedule` did not exist | ✅ 9/9 feature file passed | ✅ Visibility, modal validation, and service-backed update covered | ✅ Test helper supports overrides/schedules |
| Supplemental | `tests/Unit/BookingServiceTest.php` | Unit/integration | ✅ 30/30 relevant baseline | ✅ Written before any additional production change; no production change needed | ✅ 9/9 reschedule-filter tests passed | ✅ No-client notification job preserves reschedule/payment/cancellation state | ➖ None needed |

## Test Summary

- Baseline: `php artisan test tests/Feature/Filament/BookingResourceTest.php tests/Unit/BookingServiceTest.php` → 30 passed, 125 assertions.
- RED: `php artisan test tests/Feature/Filament/BookingResourceTest.php tests/Unit/BookingServiceTest.php --filter='reschedule|without_client'` → failed because Filament table action `reschedule` was not found; supplemental no-client unit test already passed against existing backend behavior.
- GREEN/REFACTOR: `php artisan test tests/Feature/Filament/BookingResourceTest.php tests/Unit/BookingServiceTest.php --filter='reschedule|without_client'` → 9 passed, 76 assertions.
- Final relevant run: `php artisan test tests/Feature/Filament/BookingResourceTest.php tests/Unit/BookingServiceTest.php` → 34 passed, 172 assertions.
- Full suite: `php artisan test` → 137 passed, 472 assertions.
- Formatting: `vendor/bin/pint --dirty --test` → PASS, 0 files needed formatting.
- Total tests written in PR 2: 4.
- Total tests passing in relevant files: 34.
- Layers used: Feature/Filament (3 new tests), Unit/integration (1 supplemental test).
- Approval tests: None — behavior was added, not refactored.
- Pure functions created: 0.

## Files Changed

| File | Action | What Was Done |
|------|--------|---------------|
| `app/Filament/Resources/BookingResource.php` | Modified | Added tenant-scoped Business Admin `reschedule` table action with date/start/end/reason modal and service delegation. |
| `tests/Feature/Filament/BookingResourceTest.php` | Modified | Added reschedule action success, modal validation, and visibility/authorization coverage; attached employee to service for availability-backed tests. |
| `tests/Unit/BookingServiceTest.php` | Modified | Added supplemental no-client reschedule notification job preservation coverage. |
| `openspec/changes/reschedule-wiring/tasks.md` | Modified | Marked PR 2 tasks complete and recorded `stacked-to-main` chain strategy. |
| `openspec/changes/reschedule-wiring/apply-progress.md` | Created | Persisted cumulative apply progress and TDD evidence for PR 1 + PR 2. |

## Deviations from Design

None — implementation matches the design. The action is table-scoped as designed and delegates lifecycle rules to `BookingService::rescheduleBooking(...)`.

## Issues Found

- The supplemental PR 1 schema/index assertion was not added because an existing feature test already covers dashboard index shape, and adding DB-driver-specific availability index introspection was not needed for the PR 2 UI slice.

## Remaining Tasks

None.

## Status

11/11 tasks complete. Ready for sdd-verify.
