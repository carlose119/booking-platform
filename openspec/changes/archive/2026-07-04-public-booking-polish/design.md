# Design: Public Booking Polish

## Technical Approach

Polish the existing `BookingCalendar` Livewire surface in place. The implementation stays UX-only: Blade/Tailwind/Livewire attributes improve mobile slot cards, progress clarity, loading/disabled states, guest form spacing, validation proximity, recoverable errors, payment retry copy, and confirmation summary. Booking, hold, payment, Stripe, notification, and tenant-isolation behavior remain unchanged.

## Architecture Decisions

| Option | Tradeoff | Decision |
|---|---|---|
| Keep one Livewire Blade component vs. split new child components | One file remains larger, but review scope and behavior risk stay low | Keep `resources/views/livewire/booking-calendar.blade.php`; no new component files |
| Replace slot table with responsive cards vs. preserve table | Cards improve mobile touch targets; table semantics are less important for this public flow | Render filtered availability as stacked employee cards with large slot buttons |
| PHP changes for presentation helpers only vs. no PHP changes | Confirmation needs stable service/date/time labels; helpers avoid duplicating queries in Blade | Add computed/read-only summary helpers only if Blade cannot express the state cleanly |
| Touch Stripe JS/payment internals vs. polish wrapper states | Stripe changes would risk payment semantics | Do not change Stripe API calls, PaymentIntent creation, webhook assumptions, or payment statuses |

## Data Flow

Existing behavior remains the source of truth:

    Guest filters ──wire:model.live──> BookingCalendar state
         │                                 │
         └── availableSlots computed <─────┘
    Slot button ──selectSlot──> BookingService::createHold ──> Step 2
    Guest form ──submitGuestForm──> confirmBooking ──> Step 3 payment or Step 4 confirmation

The view only changes how states are presented: mobile cards, disabled/loading affordances, inline validation, empty/error panels, and confirmation summary.

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `resources/views/livewire/booking-calendar.blade.php` | Modify | Main UX polish: responsive stepper, slot cards, empty/error states, form controls, loading buttons, confirmation summary |
| `resources/views/livewire/guest-booking-form.blade.php` | No-op | File is not present; guest form is embedded in `booking-calendar.blade.php` |
| `app/Livewire/BookingCalendar.php` | Modify if needed | Add presentation-only computed helpers/validation labels for confirmation and clearer errors; no service/payment behavior changes |
| `tests/Feature/BookingCalendarTest.php` | Modify | Assert rendering, empty state, validation, hold conflict/expiry copy, and slot selection state preservation |
| `tests/Feature/BookingWithPaymentTest.php` | Modify | Preserve no-payment/payment branching and assert payment-step polish without changing Stripe mocks |

## Interfaces / Contracts

No public API, database, event, queue, or payment contract changes.

Allowed Livewire contract additions are presentation-only, for example:

```php
#[Computed]
public function confirmationSummary(): array
```

This helper may read the confirmed booking for display, but MUST NOT mutate booking, hold, payment, or notification state.

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Unit | Presentation helpers, if added | PHPUnit assertions on computed output using seeded booking data |
| Integration | Slot cards, empty state, validation, hold expiry/conflict, step transitions | Existing Livewire feature tests with stable text/selectors and database assertions for unchanged records |
| E2E | Not required for this slice | Feature tests cover server-rendered Livewire behavior; no browser-only behavior except existing Stripe JS |

## Migration / Rollout

No migration required. Rollout is a single low-risk view/component/test change. Rollback by reverting these files.

## Open Questions

- [ ] None blocking. Product copy can be refined later without changing the implementation shape.
