# Delta for Public Booking Calendar

## ADDED Requirements

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
