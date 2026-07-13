## Status
success

## Executive Summary
Expired booking holds do **not** block availability anymore, because `AvailabilityService` filters `expires_at > now()`. The real failure mode is in `BookingService::createHold()`: it inserts into `booking_holds`, but the table uses a plain composite unique index on `(tenant_id, employee_id, date, start_time, end_time)`, so an expired row still causes a `QueryException` until cleanup deletes it. That means the slot can look open in the UI, yet the insert still fails.

The current cleanup path is also incomplete for the spec: `expireHolds()` deletes globally and `routes/console.php` runs it every minute, but there is no on-demand cleanup before insert and no MySQL/MariaDB-compatible active-only uniqueness strategy yet.

Best-fit fix options for MySQL/MariaDB:
- transaction + cleanup-before-insert (simple, but still race-prone without an active-only unique key)
- generated active-key column + unique index (most robust fit for MySQL/MariaDB)
- status/nullable-token pattern (also viable, but requires careful insert/update semantics)

## Artifacts
- OpenSpec: `openspec/changes/booking-hold-expiry-semantics/exploration.md`
- Engram: `sdd/booking-hold-expiry-semantics/explore`
- Key evidence: `app/Services/BookingService.php`, `app/Services/AvailabilityService.php`, `database/migrations/2026_07_04_000001_create_booking_holds_table.php`, `tests/Unit/BookingServiceTest.php`, `tests/Feature/BookingCalendarTest.php`, `openspec/specs/booking-holds/spec.md`, `openspec/changes/archive/2026-07-04-booking-flow/design.md`

## Next Recommended
sdd-propose — choose the MySQL/MariaDB-safe hold-expiry strategy and define the migration/test slice.

## Risks
- MySQL/MariaDB does not support PostgreSQL-style partial unique indexes, so the schema fix must use a different pattern.
- Cleanup-before-insert alone can still race under concurrent slot grabs.
- The test suite runs on SQLite (`phpunit.xml`), so DB-specific uniqueness behavior must be validated carefully on MySQL/MariaDB.
- Cleanup is currently global, not tenant-scoped as the booking-holds spec describes.

## Skill Resolution
paths-injected — `sdd-explore`, `_shared`
