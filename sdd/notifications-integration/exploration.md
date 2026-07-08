# Exploration: Notifications Integration

## Current State

The booking-platform has **skeleton notification infrastructure** but **zero notification implementation**:

### Existing Notification Infrastructure
- **Booking model** (`app/Models/Booking.php`): Has `notification_channel` field (default: 'email')
- **User model** (`app/Models/User.php`): Has `notification_channel` field and uses `Notifiable` trait
- **Laravel Notifications**: Framework provides `Illuminate\Notifications` with Mail, Database, Broadcast channels
- **Queue system**: Database driver configured (`config/queue.php`), jobs table exists
- **Scheduler**: Laravel Scheduler configured in `routes/console.php` with two commands

### What's Missing
- **Notification classes**: No custom notification classes for booking events
- **Queue jobs**: No notification-specific queue jobs
- **Scheduled tasks**: No reminder job (24h before appointment)
- **Twilio integration**: No SMS service or configuration
- **Mailgun integration**: No Mailgun configuration (only SMTP/log drivers)
- **Notification channel routing**: No logic to route based on `notification_channel` preference
- **Event triggers**: No notification dispatch in booking flow

### Multi-Tenant Context
- Tenant model (`app/Models/Tenant.php`): No notification configuration (SMS/email settings)
- Single-database multi-tenancy with `tenant_id` foreign keys
- FilamentPHP v5 with native multi-tenancy support

## Affected Areas

- `app/Models/Tenant.php` — Add notification configuration (Twilio keys, Mailgun settings)
- `app/Services/BookingService.php` — Dispatch notifications on booking events
- `app/Livewire/BookingCalendar.php` — Add notification channel selection to guest form
- `composer.json` — Install `twilio/sdk` for SMS
- `config/services.php` — Add Twilio and Mailgun configuration
- `config/booking.php` — Add notification configuration (reminder timing, etc.)
- `app/Console/Commands/` — New command for sending reminders
- `routes/console.php` — Schedule reminder command
- `database/migrations/` — New migration for tenant notification settings
- `app/Filament/Resources/TenantResource.php` — Admin UI for notification configuration

## Approaches

### 1. Laravel Notifications with Custom Channels

Use Laravel's built-in notification system with custom channels for SMS. Create notification classes for each event (BookingConfirmed, BookingReminder, etc.) and implement a custom SMS channel using Twilio.

| Aspect | Details |
|--------|---------|
| **Pros** | Leverages Laravel's proven notification system; easy to add new channels; built-in queue support; database notifications for history |
| **Cons** | Requires custom channel for SMS; more abstraction layers; may be overkill for simple notifications |
| **Effort** | Medium |

### 2. Custom Notification Service with Queue Jobs

Create a dedicated `NotificationService` that handles all notification logic. Use queue jobs for each notification type with direct Twilio/Mailgun API calls.

| Aspect | Details |
|--------|---------|
| **Pros** | Full control over notification logic; simpler to understand; easier to customize per-tenant |
| **Cons** | Reinvents the wheel; more code to maintain; misses Laravel's notification features |
| **Effort** | Medium |

### 3. Hybrid Approach (Recommended)

Use Laravel Notifications for email (via Mail channel) and a custom SMS channel for Twilio. Create a `NotificationService` to orchestrate channel selection based on user preferences. Use queue jobs for async processing.

| Aspect | Details |
|--------|---------|
| **Pros** | Best of both worlds; Laravel's mail system for email; custom SMS for Twilio; centralized orchestration |
| **Cons** | Slightly more complex than pure custom; two systems to maintain |
| **Effort** | Medium |

## Recommendation

**Approach 3: Hybrid Approach**

This is the right choice for a multi-tenant SaaS:

1. **Laravel Notifications for Email**: Use `MailMessage` with Laravel's mail system. Supports SMTP, Mailgun, and other drivers out of the box. Easy to template and maintain.

2. **Custom SMS Channel for Twilio**: Implement `Illuminate\Notifications\Channel` for SMS. Twilio PHP SDK is straightforward. Allows per-tenant Twilio credentials.

3. **NotificationService Orchestration**: Central service that:
   - Checks user's `notification_channel` preference
   - Routes to appropriate channels (email, SMS, or both)
   - Handles queue dispatch for async processing

4. **Event-Driven Architecture**: Use Laravel Events for booking state changes. Notification listeners subscribe to events and dispatch appropriate notifications.

### Implementation Strategy

**Phase 1: Foundation**
- Add tenant notification configuration (Twilio keys, Mailgun settings)
- Create `NotificationService` with channel routing logic
- Implement base notification classes

**Phase 2: Core Notifications**
- Booking confirmation (email + SMS)
- Business cancellation notification
- Reschedule notification

**Phase 3: Scheduled Notifications**
- Reminder command (24h before appointment)
- Scheduler integration

**Phase 4: Admin Configuration**
- Filament UI for tenant notification settings
- Per-tenant Twilio/Mailgun configuration

## Risks

- **Twilio rate limits**: SMS delivery can be throttled. Need retry logic and queue backoff.
- **Mailgun deliverability**: Email may land in spam. Need proper DNS setup (SPF, DKIM) per tenant.
- **Multi-tenant credentials**: Storing Twilio/Mailgun keys per-tenant requires encryption. Use Laravel's encrypted attributes.
- **Notification preferences**: Users may change preferences after booking. Need to handle preference updates gracefully.
- **Queue failures**: Notifications must be idempotent. Duplicate sends should be safe.
- **Cost management**: SMS costs money. Need usage tracking and limits per tenant.

## Ready for Proposal

**Yes** — the exploration is complete. The orchestrator should proceed to `sdd-propose` with the recommendation to use **Approach 3 (Hybrid Approach)** unless the user specifically requests a different architecture.
