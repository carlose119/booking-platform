# Public Booking Calendar Specification

## Purpose

Enable customers to browse available time slots per service, employee, and date via a public-facing calendar UI. Read-only (no booking creation in Slice 1).

## Requirements

### Requirement: Currency-Aware Public Amount Display

The system MUST display service, booking, and payment amounts in the active tenant's resolved currency on the public booking flow. The system MUST NOT perform FX conversion and MUST fall back to `usd` for existing or missing currency data.

#### Scenario: Service amount uses tenant currency

- GIVEN tenant T1 default_currency=`eur` and service S1 has a stored price
- WHEN a guest views T1's public booking page
- THEN S1's amount is displayed with EUR currency context
- AND data from other tenants is not used

#### Scenario: Payment amount matches snapshot

- GIVEN a payment-required booking has charged_amount and charged_currency
- WHEN the guest reviews or pays for the booking
- THEN the displayed payment amount matches the booking snapshot
- AND no converted amount is shown

#### Scenario: Legacy booking display falls back to USD

- GIVEN an existing booking has no charged_currency snapshot
- WHEN it is displayed in the public flow
- THEN the amount is displayed using `usd`

### Requirement: Availability Slot Generation

The system SHALL generate time slots from EmployeeSchedule records matching the requested date's day_of_week. Slot step equals the selected service's `duration_minutes`. No buffer between consecutive slots.

#### Scenario: Generate slots from schedule

- GIVEN employee E1 has schedule day_of_week=1 (Monday) 09:00-17:00 and service S1 (30 min)
- WHEN availability is requested for E1 on a Monday
- THEN slots are generated at 09:00, 09:30, 10:00, ... 16:30
- AND each slot start + duration_minutes <= 17:00

#### Scenario: No schedule for day

- GIVEN employee E1 has no schedule for day_of_week=3 (Wednesday)
- WHEN availability is requested for E1 on a Wednesday
- THEN an empty slot list is returned

### Requirement: Conflict Filtering

The system SHALL exclude slots that overlap with existing Bookings where `status != 'cancelled'` AND exclude slots with active (expires_at > now()) holds. Expired holds MUST NOT block availability. For admin reschedule validation only, the system MUST ignore the booking currently being moved while still considering every other booking/active hold in the same tenant, employee, date, and time range.

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

#### Scenario: No conflicts or active holds

- GIVEN E1 has no bookings and no active holds on Monday and service S1 (30 min)
- WHEN availability is requested for E1 on Monday
- THEN all schedule-derived slots are returned as available

#### Scenario: Current booking ignored during reschedule

- GIVEN booking B1 currently occupies 10:00-10:30
- WHEN B1 is validated for a target slot with B1 excluded
- THEN B1 does not conflict with itself
- AND any other overlapping booking or hold still blocks the slot

### Requirement: Past-Time Filtering

The system SHALL filter out slots whose start_time has already passed when the requested date is today.

#### Scenario: Today's past slots hidden

- GIVEN current time is 11:00 and service S1 (30 min)
- WHEN availability is requested for today
- THEN slots before 11:00 are excluded
- AND slots from 11:00 onward remain available

#### Scenario: Future date unaffected

- GIVEN a date 3 days from now
- WHEN availability is requested
- THEN no time-based filtering is applied regardless of current time

### Requirement: Multi-Employee Display

The system SHALL return available slots grouped by employee_id for the requested service and date.

#### Scenario: Multiple employees

- GIVEN service S1 is offered by E1 and E2, both with schedules on Monday
- WHEN availability is requested for S1 on Monday
- THEN a map of employee_id → slot list is returned
- AND each employee's slots are computed independently

### Requirement: Tenant Isolation

The system SHALL scope all availability queries to the active tenant. No cross-tenant data SHALL be returned.

#### Scenario: Tenant-scoped query

- GIVEN tenants T1 and T2 both have employees with Monday schedules
- WHEN availability is requested for T1
- THEN only T1's employees and bookings are considered
- AND T2's data is never queried or returned

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
