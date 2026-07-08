# Proposal: Admin Dashboard

## Intent

The tenant panel uses Filament's default `Dashboard` page with only an `AccountWidget` — tenants have zero visibility into bookings, revenue, or operational metrics. This change adds a custom dashboard with composed widgets so admins can monitor business health at a glance.

## Scope

### In Scope
- Custom `Dashboard` page extending `Filament\Pages\Dashboard` (Tenant panel only)
- Stats overview widget: bookings today, revenue (today/month), active bookings count
- Revenue chart widget: daily revenue trend (last 30 days)
- Upcoming appointments table widget (next 7 days)
- Quick actions widget (links to bookings, services, employees)
- Metrics service class for aggregated queries (tenant-scoped)
- Register widgets in `TenantPanelProvider`

### Out of Scope
- SuperAdmin panel dashboard (unchanged)
- Real-time activity feed (no activity log table yet)
- Occupancy rate metric (needs business definition first)
- Widget lazy-loading or polling optimization (add after first deploy)
- Employee performance or service popularity widgets

## Capabilities

### New Capabilities
- `admin-dashboard`: Custom FilamentPHP dashboard page with composed metric, chart, and table widgets for tenant-scoped business visibility

### Modified Capabilities
None — this is additive. Existing specs (multi-tenant-scaffold, booking-holds, service-management) are referenced but their requirements don't change.

## Approach

Custom Dashboard page + widget composition (Approach 1 from exploration):

1. Create `app/Filament/Pages/Dashboard.php` extending `Filament\Pages\Dashboard`
2. Override `getWidgets()` with ordered widget array
3. Create widget classes: `StatsOverviewWidget`, `RevenueChartWidget`, `UpcomingAppointmentsWidget`, `QuickActionsWidget`
4. Create `app/Services/DashboardMetricsService.php` for tenant-scoped aggregation queries
5. Update `TenantPanelProvider` to register custom Dashboard page

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `app/Providers/Filament/TenantPanelProvider.php` | Modified | Register custom Dashboard page |
| `app/Filament/Pages/Dashboard.php` | New | Custom page with widget composition |
| `app/Filament/Widgets/StatsOverviewWidget.php` | New | Metric cards (bookings, revenue) |
| `app/Filament/Widgets/RevenueChartWidget.php` | New | Daily revenue trend chart |
| `app/Filament/Widgets/UpcomingAppointmentsWidget.php` | New | Table of upcoming bookings |
| `app/Filament/Widgets/QuickActionsWidget.php` | New | Quick nav links |
| `app/Services/DashboardMetricsService.php` | New | Tenant-scoped metric queries |
| `resources/views/filament/widgets/quick-actions-widget.blade.php` | New | Custom Blade view |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Slow queries on large datasets | Medium | Date range filters, caching aggregates, explicit tenant_id indexes |
| Activity feed has no data source | Low | Skip for MVP; show recent bookings via `updated_at` |
| Multi-tenant data leak if query misses tenant_id | Low | All queries go through MetricsService with `Filament::getTenant()` |

## Rollback Plan

Remove custom Dashboard page and widgets. Restore default `Dashboard` by removing the `->pages([...])` override in `TenantPanelProvider`. No database migrations to revert.

## Dependencies

- FilamentPHP v5 widget system (already installed)
- Existing `bookings`, `services` tables with `tenant_id` indexes

## Success Criteria

- [ ] Tenant admin sees 4 widgets on dashboard load
- [ ] Stats widget shows today's booking count and revenue
- [ ] Revenue chart renders 30-day daily trend
- [ ] Upcoming appointments table lists next 7 days of bookings
- [ ] All data is tenant-scoped (no cross-tenant leakage)
- [ ] Dashboard loads in < 2s with 1000+ bookings

## Proposal Question Round

The exploration identified 4 product questions. My assumptions are below — review and correct before specs phase:

1. **Which panel?** → Tenant panel only. SuperAdmin panel unchanged. **Assumption**: correct?
2. **Occupancy rate?** → Deferred to future iteration. Needs business definition (bookings/time-slot vs revenue/capacity). **Assumption**: skip for MVP?
3. **Revenue chart aggregation?** → Daily for last 30 days. No selectable range in MVP. **Assumption**: acceptable?
4. **Quick actions: "Create New Booking"?** → Not included in MVP quick actions (links only: view bookings, manage services, manage employees). **Assumption**: skip admin-initiated booking for now?
