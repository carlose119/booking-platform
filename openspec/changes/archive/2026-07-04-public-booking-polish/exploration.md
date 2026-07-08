**Status**: success
**Executive Summary**: The public booking flow is a single Livewire component rendered from `/{tenant}/book`, with step-based state already in place (`currentStep`, holds, payment, confirmation). The UX is functional but mobile-hostile in the slot table and lacks several low-risk polish states (loading/disabled/error/accessibility cues).

**Artifacts**: Engram `sdd/public-booking-polish/explore` | OpenSpec `openspec/changes/public-booking-polish/exploration.md`

**Next Recommended**: sdd-propose

**Risks**: The slot table is the main mobile bottleneck; payment JS is brittle; visual/accessibility regressions are not well covered by current tests.

**Skill Resolution**: paths-injected — `sdd-explore`, `_shared`

## Detailed Report

### Current State
- `routes/web.php` exposes `GET /{tenant}/book`, and `BookingController` renders `resources/views/pages/booking.blade.php`, which mounts `BookingCalendar`.
- `app/Livewire/BookingCalendar.php` owns the full flow: slot selection, hold creation, guest form, Stripe payment step, confirmation, and cancellation.
- `resources/views/livewire/booking-calendar.blade.php` is a single monolithic Blade view; there is no separate guest-form partial in the current tree.
- Step 1 uses select/date/employee filters plus a desktop-style table of slots; Step 2 is a plain stacked form; Step 3 mounts Stripe Elements; Step 4 is a simple success card.
- Existing tests cover core transitions and payment policy behavior, but not mobile layout, loading states, or accessibility semantics.

### Affected Areas
- `app/Livewire/BookingCalendar.php` — add small UI-state helpers if needed (e.g. step metadata, validation/reset behavior, loading flags).
- `resources/views/livewire/booking-calendar.blade.php` — main UX polish surface for mobile layout, stepper, loading/disabled states, error/empty states, and confirmation copy.
- `tests/Feature/BookingCalendarTest.php` — verify step transitions and the new visible UX text/states.
- `tests/Feature/BookingWithPaymentTest.php` — verify payment-step copy, disabled states, and confirmation behavior remain intact.

### Approaches
1. **Polish in place** — keep the current Livewire component and improve the existing Blade view with responsive wrappers, larger touch targets, `wire:loading`/`disabled` states, `role="alert"` error output, and richer empty/confirmation states.
   - Pros: lowest risk, smallest diff, best fit for a 400-line review budget.
   - Cons: the view stays large; only partial cleanup of mobile table pain.
   - Effort: Low

2. **Extract step partials** — split step 1/2/3/4 into smaller Blade partials or subcomponents before polishing.
   - Pros: cleaner long-term structure, easier future iteration.
   - Cons: more churn, more review surface, likely over budget for a first slice.
   - Effort: Medium

### Recommendation
Start with **polish in place**. The biggest wins are visual and behavioral, not architectural: add responsive/mobile-first spacing, make buttons and selects touch-friendly, disable duplicate submits, announce errors/accessibility states, and improve empty/confirmation messaging. That gives a useful first slice without disturbing booking logic or payment integration.

### Risks
- The slot table will still be the hardest mobile element until it is redesigned.
- Stripe Elements and the inline script are fragile; avoid touching payment wiring in the first slice unless necessary.
- Current automated tests mostly assert text/state, so visual regressions need careful manual review.

### Ready for Proposal
Yes — the next step should be `sdd-propose`, scoped to a small UX-only slice that stays under the 400-line review budget.
