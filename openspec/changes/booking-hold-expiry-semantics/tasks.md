# Tasks: Booking Hold Expiry Semantics

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 450-650 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 migration/model → PR 2 service/release → PR 3 calendar/DB receipt |
| Delivery strategy | two slices / stacked-to-main |
| Chain strategy | stacked-to-main |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | Active-key schema safety | PR 1 | `php artisan test --filter=BookingHoldMigrationTest` | `php artisan migrate:fresh --seed` | migration + `app/Models/BookingHold.php` |
| 2 | Hold creation/release semantics | PR 2 | `php artisan test --filter=BookingServiceHoldTest` | Public booking hold attempt on expired slot | `app/Services/BookingService.php` behavior |
| 3 | Calendar alignment + DB receipt | PR 3 | `php artisan test --filter=BookingCalendarTest` | MySQL/Sail receipt command or documented manual SQL | calendar tests + receipt docs/tests |

## Phase 1: RED Migration Safety Tests

- [x] 1.1 Add `tests/Feature/BookingHoldMigrationTest.php`: expired rows backfill to `active_slot_key = null`, active rows to `'active'`.
- [x] 1.2 Add duplicate-active preflight RED case: same tenant/employee/date/start/end active rows abort before replacing index.

## Phase 2: GREEN Schema and Model

- [x] 2.1 Create `database/migrations/*_add_active_slot_key_to_booking_holds.php` with preflight, backfill, index swap, and guarded rollback.
- [x] 2.2 Update `app/Models/BookingHold.php` with `active_slot_key` fillable and `ACTIVE_SLOT_KEY` constant/scope if useful.

## Phase 3: RED Service Semantics

- [ ] 3.1 Add `tests/Unit/BookingServiceHoldTest.php`: expired hold rebooking succeeds without cleanup; active hold conflict still fails.
- [ ] 3.2 Add release/consume RED cases: cancel/expiration/confirmation paths clear or remove `active_slot_key` before rows can linger.
- [ ] 3.3 Add cleanup hygiene RED cases: expired cleanup deletes expired rows, preserves future rows, and respects tenant isolation.

## Phase 4: GREEN Service Implementation

- [ ] 4.1 Update `app/Services/BookingService.php`: transactionally clear expired matching slot tokens, then insert with `ACTIVE_SLOT_KEY`.
- [ ] 4.2 Update release/consume paths in `app/Services/BookingService.php` and `app/Livewire/BookingCalendar.php` to null active keys when rows remain.
- [ ] 4.3 Verify cleanup remains hygiene-only; adjust cleanup command/service only if tests show active keys linger incorrectly.

## Phase 5: Calendar Alignment and DB Receipt

- [ ] 5.1 Add `tests/Feature/Livewire/BookingCalendarTest.php`: expired-held slot displays available and selected hold succeeds.
- [ ] 5.2 Add active-held calendar conflict coverage: active hold hides slot while neighboring valid slot remains available.
- [x] 5.3 Add MySQL/MariaDB validation receipt: automated optional test command or `docs/testing/booking-hold-expiry-mysql.md` manual command proving active duplicate reject + expired rebook allow.

## Surgical Remediation: Slice 1 Review Blockers

- [x] R1 Make `docs/testing/booking-hold-expiry-mysql.md` self-contained: fixture creation, captured IDs, engine/version capture, transaction rollback/cleanup checks, and pass/fail recording.
- [x] R2 Add service-path coverage proving `BookingService::createHold()` persists `active_slot_key = BookingHold::ACTIVE_SLOT_KEY` when the column exists.
- [x] R3 Remove duplicated migration duplicate-slot exception formatting by extracting a shared formatter covered by up/down duplicate-message tests.
- [x] R4 Make the active/legacy unique index swap release-safe: create `booking_holds_unique_active_slot` before dropping `booking_holds_unique_slot`, preserving FK-support indexing and documenting the fail-closed recovery path.
- [x] R5 Make rollback fail closed: recreate `booking_holds_unique_slot` before dropping `booking_holds_unique_active_slot` or `active_slot_key`, and document duplicate-row cleanup requirements.
