# Proposal: Booking Hold Expiry Semantics

## Intent

Expired holds must not block customers from taking an available slot. Today availability ignores expired holds, but hold insertion can still fail because the slot unique index includes expired rows. Cleanup remains hygiene only; correctness must come from active-hold semantics.

## Scope

### In Scope
- Make expired holds immediately non-blocking for availability and new hold creation.
- Preserve database-level race-condition prevention for active holds.
- Add MySQL/MariaDB-compatible migration/backfill/index safety.
- Cover concurrency and DB compatibility in the test strategy.
- Keep scheduled cleanup as hygiene, not correctness.

### Out of Scope
- Changing hold TTL, payment flow, booking confirmation, or notification behavior.
- Relying on cleanup-before-insert as the primary correctness mechanism.

## Capabilities

### New Capabilities
- None

### Modified Capabilities
- `booking-holds`: clarify active-only uniqueness, expired-row rebooking, and cleanup-as-hygiene.
- `public-booking-calendar`: align slot availability with hold creation success when only expired holds exist.

## Approach

Use a MySQL/MariaDB-compatible active uniqueness pattern for `booking_holds`: add an active-slot key/token that participates in a unique index only while the hold is active, and becomes non-conflicting when expired/converted/cleaned. Update `BookingService::createHold()` to write active-key fields and keep `AvailabilityService` filtering on `expires_at > now()`. Add a migration that backfills rows, replaces `booking_holds_unique_slot`, and fails safely if duplicate active holds exist.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `database/migrations/*booking_holds*` | Modified/New | Replace plain slot uniqueness with active-only uniqueness. |
| `app/Services/BookingService.php` | Modified | Ensure hold creation writes active-key semantics and preserves duplicate active rejection. |
| `app/Models/BookingHold.php` | Modified | Expose any active-key fields/casts/scopes needed by the schema. |
| `app/Services/AvailabilityService.php` | Modified | Verify availability semantics remain active-hold-only. |
| `tests/Unit`, `tests/Feature` | Modified | Add expired-row rebooking, active conflict, concurrency, and DB coverage. |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| MySQL/MariaDB lack partial indexes | High | Use generated/nullable active-key strategy, not PostgreSQL partial indexes. |
| Existing duplicate active holds | Med | Preflight/backfill check before adding the new unique constraint. |
| SQLite tests miss production behavior | Med | Add MySQL/MariaDB validation path or schema-specific integration evidence. |

## Rollback Plan

Revert code and migration, restore `booking_holds_unique_slot`, and run cleanup to remove expired rows before rollback if duplicates would violate the old index.

## Dependencies

- MySQL 8.0/MariaDB-compatible generated or nullable indexed columns.
- Migration window with preflight duplicate-active-hold check.

## Success Criteria

- [ ] Expired hold rows never block a new hold for the same slot.
- [ ] Concurrent active hold attempts still allow only one winner.
- [ ] Availability display and insert behavior match.
- [ ] Cleanup command remains optional hygiene for correctness.
