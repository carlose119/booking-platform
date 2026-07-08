# Delta for Public Booking Calendar

## MODIFIED Requirements

### Requirement: Conflict Filtering

The system SHALL exclude slots overlapping existing non-cancelled bookings and active holds. For admin reschedule validation only, the system MUST ignore the booking currently being moved while still considering every other booking/hold in the same tenant, employee, date, and time range.

(Previously: Conflict filtering had no current-booking exclusion for reschedule validation.)

#### Scenario: Slot partially overlaps booking

- GIVEN E1 has booking B1 at 10:00-10:30
- WHEN availability is requested without exclusion
- THEN the 10:00 slot is excluded and adjacent valid slots remain

#### Scenario: Slot is active hold

- GIVEN an active hold exists at 10:00-10:30
- WHEN availability is requested
- THEN the 10:00 slot is excluded

#### Scenario: No conflicts or holds

- GIVEN E1 has no bookings and no active holds
- WHEN availability is requested
- THEN all schedule-derived valid slots are returned

#### Scenario: Current booking ignored during reschedule

- GIVEN booking B1 currently occupies 10:00-10:30
- WHEN B1 is validated for a target slot with B1 excluded
- THEN B1 does not conflict with itself
- AND any other overlapping booking or hold still blocks the slot
