# Apply Progress: Business Cancellation Wiring

## Mode

Strict TDD

## Workload / PR Boundary

- Mode: stacked PR slice
- Current work unit: PR 3 — refund/notification hardening and cleanup
- Boundary: auto-refund command eligibility/idempotency, cancellation notification dispatch/idempotency coverage, partial-payment refund messaging, final verification cleanup, and Strict TDD verification-gap tests for unpaid refunds and no-client cancellations.
- Estimated review budget impact: kept to the autonomous PR 3 hardening slice; latest batch touched two test files and SDD apply-progress only.

## Completed Tasks

- [x] 1.1 RED: Extend `tests/Unit/BookingServiceTest.php` for own-tenant cancel, duplicate no-op, and cross-tenant denial.
- [x] 1.2 Create `database/migrations/*_add_cancellation_audit_to_bookings.php` with nullable `cancellation_reason` and `cancelled_by_user_id` FK.
- [x] 1.3 Update `app/Models/Booking.php` fillable/casts and add `cancelledBy()` relationship.
- [x] 1.4 GREEN: Add `BookingService::cancelBooking(int $bookingId, int $tenantId, int $actorUserId, string $reason)` in `app/Services/BookingService.php`.
- [x] 2.1 RED: Create `tests/Feature/Filament/BookingResourceTest.php` for active-tenant list scope and cancel modal validation.
- [x] 2.2 Create `app/Filament/Resources/BookingResource.php` with tenant-scoped table/view data and business-admin cancel action.
- [x] 2.3 Create `app/Filament/Resources/BookingResource/Pages/ListBookings.php` and `ViewBooking.php` using resource actions.
- [x] 2.4 Register `BookingResource` in `app/Providers/Filament/TenantPanelProvider.php`.
- [x] 2.5 Update `resources/views/filament/widgets/quick-actions-widget.blade.php` to use `BookingResource::getUrl('index')`.
- [x] 3.1 RED: Extend `tests/Unit/ProcessAutoRefundsTest.php` for cancelled paid/partial eligibility and duplicate refund skip.
- [x] 3.2 Update `app/Console/Commands/ProcessAutoRefunds.php` to process paid/partial cancelled bookings idempotently.
- [x] 3.3 Extend `tests/Feature/NotificationDispatchTest.php` for one cancelled notification with reason and applicable refund info.
- [x] 3.4 Wire `BookingService::cancelBooking()` to dispatch `SendBookingNotification($booking, 'cancelled', $reason)` once.
- [x] 4.1 Run focused tests: `BookingServiceTest`, `BookingResourceTest`, `ProcessAutoRefundsTest`, `NotificationDispatchTest`.
- [x] 4.2 Run `php artisan test` and `vendor/bin/pint --dirty`; fix regressions without adding customer cancellation or reschedule flow.
- [x] Verification gap: Add direct coverage for `payment-processing` scenario “Non-paid booking skips refund”.
- [x] Verification gap: Add direct coverage for `notification-events` scenario “Cancellation without notifiable client”.

## TDD Cycle Evidence

