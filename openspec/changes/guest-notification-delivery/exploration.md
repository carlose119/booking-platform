# Exploration: guest-notification-delivery

### Current State
- Guest checkout already collects `guestName`, `guestEmail`, `guestPhone`, and `guestNotificationChannel`, and persists `notification_channel` on `bookings`.
- `NotificationService` only resolves a `User` when `booking.client_id` exists; guest bookings return `null`, so confirmation/reminder/cancellation/reschedule all short-circuit.
- `BookingService::confirmBooking()` only dispatches confirmation for `nopayment` bookings; payment-required bookings become `confirmed` later in `ProcessWebhook` with no notification dispatch.
- Existing notifications (`BookingConfirmed`, `BookingReminder`, `BookingCancelled`, `BookingRescheduled`) route by `User::notification_channel`, so they do not currently support guest contact fields.
- Several tests assert the wrong guest behavior (`assertNothingSent`) and would block the intended change.

### Affected Areas
- `app/Services/NotificationService.php` — guest resolution and dispatch routing.
- `app/Services/BookingService.php` — confirmation dispatch for nopayment bookings and eventual payment-success confirmation path.
- `app/Jobs/ProcessWebhook.php` — payment-success flow confirms paid bookings but does not notify.
- `app/Notifications/*.php` — notification classes currently assume a `User` notifiable.
- `app/Livewire/BookingCalendar.php` — guest channel selection and confirmation summary already expose the intended guest channel semantics.
- `tests/Unit/NotificationServiceTest.php` — encodes “no notification for guest booking” today.
- `tests/Feature/NotificationDispatchTest.php` — encodes no guest notification on cancellation today.
- `tests/Unit/BookingServiceTest.php` — encodes no guest notification on reschedule today.
- `tests/Feature/SendRemindersTest.php` — reminder command queues guest bookings, but delivery is blocked in the service layer.

### Approaches
1. **Guest recipient abstraction** — introduce a guest notifiable/recipient object that routes mail to `client_email` and SMS to `client_phone`, while keeping the existing notification classes mostly intact.
   - Pros: preserves current notification templates; centralizes guest routing; covers all events consistently.
   - Cons: adds a new abstraction; needs careful handling of `notification_channel` on bookings vs users.
   - Effort: Medium

2. **Service-level ad hoc routing** — keep user notifications as-is, but add guest-specific branches in `NotificationService` that send mail/SMS directly from booking fields.
   - Pros: smallest surface area; easiest to reason about for this slice.
   - Cons: duplicates message/channel logic; splits behavior between users and guests.
   - Effort: Low/Medium

### Recommendation
Use the **guest recipient abstraction**. It is the safest long-term path because the booking already stores the exact guest delivery data and channel preference, and confirmation/reminder/cancellation/reschedule all need identical routing semantics without creating a client account.

### Risks
- Guest support currently spans multiple flows; fixing only confirmation would leave reminders/cancellations/reschedules inconsistent.
- Payment-success confirmation is a separate gap: `ProcessWebhook` confirms paid bookings but never dispatches a notification.
- Tests currently encode the wrong expectation for guest bookings, so the suite will need deliberate updates, not just additions.

### Ready for Proposal
Yes — the scope is clear enough to propose. The proposal should define guest notification routing for `email`, `sms`, and `both`, plus the payment-success confirmation path.
