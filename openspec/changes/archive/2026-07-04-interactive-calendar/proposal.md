# Proposal: Interactive Calendar (Slice 1: Availability + Calendar UI)

## Intent

The booking platform has complete data models but **zero availability logic or public-facing booking UI**. Customers cannot see available time slots or book services. This change implements the core availability algorithm and a public calendar interface, enabling customers to view real-time availability per employee/service/date.

## Scope

### In Scope (Slice 1)
- Availability algorithm: slot generation from EmployeeSchedule + conflict filtering against Bookings
- Livewire 3 + Alpine.js calendar component (date picker, service/employee selection, slot display)
- Public routes (tenant-aware via slug) for browsing availability
- Tenant isolation on all availability queries
- Composite database index for performance
- Pest tests for availability calculation (critical path)

### Out of Scope (deferred to Slices 2-3)
- Booking creation flow (guest checkout, payment)
- booking_holds table and race condition prevention
- Stripe payment integration
- Calendar UI for selecting slots (only viewing in Slice 1)
- Mobile-responsive optimizations beyond basic Tailwind

## Capabilities

### New Capabilities
- `public-booking-calendar`: Public-facing calendar UI for browsing available time slots per service, employee, and date

### Modified Capabilities
- `data-model`: Add composite index on bookings for availability queries
- `schedule-management`: Expose schedule data via public API for calendar rendering

## Approach

**Livewire 3 + Alpine.js** as recommended in exploration.

1. **Availability Service**: Create `App\Services\AvailabilityService` with:
   - `getAvailableSlots(serviceId, date, tenantId)`: returns per-employee slot map
   - Slot generation from EmployeeSchedule (day_of_week match)
   - Conflict filtering against Bookings (status != cancelled, overlap check)
   - Past-time filtering for today's date

2. **Livewire Component**: `App\Http\Livewire\BookingCalendar`:
   - Properties: `$selectedService`, `$selectedDate`, `$selectedEmployee`
   - Auto-refresh slots on property change via `wire:model`
   - Renders date picker, employee list, time slot grid

3. **Public Routes**: Add to `routes/web.php`:
   - `GET /{tenant}/book` → Calendar view
   - Tenant resolved via slug middleware

4. **Database**: Add composite index on `bookings`:
   ```sql
   CREATE INDEX idx_bookings_availability 
   ON bookings (tenant_id, employee_id, date, status, start_time, end_time)
   ```

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `app/Services/AvailabilityService.php` | New | Core availability algorithm |
| `app/Http/Livewire/BookingCalendar.php` | New | Public calendar component |
| `resources/views/livewire/booking-calendar.blade.php` | New | Calendar UI template |
| `routes/web.php` | Modified | Add public booking routes |
| `database/migrations/` | New | Add availability index |
| `app/Models/Booking.php` | Modified | Add scopes for availability queries |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Race conditions (double-booking) | High | Deferred to Slice 2 (booking_holds table) |
| Performance with many bookings | Medium | Composite index + optional Redis caching |
| Multi-tenant data leakage | Medium | Explicit tenant_id scope on all queries |
| No existing tests for availability | Low | Include Pest tests in this slice |

## Rollback Plan

1. Remove Livewire component and views
2. Remove public routes from `routes/web.php`
3. Drop composite index (optional, low impact)
4. Remove `AvailabilityService`
5. No data migration to revert (read-only slice)

## Dependencies

- Existing `EmployeeSchedule`, `Service`, `Booking` models (already implemented)
- Livewire 3 (already bundled with Filament)
- Tenant slug middleware (already exists)

## Success Criteria

- [ ] Availability algorithm correctly generates slots from EmployeeSchedule
- [ ] Conflict filtering excludes booked time slots
- [ ] Past-time slots filtered for today's date
- [ ] Calendar UI renders available slots per employee/date
- [ ] Tenant isolation verified (no cross-tenant data leakage)
- [ ] Composite index improves query performance
- [ ] Pest tests cover: slot generation, conflict filtering, edge cases (no schedule, full day)

## Proposal Question Round

Since this is a sub-agent execution, I'm flagging these product/PRD questions for orchestrator review before finalizing:

1. **Slot step interval**: Should slots be generated at fixed intervals (e.g., every 15 minutes) or at service-duration intervals (a 30min service = slots every 30min)?
   - *Current assumption*: Use service.duration_minutes as step (simpler, matches service length)

2. **Buffer time between bookings**: Should there be a configurable buffer between consecutive bookings for the same employee?
   - *Current assumption*: No buffer by default. Can add `buffer_minutes` column to Service later.

3. **Past-time filtering**: For today's date, should we filter out slots that have already passed, or show them as unavailable?
   - *Current assumption*: Filter out past slots (user cannot book in the past).

4. **Calendar UI scope**: Slice 1 is read-only (viewing slots only). Should the UI include a "Select" button that does nothing yet, or just display slots?
   - *Current assumption*: Display-only with visual indication of available vs booked slots.

5. **Employee selection**: If a service has multiple employees, should the calendar show all employees' slots at once, or require the user to pick an employee first?
   - *Current assumption*: Show all employees' slots in separate columns/sections.