| Task | Test File | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|------|-----------|-------|------------|-----|-------|-------------|----------|
| 1.1 | `tests/Unit/BookingServiceTest.php` | Unit | ✅ 13/13 baseline passing | ✅ 3 failing tests for missing `cancelBooking()` | ✅ `BookingServiceTest` 18/18 passing | ✅ Added customer self-cancellation denial, refund queue dispatch, and relationship assertion | ✅ Pint fixed style |
| 1.2 | `tests/Unit/BookingServiceTest.php` | Unit/database | ✅ 13/13 baseline passing | ✅ Audit persistence tests required missing columns | ✅ Migration made audit columns available | ✅ Duplicate cancellation exercises existing audit data | ✅ Nullable FK with null-on-delete |
| 1.3 | `tests/Unit/BookingServiceTest.php` | Unit/model | ✅ 13/13 baseline passing | ✅ Audit relationship assertion required model relationship | ✅ `cancelledBy()` resolves actor | ✅ Database + relationship assertions cover fillable and relation | ✅ Pint fixed style |
| 1.4 | `tests/Unit/BookingServiceTest.php` | Unit/service | ✅ 13/13 baseline passing | ✅ Missing method failed before implementation | ✅ Service transaction, tenant guard, idempotency, audit, notification dispatch, and refund queue trigger passed | ✅ Cross-tenant, customer-denial, and paid-refund cases cover alternate paths | ✅ Centralized lifecycle method kept minimal |
| 2.1 | `tests/Feature/Filament/BookingResourceTest.php` | Feature/Livewire | N/A (new test file) | ✅ 5 tests failed because `BookingResource` / `ListBookings` did not exist | ✅ `BookingResourceTest` 6/6 passing | ✅ Tenant-scope, cancel success, validation, action visibility, view page, and quick link cases | ✅ Helper setup kept local to test file |
| 2.2 | `tests/Feature/Filament/BookingResourceTest.php` | Feature/Livewire | N/A (new resource) | ✅ List/action tests required tenant-scoped resource and cancel table action | ✅ `BookingResourceTest` 6/6 passing | ✅ Own-tenant visible, cross-tenant hidden, admin action visible, employee action hidden | ✅ Used Filament v5 `Schema`, `recordActions`, and centralized service call |
| 2.3 | `tests/Feature/Filament/BookingResourceTest.php` | Feature/Livewire | N/A (new pages) | ✅ List/view page tests failed before pages existed | ✅ `ListBookings` and `ViewBooking` render and expose booking detail data | ✅ List and detail pages both covered | ✅ View header reuses resource cancel action |
| 2.4 | `tests/Feature/Filament/BookingResourceTest.php` | Feature/Livewire | ✅ `DashboardPageTest` 6/6 baseline passing | ✅ Quick link/resource URL test required registered tenant resource | ✅ `BookingResourceTest` and `DashboardPageTest` passing | ✅ Registered resource plus generated URL verified | ✅ Import ordering fixed by Pint |
| 2.5 | `tests/Feature/Filament/BookingResourceTest.php` | Feature/Livewire | ✅ `DashboardPageTest` 6/6 baseline passing | ✅ Quick action test expected `BookingResource::getUrl('index')` instead of hard-coded URL | ✅ Quick actions widget renders generated booking resource URL | ✅ Dashboard widget baseline and focused booking quick-link test both pass | ✅ Blade change limited to bookings link only |
| 3.1 | `tests/Unit/ProcessAutoRefundsTest.php` | Unit/command | ✅ 3/3 existing tests passing | ✅ Partial cancelled booking command test failed because command only selected `paid` bookings | ✅ `ProcessAutoRefundsTest` 5/5 passing | ✅ Paid, partial, outside-window, and already-refunded cases cover eligibility/idempotency | ✅ Query helper aligned to command eligibility |
| Verification gap: unpaid cancellation refund skip | `tests/Unit/ProcessAutoRefundsTest.php` | Unit/command | ✅ `ProcessAutoRefundsTest` 5/5 baseline passing | ✅ Added unpaid cancelled booking command test before production changes | ✅ `ProcessAutoRefundsTest` 6/6 passing, 13 assertions | ✅ Existing paid/partial positive-path tests prove the command runs provider refunds only for eligible paid states | ✅ No production refactor needed |
| 3.2 | `tests/Unit/ProcessAutoRefundsTest.php` | Unit/command | ✅ 3/3 existing tests passing | ✅ Command-level paid duplicate test required the second run to process 0 refunds | ✅ Command now selects `paid`/`partial`, resolves `StripeService` via container, and marks refunded after success | ✅ Second command run proves refunded bookings are skipped | ✅ Kept no new refund tracking columns per design |
| 3.3 | `tests/Feature/NotificationDispatchTest.php` | Feature/job/notification | ✅ 6/6 existing tests passing | ✅ Partial payment notification content test failed because refund copy only covered `paid` | ✅ `NotificationDispatchTest` 9/9 passing | ✅ Service dispatch, duplicate skip, mail reason, mail refund info, SMS reason, and SMS refund info covered | ✅ Reused existing `BookingCancelled` path |
| Verification gap: cancellation without notifiable client | `tests/Feature/NotificationDispatchTest.php` | Feature/service/job/notification | ✅ `NotificationDispatchTest` 9/9 baseline passing | ✅ Added no-client business cancellation test before production changes | ✅ `NotificationDispatchTest` 10/10 passing, 20 assertions | ✅ Companion client notification tests cover dispatch when a client exists; new no-client case covers the null-recipient branch | ✅ No production refactor needed |
| 3.4 | `tests/Feature/NotificationDispatchTest.php` | Feature/service/job | ✅ `BookingServiceTest` 18/18 passing | ✅ Added service-driven notification dispatch/idempotency tests before any production notification changes | ✅ Existing `BookingService::cancelBooking()` dispatch behavior stayed green; no extra wiring required | ✅ Duplicate cancellation asserted one queued cancelled job with original reason | ✅ No customer self-cancellation or reschedule flow added |
| 4.1 | Focused test commands | Verification | ✅ Relevant baseline tests passing before PR 3 changes | ✅ N/A — verification task | ✅ `BookingServiceTest`, `BookingResourceTest`, `ProcessAutoRefundsTest`, and `NotificationDispatchTest` all passed | ✅ Included prior UI/service dependency tests with PR 3 tests | ✅ No code changes needed after focused run |
| 4.2 | Full suite + Pint | Verification | ✅ Focused tests passing | ✅ N/A — cleanup task | ✅ `php artisan test` passed 125 tests / 384 assertions; `vendor/bin/pint --dirty --test` passed | ✅ Full regression suite covers public booking, tenant UI, refunds, and notifications | ✅ `vendor/bin/pint --dirty` reported 0 files changed |

