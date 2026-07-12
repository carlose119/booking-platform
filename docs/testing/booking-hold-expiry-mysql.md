# Booking Hold Expiry MySQL/MariaDB Validation Receipt

This receipt proves the production database behavior that SQLite cannot fully validate: nullable active-slot uniqueness must reject duplicate active holds while allowing expired/non-active rows to remain.

## Scope

- Change: `booking-hold-expiry-semantics`
- Slice: 1 remediation — schema/model release-order safety and DB receipt quality
- Database engines: MySQL 8.0 or MariaDB 10.x on a disposable database only
- Migration under test: `2026_07_11_000001_add_active_slot_key_to_booking_holds.php`

## Pass/Fail Record

Fill this section in the PR or review receipt after running the commands.

| Field | Value |
|---|---|
| Operator | |
| Date/time UTC | |
| Engine/version output | |
| Laravel app commit | |
| `php artisan migrate:fresh --env=testing` | PASS / FAIL |
| Expired duplicate SQL | PASS / FAIL |
| Active duplicate SQL | PASS / FAIL |
| Old-node default SQL | PASS / FAIL |
| Rollback legacy index preflight/order | PASS / FAIL |
| Cleanup transaction rolled back | PASS / FAIL |
| Notes / failure output | |

## Setup

1. Point `.env.testing` at a disposable MySQL/MariaDB schema.
2. Confirm the target database name is not production:

   ```bash
   php artisan env
   php artisan migrate:status --env=testing
   ```

3. Capture the database engine/version:

   ```bash
   php artisan tinker --execute="dump(DB::selectOne('select version() as version'))" --env=testing
   ```

4. Rebuild the disposable schema:

   ```bash
   php artisan migrate:fresh --env=testing
   ```

Expected migration result: `booking_holds.active_slot_key` exists, is nullable, defaults to `active`, index `booking_holds_unique_active_slot` exists, and legacy index `booking_holds_unique_slot` no longer exists.

Release-safety expectation: the migration creates `booking_holds_tenant_id_fk_support` first, creates `booking_holds_unique_active_slot` second, and only then drops `booking_holds_unique_slot`. Rollback mirrors the safety rule: it preflights duplicate slot rows, creates `booking_holds_unique_slot`, and only then drops `booking_holds_unique_active_slot` and `active_slot_key`. MySQL/MariaDB DDL auto-commits, so this order is intentional: if replacement unique-index DDL fails in either direction, the currently active uniqueness remains in place and production fails closed instead of running without slot uniqueness protection.

## Transactional SQL Receipt

Run the receipt in one transaction and roll it back so the disposable database remains clean. The script creates its own tenant, service, and employee fixtures, captures their IDs in session variables, and uses only those captured IDs for hold rows.

