# Tasks: Services & Employees Management UI

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 215-250 |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Suggested split | Single PR |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: Yes
Chained PRs recommended: No
Chain strategy: pending
400-line budget risk: Low

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | ServiceResource + EmployeeScheduleResource + UserResource enhancement | PR 1 | Single PR with all UI changes; tests included |

## Phase 1: ServiceResource (CRUD with price conversion)

- [x] 1.1 Create `app/Filament/Resources/ServiceResource.php` with form schema (name, description, price with dollar-to-cents conversion, duration, active toggle) and table columns
- [x] 1.2 Create `app/Filament/Resources/ServiceResource/Pages/ListServices.php` with searchable, sortable list
- [x] 1.3 Create `app/Filament/Resources/ServiceResource/Pages/CreateService.php` with validation rules (price > 0, duration > 0)
- [x] 1.4 Create `app/Filament/Resources/ServiceResource/Pages/EditService.php` with same form schema

## Phase 2: EmployeeScheduleResource (CRUD with validation)

- [x] 2.1 Create `app/Filament/Resources/EmployeeScheduleResource.php` with form schema (employee select, day_of_week select, start/end time pickers) and tenant-scoped employee relationship
- [x] 2.2 Create `app/Filament/Resources/EmployeeScheduleResource/Pages/ListSchedules.php` with employee filter
- [x] 2.3 Create `app/Filament/Resources/EmployeeScheduleResource/Pages/CreateSchedule.php` with validation (end_time > start_time, day_of_week 0-6)
- [x] 2.4 Create `app/Filament/Resources/EmployeeScheduleResource/Pages/EditSchedule.php` with same validation

## Phase 3: UserResource enhancement (Repeater for services)

- [x] 3.1 Modify `app/Filament/Resources/UserResource.php` to add conditional Repeater for employee-service association
- [x] 3.2 Implement Repeater visibility logic: show only when role=Employee
- [x] 3.3 Add CheckboxList inside Repeater to list tenant services for selection
- [x] 3.4 Add afterSave hook to sync employee_services pivot table

## Phase 4: Panel registration + TenantPanelProvider update

- [x] 4.1 Modify `app/Providers/Filament/TenantPanelProvider.php` to register ServiceResource and EmployeeScheduleResource
- [x] 4.2 Verify navigation appears correctly in BusinessAdmin panel
- [x] 4.3 Test tenant scoping: ensure resources only show data for current tenant
