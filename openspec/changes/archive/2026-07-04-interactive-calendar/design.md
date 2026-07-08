# Design: Interactive Calendar (Slice 1: Availability + Calendar UI)

## Technical Approach

Build a public-facing calendar that displays available time slots per service, employee, and date. The core is `AvailabilityService` — a standalone class that generates slots from `EmployeeSchedule` and filters conflicts against `Bookings`. A Livewire 3 component with Alpine.js handles the reactive UI. Public routes use tenant slug for isolation.

## Architecture Decisions

| Decision | Choice | Alternatives | Rationale |
|----------|--------|--------------|-----------|
| UI Framework | Livewire 3 + Alpine.js | Vue/React SPA, Inertia.js | Already bundled with Filament; reactive without API layer; matches Laravel conventions |
| Availability Logic | Standalone Service class | Livewire component method, Eloquent scope | Reusable across admin panels and API; testable in isolation; follows SRP |
| Slot Generation | Duration-based (service.duration_minutes) | Fixed 15-min intervals | Simpler; slots match service length; avoids irrelevant time gaps |
| Route Pattern | `/{tenant}/book` with slug middleware | Subdomain-based tenanting | Existing Tenant model has slug field; simpler infrastructure; no DNS config |
| Index Strategy | Composite index on bookings | Partial index, materialized view | Covers the exact query pattern (tenant+employee+date+status+range); minimal overhead |

## Data Flow

```
User selects: Service → Date → (optional) Employee
                │
                ▼
┌─────────────────────────────────────┐
│  BookingCalendar Livewire Component │
│  - wire:model bindings              │
│  - Alpine.js for slot interaction   │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│  AvailabilityService                │
│  1. Get EmployeeSchedule by day     │
│  2. Generate slots from schedule    │
│  3. Filter by service duration      │
│  4. Remove conflicts (Bookings)     │
│  5. Remove past times (today)       │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│  Database Queries                   │
│  - EmployeeSchedule (day_of_week)   │
│  - Bookings (composite index)       │
│  - Service (duration_minutes)       │
└─────────────────────────────────────┘
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `app/Services/AvailabilityService.php` | Create | Core availability algorithm: slot generation + conflict filtering |
| `app/Http/Livewire/BookingCalendar.php` | Create | Public calendar component with reactive property bindings |
| `resources/views/livewire/booking-calendar.blade.php` | Create | Calendar UI template with date picker, employee list, slot grid |
| `routes/web.php` | Modify | Add `/{tenant}/book` public route with tenant resolution |
| `database/migrations/xxxx_add_availability_index_to_bookings.php` | Create | Composite index `(tenant_id, employee_id, date, status, start_time, end_time)` |
| `app/Models/Booking.php` | Modify | Add query scopes for availability filtering |

## Interfaces / Contracts

### AvailabilityService

```php
namespace App\Services;

class AvailabilityService
{
    /**
     * Get available time slots for a service on a given date.
     * Returns array keyed by employee_id, values are arrays of slot objects.
     */
    public function getAvailableSlots(
        int $serviceId,
        string $date,
        ?int $tenantId = null
    ): array;

    /**
     * Generate raw slots from schedule (before conflict filtering).
     */
    protected function generateSlotsFromSchedule(
        EmployeeSchedule $schedule,
        int $durationMinutes
    ): array;
}
```

### Slot Data Structure

```php
[
    'employee_id' => 1,
    'employee_name' => 'Jane Doe',
    'slots' => [
        ['start' => '09:00', 'end' => '09:30', 'available' => true],
        ['start' => '09:30', 'end' => '10:00', 'available' => false], // booked
        // ...
    ]
]
```

### Livewire Component Properties

```php
class BookingCalendar extends Component
{
    public ?int $tenantId = null;
    public ?int $selectedService = null;
    public ?string $selectedDate = null;
    public ?int $selectedEmployee = null;
    public array $services = [];
    public array $availableSlots = [];

    public function updatedSelectedService(): void { /* refresh slots */ }
    public function updatedSelectedDate(): void { /* refresh slots */ }
    public function updatedSelectedEmployee(): void { /* refresh slots */ }
}
```

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Unit | Slot generation from schedule | Test `generateSlotsFromSchedule()` with various schedules |
| Unit | Conflict filtering | Test that booked slots are removed, cancelled slots kept |
| Unit | Past-time filtering | Test today's date filters past slots |
| Unit | Edge cases | No schedule, full day booked, employee with no service |
| Integration | Full availability query | `getAvailableSlots()` with real DB (Pest + factories) |
| Integration | Tenant isolation | Verify cross-tenant data never leaks |
| E2E | Calendar renders slots | Livewire test: select service+date, assert slot display |

## Migration / Rollout

No data migration required (read-only slice). Single migration adds composite index. Rollback: drop index, remove component/routes/service.

## Open Questions

- [ ] Should slots show employee names or just time ranges when viewing a specific employee?
- [ ] Should the calendar default to today or require explicit date selection?
