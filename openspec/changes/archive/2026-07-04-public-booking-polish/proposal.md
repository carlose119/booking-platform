# Proposal: Public Booking Polish

## Intent

Improve the public booking flow's mobile usability and state clarity without changing booking, hold, payment, or Stripe semantics. The current single Livewire surface works functionally, but slot selection, form controls, loading actions, empty states, and confirmation feedback need clearer UX for guests.

## Scope

### In Scope
- Mobile-first slot layout using cards or responsive improvements.
- Step/progress clarity across service/date/slot, guest details, payment/no-payment, and confirmation states.
- Loading and disabled states for Livewire actions, especially slot selection and confirmation.
- Better empty/error states for no availability, hold conflicts, expired holds, and validation.
- Touch-friendly guest form controls and confirmation page polish.

### Out of Scope
- Payment policy changes, Stripe PaymentIntent/webhook/refund internals, or booking status semantics.
- New booking capabilities, admin changes, notifications, or data model changes.
- Large redesign beyond the first UX polish slice.

## Capabilities

### New Capabilities
- None.

### Modified Capabilities
- `public-booking-calendar`: clarify responsive slot presentation, progress cues, loading states, and empty/error feedback while preserving availability behavior.
- `guest-checkout`: improve guest form touch usability and confirmation-state presentation while preserving validation and confirmation behavior.

## Proposal question round

Assumptions for user review: prioritize mobile guests, keep copy minimal/English-first, preserve current payment branching, and keep the implementation under the 400-line review budget. Product questions: What confirmation details matter most to guests? Should empty availability guide users to change date/service? Which error states need the strongest reassurance?

## Approach

Polish the existing Livewire Blade flow in place. Prefer small markup/class changes, `wire:loading`/disabled affordances, responsive slot cards, clearer section headings, accessible touch targets, and focused test updates only where behavior assertions need stable selectors or text.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `resources/views/livewire/booking-calendar.blade.php` | Modified | Main responsive UX, states, form controls |
| `resources/views/pages/booking.blade.php` | Modified | Public page framing if needed |
| `app/Livewire/BookingCalendar.php` | Modified | Minimal state/copy helpers only if needed |
| `tests/Feature/BookingCalendarTest.php` | Modified | Preserve behavior coverage |
| `tests/Feature/BookingWithPaymentTest.php` | Modified | Guard payment behavior remains unchanged |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| UX markup breaks Livewire interactions | Med | Keep component bindings unchanged; run existing feature tests |
| Slice exceeds 400 changed lines | Med | Defer nonessential redesign/copy expansion |
| Payment flow regression | Low | No Stripe internals; keep payment tests green |

## Rollback Plan

Revert the proposal's view/component/test changes. No migrations or persisted state changes are expected.

## Dependencies

- Existing Livewire booking flow and current Tailwind/UI conventions.

## Success Criteria

- [ ] Mobile slot selection is readable and touch-friendly.
- [ ] Guests see clear progress, loading/disabled, empty/error, and confirmation states.
- [ ] Existing booking and payment tests continue to pass.
