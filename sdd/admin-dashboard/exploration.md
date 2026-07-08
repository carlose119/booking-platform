# Exploration: Admin Dashboard

## Current State

The booking-platform has a **fully functional FilamentPHP v5 multi-tenant setup** with **no dashboard customization**:

### Existing Infrastructure
- **Two Filament panels**: `super-admin` and `tenant` (tenant uses Filament native multi-tenancy with `Tenant` model)
- **Tenant panel resources**: `UserResource`, `ServiceResource`, `EmployeeScheduleResource`
- **No dashboard widgets**: Both panels use default `Dashboard` page with only `AccountWidget`
- **Booking model** (`app/Models/Booking.php`): Has `date`, `start_time`, `end_time`, `status`, `payment_status`, relationships to `Service` and `Employee` (User)
- **Service model** (`app/Models/Service.php`): Has `price_cents`, `duration_minutes`, `active` flag
- **Tenant model** (`app\Models/Tenant.php`): Implements `HasTenants`, has `bookings()`, `services()`, `users()` relationships
- **Booking statuses**: `pending`, `confirmed`, `cancelled`, `completed`
- **Payment statuses**: `unpaid`, `paid`, `refunded`, `partial`
- **Indexes**: `bookings` table has availability index (`date`, `start_time`, `end_time`) and `tenant_id` foreign key index

### What's Missing
- **No custom Dashboard page**: Using default Filament `Dashboard` class (2-column layout, no widget ordering)
- **No widget classes**: No `StatsOverviewWidget`, `ChartWidget`, `TableWidget` implementations
- **No metric calculations**: No service classes for aggregating bookings, revenue, occupancy
- **No activity feed**: No logging of recent booking changes (only timestamps on models)
- **No revenue aggregation**: No queries summing `service.price_cents` for paid bookings
- **No occupancy rate logic**: No definition or calculation of time-slot utilization

### Multi-Tenant Context
- Dashboard must be **tenant-scoped** (each tenant sees only their own data)
- Filament's `Filament::getTenant()` provides current tenant context
- Widget queries must filter by `tenant_id`

## Affected Areas

- `app/Providers/Filament/TenantPanelProvider.php` — Replace default `Dashboard` with custom page, register widget classes
- `app/Filament/Pages/Dashboard.php` — **New file**: Custom dashboard page extending `Filament\Pages\Dashboard`
- `app/Filament/Widgets/StatsOverviewWidget.php` — **New file**: Overview metrics (bookings today, revenue, occupancy)
- `app/Filament/Widgets/RevenueChartWidget.php` — **New file**: Revenue trends (daily/weekly/monthly)
- `app/Filament/Widgets/UpcomingAppointmentsWidget.php` — **New file**: Table of upcoming bookings
- `app/Filament/Widgets/RecentActivityWidget.php` — **New file**: Recent booking changes
- `app/Filament/Widgets/QuickActionsWidget.php` — **New file**: Quick action buttons (view bookings, manage services, etc.)
- `app/Services/DashboardMetricsService.php` — **New file**: Metric calculation logic (optional, can be inline)
- `resources/views/filament/widgets/quick-actions-widget.blade.php` — **New file**: Custom Blade view for quick actions widget

## Approaches

### 1. Custom Dashboard Page with Widget Composition

Extend `Filament\Pages\Dashboard` and override `getWidgets()` to return an ordered array of custom widget classes. Each widget is a separate class extending `StatsOverviewWidget`, `ChartWidget`, `TableWidget`, or `Widget`.

| Aspect | Details |
|--------|---------|
| **Pros** | Full control over widget ordering and column spans; clean separation of concerns; each widget is testable; matches Filament conventions |
| **Cons** | Requires creating a custom page class (trivial); need to maintain widget registration in one place |
| **Effort** | Low |

### 2. Default Dashboard with Panel Widget Registration

Add widget classes directly to `TenantPanelProvider`'s `->widgets([...])` array, keeping the default `Dashboard` page.

| Aspect | Details |
|--------|---------|
| **Pros** | No custom page needed; simplest setup |
| **Cons** | Limited layout control (default 2-column grid); widget order determined by `$sort` property; cannot customize column spans per widget; harder to achieve desired dashboard layout |
| **Effort** | Very Low |

### 3. Custom Dashboard with Inline Metrics (No Separate Service)

Compute metrics directly in widget classes without a separate service layer.

| Aspect | Details |
|--------|---------|
| **Pros** | Fewer files; immediate context in widget |
| **Cons** | Potential duplicate queries across widgets; harder to test metric logic; mixing presentation and business logic |
| **Effort** | Low |

## Recommendation

**Approach 1: Custom Dashboard Page with Widget Composition**

This is the clear winner for a booking SaaS dashboard:

1. **Layout control**: Stats overview should span full width, revenue chart full width, upcoming appointments and recent activity side-by-side (2 columns), quick actions maybe full width or right column. Custom page allows `Grid::make(2)->schema([...])` with precise column spans.

2. **Widget separation**: Each widget class focuses on one concern (metrics, charts, tables). Easy to reorder, enable/disable per tenant, or lazy-load.

3. **Filament-native**: Follows Filament's widget system exactly. Uses built-in `StatsOverviewWidget` for metric cards, `ChartWidget` for revenue charts, `TableWidget` for appointment lists.

4. **Performance**: Widget polling (`canPoll`) and lazy loading (`$isLazy`) can be enabled per widget to avoid heavy queries on initial load.

5. **Future-proof**: Adding new widgets (e.g., employee performance, service popularity) is just adding a class and registering it.

**Implementation plan**:
1. Create `app/Filament/Pages/Dashboard.php` extending `Filament\Pages\Dashboard`
2. Override `getWidgets()` to return ordered widget classes
3. Create each widget class in `app/Filament/Widgets/`
4. Update `TenantPanelProvider` to use custom Dashboard page
5. (Optional) Create `DashboardMetricsService` for complex queries

## Risks

- **Performance with large datasets**: Aggregating revenue and occupancy over months could be slow without proper indexing. Mitigation: use date range filters, cache aggregates, consider materialized views for high-volume tenants.
- **Occupancy rate definition**: No clear business definition of "occupancy". Is it percentage of booked time slots? Bookings vs available slots per employee? Need clarification from user. Mitigation: start with simple metric (bookings per day vs average) and iterate.
- **Widget ordering across panels**: SuperAdmin panel may want a different dashboard (system-wide metrics). The custom dashboard page is per-panel, so no conflict, but need to ensure SuperAdmin panel remains unchanged.
- **Activity feed data source**: No activity log exists. Recent bookings can be derived from `updated_at` timestamps, but status changes aren't tracked. Mitigation: for MVP, show recent bookings (created/updated) and later add activity logging.
- **Multi-tenant query isolation**: All widget queries must filter by `tenant_id`. Forgetting this filter would leak data across tenants. Mitigation: use Filament's tenant context or explicit `where('tenant_id', Filament::getTenant()->id)`.

## Ready for Proposal

**Yes** — the exploration is complete. The orchestrator should proceed to `sdd-propose` with the recommendation to implement **Approach 1 (Custom Dashboard Page with Widget Composition)**. The user should be asked to clarify:
1. Which panel(s) should have the admin dashboard (Tenant panel only, or also SuperAdmin?)
2. Definition of "occupancy rate" (bookings per time slot? revenue vs capacity?)
3. Preferred revenue chart aggregation (daily, weekly, monthly – all three or selectable?)
4. Whether quick actions should include "Create New Booking" (admin can book on behalf of client)