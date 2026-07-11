# Proposal: Guest Notification Delivery

## Intent

Guest bookings collect contact fields and notification preference, but delivery currently requires `client_id`. This change enables confirmation, reminder, cancellation, and reschedule notifications for guests while preserving registered-client behavior. Paid guest bookings MUST receive confirmation only after Stripe webhook success confirms the booking.

## Proposal Question Round

- Assumption: missing contact data disables only unavailable channels, not the booking workflow.
- Assumption: payment-required guest confirmation is webhook-driven and idempotent.
- Question for spec review: should `both` degrade to one available channel or require both contacts?

## Scope

### In Scope
- Route guest notifications using `client_email`, `client_phone`, and booking `notification_channel`.
- Cover existing confirmation, reminder, cancellation, and reschedule events.
- Dispatch paid guest confirmation after webhook marks booking paid/partial and confirmed.
- Preserve registered-client routing and replace tests asserting guests are skipped.

### Out of Scope
- New notification event types, client account creation, or customer self-reschedule.
- Per-tenant notification provider configuration fixes beyond existing behavior.
- Production code changes in this proposal phase.

## Capabilities

### New Capabilities
- None

### Modified Capabilities
- `notification-channels`: routing must support booking guest contact fields as notification recipients without `client_id`.
- `notification-events`: existing booking events must deliver to guest recipients and confirm paid guest bookings only after payment success.

## Approach

Use a guest recipient/notifiable abstraction so existing notification classes can route mail/SMS from booking contact fields and booking preference. Keep `SendBookingNotification` event wiring, add payment-success confirmation dispatch in the webhook path, and update unit/feature tests around guest delivery and idempotency.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `app/Services/NotificationService.php` | Modified | Resolve registered users or guest recipients. |
| `app/Jobs/ProcessWebhook.php` | Modified | Dispatch confirmation after paid confirmation. |
| `app/Notifications/*.php` | Modified | Accept guest-capable notifiables. |
| `tests/*Notification*`, `tests/*Booking*`, `tests/*Webhook*` | Modified | Replace wrong skip assertions and add coverage. |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Duplicate paid confirmations | Med | Guard webhook dispatch by prior payment/status state. |
| Channel/contact mismatch | Med | Explicit fallback/skip rules in specs and tests. |
| Registered-client regression | Low | Preserve existing user tests. |

## Rollback Plan

Revert guest recipient changes, webhook notification dispatch, and updated tests; registered-client notification behavior should remain unchanged.

## Dependencies

- Existing notification jobs/classes and Stripe webhook payment-success flow.

## Success Criteria

- [ ] Guests receive existing notifications via email, SMS, or both when contact data exists.
- [ ] Paid guest confirmations send only after webhook success, with no duplicates.
- [ ] Registered-client notification tests still pass.