```sql
SELECT VERSION() AS engine_version;

START TRANSACTION;

SET @receipt_run := REPLACE(UUID(), '-', '');
SET @tenant_slug := CONCAT('hold-expiry-receipt-', @receipt_run);
SET @employee_email := CONCAT('hold-expiry-receipt-', @receipt_run, '@example.test');

INSERT INTO tenants (name, slug, created_at, updated_at)
VALUES ('Hold Expiry Receipt Tenant', @tenant_slug, NOW(), NOW());
SET @tenant_id := LAST_INSERT_ID();

INSERT INTO services (tenant_id, name, price_cents, duration_minutes, active, created_at, updated_at)
VALUES (@tenant_id, 'Receipt Service', 5000, 30, 1, NOW(), NOW());
SET @service_id := LAST_INSERT_ID();

INSERT INTO users (tenant_id, name, email, role, password, notification_channel, created_at, updated_at)
VALUES (@tenant_id, 'Receipt Employee', @employee_email, 'employee', '$2y$12$abcdefghijklmnopqrstuu7M3S9R8QrY9K6Ppc1oC6DFE7H8I9J0K', 'email', NOW(), NOW());
SET @employee_id := LAST_INSERT_ID();

SELECT @tenant_id AS tenant_id, @service_id AS service_id, @employee_id AS employee_id;

-- Optional cleanup inside the transaction for repeatable local runs if the SQL client replays the same variables.
DELETE FROM booking_holds
WHERE session_id IN (
  'receipt-expired-a',
  'receipt-expired-b',
  'receipt-active-a',
  'receipt-active-b',
  'receipt-old-node-a',
  'receipt-old-node-b'
);

-- Expired/non-active duplicates MUST be allowed because NULL values do not collide.
INSERT INTO booking_holds
  (tenant_id, service_id, employee_id, date, start_time, end_time, session_id, expires_at, active_slot_key, created_at, updated_at)
VALUES
  (@tenant_id, @service_id, @employee_id, '2026-07-13', '10:00:00', '10:30:00', 'receipt-expired-a', '2026-07-13 09:00:00', NULL, NOW(), NOW()),
  (@tenant_id, @service_id, @employee_id, '2026-07-13', '10:00:00', '10:30:00', 'receipt-expired-b', '2026-07-13 09:05:00', NULL, NOW(), NOW());

SELECT COUNT(*) AS expired_duplicate_count
FROM booking_holds
WHERE session_id IN ('receipt-expired-a', 'receipt-expired-b');
-- Expected: expired_duplicate_count = 2

-- First active row MUST be accepted.
INSERT INTO booking_holds
  (tenant_id, service_id, employee_id, date, start_time, end_time, session_id, expires_at, active_slot_key, created_at, updated_at)
VALUES
  (@tenant_id, @service_id, @employee_id, '2026-07-13', '11:00:00', '11:30:00', 'receipt-active-a', '2026-07-13 11:25:00', 'active', NOW(), NOW());

-- Second active row for the same slot MUST fail.
INSERT INTO booking_holds
  (tenant_id, service_id, employee_id, date, start_time, end_time, session_id, expires_at, active_slot_key, created_at, updated_at)
VALUES
  (@tenant_id, @service_id, @employee_id, '2026-07-13', '11:00:00', '11:30:00', 'receipt-active-b', '2026-07-13 11:26:00', 'active', NOW(), NOW());
-- Expected MySQL: ERROR 1062 duplicate entry ... for key 'booking_holds_unique_active_slot'
-- Expected MariaDB: ERROR 1062 duplicate entry ... for key 'booking_holds_unique_active_slot'

ROLLBACK;
```

Record PASS for cleanup only after `ROLLBACK` succeeds and these checks return zero rows:

```sql
SELECT COUNT(*) AS leftover_holds
FROM booking_holds
WHERE session_id LIKE 'receipt-%';

SELECT COUNT(*) AS leftover_tenants
FROM tenants
WHERE slug LIKE 'hold-expiry-receipt-%';
```

If the active duplicate insert fails as expected, reconnect and run the old-node default check in a fresh transaction because most SQL clients abort the current script after the duplicate-key error. This second script also creates and rolls back its own fixture rows.

