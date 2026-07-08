# Proposal: Services & Employees Management UI

## Intent

The data layer for services, employees, and schedules is complete (models, migrations, seeder), but there is no admin UI to manage them. BusinessAdmins cannot create services, assign employees to services, or configure employee schedules without direct database access. This change adds the missing Filament resources to complete the management workflow.

## Scope

### In Scope
- `ServiceResource` — CRUD for service catalog (name, description, price, duration, active status)
- `EmployeeScheduleResource` — CRUD for employee availability (day, start/end times, scoped by employee)
- `UserResource` enhancement — Repeater for employee-service association when role=Employee
- Inline validation (price > 0, duration > 0, end_time > start_time, day_of_week 0-6)
- Tenant scoping on all new resources
- Dollar-to-cents conversion in Service forms

### Out of Scope
- Calendar module (future — depends on Service duration + Schedule availability)
- Ad-hoc schedule overrides (blocking free hours)
- Bulk schedule management (copy week pattern)
- New domain specs (specs exist for data-model and user-management)

## Capabilities

### New Capabilities
- `service-management`: Service catalog CRUD — create, edit, list, delete services with price/duration/active fields
- `schedule-management`: Employee schedule CRUD — manage availability per employee with day-of-week and time range validation

### Modified Capabilities
- `user-management`: Add employee-service association via Repeater when role=Employee; modify form schema conditionally

## Approach

Use **Separate Resources** (recommended by exploration):

1. **ServiceResource** — standalone Filament resource with form (name, description, price as dollars, duration, active toggle) and table (sortable, searchable). Tenant-scoped via `getEloquentQuery()`.

2. **EmployeeScheduleResource** — standalone resource with employee select filter, form (employee, day_of_week select, start_time, end_time). Validate end_time > start_time inline.

3. **UserResource enhancement** — add conditional `Repeater` in form schema: visible only when `role = Employee`, listing tenant services as checkboxes/select for association. Persist via `afterSave` hook or eager sync on the pivot.

4. **Validation** — inline Filament form rules, no separate FormRequest classes (follows UserResource pattern).

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `app/Filament/Resources/ServiceResource.php` | New | Service CRUD resource |
| `app/Filament/Resources/ServiceResource/Pages/` | New | List, Create, Edit pages |
| `app/Filament/Resources/EmployeeScheduleResource.php` | New | Schedule CRUD resource |
| `app/Filament/Resources/EmployeeScheduleResource/Pages/` | New | List, Create, Edit pages |
| `app/Filament/Resources/UserResource.php` | Modified | Add Repeater for employee-service association |
| `app/Providers/Filament/TenantPanelProvider.php` | Modified | Register new resources in navigation |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| `employee_schedules` lacks unique constraint on (employee_id, day_of_week) — duplicates possible | Medium | UI validates uniqueness before save; document need for migration in follow-up |
| `price_cents` conversion errors — user enters dollars, form must convert to cents | Low | Dedicated `dehydrateStateUsing` callback; test conversion edge cases |
| Service deletion cascades to future Booking FK | Low | Verify booking migration has `restrictOnDelete` before deploying |

## Rollback Plan

1. Delete new resource files: `ServiceResource.php`, `EmployeeScheduleResource.php`, and their `Pages/` directories
2. Revert `UserResource.php` to remove Repeater schema
3. Remove resource registrations from `TenantPanelProvider.php`
4. No migration rollback needed (data layer unchanged)

## Dependencies

- Existing `Service`, `User`, `EmployeeSchedule` models (already complete)
- Existing `employee_services` pivot table (already complete)
- FilamentPHP 5 (already installed)

## Success Criteria

- [ ] BusinessAdmin can create, edit, list, and delete services in Tenant panel
- [ ] BusinessAdmin can create and manage employee schedules with day/time validation
- [ ] BusinessAdmin can associate employees with services via UserResource when role=Employee
- [ ] All new resources are tenant-scoped (no cross-tenant data leakage)
- [ ] Price is stored in cents, displayed in dollars in forms
- [ ] No duplicate schedule entries for same employee/day (UI prevents)
