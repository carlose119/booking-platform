# Exploration: Interactive Calendar & Booking Flow

## Current State

The booking-platform has a complete data model layer but **zero availability logic or public-facing booking UI**:

- **Models exist**: `Service` (with `duration_minutes`), `EmployeeSchedule` (day_of_week, start_time, end_time), `Booking` (date, start_time, end_time, employee_id), `User` (with role enum), `Tenant`
- **Pivot table**: `employee_services` links employees to services they offer
- **Filament admin panel**: ServiceResource, EmployeeScheduleResource, UserResource — all for BusinessAdmin CRUD. Tenant-scoped via `getEloquentQuery()`.
- **No public routes**: `routes/web.php` only has a welcome page. No controllers beyond the base `Controller.php`.
- **No availability calculation**: Zero code exists for generating time slots or checking conflicts.
- **No guest checkout flow**: No mechanism for unauthenticated users to book.
- **No frontend framework**: Vite + Tailwind 4 configured, but no Vue/React. Only `welcome.blade.php`.

### Data Flow (as-designed, not implemented)

```
Service (duration_minutes) + EmployeeSchedule (day, start, end)
    ↓
Generate time slots for a given date
    ↓
Filter out slots overlapping existing Bookings (status != cancelled)
    ↓
Present available slots per employee to the client
    ↓
Client selects slot → Guest checkout (name, email, phone)
    ↓
Create Booking (pending) → Stripe payment → Confirm
```

## Affected Areas

- `app/Models/Booking.php` — needs scopes for conflict queries, status filtering
- `app/Models/EmployeeSchedule.php` — needs relationship to filter by day_of_week
- `app/Models/Service.php` — already has `employees()` relationship (used for availability)
- `app/Http/Controllers/` — new public controllers for booking flow
- `routes/web.php` — new public routes (tenant-aware via slug)
- `resources/views/` — new Blade views for calendar UI
- `resources/js/` — possibly Alpine.js or Livewire for interactivity
- `database/migrations/` — possibly add index on bookings(date, employee_id, status) for performance
- `app/Enums/BookingStatus.php` — existing, no changes needed
- `app/Enums/PaymentStatus.php` — existing, no changes needed

## Availability Algorithm Analysis

### Core Algorithm: Slot Generation + Conflict Filtering

**Input**: `service_id`, `date`, `tenant_id`

**Step 1**: Resolve employee set
```
Employees = service.employees (from employee_services pivot)
           → filtered to those with EmployeeSchedule for date.day_of_week
```

**Step 2**: For each employee, generate candidate slots
```
For each EmployeeSchedule where day_of_week == date.day_of_week:
  current = schedule.start_time
  while current + service.duration_minutes <= schedule.end_time:
    candidate_slot = { start: current, end: current + duration }
    current += duration (or configurable step, e.g., 15min)
```

**Step 3**: Filter against existing bookings
```
available_slots = candidate_slots
  -> reject where ANY existing booking overlaps:
     booking.date == date
     AND booking.employee_id == employee.id
     AND booking.status NOT IN ('cancelled')
     AND booking.start_time < slot.end
     AND booking.end_time > slot.start
```

**Step 4**: Return per-employee availability map
```
{
  "employee_1": [{ start: "09:00", end: "09:30" }, ...],
  "employee_2": [{ start: "09:00", end: "09:30" }, ...]
}
```

### Key Design Decisions Needed

1. **Slot step interval**: Fixed at `service.duration_minutes` (simple) or configurable (e.g., every 15min for more flexibility)?
   - Recommendation: Use service duration as step for simplicity. A 30min service = slots every 30min.

2. **Buffer time between bookings**: Should there be a gap?
   - PRD doesn't mention it. Keep simple: no buffer by default. Can be added later via a `buffer_minutes` column on Service.

3. **Past-time filtering**: For today's date, filter out slots that have already passed.
   - Simple: `if (date == today) reject slots where start < now`.

## Approaches

### Approach 1: Livewire + Alpine.js (Recommended)

| Aspect | Detail |
|--------|--------|
| **Stack** | Laravel Livewire 3 + Alpine.js for micro-interactions |
| **Calendar UI** | Livewire component renders date picker → fetches slots via AJAX-like wire:model |
| **Guest checkout** | Blade form with Livewire validation, no auth required |
| **Pros** | Stays in Blade ecosystem, no JS build complexity, Livewire handles real-time slot refresh, works with existing Tailwind 4 setup |
| **Cons** | Requires Livewire dependency, slightly heavier page loads than pure API |
| **Effort** | Medium |

