# Tasks: Complete Tenant Admin Resources

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 520-750 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 Tenant User Management → PR 2 Employee Schedule Management |
| Delivery strategy | ask-always |
| Chain strategy | stacked-to-main |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Tenant User Management | PR 1 | Register `UserResource`; include policy/resource tests. |
| 2 | Employee Schedule Management | PR 2 | Register `EmployeeScheduleResource`; include schedule policy/resource tests. |

## Phase 1: RED — Tenant User Management

- [x] 1.1 Create `tests/Unit/Policies/UserPolicyTest.php` for BusinessAdmin-only CRUD, tenant-bound records, and Employee/Client denial.
- [x] 1.2 Create `tests/Feature/Filament/UserResourceTest.php` for T1-only listing, denied non-admin access, active-tenant create, elevated-role rejection, and T1 service options.
- [x] 1.3 Run `php artisan test tests/Unit/Policies/UserPolicyTest.php tests/Feature/Filament/UserResourceTest.php` and confirm failures describe missing safeguards.

## Phase 2: GREEN/REFACTOR — Tenant User Management

- [x] 2.1 Update `app/Policies/UserPolicy.php` so only active-tenant BusinessAdmins can list/create/update/delete tenant users.
- [x] 2.2 Update `app/Filament/Resources/UserResource.php` to scope queries, expose only Employee/Client roles, and restrict employee service options to active-tenant services.
- [x] 2.3 Update `app/Filament/Resources/UserResource/Pages/CreateUser.php` to force `tenant_id` from the active Filament tenant before create.
- [x] 2.4 Update `app/Filament/Resources/UserResource/Pages/EditUser.php` to reject elevated roles, cross-tenant services, and self-role changes.
- [x] 2.5 Register `UserResource` in `app/Providers/Filament/TenantPanelProvider.php`; rerun the Phase 1 command plus `vendor/bin/pint --dirty`.

## Slice 1 Surgical Remediation — Tenant User Management

- [x] SR-1 Add RED coverage proving BusinessAdmin cannot update/delete self or another BusinessAdmin, including Filament edit/action entry points.
- [x] SR-2 Restrict `UserPolicy::update/delete` to same-tenant Employee/Client targets so BusinessAdmins cannot demote or delete BusinessAdmins.
- [x] SR-3 Verify BusinessAdmin can still update safe fields/roles for Employee/Client users and delete Employee/Client users in the active tenant.
- [x] SR-4 Run targeted UserPolicy/UserResource tests, relevant Filament/RBAC tests, full `php artisan test`, Pint dirty test, and `git diff --check`.

## Phase 3: RED — Employee Schedule Management

- [x] 3.1 Create `tests/Unit/Policies/EmployeeSchedulePolicyTest.php` for BusinessAdmin tenant CRUD and Employee own-schedule read-only access.
- [x] 3.2 Create `tests/Feature/Filament/EmployeeScheduleResourceTest.php` for tenant-only listings/options, cross-tenant denial, valid hours, invalid day/time, and Employee write denial.
- [x] 3.3 Run `php artisan test tests/Unit/Policies/EmployeeSchedulePolicyTest.php tests/Feature/Filament/EmployeeScheduleResourceTest.php` and confirm failures cover schedule rules.

## Phase 4: GREEN/REFACTOR — Employee Schedule Management

- [x] 4.1 Create `app/Policies/EmployeeSchedulePolicy.php` with BusinessAdmin same-tenant CRUD and Employee own-schedule `viewAny/view` only.
- [x] 4.2 Update `app/Filament/Resources/EmployeeScheduleResource.php` to scope query by role/tenant, filter employee options, validate `day_of_week` 0-6, and require `end_time` after `start_time`.
- [x] 4.3 Hide or deny schedule create/edit/delete actions for Employees in `app/Filament/Resources/EmployeeScheduleResource.php`.
- [x] 4.4 Register `EmployeeScheduleResource` in `app/Providers/Filament/TenantPanelProvider.php`; run schedule tests and `vendor/bin/pint --dirty`.

## Phase 5: Final Verification

- [x] 5.1 Run `php artisan test tests/Unit/Policies tests/Feature/Filament/UserResourceTest.php tests/Feature/Filament/EmployeeScheduleResourceTest.php`.
- [x] 5.2 Run `composer test` and confirm user-management, schedule-management, and rbac-roles scenarios pass.

## Slice 2 Surgical Remediation — Employee Schedule Management

- [x] SR-5 Add RED coverage proving BusinessAdmin can successfully edit same-tenant employee schedules through the Filament edit page.
- [x] SR-6 Add RED coverage proving BusinessAdmin can successfully delete same-tenant employee schedules through the EditSchedule header delete action.
- [x] SR-7 Verify tenant isolation and Employee read-only behavior still pass with targeted schedule, relevant policy/Filament, full suite, Pint dirty test, and `git diff --check`.

## Slice 2 Reliability Remediation — Employee Schedule Reassignment

- [x] SR-8 Add RED coverage proving BusinessAdmin cannot update an existing same-tenant schedule by reassigning `employee_id` to another tenant's employee.
- [x] SR-9 Keep production unchanged if existing `EmployeeScheduleResource` validation already rejects the reassignment and preserves the original schedule data.
- [x] SR-10 Run targeted schedule, policy + schedule, relevant Filament/RBAC, full suite, Pint dirty test, and `git diff --check` verification.
