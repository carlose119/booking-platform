# Tasks: Business Cancellation Wiring

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 650-900 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 data/service → PR 2 tenant UI/link → PR 3 refund/notification hardening |
| Delivery strategy | stacked-to-main |
| Chain strategy | stacked-to-main |

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Cancellation audit columns and `BookingService::cancelBooking()` | PR 1 | Include service tests for success, duplicate skip, and tenant denial. |
| 2 | Tenant `BookingResource`, pages, registration, dashboard link | PR 2 | Depends on PR 1; include Filament tenant-scope/action tests. |
| 3 | Refund and notification idempotency coverage | PR 3 | Depends on PR 1; include command and notification tests. |

## Phase 1: Data and Service Foundation

- [x] 1.1 RED: Extend `tests/Unit/BookingServiceTest.php` for own-tenant cancel, duplicate no-op, and cross-tenant denial.
- [x] 1.2 Create `database/migrations/*_add_cancellation_audit_to_bookings.php` with nullable `cancellation_reason` and `cancelled_by_user_id` FK.
- [x] 1.3 Update `app/Models/Booking.php` fillable/casts and add `cancelledBy()` relationship.
- [x] 1.4 GREEN: Add `BookingService::cancelBooking(int $bookingId, int $tenantId, int $actorUserId, string $reason)` in `app/Services/BookingService.php`.

## Phase 2: Tenant UI Wiring

- [x] 2.1 RED: Create `tests/Feature/Filament/BookingResourceTest.php` for active-tenant list scope and cancel modal validation.
- [x] 2.2 Create `app/Filament/Resources/BookingResource.php` with tenant-scoped table/view data and business-admin cancel action.
- [x] 2.3 Create `app/Filament/Resources/BookingResource/Pages/ListBookings.php` and `ViewBooking.php` using resource actions.
- [x] 2.4 Register `BookingResource` in `app/Providers/Filament/TenantPanelProvider.php`.
- [x] 2.5 Update `resources/views/filament/widgets/quick-actions-widget.blade.php` to use `BookingResource::getUrl('index')`.

## Phase 3: Refund and Notification Integration

- [x] 3.1 RED: Extend `tests/Unit/ProcessAutoRefundsTest.php` for cancelled paid/partial eligibility and duplicate refund skip.
- [x] 3.2 Update `app/Console/Commands/ProcessAutoRefunds.php` to process paid/partial cancelled bookings idempotently.
- [x] 3.3 Extend `tests/Feature/NotificationDispatchTest.php` for one cancelled notification with reason and applicable refund info.
- [x] 3.4 Wire `BookingService::cancelBooking()` to dispatch `SendBookingNotification($booking, 'cancelled', $reason)` once.

## Phase 4: Verification and Cleanup

- [x] 4.1 Run focused tests: `BookingServiceTest`, `BookingResourceTest`, `ProcessAutoRefundsTest`, `NotificationDispatchTest`.
- [x] 4.2 Run `php artisan test` and `vendor/bin/pint --dirty`; fix regressions without adding customer cancellation or reschedule flow.
