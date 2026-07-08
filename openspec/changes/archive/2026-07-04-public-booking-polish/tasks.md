# Tasks: Public Booking Polish

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 350-480 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 (calendar/slots) → PR 2 (checkout/confirmation/tests) |
| Delivery strategy | ask-on-risk |
| Chain strategy | stacked-to-main |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Polish stepper, filters, slot cards, loading, empty/conflict states | PR 1 | Base main; verify slot selection and no-slot scenarios |
| 2 | Polish guest form, payment wrapper copy, confirmation summary, tests | PR 2 | Base PR 1 branch or main after PR 1; verify checkout/payment paths |

## Phase 1: Foundation / Safe Presentation Helpers

- [x] 1.1 Review `resources/views/livewire/booking-calendar.blade.php` state blocks and keep all existing `wire:*` method names unchanged.
- [x] 1.2 Add presentation-only computed helper(s) in `app/Livewire/BookingCalendar.php` only if needed for confirmation service/date/time labels; no mutations.
- [x] 1.3 Keep `createPaymentIntent()`, Stripe metadata, booking statuses, holds, and notification flow untouched.

## Phase 2: Calendar Selection UX

- [x] 2.1 Update `resources/views/livewire/booking-calendar.blade.php` step/progress indicator with clear current/completed states for steps 1-4.
- [x] 2.2 Convert available slot rendering in `resources/views/livewire/booking-calendar.blade.php` to stacked mobile employee cards with large slot buttons.
- [x] 2.3 Add `wire:loading`, disabled states, and loading text/spinners to service/date/employee filters and slot buttons.
- [x] 2.4 Improve empty and hold-conflict panels in `resources/views/livewire/booking-calendar.blade.php`; preserve refreshed availability and no record creation.

## Phase 3: Checkout and Confirmation UX

- [x] 3.1 Improve guest form labels, spacing, inputs, radio controls, and inline validation errors in `resources/views/livewire/booking-calendar.blade.php`.
- [x] 3.2 Add disabled/loading feedback to the guest submit and cancel actions without changing `submitGuestForm()` or `cancelBooking()` semantics.
- [x] 3.3 Improve expired-hold and payment-error copy in the Blade state panels; do not modify Stripe internals.
- [x] 3.4 Improve confirmation view in `resources/views/livewire/booking-calendar.blade.php` to show service, date/time, guest contact, and next-step guidance.

## Phase 4: Focused Tests / Verification

- [x] 4.1 Update `tests/Feature/BookingCalendarTest.php` assertions for slot cards, no-slot empty state, loading-safe labels, and unchanged hold creation.
- [x] 4.2 Update `tests/Feature/BookingCalendarTest.php` for guest validation proximity, expired-hold recovery, confirmation summary, and no booking on invalid data.
- [x] 4.3 Update `tests/Feature/BookingWithPaymentTest.php` to assert payment-step polish while preserving payment/no-payment branching and Stripe mocks.
- [x] 4.4 Run focused tests: `php artisan test tests/Feature/BookingCalendarTest.php tests/Feature/BookingWithPaymentTest.php`.

## Phase 5: Cleanup

- [x] 5.1 Remove any temporary copy/selectors and ensure no `resources/views/livewire/guest-booking-form.blade.php` is introduced.
- [x] 5.2 Run formatting if PHP changes were made: `./vendor/bin/pint app/Livewire/BookingCalendar.php tests/Feature/BookingCalendarTest.php tests/Feature/BookingWithPaymentTest.php`.
