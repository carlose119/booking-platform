# Delta for Public Booking Calendar

## ADDED Requirements

### Requirement: Mobile Slot Selection UX

The system MUST present public booking slots in a mobile-first, touch-friendly layout while preserving existing availability, hold creation, and tenant isolation behavior.

#### Scenario: Mobile slots are selectable

- GIVEN available slots exist for the selected service and date
- WHEN a guest views the calendar on a small viewport
- THEN each slot is readable and selectable with a touch-friendly target
- AND selecting a slot creates the same hold as the existing flow

#### Scenario: No slots are available

- GIVEN no available slots exist for the selected service and date
- WHEN the calendar refreshes availability
- THEN the guest sees an empty state that explains no slots are available
- AND booking, payment, and hold records are unchanged

### Requirement: Public Booking State Feedback

The system MUST show clear progress, loading, disabled, and recoverable error states for public calendar actions without changing booking semantics.

#### Scenario: Step and loading feedback during slot selection

- GIVEN a guest is selecting a service, date, or slot
- WHEN an async calendar action is running
- THEN the current step remains clear
- AND relevant controls are visibly disabled or marked loading until completion

#### Scenario: Hold conflict feedback

- GIVEN a selected slot is no longer available because another guest holds it
- WHEN hold creation fails
- THEN the guest sees a clear conflict message
- AND availability refreshes without creating a booking or payment
