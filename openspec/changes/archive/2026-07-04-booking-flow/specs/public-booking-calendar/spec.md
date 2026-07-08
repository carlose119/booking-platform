# Delta for Public Booking Calendar

## MODIFIED Requirements

### Requirement: Conflict Filtering

The system SHALL exclude slots that overlap with existing Bookings where `status != 'cancelled'` AND exclude slots with active (expires_at > now()) holds.

(Previously: Only excluded slots overlapping with existing Bookings)

#### Scenario: Slot partially overlaps booking

- GIVEN E1 has a booking 10:00-10:30 on Monday and service S1 (30 min)
- WHEN availability is requested for E1 on Monday
- THEN the 10:00 slot is excluded
- AND the 10:30 slot remains available (if within schedule)

#### Scenario: Slot is active hold

- GIVEN hold H1 exists for E1 on Monday 10:00-10:30 with expires_at > now()
- WHEN availability is requested for E1 on Monday
- THEN the 10:00 slot is excluded
- AND the 10:30 slot remains available (if within schedule)

#### Scenario: No conflicts or holds

- GIVEN E1 has no bookings and no active holds on Monday and service S1 (30 min)
- WHEN availability is requested for E1 on Monday
- THEN all schedule-derived slots are returned as available

### Requirement: Livewire Calendar Component

The system SHALL provide a Livewire component exposing `$selectedService`, `$selectedDate`, and `$selectedEmployee` properties that auto-refresh slots on change. Slot selection SHALL trigger hold creation and transition to booking form.

(Previously: Read-only calendar with auto-refresh on selection changes)

#### Scenario: Service selection triggers refresh

- GIVEN the calendar component is rendered
- WHEN the user selects a different service
- THEN available slots re-query with the new service's duration
- AND the slot grid updates without full page reload

#### Scenario: Slot click triggers hold creation

- GIVEN available slot 10:00-10:30 for employee E1
- WHEN the user clicks the slot
- THEN a hold is created via BookingService::createHold()
- AND the component transitions to the guest booking form
- AND the hold details are passed to the form

#### Scenario: Hold creation failure shows error

- GIVEN slot 10:00-10:30 is already held by another guest
- WHEN the user clicks the slot
- THEN hold creation fails
- AND the user sees an error message
- AND the available slots refresh to show current availability

### Requirement: Public Route

The system SHALL expose `GET /{tenant}/book` resolved via tenant slug middleware. Invalid slugs SHALL return 404. The route SHALL support hold creation and booking confirmation.

(Previously: Read-only calendar route)

#### Scenario: Valid tenant slug

- GIVEN a tenant with slug "salon-acme"
- WHEN GET /salon-acme/book is accessed
- THEN the calendar view renders

#### Scenario: Invalid tenant slug

- WHEN GET /nonexistent/book is accessed
- THEN a 404 response is returned
