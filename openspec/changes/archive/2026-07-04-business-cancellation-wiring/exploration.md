## Exploration: Business Cancellation Wiring

### Current State
- Tenant admin UI is only partially wired for bookings: the dashboard shows a Quick Actions card linking to `/tenant/{tenantId}/bookings`, but the tenant panel has no registered booking resource and its resource list is commented out.
- The booking domain already has `status`, `payment_status`, `cancelled_at`, `stripe_payment_intent_id`, and `notification_channel` on the model.
- Existing primitives cover notifications and refunds: `SendBookingNotification`, `NotificationService::sendBookingCancelled()`, `BookingCancelled`, `ProcessAutoRefunds`, and `ProcessWebhook`.
- Cancellation/refund behavior is split today: public booking can cancel the hold UI state, but there is no business/admin booking cancellation workflow.

### Affected Areas
- `app/Services/BookingService.php` — central place for booking lifecycle rules; needs a cancel method.
- `app/Filament/Resources/TenantResource.php` / `app/Providers/Filament/TenantPanelProvider.php` — tenant panel currently has no booking resource registered.
- `app/Filament/Widgets/QuickActionsWidget.php` + `resources/views/filament/widgets/quick-actions-widget.blade.php` — already points to a bookings page that does not exist.
- `app/Notifications/BookingCancelled.php`, `app/Services/NotificationService.php`, `app/Jobs/SendBookingNotification.php` — cancellation notification path exists, but no business cancellation entrypoint uses it.
- `app/Console/Commands/ProcessAutoRefunds.php`, `app/Jobs/ProcessWebhook.php`, `app/Services/StripeService.php` — refund primitives exist and need cancellation-trigger semantics.
- `database/migrations/*bookings*`, `app/Models/Booking.php` — data model has no `cancellation_reason`, `cancelled_by`, or explicit refund audit fields.
- Tests: `tests/Unit/BookingServiceTest.php`, `tests/Unit/NotificationServiceTest.php`, `tests/Unit/ProcessAutoRefundsTest.php`, `tests/Feature/Filament/DashboardPageTest.php`.

### Approaches
1. **Service-first booking cancellation + tenant BookingResource** — add `BookingService::cancelBooking()` and a tenant-scoped Filament resource/list with row actions for cancel/refund.
   - Pros: centralizes rules, reuses existing notification/refund primitives, makes the missing `/tenant/{id}/bookings` target real.
   - Cons: new resource + registration work, requires careful authorization and tenant scoping.
   - Effort: Medium

2. **Dedicated management page or widget action** — build a narrower bookings management page with a cancel modal and dispatch refunds separately.
   - Pros: smaller surface area, faster to ship.
   - Cons: duplicates list logic, weaker discoverability, and still leaves cancellation rules split unless service-backed.
   - Effort: Low/Medium

### Recommendation
Choose option 1. It is the lowest-risk path that actually fixes the missing admin wiring: one service method, one tenant-scoped booking UI, and explicit notification/refund dispatch. Keep refund behavior explicit in the service so the UI only orchestrates it.

### Risks
- The current data model has no `cancellation_reason` or `cancelled_by`, so auditability is weak unless those fields are added.
- Refund timing is ambiguous for paid/partial bookings: immediate synchronous refund vs queued command/job needs product confirmation.

### Ready for Proposal
Yes — propose a service-backed tenant booking resource plus cancellation action; confirm whether first slice includes audit fields and whether refunds should be synchronous or queued.
