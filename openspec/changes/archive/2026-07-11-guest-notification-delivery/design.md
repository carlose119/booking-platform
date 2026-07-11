# Design: Guest Notification Delivery

## Technical Approach

Introduce a booking-recipient notifiable that lets `NotificationService` resolve either the registered `User` or guest contact fields from `Booking`. Existing `SendBookingNotification` event flow remains the single event dispatcher for confirmation, reminder, cancellation, and reschedule. Stripe webhook success adds only the missing paid-guest confirmation dispatch, guarded by the existing payment-status transition.

## Architecture Decisions

| Decision | Choice | Alternatives considered | Rationale |
|---|---|---|---|
| Recipient abstraction | Create `App\Notifications\BookingRecipient` using Laravel `Notifiable`, with `tenant`, `email`, `phone`, and `notification_channel`. | Ad hoc mail/SMS branches in `NotificationService`. | Centralizes registered/guest routing and avoids duplicating event logic across four notification types. |
| Channel routing | Resolve channels from recipient preference, then remove unavailable guest channels independently. | Require both contacts for `both`; fail booking on missing contact. | Matches spec: `both` degrades to available methods and missing contacts must not block workflows. |
| Webhook confirmation | In `ProcessWebhook::handlePaymentSucceeded()`, dispatch `SendBookingNotification(..., 'confirmed')` only after an unpaid guest booking transitions to paid/partial + confirmed. | Dispatch during booking creation; notify all paid bookings. | Paid guests must wait for Stripe success, and registered-client behavior should not change in this slice. |
| Idempotency | Use existing `payment_status in ['paid','partial']` early return as the duplicate webhook guard. | Add a notification ledger column/table. | Sufficient for current duplicate-webhook spec and keeps the review slice under budget. |

## Data Flow

```text
Booking event/job -> NotificationService -> User OR BookingRecipient
                                      -> Booking* notification -> mail/SmsChannel

Stripe webhook success -> ProcessWebhook transition guard -> confirmed guest job
Duplicate webhook      -> existing paid/partial guard      -> no job
```

## File Changes

| File | Action | Description |
|---|---|---|
| `app/Notifications/BookingRecipient.php` | Create | Guest-capable notifiable adapter over booking contact fields and tenant. |
| `app/Services/NotificationService.php` | Modify | Replace `resolveClient()` with recipient resolution and channel filtering. |
| `app/Channels/SmsChannel.php` | Modify | Accept generic notifiables; use route/fallback phone and tenant config. |
| `app/Notifications/BookingConfirmed.php` | Modify | Loosen notifiable type hints and delegate channel selection to recipient-compatible data. |
| `app/Notifications/BookingReminder.php` | Modify | Same as above. |
| `app/Notifications/BookingCancelled.php` | Modify | Same as above. |
| `app/Notifications/BookingRescheduled.php` | Modify | Same as above. |
| `app/Jobs/ProcessWebhook.php` | Modify | Dispatch paid guest confirmation after successful transition. |
| `tests/Unit/NotificationServiceTest.php` | Modify | Replace guest skip expectations with guest channel/missing-contact cases. |
| `tests/Feature/NotificationDispatchTest.php` | Modify | Cover guest cancellation/reschedule behavior and no-workflow-blocking paths. |
| `tests/Unit/ProcessWebhookTest.php` | Modify | Cover paid guest confirmation and duplicate webhook idempotency. |
| `tests/Feature/SendRemindersTest.php` | Modify | Prove reminders continue and guest recipient delivery is reachable. |

## Interfaces / Contracts

```php
final class BookingRecipient {
    use Notifiable;
    public function routeNotificationForMail(): ?string;
    public function routeNotificationForSms(): ?string;
}
```

`NotificationService` contract: `resolveRecipient(Booking): User|BookingRecipient|null`; null means no usable channel and MUST silently skip delivery.

## Testing Strategy

| Layer | What to Test | Approach |
|---|---|---|
| Unit | Recipient resolution, `email`/`sms`/`both`, missing selected contact. | `Notification::fake()` assertions against `User` and `BookingRecipient`. |
| Integration | `SendBookingNotification` execution for confirmation/reminder/cancellation/reschedule. | Existing job/service tests updated from `assertNothingSent()` to expected guest delivery. |
| Webhook | Paid guest confirmation waits for success and duplicate webhook sends no duplicate. | Process webhook test asserts one `SendBookingNotification` job after unpaid->paid transition. |

## Threat Matrix

| Boundary | Applicability | Design response | Planned RED tests |
|---|---|---|---|
| Documentation-like paths | N/A: no executable-file classification. | None. | None. |
| Git repository selection | N/A: no VCS commands. | None. | None. |
| Commit state | N/A: no commit automation. | None. | None. |
| Push state | N/A: no push automation. | None. | None. |
| PR commands | N/A: no PR automation. | None. | None. |

## Migration / Rollout

No migration required. This uses existing booking contact and notification preference columns. Estimated implementation is near the 400-line review budget; split into recipient/channel changes first, webhook/test updates second if diff exceeds budget.

## Open Questions

- [ ] None.