```sql
SELECT VERSION() AS engine_version;

START TRANSACTION;

SET @receipt_run := REPLACE(UUID(), '-', '');
SET @tenant_slug := CONCAT('hold-expiry-receipt-default-', @receipt_run);
SET @employee_email := CONCAT('hold-expiry-receipt-default-', @receipt_run, '@example.test');

INSERT INTO tenants (name, slug, created_at, updated_at)
VALUES ('Hold Expiry Default Receipt Tenant', @tenant_slug, NOW(), NOW());
SET @tenant_id := LAST_INSERT_ID();

INSERT INTO services (tenant_id, name, price_cents, duration_minutes, active, created_at, updated_at)
VALUES (@tenant_id, 'Receipt Default Service', 5000, 30, 1, NOW(), NOW());
SET @service_id := LAST_INSERT_ID();

INSERT INTO users (tenant_id, name, email, role, password, notification_channel, created_at, updated_at)
VALUES (@tenant_id, 'Receipt Default Employee', @employee_email, 'employee', '$2y$12$abcdefghijklmnopqrstuu7M3S9R8QrY9K6Ppc1oC6DFE7H8I9J0K', 'email', NOW(), NOW());
SET @employee_id := LAST_INSERT_ID();

SELECT @tenant_id AS tenant_id, @service_id AS service_id, @employee_id AS employee_id;

-- Simulates an old application node after migration: it omits active_slot_key.
-- The column default MUST fail closed by storing 'active'.
INSERT INTO booking_holds
  (tenant_id, service_id, employee_id, date, start_time, end_time, session_id, expires_at, created_at, updated_at)
VALUES
  (@tenant_id, @service_id, @employee_id, '2026-07-13', '12:00:00', '12:30:00', 'receipt-old-node-a', '2026-07-13 12:25:00', NOW(), NOW());

SELECT session_id, active_slot_key
FROM booking_holds
WHERE session_id = 'receipt-old-node-a';
-- Expected: active_slot_key = active

INSERT INTO booking_holds
  (tenant_id, service_id, employee_id, date, start_time, end_time, session_id, expires_at, created_at, updated_at)
VALUES
  (@tenant_id, @service_id, @employee_id, '2026-07-13', '12:00:00', '12:30:00', 'receipt-old-node-b', '2026-07-13 12:26:00', NOW(), NOW());
-- Expected: ERROR 1062 duplicate entry ... for key 'booking_holds_unique_active_slot'

ROLLBACK;
```

## Rollback Safety Receipt

Rollback cannot be zero-downtime safe when expired/non-active duplicate slot rows exist, because the legacy unique index covers all rows for a tenant, employee, date, and time range. That is intentional: the rollback MUST refuse before destructive DDL and requires maintenance cleanup/deduplication first.

To prove rollback order on MySQL/MariaDB, inspect the migration source and, on a disposable schema with no duplicate slot rows, run `php artisan migrate:rollback --step=1 --env=testing`. Expected rollback order:

1. Query duplicate slot groups across all `booking_holds` rows.
2. Throw with cleanup instructions if any duplicate group exists.
3. Create `booking_holds_unique_slot` while `booking_holds_unique_active_slot` and `active_slot_key` still exist.
4. Drop `booking_holds_unique_active_slot`.
5. Drop `active_slot_key`.
6. Drop `booking_holds_tenant_id_fk_support` after the legacy unique index is back.

If duplicate expired/non-active rows exist, record PASS only when rollback aborts before `booking_holds_unique_active_slot` or `active_slot_key` is removed. Recovery is to delete or archive the duplicate expired/non-active rows identified by the exception, then rerun rollback.

Record the captured `tenant_id`, `service_id`, `employee_id`, engine version, duplicate-key output, rollback result, and any cleanup check output in the pass/fail table above.

## Expected Outcome

- Expired duplicates with `active_slot_key = NULL` insert successfully.
- Duplicate active rows with `active_slot_key = 'active'` fail on `booking_holds_unique_active_slot`.
- The active unique index is created before the legacy unique index is dropped; if replacement index creation fails, legacy uniqueness remains as the concrete fail-closed recovery path.
- Rollback creates the legacy unique index before dropping active uniqueness or `active_slot_key`; if legacy index creation is impossible because expired duplicates exist, rollback refuses before destructive DDL and requires maintenance cleanup.
- Old application nodes that omit `active_slot_key` receive the database default `active`, so migration-before-code fails closed instead of creating NULL active holds.
- Code deployed before the migration omits `active_slot_key` when the column is absent, so inserts still work against the pre-migration schema.

## Deployment Note

The Slice 1 remediation supports either code-before-migration or migration-before-code for hold creation. The database default protects old nodes after migration, and `BookingService::createHold()` checks column existence before writing `active_slot_key`. Slice 2 still owns expired-rebooking service semantics that clear expired active tokens before insert.
