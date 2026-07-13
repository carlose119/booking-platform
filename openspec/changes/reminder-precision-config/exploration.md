## Exploration: reminder-precision-config

### Current State
- `routes/console.php` schedules `booking:send-reminders` daily at `08:00`.
- `app/Console/Commands/SendReminders.php` currently selects all non-cancelled bookings for `tomorrow`, skips only `reminded_at != null`, dispatches `SendBookingNotification` with event `reminder`, then immediately sets `reminded_at`.
- `app/Services/NotificationService.php` and `app/Notifications/BookingReminder.php` already preserve registered-user vs guest routing; the reminder change should not rewrite that path.
- `app/Models/Tenant.php` has notification/payment settings but no `reminder_hours` field.
- OpenSpec/PRD expect 24h reminders with a ±5 minute window and a configurable lead time (default 24, custom 48); the current implementation does not meet that precision/config requirement.

### Affected Areas
- `app/Console/Commands/SendReminders.php` — current selection logic is day-based, not time-window based.
- `routes/console.php` — current daily-at-08:00 cadence is too coarse for ±5 minute precision.
- `app/Models/Tenant.php` / tenant migration / Filament tenant resource — needed if `reminder_hours` is tenant-scoped.
- `app/Models/Booking.php` — `reminded_at` exists and is the current idempotency marker.
- `app/Services/NotificationService.php` and `app/Notifications/BookingReminder.php` — should be preserved; they already handle guest/registered routing.
- `tests/Feature/SendRemindersTest.php`, `tests/Unit/NotificationServiceTest.php`, `tests/Unit/TenantNotificationSettingsTest.php` — lack coverage for precision, configurable lead time, and tenant-config missing behavior.
- `openspec/specs/reminder-scheduler/spec.md`, `openspec/specs/notification-events/spec.md`, `openspec/specs/notification-channels/spec.md`, `prd.md` — source of truth for the gap.

### Approaches
1. **Tenant-scoped reminder_hours with fallback default** — add a tenant setting (default 24, allow 48) and have the command compute a 5-minute datetime window around `now() + reminder_hours`.
   - Pros: matches multi-tenant model and OpenSpec/PRD intent; keeps config per business.
   - Cons: schema/admin/UI work; needs timezone-aware windowing.
   - Effort: Medium/High

2. **Global config default first, tenant override later** — read reminder lead time from config/env now, then layer tenant override in a later slice.
   - Pros: fastest path to precision; smaller first change.
   - Cons: weaker fit for tenant-specific operations; may need a second follow-up to align with tenant settings.
   - Effort: Low/Medium

### Recommendation
Use tenant-scoped `reminder_hours` with a `24` default and a `48` allowed override, then schedule the command frequently enough to catch the ±5 minute window (not once daily). Keep the existing notification routing intact.

### Risks
- Timezone drift: `now()` + `whereDate()` is too coarse; precision needs explicit datetime windowing.
- Scheduler cadence: daily execution cannot guarantee a 5-minute window.
- Idempotency: `reminded_at` is set before queue delivery completes, so job retry semantics may still duplicate or suppress reminders incorrectly.
- Tenant configuration gaps: the command currently does not skip/log tenants missing notification config.
- Test determinism: window-based tests will need frozen time and careful queue assertions.

### Ready for Proposal
Yes — the exploration is sufficient to draft a proposal focused on tenant-level lead time, precise windowing, and scheduler cadence while preserving guest routing.
