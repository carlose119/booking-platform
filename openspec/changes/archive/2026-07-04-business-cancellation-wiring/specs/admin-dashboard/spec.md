# Delta for Admin Dashboard

## MODIFIED Requirements

### Requirement: Quick Actions Widget

The system SHALL display a navigation widget with links to key admin resources. The View Bookings action MUST navigate to a registered tenant booking management surface.

(Previously: View Bookings targeted a booking list page that was specified but not backed by a tenant booking resource.)

| Action | Target |
|--------|--------|
| View Bookings | Tenant booking management list page |
| Manage Services | Services list page |
| Manage Employees | Employee schedules page |

#### Scenario: Quick actions render

- GIVEN a tenant admin is authenticated
- WHEN the dashboard loads
- THEN the quick actions widget displays three navigation links
- AND each link navigates to the correct Filament resource page

#### Scenario: View Bookings target is registered

- GIVEN a tenant admin clicks View Bookings
- WHEN navigation occurs
- THEN the registered tenant booking management list page opens
- AND all displayed bookings are scoped to the active tenant
