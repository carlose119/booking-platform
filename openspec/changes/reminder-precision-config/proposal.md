# Proposal: Reminder Precision Config

## Intent

Make booking reminders tenant-configurable and time-precise. The current scheduler is day-wide and daily, which cannot satisfy the expected ±5 minute reminder window or tenant-specific 24/48 hour lead time.

## Scope

### In Scope
- Add tenant-scoped `reminder_hours` with default `24` and allowed `48` option.
- Select reminders by datetime window around `appointment_start - reminder_hours`, expected ±5 minutes.
- Run the scheduler frequently enough to catch the precision window reliably.
- Apply the current tenant setting immediately to all not-yet-reminded bookings, including existing bookings inside the new window.
- Preserve `reminded_at` idempotency and existing registered/guest notification routing.
- Define missing/invalid tenant config fallback, timezone handling, and existing-tenant defaults.

### Out of Scope
- Per-tenant Mailgun/SMTP/Twilio configuration changes.
- New notification routing, templates, or channel preferences beyond reminder timing.
- Retry/idempotency redesign beyond preserving `reminded_at` behavior.

## Capabilities

### New Capabilities
- None

### Modified Capabilities
- `reminder-scheduler`: Replace daily day-wide scanning with tenant-configured datetime precision scanning and scheduler cadence.
- `tenant-management`: Store and validate tenant-level `reminder_hours` defaults/options.
- `notification-events`: Change reminder timing semantics from fixed 24h to tenant-configured lead time while preserving recipients.

## Approach

Add `reminder_hours` to tenants with migration/default handling for existing rows. Validate allowed values in tenant admin/model boundaries. Update `booking:send-reminders` to compute each tenant's target window using the tenant setting, app timezone by default, and fallback to `24` for missing/invalid config. Query active, not-reminded appointments whose start time falls within `now() + reminder_hours ± 5 minutes`. Increase scheduler cadence from daily to frequent execution, preserving `reminded_at` as the duplicate guard.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `app/Console/Commands/SendReminders.php` | Modified | Use tenant lead time and datetime window. |
| `routes/console.php` | Modified | Run reminder command frequently enough for ±5 minutes. |
| `app/Models/Tenant.php` + migration + Filament tenant resource | Modified | Store, default, and validate `reminder_hours`. |
| `tests/Feature/SendRemindersTest.php` | Modified | Cover 24/48h windows, immediate setting changes, fallback, and idempotency. |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Timezone drift | Med | Use app timezone consistently and freeze time in tests. |
| Missed reminders between scheduler runs | Med | Schedule cadence narrower than the ±5 minute window. |
| Existing bookings get unexpected reminders after config change | Med | Document immediate-application behavior and rely on `reminded_at`. |

## Rollback Plan

Revert command, scheduler, admin/model validation, and migration changes. If deployed, restore default `24` behavior and keep `reminded_at` values; do not resend already marked reminders.

## Dependencies

- Laravel Scheduler and queue workers must remain operational.
- Existing tenant and notification routing models.

## Success Criteria

- [ ] Tenants default to `24` and can choose `48` only.
- [ ] Reminders dispatch only within the configured lead-time ±5 minute window.
- [ ] Scheduler cadence reliably catches the window.
- [ ] Existing not-yet-reminded bookings follow the current tenant setting immediately.
- [ ] Guest and registered-client routing remains unchanged.
