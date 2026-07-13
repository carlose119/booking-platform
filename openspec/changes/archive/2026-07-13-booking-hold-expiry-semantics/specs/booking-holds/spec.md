# Delta for Booking Holds

## ADDED Requirements

### Requirement: Active-Hold Migration Safety

The system MUST migrate booking_holds to active-only uniqueness without relying on cleanup. Migration MUST backfill expired/non-active rows as non-conflicting and MUST fail safely when duplicate active rows exist for the same tenant, employee, date, and time range.

#### Scenario: Expired rows backfill safely

- GIVEN expired hold rows exist for previously selected slots
- WHEN the active-only uniqueness migration runs
- THEN those rows become non-conflicting for uniqueness
- AND no cleanup is required before new holds can be created

#### Scenario: Duplicate active rows detected

- GIVEN two active holds conflict for the same tenant and slot
- WHEN migration preflight runs
- THEN the migration reports the duplicates and stops before adding the constraint

### Requirement: Database-Specific Hold Tests

The system SHOULD prove active-only uniqueness on MySQL/MariaDB. SQLite tests MAY cover service semantics but MUST NOT be the only evidence for production uniqueness behavior.

#### Scenario: MySQL/MariaDB active uniqueness evidence

- GIVEN the production database supports active-only indexed semantics
- WHEN hold conflict tests run against MySQL/MariaDB
- THEN active duplicates are rejected and expired rows do not block

## MODIFIED Requirements

### Requirement: Race Condition Prevention

The system SHALL enforce database-level uniqueness only for active holds for the same tenant_id, employee_id, date, start_time, and end_time. Expired, converted, or cleaned holds MUST NOT participate in the active uniqueness key. The database MUST reject duplicate active holds for the same slot.
(Previously: The spec required active-only uniqueness, but implementation still used plain slot uniqueness that included expired rows.)

#### Scenario: Second active hold on same slot rejected

- GIVEN hold H1 exists for E1 on Monday 10:00-10:30 with expires_at > now()
- WHEN a second hold H2 is attempted for the same slot
- THEN the database throws a unique constraint violation
- AND H2 is not created

#### Scenario: Expired hold does not block new hold

- GIVEN hold H1 exists for E1 on Monday 10:00-10:30 with expires_at < now()
- WHEN a new hold H2 is attempted for the same slot
- THEN H2 is created successfully
- AND H1 remains safe to delete later as hygiene

### Requirement: Expired Hold Cleanup

The system SHALL run scheduled cleanup to delete holds where expires_at < now(). Cleanup is hygiene only: correctness for availability and hold creation MUST NOT depend on cleanup running before insert. Cleanup MUST preserve tenant isolation.
(Previously: Cleanup deleted expired holds every minute and was tenant-scoped, but correctness dependency was unspecified.)

#### Scenario: Expired holds deleted

- GIVEN holds H1 (expires_at = 9:50) and H2 (expires_at = 10:05) exist
- WHEN the cleanup command runs at 10:00
- THEN H1 is deleted
- AND H2 remains

#### Scenario: Cleanup respects tenant isolation

- GIVEN tenant T1 has expired hold H1 and tenant T2 has expired hold H2
- WHEN cleanup runs for T1
- THEN only H1 is deleted
- AND T2's H2 remains until T2 cleanup runs

#### Scenario: Cleanup absence does not block rebooking

- GIVEN an expired hold still exists for slot 10:00-10:30
- WHEN another guest creates a hold for that slot
- THEN hold creation succeeds without first running cleanup
