# Design: Notifications Integration

## Technical Approach

Extend the existing multi-tenant booking platform with async email/SMS notifications. Laravel Notifications + custom Twilio SMS channel for delivery, queue jobs for async processing, scheduled command for 24h reminders. Central `NotificationService` orchestrates channel routing based on user `notification_channel` preference.

## Architecture Decisions

| Decision | Option A | Option B | Tradeoff | Choice |
|----------|----------|----------|----------|--------|
| SMS Provider | Twilio SDK | Custom HTTP | SDK handles auth, retries, errors | Twilio SDK |
| Notification Dispatch | Laravel Notification class | Raw service method | Laravel provides channels, queueable, testable | Laravel Notification |
| Channel Routing | Service class | Notification via vsNotification() | Service gives explicit control per-tenant config | NotificationService |
| Reminder Storage | DB column `reminded_at` | Separate pivot table | Column simpler, pivot overkill for single flag | `reminded_at` column |
| Email Transport | Mailgun API | SMTP direct | Mailgun handles deliverability, bounce tracking | Mailgun with SMTP fallback |

## Data Flow

    Booking Created ──→ BookingService::confirmBooking()
                              │
                              ▼
                    NotificationService::sendBookingConfirmed($booking)
                              │
                              ├──→ User.notification_channel === 'email' ──→ SendBookingNotification (job) ──→ Mail Channel
                              ├──→ User.notification_channel === 'sms'   ──→ SendBookingNotification (job) ──→ SmsChannel
                              └──→ User.notification_channel === 'both'  ──→ SendBookingNotification (job) ──→ Both Channels

    Scheduler (daily) ──→ SendReminders command
                              │
                              ▼
                    Bookings WHERE date = tomorrow AND reminded_at IS NULL
                              │
                              ▼
                    NotificationService::sendBookingReminder($booking) ──→ Queue Job
                              │
                              ▼
                    Update reminded_at = now()

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `app/Services/NotificationService.php` | Create | Core routing: resolve channel, dispatch queue jobs |
| `app/Channels/SmsChannel.php` | Create | Custom channel wrapping Twilio SDK |
| `app/Notifications/BookingConfirmed.php` | Create | Notification class for booking confirmation |
| `app/Notifications/BookingReminder.php` | Create | Notification class for 24h reminders |
| `app/Notifications/BookingCancelled.php` | Create | Notification class for cancellations |
| `app/Notifications/BookingRescheduled.php` | Create | Notification class for reschedules |
| `app/Jobs/SendBookingNotification.php` | Create | Queue job wrapping notification dispatch |
| `app/Console/Commands/SendReminders.php` | Create | Scheduled command scanning bookings |
| `app/Models/Tenant.php` | Modify | Add notification fields + encrypted casts |
| `app/Models/Booking.php` | Modify | Add `reminded_at` column |
| `app/Filament/Resources/TenantResource.php` | Modify | Add notification config section |
| `config/services.php` | Modify | Add Twilio and Mailgun config keys |
| `database/migrations/..._add_notification_config_to_tenants.php` | Create | Migration for tenant notification fields |
| `database/migrations/..._add_reminded_at_to_bookings.php` | Create | Migration for reminded_at flag |
| `routes/console.php` | Modify | Register scheduler command |
| `composer.json` | Modify | Add `twilio/sdk` dependency |

## Interfaces / Contracts

```php
// NotificationService — core routing
class NotificationService
{
    public function sendBookingConfirmed(Booking $booking): void;
    public function sendBookingReminder(Booking $booking): void;
    public function sendBookingCancelled(Booking $booking, ?string $reason = null): void;
    public function sendBookingRescheduled(Booking $booking, string $originalDate, string $originalTime): void;
}

// SmsChannel — wraps Twilio
class SmsChannel
{
    public function send(User $user, Notification $notification): void;
}

// SendBookingNotification — queue job
class SendBookingNotification implements ShouldQueue
{
    public int $tries = 3;
    public int $backoff = [30, 120, 300];

    public function handle(NotificationService $service, Booking $booking, string $event): void;
}
```

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Unit | NotificationService channel routing | Mock User notification_channel, assert correct channel called |
| Unit | Message templates | Assert plain-text content includes booking details |
| Integration | SendBookingNotification job | Use Queue::fake(), assert job dispatched |
| Integration | SendReminders command | Use DatabaseSeeder with test bookings, assert reminded_at updated |
| E2E | Full booking flow | Create booking → assert notification queued (not sent in test) |

## Migration / Rollout

1. Run migration adding notification config to tenants table
2. Run migration adding `reminded_at` to bookings table
3. Add `twilio/sdk` via composer
4. Deploy notification classes and service
5. Register scheduler command in `routes/console.php`
6. No feature flags needed — notifications gracefully fail if tenant config is missing

## Open Questions

- [ ] Should reminder window be configurable per-tenant or global config only?
