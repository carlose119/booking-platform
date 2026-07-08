# Admin Dashboard Specification

## Purpose

Provide tenant admins with a centralized dashboard displaying real-time business metrics, revenue trends, upcoming appointments, and quick navigation — replacing the default FilamentPHP dashboard with zero operational visibility.

## Requirements

### Requirement: Dashboard Metrics Overview

The system SHALL display a stats overview widget with key business metrics on the tenant dashboard.

| Metric | Source | Scope |
|--------|--------|-------|
| Bookings today | `bookings` table, `date = today`, `tenant_id` | Current tenant |
| Revenue today | `bookings` where `payment_status = paid`, sum of `service.price_cents` | Current tenant |
| Active bookings | `bookings` where `status IN (confirmed, pending)` | Current tenant |

#### Scenario: Admin views dashboard metrics

- GIVEN a tenant admin is authenticated and on the dashboard page
- WHEN the dashboard loads
- THEN the stats widget displays bookings count, revenue amount, and active bookings count for today
- AND all values are scoped to the current tenant

#### Scenario: No bookings today

- GIVEN a tenant admin is authenticated
- WHEN the dashboard loads and no bookings exist for today
- THEN the stats widget displays zero for all metrics
- AND no error is thrown

### Requirement: Revenue Trend Chart

The system SHALL display a line chart showing daily revenue for the last 30 days.

#### Scenario: Revenue chart renders with data

- GIVEN a tenant admin is authenticated
- WHEN the dashboard loads
- THEN a line chart displays daily revenue (paid bookings) for the past 30 days
- AND the x-axis shows dates, y-axis shows revenue amount

#### Scenario: Revenue chart with no historical data

- GIVEN a tenant admin with no paid bookings in the last 30 days
- WHEN the dashboard loads
- THEN the chart renders with zero values across all days
- AND no error is thrown

### Requirement: Upcoming Appointments Table

The system SHALL display a table of bookings scheduled for the next 7 days.

| Column | Source |
|--------|--------|
| Date | `bookings.date` |
| Time | `bookings.start_time` |
| Customer | `bookings.customer_name` or `guest_name` |
| Service | `bookings.service.name` |
| Status | `bookings.status` |

#### Scenario: Upcoming appointments listed

- GIVEN a tenant admin is authenticated
- WHEN the dashboard loads
- THEN a table lists all bookings where `date BETWEEN today AND today + 7 days`
- AND results are ordered by date ASC, then start_time ASC
- AND all results are scoped to the current tenant

#### Scenario: No upcoming appointments

- GIVEN a tenant admin with no bookings in the next 7 days
- WHEN the dashboard loads
- THEN the table displays an empty state message
- AND no error is thrown

### Requirement: Quick Actions Widget

The system SHALL display a navigation widget with links to key admin resources.

| Action | Target |
|--------|--------|
| View Bookings | Bookings list page |
| Manage Services | Services list page |
| Manage Employees | Employee schedules page |

#### Scenario: Quick actions render

- GIVEN a tenant admin is authenticated
- WHEN the dashboard loads
- THEN the quick actions widget displays three navigation links
- AND each link navigates to the correct Filament resource page

### Requirement: Tenant Data Isolation

The system SHALL ensure all dashboard queries are scoped to the authenticated tenant.

#### Scenario: Metrics exclude other tenants

- GIVEN two tenants with bookings on the same date
- WHEN tenant A views the dashboard
- THEN metrics, chart, and table only include tenant A's data
- AND tenant B's data is not visible

### Requirement: Dashboard Performance

The system SHALL load the complete dashboard in under 2 seconds with up to 1000 bookings.

#### Scenario: Dashboard loads within performance budget

- GIVEN a tenant with 1000+ bookings in the database
- WHEN the dashboard page is requested
- THEN all widgets render within 2 seconds total
- AND no widget triggers N+1 queries

## Non-Functional Requirements

| Requirement | Target |
|-------------|--------|
| Page load time | < 2s with 1000 bookings |
| Query count | ≤ 5 queries per widget |
| Tenant isolation | All queries use `Filament::getTenant()` |
| Caching | Metrics service MAY cache aggregates (TTL: 60s) |

## Integration Points

| System | Integration |
|--------|-------------|
| FilamentPHP v5 | Widget system, tenant panel, page registration |
| Booking model | Read-only queries for metrics |
| Service model | Price data for revenue calculations |
| TenantPanelProvider | Register custom Dashboard page |

## Acceptance Criteria

- [ ] Tenant admin sees 4 widgets on dashboard load
- [ ] Stats widget shows today's booking count and revenue
- [ ] Revenue chart renders 30-day daily trend
- [ ] Upcoming appointments table lists next 7 days of bookings
- [ ] All data is tenant-scoped (no cross-tenant leakage)
- [ ] Dashboard loads in < 2s with 1000+ bookings
- [ ] Empty states display gracefully when no data exists
