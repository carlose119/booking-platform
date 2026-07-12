# Delta for Public Booking Calendar

## MODIFIED Requirements

### Requirement: Conflict Filtering

The system SHALL exclude slots that overlap with existing Bookings where `status != 'cancelled'` AND exclude slots with active (expires_at > now()) holds. Expired holds MUST NOT block availability. For admin reschedule validation only, the system MUST ignore the booking currently being moved while still considering every other booking/active hold in the same tenant, employee, date, and time range.
(Previously: Availability excluded active holds, but alignment with hold creation after expired rows remained ambiguous.)

#### Scenario: Slot partially overlaps booking

- GIVEN E1 has a booking 10:00-10:30 on Monday and service S1 (30 min)
- WHEN availability is requested for E1 on Monday
- THEN the 10:00 slot is excluded
- AND the 10:30 slot remains available if within schedule

#### Scenario: Slot is active hold

- GIVEN hold H1 exists for E1 on Monday 10:00-10:30 with expires_at > now()
- WHEN availability is requested for E1 on Monday
- THEN the 10:00 slot is excluded
- AND the 10:30 slot remains available if within schedule

#### Scenario: Slot has only expired holds

- GIVEN only expired holds exist for E1 on Monday 10:00-10:30
- WHEN availability is requested and the guest selects 10:00
- THEN the slot is shown as available
- AND hold creation for that slot succeeds unless a new active conflict appears

#### Scenario: No conflicts or active holds

- GIVEN E1 has no bookings and no active holds on Monday and service S1 (30 min)
- WHEN availability is requested for E1 on Monday
- THEN all schedule-derived slots are returned as available

#### Scenario: Current booking ignored during reschedule

- GIVEN booking B1 currently occupies 10:00-10:30
- WHEN B1 is validated for a target slot with B1 excluded
- THEN B1 does not conflict with itself
- AND any other overlapping booking or active hold still blocks the slot
