# Design: Booking Hold Expiry Semantics

## Technical Approach

Replace plain slot uniqueness with an active-only nullable token. `booking_holds.active_slot_key` is `'active'` only while a hold participates in uniqueness; expired/released/converted rows use `NULL`. MySQL/MariaDB and SQLite unique indexes allow multiple `NULL` values, so expired rows can remain without blocking rebooking. `BookingService::createHold()` will run in a transaction, clear expired tokens for the requested slot, then insert a new active row. Availability already filters `expires_at > now()` and remains the read-side source for calendar display.

## Architecture Decisions

| Decision | Choice | Alternatives considered | Rationale |
|---|---|---|---|
| Active uniqueness | Nullable `active_slot_key` plus unique index on tenant/employee/date/start/end/key | Partial indexes; generated column using `NOW()`; cleanup-before-insert only | Portable across MySQL/MariaDB and SQLite; avoids unsupported dynamic partial uniqueness; keeps DB-level active conflict rejection. |
| Expiry transition | Clear expired matching slot tokens inside `createHold()` before insert | Global cleanup before insert; no clearing | Makes expired rows non-blocking at selection time without requiring deletion, while preserving cleanup as hygiene. |
| Failure mode | Migration preflight aborts on duplicate active rows before adding the unique index | Let index creation fail opaquely | Produces actionable duplicate details and leaves old schema intact. |
| Concurrency proof | Rely on unique index for one active winner; validate true races on MySQL/MariaDB | Prove all races in SQLite | SQLite can test semantics but not production lock/concurrency behavior. |

## Data Flow

    Calendar availability ──reads active holds (`expires_at > now()`)──→ slots
         │
         └─ select slot ─→ BookingService::createHold()
                         ├─ transaction: clear expired active_slot_key for slot
                         └─ insert active hold (`active_slot_key = 'active'`)
                                └─ DB unique index rejects concurrent active duplicate

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `database/migrations/*_add_active_slot_key_to_booking_holds.php` | Create | Add nullable `active_slot_key`, preflight duplicate active rows, backfill expired rows to `NULL` and active rows to `'active'`, replace `booking_holds_unique_slot` with active-only unique index. |
| `app/Models/BookingHold.php` | Modify | Add `active_slot_key` to `$fillable`; optionally add constants/scopes for active token. |
| `app/Services/BookingService.php` | Modify | Wrap `createHold()` in a transaction, clear expired slot tokens, insert with active token; ensure expired confirmation/cancel paths clear token before delete/update if row remains. |
| `app/Livewire/BookingCalendar.php` | Modify | Replace direct cancel update with service method or include `active_slot_key = null` when expiring a hold. |
| `app/Services/AvailabilityService.php` | Verify/Modify | Keep `BookingHold::active()` filtering; add coverage proving expired holds align with successful hold creation. |
| `tests/Unit/*`, `tests/Feature/*` | Modify | Add semantic, migration-safety, and calendar alignment tests. |

## Interfaces / Contracts

```php
// booking_holds
$table->string('active_slot_key')->nullable(); // 'active' or NULL
$table->unique([
    'tenant_id', 'employee_id', 'date', 'start_time', 'end_time', 'active_slot_key'
], 'booking_holds_unique_active_slot');
```

`BookingHold::ACTIVE_SLOT_KEY = 'active'` should be the only written non-null value.

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Unit | Expired hold no longer blocks `createHold()`; active duplicate still fails; cancel/expired confirmation clears token. | SQLite service tests using nullable unique semantics. |
| Feature | Calendar shows expired-held slot as available and selecting it creates a new hold. | Livewire `BookingCalendarTest`. |
| Migration | Backfill active/expired tokens; duplicate active preflight aborts before index replacement. | Migration-focused tests where practical; document manual MySQL/MariaDB validation. |
| DB-specific | MySQL/MariaDB rejects active duplicates and allows expired-row rebooking under concurrent attempts. | Optional Sail/MySQL integration command or documented validation script; SQLite evidence is not sufficient alone. |

## Threat Matrix

N/A — no routing, shell, subprocess, VCS/PR automation, executable-file classification, or process-integration boundary.

## Migration / Rollout

Create a forward migration that: adds nullable column with default `'active'` for old application nodes; sets expired rows to `NULL`, non-expired rows to `'active'`; queries grouped active rows and throws with duplicate slot IDs if any group count exceeds one; drops `booking_holds_unique_slot`; adds `booking_holds_unique_active_slot`. `BookingService::createHold()` must omit `active_slot_key` when the column is absent so code-before-migration remains compatible, while migration-before-code fails closed through the database default. Rollback must drop the new index/column and restore the old unique index only after asserting no duplicate slot rows exist, otherwise fail with cleanup instructions.

## Open Questions

- [ ] Should DB-specific validation be added as an automated optional test suite or documented as a manual Sail/MySQL receipt?
