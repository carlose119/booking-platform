# Delta for Admin Dashboard

## MODIFIED Requirements

### Requirement: Dashboard Metrics Overview

The system SHALL display a stats overview widget with key business metrics on the tenant dashboard. Revenue metrics MUST use booking payment snapshots when available and MUST NOT aggregate different currencies into one total; revenue MUST be scoped to one currency or grouped by currency.
(Previously: Revenue today summed `service.price_cents` as one tenant total.)

| Metric | Source | Scope |
|--------|--------|-------|
| Bookings today | `bookings`, date=today, tenant_id | Current tenant |
| Revenue today | paid booking snapshots grouped/scoped by currency | Current tenant + currency |
| Active bookings | status IN (confirmed, pending) | Current tenant |

#### Scenario: Admin views dashboard metrics
- GIVEN a tenant admin is authenticated and paid bookings exist in one currency
- WHEN the dashboard loads
- THEN revenue is displayed for that currency
- AND all values are scoped to the current tenant

#### Scenario: Mixed currencies are not summed
- GIVEN paid bookings exist for the tenant in USD and EUR
- WHEN revenue metrics are calculated
- THEN revenue is grouped by currency or requires a selected currency
- AND no single mixed-currency total is displayed

### Requirement: Revenue Trend Chart

The system SHALL display a line chart showing daily revenue for the last 30 days, grouped or scoped by currency. The chart MUST NOT convert currencies or combine unlike currencies.
(Previously: Chart displayed one daily revenue series without currency grouping.)

#### Scenario: Revenue chart renders with data
- GIVEN paid booking snapshots exist for one currency
- WHEN the dashboard loads
- THEN the chart displays daily revenue for that currency over 30 days

#### Scenario: Revenue chart with mixed currencies
- GIVEN paid booking snapshots exist in multiple currencies
- WHEN the dashboard loads
- THEN chart data is separated by currency or filtered to one currency
- AND no FX conversion is performed
