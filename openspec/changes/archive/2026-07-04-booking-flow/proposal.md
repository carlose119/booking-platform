# Proposal: Booking Flow (Creation + Guest Checkout)

## Intent

Users can browse available slots but cannot book them — zero booking creation logic exists. This change adds the core booking flow: hold a slot with race-condition prevention, collect guest info, and create the booking. Payment integration is deferred to a later slice.

## Scope

### In Scope
- `booking_holds` table with TTL-based expiry for race condition prevention
- `BookingService` for hold creation, guest info collection, and hold-to-booking conversion
- `AvailabilityService` update to exclude active holds from slot availability
- Guest checkout form (name, email, phone) via Livewire
- Slot selection → booking form transition in `BookingCalendar` component
- Scheduled command to clean expired holds

### Out of Scope
- Payment processing (Stripe integration — Slice 3)
- Reservation modification or cancellation flow
- Authenticated user checkout
- SMS/email notifications for booking confirmation

## Capabilities

### New Capabilities
- `booking-holds`: TTL-based hold table to prevent double-booking during checkout; includes cleanup job and AvailabilityService exclusion
- `guest-checkout`: Guest booking form (name, email, phone) that creates a confirmed booking from an active hold

### Modified Capabilities
- `public-booking-calendar`: Slot selection now triggers hold creation and transitions to booking form (previously read-only)

## Approach

1. **`booking_holds` migration**: tenant_id, employee_id, service_id, date, start_time, end_time, guest fields, expires_at. Composite unique index on `(tenant_id, employee_id, date, start_time, end_time)` where `expires_at > now()` — database rejects second hold.
2. **`BookingService`**: `createHold()`, `confirmBooking()`, `expireHolds()`. All tenant-scoped.
3. **`AvailabilityService` update**: add hold exclusion filter (one-line WHERE clause).
4. **Scheduled command**: `CleanExpiredHolds` runs every minute, deletes where `expires_at < now()`.
5. **Livewire flow**: Slot click → `BookingService::createHold()` → render guest form → confirm → booking created with `status=pending`, `payment_status=unpaid`.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `database/migrations/` | New | `create_booking_holds_table` migration |
| `app/Models/BookingHold.php` | New | Eloquent model with expiry scope |
| `app/Services/BookingService.php` | New | Hold lifecycle + booking creation |
| `app/Services/AvailabilityService.php` | Modified | Exclude active holds from slots |
| `app/Livewire/BookingCalendar.php` | Modified | Slot selection + form transition |
| `app/Livewire/GuestBookingForm.php` | New | Guest info form component |
| `app/Console/Commands/CleanExpiredHolds.php` | New | Scheduled cleanup command |
| `routes/web.php` | Modified | New hold/confirm routes |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Two users hold same slot simultaneously | Low | Composite unique index rejects duplicate |
| Hold created but user abandons checkout | High | TTL auto-expires, cleanup job runs every minute |
| Guest submits invalid phone/email | Med | Server-side validation on confirm |
| Expired holds accumulate faster than cleanup | Low | Index on expires_at; runs every 60s |

## Rollback Plan

1. Remove `BookingHold` model and migration
2. Remove `BookingService` and `CleanExpiredHolds` command
3. Revert `AvailabilityService` hold-exclusion filter
4. Revert `BookingCalendar` to read-only mode
5. Rollback migration: `php artisan migrate:rollback`

## Dependencies

- Existing `Booking` model with guest fields (already in data-model spec)
- Laravel Scheduler configured for `CleanExpiredHolds`

## Success Criteria

- [ ] Two users selecting the same slot simultaneously → second hold rejected at DB level
- [ ] Expired holds cleaned within 60 seconds
- [ ] Guest checkout creates booking with correct `status=pending`, `payment_status=unpaid`
- [ ] Availability slots exclude active (non-expired) holds
- [ ] Tenant isolation maintained — no cross-tenant holds visible

## Proposal Question Round

The following questions are meant to improve the proposal by uncovering business rules, implications, and edge cases. Please answer, skip, or correct:

1. **Hold TTL**: Is 10 minutes reasonable for guest checkout, or should it be shorter (5 min) / longer (15 min)?
2. **Guest fields**: Should `client_name` be optional (email-only checkout), or required for all bookings?
3. **Concurrent hold limit**: Should we cap how many active holds a single IP can have (e.g., max 3) to prevent abuse?
4. **Booking status on creation**: Should new bookings start as `pending` (awaiting admin approval) or `confirmed` (auto-confirmed)?
5. **Hold expiry UX**: When a hold expires mid-checkout, should the user see an error and return to calendar, or auto-refresh available slots?
