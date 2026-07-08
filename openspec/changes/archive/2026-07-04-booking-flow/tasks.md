# Tasks: Booking Flow (Creation + Guest Checkout)

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 450–520 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 → PR 2 → PR 3 |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Migration + model + service + availability filter | PR 1 | base: main. Core infrastructure, no UI. Tests included. |
| 2 | Livewire booking flow (multi-step + guest form) | PR 2 | base: PR 1 branch. Depends on PR 1 services. |
| 3 | Cleanup command + route schedule + tests | PR 3 | base: PR 2 branch. Standalone command + integration tests. |

## Phase 1: Migration + Model

- [x] 1.1 Create `database/migrations/2026_07_04_000001_create_booking_holds_table.php` — columns: tenant_id, employee_id, service_id, date, start_time, end_time, client_name, client_email, client_phone, expires_at. Composite unique index on `(tenant_id, employee_id, date, start_time, end_time)`.
- [x] 1.2 Create `app/Models/BookingHold.php` — fillable fields, casts (date, datetime:H:i, datetime), `scopeActive()` where expires_at > now, belongsTo relations for tenant/employee/service.
- [x] 1.3 Run `php artisan migrate` to verify migration applies cleanly.

## Phase 2: BookingService

- [x] 2.1 Create `app/Services/BookingService.php` — `createHold()` inserts booking_holds row with expires_at = now + 10min. Let unique constraint throw on conflict.
- [x] 2.2 Add `confirmBooking(holdId, tenantId, clientName, clientEmail, clientPhone)` — verify hold active, create Booking (status=pending, payment_status=unpaid), delete hold.
- [x] 2.3 Add `expireHolds()` — delete where expires_at < now(), return count. Tenant-scoped.

## Phase 3: AvailabilityService Update

- [x] 3.1 In `app/Services/AvailabilityService.php` `getAvailableSlots()`, add query for active holds: `BookingHold::where('tenant_id', $tenantId)->whereIn('employee_id', $employeeIds)->whereDate('date', $date)->active()->get()`.
- [x] 3.2 Pass holds into `filterConflicts()` or add parallel `filterHoldConflicts()` to mark slots with active holds as unavailable.

## Phase 4: Livewire Booking Flow

- [x] 4.1 Modify `app/Livewire/BookingCalendar.php` — add properties: `$currentStep`, `$holdId`, `$clientName`, `$clientEmail`, `$clientPhone`. Inject `BookingService`.
- [x] 4.2 Add `selectSlot()` method — calls `BookingService::createHold()`, sets `$holdId`, advances to step 2. On failure, flash error.
- [x] 4.3 Add `submitGuestForm()` — validates name/email/phone required, advances to step 3.
- [x] 4.4 Add `confirmBooking()` — calls `BookingService::confirmBooking()`, shows success, resets to step 1.
- [x] 4.5 Add `cancelBooking()` — deletes hold (set expires_at = now), resets to step 1.
- [x] 4.6 Create `resources/views/livewire/guest-booking-form.blade.php` — form fields for name, email, phone with wire:model bindings and validation errors.
- [x] 4.7 Modify `resources/views/livewire/booking-calendar.blade.php` — add `@if` step transitions: step 1 = slot grid (existing), step 2 = `@include('livewire.guest-booking-form')`, step 3 = confirmation message.

## Phase 5: Cleanup Command + Scheduling

- [x] 5.1 Create `app/Console/Commands/CleanExpiredHolds.php` — artisan command that calls `BookingService::expireHolds()`, logs count deleted.
- [x] 5.2 Modify `routes/console.php` — schedule `CleanExpiredHolds` to run every minute.

## Phase 6: Tests

- [x] 6.1 Unit test: `BookingService::createHold()` creates record with expires_at = now + 10min.
- [x] 6.2 Unit test: `BookingService::confirmBooking()` creates booking with status=pending, payment_status=unpaid, deletes hold.
- [x] 6.3 Unit test: `BookingService::confirmBooking()` rejects expired hold (throws exception).
- [x] 6.4 Unit test: `CleanExpiredHolds` command deletes expired holds, keeps active ones.
- [x] 6.5 Unit test: `AvailabilityService` excludes slots with active holds.
- [x] 6.6 Integration test: Unique constraint prevents second active hold on same slot.
- [x] 6.7 Integration test: Full slot → confirm flow creates booking end-to-end.
- [x] 6.8 Livewire test: Slot click → form → confirm → booking exists in DB.
