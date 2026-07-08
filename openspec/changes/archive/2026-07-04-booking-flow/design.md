# Design: Booking Flow (Creation + Guest Checkout)

## Technical Approach

Extend the existing read-only calendar into a full booking pipeline. A `booking_holds` table with TTL-based expiry holds slots during checkout, preventing double-booking at the database level. `BookingService` manages the hold lifecycle and converts holds to `Bookings`. `AvailabilityService` gains a hold-exclusion filter. Livewire components handle the multi-step UI (slot → guest form → confirm). A scheduled artisan command cleans expired holds.

## Architecture Decisions

| Decision | Choice | Alternatives | Rationale |
|----------|--------|--------------|-----------|
| Race Prevention | DB composite unique index on `booking_holds` | Application-level lock, Redis mutex | Database is the source of truth; index rejects duplicates without coordination; survives process crashes |
| Hold TTL | 10 minutes | 5 min (too short for form fill), 15 min (too long — slots locked unnecessarily) | Balances checkout time against slot lock duration |
| Service Layer | Dedicated `BookingService` | Methods on Livewire component, Eloquent scopes | Reusable from admin panel and future API; testable in isolation; follows existing `AvailabilityService` pattern |
| Livewire Flow | Multi-step within `BookingCalendar` | Separate `GuestBookingForm` component, full-page Livewire | Keeps state in one component; avoids prop-drilling; Blade `@if` toggles steps |
| Cleanup | Scheduled artisan command (`schedule:run`) | Queue job with delay, database event | Matches Laravel 13 conventions; `schedule:run` is already the project pattern; no queue dependency |
| Guest Fields | Required: name, email, phone | Optional phone, email-only | Spec requires all three; matches business need for contact |

## Data Flow

```
Slot Click
    │
    ▼
BookingService::createHold()
    │
    ├─→ INSERT booking_holds (expires_at = now + 10min)
    │       └─ unique index rejects if active hold exists
    │
    ▼
GuestForm displayed (hold_id stored in session/Livewire state)
    │
    ▼
Guest submits: name, email, phone
    │
    ▼
BookingService::confirmBooking(hold_id, guest_data)
    │
    ├─→ Verify hold active (expires_at > now)
    ├─→ CREATE booking (status=pending, payment_status=unpaid)
    ├─→ DELETE booking_hold
    │
    ▼
Redirect to confirmation / show success
```

**Availability filter (parallel flow):**
```
AvailabilityService::getAvailableSlots()
    │
    ├─→ Existing bookings filter (already exists)
    ├─→ NEW: Active holds filter (expires_at > now())
    │
    ▼
Slots returned without held or booked times
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `database/migrations/2026_07_04_000001_create_booking_holds_table.php` | Create | `booking_holds` table with tenant, employee, service, date, times, guest fields, expires_at. Composite unique index on `(tenant_id, employee_id, date, start_time, end_time)` |
| `app/Models/BookingHold.php` | Create | Eloquent model with `scopeActive` (expires_at > now), belongsTo relations, casts |
| `app/Services/BookingService.php` | Create | `createHold()`, `confirmBooking()`, `expireHolds()` — tenant-scoped |
| `app/Livewire/BookingCalendar.php` | Modify | Add slot click handler, step state, guest form properties, hold ID tracking |
| `app/Services/AvailabilityService.php` | Modify | Add active-holds exclusion query in `getAvailableSlots()` |
| `resources/views/livewire/booking-calendar.blade.php` | Modify | Add step transitions: slot grid → guest form → confirmation |
| `app/Console/Commands/CleanExpiredHolds.php` | Create | Artisan command: delete where `expires_at < now()`, tenant-scoped |
| `routes/console.php` | Modify | Schedule `CleanExpiredHolds` to run every minute |
| `resources/views/livewire/guest-booking-form.blade.php` | Create | Blade partial for guest info form (name, email, phone) |

## Interfaces / Contracts

### BookingService

```php
namespace App\Services;

class BookingService
{
    public function createHold(
        int $tenantId, int $employeeId, int $serviceId,
        string $date, string $startTime, string $endTime
    ): BookingHold;

    public function confirmBooking(
        int $holdId, int $tenantId,
        string $clientName, string $clientEmail, string $clientPhone
    ): Booking;

    public function expireHolds(): int; // returns count deleted
}
```

### BookingHold Model

```php
class BookingHold extends Model
{
    protected $fillable = [
        'tenant_id', 'employee_id', 'service_id',
        'date', 'start_time', 'end_time',
        'client_name', 'client_email', 'client_phone',
        'expires_at',
    ];

    protected $casts = [
        'date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'expires_at' => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }
}
```

### Livewire Properties (additions to BookingCalendar)

```php
public int $currentStep = 1;       // 1=slots, 2=guest form, 3=confirm
public ?int $holdId = null;
public string $clientName = '';
public string $clientEmail = '';
public string $clientPhone = '';

public function selectSlot(int $employeeId, string $start, string $end): void;
public function submitGuestForm(): void;
public function confirmBooking(): void;
public function cancelBooking(): void;  // back to step 1, release hold
```

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Unit | `BookingService::createHold()` creates record with correct TTL | Pest, assert DB row exists with `expires_at` |
| Unit | `BookingService::confirmBooking()` creates booking, deletes hold | Pest, assert booking + hold deleted |
| Unit | `BookingService::confirmBooking()` rejects expired hold | Pest, create expired hold, expect exception |
| Unit | Expired hold cleanup deletes old records | Pest, insert expired hold, run command, assert deleted |
| Unit | AvailabilityService excludes active holds | Pest, insert hold, assert slot marked unavailable |
| Integration | Hold unique constraint prevents double-hold | Pest, create hold, attempt second → expect unique violation |
| Integration | Full slot → confirm flow (hold + booking creation) | Pest, test service methods end-to-end |
| E2E | Slot click → form → confirm → booking visible | Livewire test: click slot, fill form, assert booking exists |

## Migration / Rollout

Single migration creates `booking_holds` table. Add `CleanExpiredHolds` to `routes/console.php` schedule. No data migration needed — new table only. Rollback: `php artisan migrate:rollback`, remove model/service/command/routes.

## Open Questions

- [ ] None — all decisions resolved from specs and proposal.