## Test Summary

- **Total tests written**: 5 new unit tests in PR 1; 6 new feature tests in PR 2; 3 new unit/command tests and 4 new feature/job/notification tests in PR 3 including verification-gap coverage
- **Total tests passing**: 127/127 full suite, 391 assertions
- **Layers used**: Unit/database/command (PR 1 + PR 3), Feature/Livewire (PR 2), Feature/job/notification (PR 3)
- **Approval tests**: None — behavior was additive, not a refactor-only change
- **Pure functions created**: 0

## Tests Run

- `php artisan test --filter BookingServiceTest` — PASS, 18 tests / 60 assertions
- `vendor/bin/pint app/Services/BookingService.php app/Models/Booking.php tests/Unit/BookingServiceTest.php database/migrations/2026_07_06_000001_add_cancellation_audit_to_bookings.php` — PASS, style fixed
- `vendor/bin/pint tests/Unit/BookingServiceTest.php && php artisan test --filter BookingServiceTest` — PASS after refund queue test, 18 tests / 60 assertions
- `php artisan test tests/Feature/Filament/BookingResourceTest.php` — RED, 5 failing tests due to missing `BookingResource` / `ListBookings`
- `php artisan test tests/Feature/Filament/BookingResourceTest.php` — PASS, 6 tests / 31 assertions
- `php artisan test --filter DashboardPageTest` — PASS, 6 tests / 14 assertions
- `vendor/bin/pint app/Filament/Resources/BookingResource.php app/Filament/Resources/BookingResource/Pages/ListBookings.php app/Filament/Resources/BookingResource/Pages/ViewBooking.php app/Providers/Filament/TenantPanelProvider.php tests/Feature/Filament/BookingResourceTest.php` — PASS, style fixed
- `php artisan test tests/Feature/Filament/BookingResourceTest.php && php artisan test --filter DashboardPageTest && php artisan test --filter BookingServiceTest` — PASS, 30 tests / 105 assertions
- `php artisan test tests/Unit/ProcessAutoRefundsTest.php && php artisan test tests/Feature/NotificationDispatchTest.php && php artisan test --filter BookingServiceTest` — PASS, 32 tests / 86 assertions
- `php artisan test tests/Feature/Filament/BookingResourceTest.php && vendor/bin/pint --dirty` — PASS, 6 tests / 31 assertions; Pint changed 0 files
- `php artisan test && vendor/bin/pint --dirty --test` — PASS, 125 tests / 384 assertions; Pint reported 0 files need formatting
- `php artisan test tests/Unit/ProcessAutoRefundsTest.php && php artisan test tests/Feature/NotificationDispatchTest.php` — SAFETY NET PASS before verification-gap edits, 14 tests / 26 assertions
- `php artisan test tests/Unit/ProcessAutoRefundsTest.php && php artisan test tests/Feature/NotificationDispatchTest.php` — PASS after verification-gap tests, 16 tests / 33 assertions
- `vendor/bin/pint --dirty --test && php artisan test` — PASS, Pint reported 0 files need formatting; full suite passed 127 tests / 391 assertions

## Deviations / Notes

- No refund audit column was added because the design explicitly chose no new refund tracking column in this slice.
- Service-level refund scheduling remains limited to queueing the existing `booking:auto-refund` command for paid/partial bookings; PR 3 hardened the command path and idempotency coverage.
- Customer self-cancellation is blocked at the service boundary by rejecting `client` actors.
- PR 2 uses the existing `BookingService::cancelBooking()` boundary and does not add refund/notification hardening beyond that service call.
- Filament v5 in the installed project requires `Filament\Schemas\Schema`, `recordActions()`, and `Action::schema()`; the new resource uses those APIs even though older existing resources still use legacy signatures.
- PR 3 did not add new refund tracking columns; duplicate refund prevention continues to rely on `payment_status=refunded` after successful provider refund, matching the design.
- `ProcessAutoRefunds` now resolves `StripeService` through the container so command tests can inject the existing Stripe mock primitive while production still passes the tenant API key.
- Partial payments now receive the same refund-info cancellation copy as fully paid bookings because both are eligible for auto-refund.
- Direct verification-gap coverage now proves unpaid cancelled bookings process 0 provider refunds and keep `payment_status=unpaid`.
- Direct verification-gap coverage now proves business cancellation for a booking without `client_id` still persists cancellation audit data, queues the existing cancellation job, and the notification handler exits without sending or throwing.

## Remaining Tasks

- None — implementation tasks are complete and ready for final SDD verification.
