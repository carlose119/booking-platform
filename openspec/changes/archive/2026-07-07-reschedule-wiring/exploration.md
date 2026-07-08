## Exploration: Reschedule Wiring for booking-platform

### Current State
The tenant panel already has a BookingResource with list + view pages, but only cancellation is wired in the UI. The backend already has the notification plumbing for reschedules (`BookingRescheduled`, `SendBookingNotification`, `NotificationService::sendBookingRescheduled`), but there is no booking lifecycle method for rescheduling and no audit fields for who changed the booking or what the original slot was. Availability checks also do not have an “exclude this booking” path, so a naive reschedule validation can falsely collide with the booking being moved.

### Affected Areas
- `app/Filament/Resources/BookingResource.php` — current business booking UI; the reschedule action should live here.
- `app/Filament/Resources/BookingResource/Pages/ViewBooking.php` — best low-risk first home for a reschedule header action.
- `app/Services/BookingService.php` — needs the reschedule lifecycle method and tenant/availability guards.
- `app/Services/AvailabilityService.php` — needs an exclude-booking path for “move this booking” validation.
- `app/Services/NotificationService.php`, `app/Jobs/SendBookingNotification.php`, `app/Notifications/BookingRescheduled.php` — notification plumbing already exists and should be reused.
- `app/Models/Booking.php`, `database/migrations/*bookings*` — currently no reschedule audit fields exist.
- `tests/Feature/Filament/BookingResourceTest.php`, `tests/Unit/BookingServiceTest.php`, `tests/Feature/NotificationDispatchTest.php` — need coverage for UI visibility, idempotency, and notification payloads.

### Approaches
1. **View-page-first reschedule action** — add a reschedule header action on `ViewBooking` backed by `BookingService::rescheduleBooking(...)`, and reuse the existing notification job.
   - Pros: smallest safe surface, single-record context, easy to gate by tenant/admin role, likely fits the 400-line review budget in the first slice.
   - Cons: no quick action from the list page yet; still needs an availability exclusion path; audit persistence may be minimal at first.
   - Effort: Medium

2. **Full booking management reschedule workflow** — add list + view actions, a richer modal/step flow, and schema changes for reschedule audit fields in the same slice.
   - Pros: complete admin experience, better auditability, less follow-up work.
   - Cons: broader blast radius, higher test surface, likely exceeds the 400-line review budget.
   - Effort: High

### Recommendation
Start with the view-page-first slice. It is the lowest-risk way to wire business/admin rescheduling without inventing a new management surface: add one service method, one view-page action, and one availability exclusion path. If auditability is required now, add only `rescheduled_at` + `rescheduled_by_user_id` first; defer a richer history model until the workflow is proven.

### Risks
- Availability validation must exclude the booking being moved, or same-employee/date checks will reject valid reschedules.
- The data model decision is still open: minimal audit fields vs. full `rescheduled_from_*` history.
- If the first slice also adds list-page actions and audit migrations, the change likely blows past the 400-line review budget.

### Ready for Proposal
Yes — propose a first slice centered on `ViewBooking` + `BookingService`; tell the user to confirm whether audit fields must ship now or can wait for a second slice.
