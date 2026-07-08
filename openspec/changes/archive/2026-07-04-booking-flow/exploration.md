# Exploration: Booking Flow

## Current State

The booking-platform has a **slot viewing layer** but **zero booking creation logic**:

- **AvailabilityService** (`app/Services/AvailabilityService.php`): Generates time slots from `EmployeeSchedule` and filters conflicts against existing `Booking` records. Works correctly for display purposes.
- **BookingCalendar** (`app/Livewire/BookingCalendar.php`): Livewire component that renders available slots per employee. User selects service + date → slots appear. No selection or booking action exists.
- **Booking model** (`app/Models/Booking.php`): Full schema with `status` (pending/confirmed/cancelled/completed), `payment_status` (unpaid/paid/refunded/partial), `stripe_payment_intent_id`, and guest fields (`client_name`, `client_email`, `client_phone`).
- **Enums**: `BookingStatus` and `PaymentStatus` are complete.
- **Routes**: Only `/{tenant}/book` → renders calendar view. No booking creation endpoints.
- **No holds table**, no `BookingService`, no payment integration, no guest checkout form.

## Affected Areas

- `app/Livewire/BookingCalendar.php` — needs slot selection → booking form transition
- `app/Services/AvailabilityService.php` — must exclude active holds from availability (race condition prevention)
- `app/Models/Booking.php` — may need scopes for hold-to-booking conversion
- `app/Http/Controllers/BookingController.php` — needs new actions for hold creation, confirmation
- `routes/web.php` — new routes for booking flow (hold, confirm, webhook)
- `database/migrations/` — new `booking_holds` table
- `resources/views/livewire/` — new Livewire component for booking form + hold logic
- `app/Services/StripeService.php` — new service for payment intent creation (deferred but schema-ready)
- `app/Console/Kernel.php` — scheduled job to clean expired holds

## Approaches

### 1. Booking Holds Table with TTL

Insert a hold record when user selects a slot. Availability check excludes active holds. On payment success, convert hold to booking. Hold expires after configurable TTL (e.g., 10 min).

| Aspect | Detail |
|--------|--------|
| **Pros** | Natural fit with payment flow, clear state transitions, scales well, easy TTL tuning, automatic cleanup via scheduled job |
| **Cons** | Requires new table + migration, cleanup job, slightly more complex than simple locking |
| **Effort** | Medium |

### 2. Pessimistic DB Locking (SELECT FOR UPDATE)

Lock the slot row during payment transaction. Prevents concurrent access but holds the lock until payment completes.

| Aspect | Detail |
|--------|--------|
| **Pros** | Simple, no new table needed |
| **Cons** | Lock held during payment (could be seconds/minutes), doesn't scale well, complex transaction management, not ideal for payment flows with external webhooks |
| **Effort** | Low |

### 3. Optimistic Locking (version column)

Add `version` column to bookings, use `WHERE version = X` on insert. Retry on conflict.

| Aspect | Detail |
|--------|--------|
| **Pros** | No locks, simple schema change |
| **Cons** | User sees "try again" error on conflict (bad UX), retry logic adds complexity, doesn't prevent the attempt — only catches it after |
| **Effort** | Low |

## Recommendation

**Approach 1: Booking Holds Table with TTL** — this is the clear winner.

**Rationale**:
- The PRD explicitly requires: "El sistema debe bloquear inmediatamente el espacio de tiempo seleccionado al iniciar la transacción de pago." A hold table implements this naturally.
- Holds integrate cleanly with the payment flow: hold → payment → booking (or hold expires → slot available).
- `AvailabilityService` already queries bookings to filter conflicts — adding a hold exclusion is a one-line change.
- Expired holds clean up automatically via a scheduled Artisan command.
- Scales better than pessimistic locks (no database-level locking contention).
- The previous exploration (`2026-07-04-interactive-calendar`) already recommended this approach.

## Risks

- **Race condition edge case**: Two users select the same slot within the same millisecond. Mitigation: unique composite index on `(tenant_id, employee_id, date, start_time, end_time)` with `expires_at > now()` — database rejects the second insert.
- **Hold cleanup performance**: At scale, expired holds accumulate. Mitigation: Scheduled job every minute, indexed on `expires_at`.
- **Payment failure after hold**: User gets a hold, payment fails. Mitigation: Hold auto-expires; user can retry.
- **Guest checkout validation**: No auth means no rate limiting on hold creation. Mitigation: Rate limit by IP, configurable hold limit per IP.

## Ready for Proposal

**Yes** — the exploration is complete. The orchestrator should proceed to `sdd-propose` with:
1. **Scope**: Booking creation flow with guest checkout, hold-based race condition prevention, payment integration points
2. **New entity**: `booking_holds` table with TTL-based expiry
3. **New service**: `BookingService` for hold creation, conversion, and cleanup
4. **Availability modification**: Exclude active holds from slot availability
5. **Payment schema**: `stripe_payment_intent_id` on Booking (already exists), webhook route (deferred implementation)
6. **Testing**: Pest tests for hold creation, expiry, double-booking prevention, guest checkout validation
