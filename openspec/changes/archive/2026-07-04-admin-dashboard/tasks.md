# Tasks: Admin Dashboard

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 470-600 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 (Service + Migration) → PR 2 (Page + Widgets) |
| Delivery strategy | ask-on-risk |
| Chain strategy | stacked-to-main |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | DashboardMetricsService + migration + unit tests | PR 1 (base: main) | Foundation: all other tasks depend on this |
| 2 | Dashboard page + 4 widgets + blade templates + panel config + feature tests | PR 2 (base: main) | Depends on PR 1; compose UI layer |

## Phase 1: Foundation / Infrastructure

- [x] 1.1 Create `database/migrations/2026_07_04_000006_add_dashboard_index_to_bookings.php` — composite index `(tenant_id, date, payment_status, status)` on `bookings`
- [x] 1.2 Create `app/Services/DashboardMetricsService.php` — constructor takes `$tenantId`, exposes `getTodayMetrics()`, `getRevenueTrend($days)`, `getUpcomingBookings($days)`
- [x] 1.3 Implement `getTodayMetrics()` — count bookings today, sum revenue for paid bookings, count active (confirmed/pending); all queries use `->where('tenant_id', $this->tenantId)`; wrap each in `Cache::remember("metrics:{$tenantId}:today", 60, fn)`
- [x] 1.4 Implement `getRevenueTrend($days)` — join `bookings` to `services` where `payment_status = paid`, group by date, return `['labels' => [...], 'data' => [...]]`; cached 60s
- [x] 1.5 Implement `getUpcomingBookings($days)` — query bookings where `date BETWEEN today AND today + $days`, ordered by date ASC then start_time ASC, eager-load `service` relation; cached 60s

## Phase 2: Core Implementation — Dashboard Page

- [x] 2.1 Create `app/Filament/Pages/Dashboard.php` — extend `Filament\Pages\Dashboard`, override `getWidgets()` to return `[StatsOverviewWidget::class, RevenueChartWidget::class, UpcomingAppointmentsWidget::class, QuickActionsWidget::class]`
- [x] 2.2 Override `getColumns()` for responsive layout (e.g., `['xl' => 2, 'lg' => 1]`)

## Phase 3: Widgets

- [x] 3.1 Create `app/Filament/Widgets/StatsOverviewWidget.php` — extend `Filament\Widgets\StatsOverviewWidget`, `getStats()` calls `DashboardMetricsService::getTodayMetrics()`, returns three `Stat::make()` instances (bookings today, revenue today formatted as currency, active bookings)
- [x] 3.2 Create `app/Filament/Widgets/RevenueChartWidget.php` — extend `Filament\Widgets\LineChartWidget`, `getData()` calls `DashboardMetricsService::getRevenueTrend(30)`, `getType()` returns `'line'`
- [x] 3.3 Create `app/Filament/Widgets/UpcomingAppointmentsWidget.php` — custom widget, calls `DashboardMetricsService::getUpcomingBookings(7)`, renders table via blade view
- [x] 3.4 Create `resources/views/filament/widgets/upcoming-appointments-widget.blade.php` — table with columns: Date, Time, Customer, Service, Status; empty state message when collection is empty
- [x] 3.5 Create `app/Filament/Widgets/QuickActionsWidget.php` — custom widget with static links to Bookings list, Services list, Employee Schedules
- [x] 3.6 Create `resources/views/filament/widgets/quick-actions-widget.blade.php` — navigation links using Filament components

## Phase 4: Integration / Wiring

- [x] 4.1 Modify `app/Providers/Filament/TenantPanelProvider.php` — replace `use Filament\Pages\Dashboard` import with `use App\Filament\Pages\Dashboard`, replace `AccountWidget` with the four new widgets in `->widgets([...])`, update `->pages([...])` to use custom Dashboard

## Phase 5: Testing

- [x] 5.1 Create `tests/Unit/Services/DashboardMetricsServiceTest.php` — test `getTodayMetrics()` returns correct counts/sums scoped to tenant; test `getRevenueTrend()` returns correct labels and data; test `getUpcomingBookings()` returns only future bookings ordered correctly
- [x] 5.2 Test caching behavior — mock `Cache::remember`, verify service calls with correct keys and TTL (60s)
- [x] 5.3 Test tenant isolation — create bookings for two tenants, assert metrics only return current tenant's data
- [x] 5.4 Create `tests/Feature/Filament/DashboardPageTest.php` — auth tenant user, assert dashboard page loads (200), assert stats widget visible, assert chart renders, assert upcoming table renders, assert quick actions render
- [x] 5.5 Test empty states — auth tenant with no bookings, assert all widgets render without errors, assert empty state messages display