### Approach 2: SPA with Vue/React + API

| Aspect | Detail |
|--------|--------|
| **Stack** | Vue 3 or React SPA + Laravel API routes |
| **Calendar UI** | Full client-side calendar component |
| **Guest checkout** | SPA form → API → Stripe redirect |
| **Pros** | Smooth UX, no page reloads, modern feel |
| **Cons** | Requires JS framework setup (not in current stack), separate build pipeline, more complex architecture for a booking platform |
| **Effort** | High |

### Approach 3: Blade + Vanilla JS (no framework)

| Aspect | Detail |
|--------|--------|
| **Stack** | Blade views + vanilla JS or minimal Alpine.js |
| **Calendar UI** | Fetch API calls for slots, DOM manipulation |
| **Pros** | Zero new dependencies, simple |
| **Cons** | Manual state management, tedious, harder to maintain, poor UX compared to Livewire |
| **Effort** | Medium-High |

## Recommendation

**Approach 1: Livewire + Alpine.js** is the clear winner.

**Rationale**:
- The project already uses Filament (which is built on Livewire). Adding Livewire to public pages is a natural extension — same mental model, same ecosystem.
- Slot refresh on date/service/employee selection is trivial with `wire:model` and Livewire's reactive updates.
- Guest checkout form validation is handled server-side with Livewire's built-in validation.
- No separate JS build pipeline needed — Livewire + Alpine are already bundled with Filament.
- The `resources/js/app.js` and `resources/css/app.css` are already wired via Vite.

## Risks

### High Risk: Race Conditions (Double-Booking)

**Problem**: Two users select the same slot simultaneously. Both pass availability check. Both attempt to create bookings.

**Mitigation options**:
1. **Database-level**: Add a unique composite index on `(employee_id, date, start_time)` where status != cancelled — but can't filter by status in a unique index.
2. **Optimistic locking**: Add `version` column to bookings, use `WHERE version = X` on insert. Retry on conflict.
3. **Pessimistic locking**: `SELECT ... FOR UPDATE` on the booking row during the payment transaction. Lock the slot when payment starts.
4. **Redis lock**: Distributed lock on `{tenant}:{employee}:{date}:{start_time}` during the booking transaction.

**Recommendation**: Use **pessimistic locking with a hold table** — create a `booking_holds` table (tenant_id, employee_id, date, start_time, end_time, session_id, expires_at). When user selects a slot, insert a hold with 10-min TTL. Availability check excludes active holds. On payment success, convert hold to booking. On timeout, hold expires and slot becomes available again.

### Medium Risk: Performance at Scale

**Problem**: Availability query runs on every date/employee/service change. With many bookings, the overlap query could be slow.

**Mitigation**:
- Add composite index: `CREATE INDEX idx_bookings_availability ON bookings (tenant_id, employee_id, date, status, start_time, end_time)`
- Cache slot generation results for 30-60 seconds per (service, date) combination
- The EmployeeSchedule query is tiny (one row per day per employee), so slot generation is fast; the bottleneck is booking overlap checks.

### Medium Risk: Multi-Tenant Isolation

**Problem**: All availability queries MUST be tenant-scoped. A bug here leaks data across tenants.

**Mitigation**: Use Laravel's `BelongsToTenant` scope or explicit `where('tenant_id', $tenantId)` on every query. Add tests verifying cross-tenant isolation.

### Low Risk: No Existing Tests for Availability Logic

**Problem**: The critical path (availability calculation) has zero test coverage today. The config specifies 80% coverage target with availability as a critical path.

**Mitigation**: This change MUST include Pest tests for slot generation, conflict filtering, and edge cases (no schedule, past times, full day).

## Ready for Proposal

**Yes** — the exploration is complete. The orchestrator should proceed to `sdd-propose` with:

1. **Scope**: Public-facing booking calendar + guest checkout + double-booking prevention
2. **Tech choice**: Livewire 3 + Alpine.js for the calendar UI
3. **New entities**: `booking_holds` table for slot reservation during payment
4. **Critical algorithm**: Slot generation from EmployeeSchedule + conflict filtering against Bookings + Holds
5. **Performance**: Composite index on bookings for availability queries, optional Redis caching
6. **Testing**: Pest tests for availability calculation (critical path per config.yaml)
