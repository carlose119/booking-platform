# Tasks: Interactive Calendar (Slice 1: Availability + Calendar UI)

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 400-520 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 (AvailabilityService + Migration) → PR 2 (Livewire + Routes) → PR 3 (Tests) |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | AvailabilityService + composite index migration | PR 1 | Base: main. Core algorithm + database performance. Tests included. |
| 2 | Livewire component + Blade view + routes | PR 2 | Base: PR 1 branch. UI layer depends on AvailabilityService. |
| 3 | Pest tests for full integration | PR 3 | Base: PR 2 branch. Integration tests covering all scenarios. |

## Phase 1: AvailabilityService (Core Algorithm)

- [x] 1.1 Create `app/Services/AvailabilityService.php` with `getAvailableSlots(int $serviceId, string $date, ?int $tenantId = null): array` method signature
- [x] 1.2 Implement `generateSlotsFromSchedule(EmployeeSchedule $schedule, int $durationMinutes): array` — generate time slots from schedule start/end times using service duration as step
- [x] 1.3 Implement conflict filtering — query Bookings where `status != 'cancelled'` and time overlap (`start_time < slot.end AND end_time > slot.start`)
- [x] 1.4 Implement past-time filtering for today's date — skip slots where `slot.end <= now`
- [x] 1.5 Add tenant isolation — scope all queries by `$tenantId` (use auth tenant if null)

## Phase 2: Migration + Index

- [x] 2.1 Create migration `database/migrations/xxxx_add_availability_index_to_bookings.php`
- [x] 2.2 Add composite index `idx_bookings_availability` on `(tenant_id, employee_id, date, status, start_time, end_time)`
- [x] 2.3 Add scopes to `app/Models/Booking.php`: `scopeForAvailability($query, $tenantId, $employeeId, $date)` and `scopeActive($query)` (status != cancelled)

## Phase 3: Livewire Component + Blade View

- [x] 3.1 Create `app/Livewire/BookingCalendar.php` with properties: `$tenantId`, `$selectedService`, `$selectedDate`, `$selectedEmployee`, `$services`, `$availableSlots`
- [x] 3.2 Implement `updatedSelectedService()`, `updatedSelectedDate()`, `updatedSelectedEmployee()` — call AvailabilityService and refresh `$availableSlots`
- [x] 3.3 Create `resources/views/livewire/booking-calendar.blade.php` — date picker, service dropdown, employee list, slot grid with available/booked styling
- [x] 3.4 Wire blade to Livewire: component renders via `@livewire('booking-calendar', ['tenantId' => $tenant->id])`

## Phase 4: Routes + Controller

- [x] 4.1 Add public route `GET /{tenant}/book` to `routes/web.php` with tenant slug middleware
- [x] 4.2 Create route closure or controller method that resolves tenant by slug and renders booking page with Livewire component

## Phase 5: Tests

- [x] 5.1 Unit test: `generateSlotsFromSchedule()` returns correct slots for a given schedule and duration
- [x] 5.2 Unit test: conflict filtering removes booked slots, keeps cancelled ones
- [x] 5.3 Unit test: past-time filtering removes earlier slots for today's date
- [x] 5.4 Unit test: edge case — no schedule returns empty array
- [x] 5.5 Unit test: edge case — full day booked returns all slots unavailable
- [x] 5.6 Integration test: `getAvailableSlots()` with real DB, verify tenant isolation (no cross-tenant leakage)
- [x] 5.7 Livewire test: select service + date → assert slot grid renders with correct availability
