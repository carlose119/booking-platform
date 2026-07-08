# Design: Admin Dashboard

## Technical Approach

Replace the default FilamentPHP dashboard in the Tenant panel with a custom `Dashboard` page composing four widgets: stats overview, revenue chart, upcoming appointments table, and quick actions. All queries go through a new `DashboardMetricsService` that is tenant-scoped and cached. No database schema changes — the existing `bookings` and `services` tables have sufficient indexes.

## Architecture Decisions

| Decision | Options | Tradeoff | Choice |
|----------|---------|----------|--------|
| Dashboard page | Custom `Dashboard extends Filament\Pages\Dashboard` vs. custom Livewire page | Filament base gives free layout, widget registration, and navigation. Custom Livewire requires reinventing all of that. | Extend `Filament\Pages\Dashboard` |
| Metrics queries | `DashboardMetricsService` class vs. inline in widgets | Service centralizes caching + scoping; widgets stay thin. Inline duplicates queries across widgets. | `DashboardMetricsService` |
| Tenant scoping | `Filament::getTenant()` vs. `auth()->user()->tenant_id` | Both work. Existing resources use `auth()->user()->tenant_id` — follow existing convention for consistency. | `auth()->user()->tenant_id` |
| Caching | `Cache::remember` with 60s TTL vs. no cache | 60s cache prevents hammering DB on every widget load. No cache risks slow loads at scale. | `Cache::remember` (60s TTL) |
| Chart type | Line chart (revenue trend) vs. bar chart | Line better shows trend over 30 days. Bar is better for discrete categories. | Line chart |

## Data Flow

```
TenantPanelProvider → Dashboard page
    ├── StatsOverviewWidget → DashboardMetricsService::getTodayMetrics()
    ├── RevenueChartWidget → DashboardMetricsService::getRevenueTrend(30)
    ├── UpcomingAppointmentsWidget → DashboardMetricsService::getUpcomingBookings(7)
    └── QuickActionsWidget → static links (no service needed)

DashboardMetricsService
    ├── Cache::remember('metrics:{tenant_id}:today', 60s, closure)
    ├── Cache::remember('metrics:{tenant_id}:revenue-trend', 60s, closure)
    └── Cache::remember('metrics:{tenant_id}:upcoming', 60s, closure)
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `app/Filament/Pages/Dashboard.php` | Create | Custom dashboard page, overrides `getWidgets()` to compose 4 widgets, `getColumns()` for responsive layout |
| `app/Filament/Widgets/StatsOverviewWidget.php` | Create | Stats widget: bookings today, revenue today, active bookings count |
| `app/Filament/Widgets/RevenueChartWidget.php` | Create | Line chart: 30-day daily revenue trend for paid bookings |
| `app/Filament/Widgets/UpcomingAppointmentsWidget.php` | Create | Custom widget: table of next 7 days' bookings with date, time, customer, service, status |
| `app/Filament/Widgets/QuickActionsWidget.php` | Create | Custom widget: navigation links to Bookings, Services, Employee Schedules |
| `app/Services/DashboardMetricsService.php` | Create | Tenant-scoped aggregation service with 60s cached queries |
| `resources/views/filament/widgets/upcoming-appointments-widget.blade.php` | Create | Blade template for upcoming appointments table widget |
| `resources/views/filament/widgets/quick-actions-widget.blade.php` | Create | Blade template for quick actions navigation widget |
| `app/Providers/Filament/TenantPanelProvider.php` | Modify | Replace `Dashboard::class` import, swap `AccountWidget` for new widgets, register custom Dashboard |
| `database/migrations/2026_07_04_000006_add_dashboard_index_to_bookings.php` | Create | Composite index on `(tenant_id, date, payment_status, status)` for dashboard queries |

## Interfaces / Contracts

```php
// DashboardMetricsService — public API
class DashboardMetricsService
{
    public function __construct(int $tenantId) {}
    public function getTodayMetrics(): array  // ['bookings_today' => int, 'revenue_today_cents' => int, 'active_bookings' => int]
    public function getRevenueTrend(int $days = 30): array  // ['labels' => [...], 'data' => [...]]
    public function getUpcomingBookings(int $days = 7): \Illuminate\Support\Collection
}
```

```php
// StatsOverviewWidget — getStats() returns Stat array
protected function getStats(): array
{
    // Calls DashboardMetricsService, returns Stat::make() instances
}
```

```php
// RevenueChartWidget — getData() returns Chart.js compatible array
protected function getData(): array  // ['labels' => [...], 'datasets' => [['data' => [...]]]]
protected function getType(): string  // 'line'
```

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Unit | `DashboardMetricsService` aggregation logic | Mock Booking/Service models, verify correct counts/sums per tenant |
| Unit | Caching behavior (cache hit vs. miss) | Mock `Cache::remember`, verify service calls it with correct key and TTL |
| Feature | Dashboard page renders with widgets | Livewire test: auth tenant user, assert page loads, assert stats visible |
| Feature | Tenant isolation (no cross-tenant data) | Create bookings for tenant A and B, assert tenant A dashboard shows only A's data |
| Feature | Empty state (no bookings) | Auth tenant with no data, assert widgets render without errors |

## Migration / Rollout

Single migration: add composite index `(tenant_id, date, payment_status, status)` on `bookings` table. This index covers the dashboard's most expensive queries (today's bookings, revenue sum for paid, upcoming date range). No data migration needed. No feature flag — the custom dashboard replaces the default immediately.

## Open Questions

- [ ] Should the revenue chart show `price_cents` from `services` or a `payment_amount` column on bookings? (Current: using `service.price_cents` joined to bookings where `payment_status = paid`)
- [ ] Is the `AccountWidget` still desired alongside the new widgets, or should it be removed? (Current proposal: removed to avoid redundancy)
