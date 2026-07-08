# Proposal: Notifications Integration

## Intent

The booking-platform has notification infrastructure (fields, queue, scheduler) but zero notification implementation. Clients receive no confirmations, reminders, or cancellation alerts, leading to missed appointments, support tickets, and operational overhead. This change adds async email/SMS notifications for core booking events, enabling reliable communication and reducing manual follow-up.

## Scope

### In Scope
- Twilio SDK integration for SMS delivery
- Email delivery via Laravel Mail (SMTP/Mailgun)
- Notification channel routing based on user `notification_channel` preference
- Booking confirmation notification (immediately after booking)
- Reminder scheduled command (24h before appointment)
- Tenant notification configuration (Twilio/Mailgun credentials)
- Admin UI for tenant notification settings (Filament)

### Out of Scope
- Notification history/logs UI (future)
- Marketing/promotional notifications (future)
- Multi-channel fallback logic (e.g., SMS failure → email retry)
- Notification analytics/cost tracking (future)
- Template management UI (future)

## Capabilities

### New Capabilities
- `notification-channels`: Email and SMS channel implementations, routing logic based on user preference, queue processing for async delivery.
- `notification-events`: Booking confirmation, reminder, cancellation, and reschedule notification classes with message templates.
- `reminder-scheduler`: Scheduled command to scan upcoming bookings and dispatch reminder notifications 24 hours before appointment.

### Modified Capabilities
- `tenant-management`: Add notification configuration fields (Twilio SID, auth token, phone number; Mailgun domain, secret) to tenant data model and admin UI.

## Approach

Hybrid approach: Laravel Notifications for email (Mail channel), custom SMS channel for Twilio. Central `NotificationService` orchestrates channel selection. Event-driven: Booking state changes dispatch events; notification listeners send appropriate notifications. Queue jobs for async processing.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `app/Services/NotificationService.php` | New | Core service for channel routing and dispatch |
| `app/Notifications/BookingConfirmed.php` | New | Notification class for booking confirmation |
| `app/Notifications/BookingReminder.php` | New | Notification class for reminders |
| `app/Notifications/BookingCancelled.php` | New | Notification class for cancellations |
| `app/Notifications/BookingRescheduled.php` | New | Notification class for reschedules |
| `app/Channels/SmsChannel.php` | New | Custom SMS channel for Twilio |
| `app/Console/Commands/SendReminders.php` | New | Scheduled command for reminders |
| `app/Models/Tenant.php` | Modified | Add notification config fields (encrypted) |
| `database/migrations/` | New | Migration for tenant notification settings |
| `config/services.php` | Modified | Add Twilio/Mailgun configuration |
| `composer.json` | Modified | Add `twilio/sdk` dependency |
| `app/Filament/Resources/TenantResource.php` | Modified | Add notification config form fields |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Twilio rate limits throttle SMS | Medium | Queue backoff, retry logic, per-tenant rate limiting |
| Mailgun deliverability (spam) | Medium | Proper DNS setup (SPF/DKIM) per tenant, monitoring |
| Multi-tenant credential encryption | Low | Use Laravel encrypted attributes, key rotation strategy |
| Queue failures lose notifications | Medium | Idempotent jobs, dead-letter queue, monitoring |
| SMS cost overruns | Medium | Usage tracking, optional per-tenant limits |

## Rollback Plan

1. Disable notification dispatch in `NotificationService` (feature flag)
2. Remove Twilio SDK dependency
3. Drop notification config columns from tenants table
4. Remove scheduled reminder command
5. Keep notification classes for future re-enablement

## Dependencies

- Twilio account and SDK (`twilio/sdk`)
- Mailgun account (or SMTP credentials)
- Laravel Queue worker (Supervisor)
- Laravel Scheduler (cron)

## Success Criteria

- [ ] Booking confirmation email/SMS sent within 30 seconds of booking
- [ ] Reminder notifications dispatched 24h before appointment (±5 minutes)
- [ ] Tenant admin can configure Twilio/Mailgun credentials via Filament UI
- [ ] Notifications respect user `notification_channel` preference (email, SMS, or both)
- [ ] Failed notifications retry at least 3 times with exponential backoff
- [ ] All notification jobs are idempotent (duplicate sends safe)

## Proposal Question Round

To improve the proposal, please answer these questions (or skip if assumptions are acceptable):

1. **Business priority**: Is the primary goal reducing missed appointments (reminders) or improving client experience (confirmation)? This affects which notification we optimize first.

2. **Notification preferences**: Should clients be able to set preferences during checkout, or only in their profile? Should preferences be per-tenant or global?

3. **Failure handling**: If SMS fails, should we fall back to email automatically, or just log the failure and move on? What about email bounce handling?

4. **Scope boundaries**: Should we include notification templates in this slice, or use simple plain-text messages and iterate on design later?

5. **Cost constraints**: Do we need per-tenant SMS spending limits in this slice, or can we add that later?

**Assumptions made**:
- Notifications are transactional only (no marketing)
- Templates are simple plain-text (HTML later)
- No per-tenant cost limits in first slice
- Email fallback on SMS failure is not included
- Client preferences are set during checkout and stored in profile