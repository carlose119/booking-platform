# Apply Progress: complete-tenant-admin-resources

## Slice 1 — Tenant User Management

Status: complete
Delivery: stacked-to-main, Slice 1 of 2
Mode: Strict TDD

### Completed Tasks

- [x] 1.1 Create `tests/Unit/Policies/UserPolicyTest.php` for BusinessAdmin-only CRUD, tenant-bound records, and Employee/Client denial.
- [x] 1.2 Create `tests/Feature/Filament/UserResourceTest.php` for T1-only listing, denied non-admin access, active-tenant create, elevated-role rejection, and T1 service options.
- [x] 1.3 Run targeted RED tests and confirm failures describe missing safeguards.
- [x] 2.1 Update `app/Policies/UserPolicy.php` so only active-tenant BusinessAdmins can list/create/update/delete tenant users.
- [x] 2.2 Update `app/Filament/Resources/UserResource.php` to scope queries, expose only Employee/Client roles, and restrict employee service options to active-tenant services.
- [x] 2.3 Update `app/Filament/Resources/UserResource/Pages/CreateUser.php` to force `tenant_id` from the active Filament tenant before create.
- [x] 2.4 Update `app/Filament/Resources/UserResource/Pages/EditUser.php` to reject elevated roles, cross-tenant services, and self-role changes.
- [x] 2.5 Register `UserResource` in `app/Providers/Filament/TenantPanelProvider.php`; rerun targeted tests and Pint.

### TDD Cycle Evidence

| Task | Test File | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|------|-----------|-------|------------|-----|-------|-------------|----------|
| 1.1 | `tests/Unit/Policies/UserPolicyTest.php` | Unit | N/A (new) | ✅ `UserPolicyTest` failed on Employee viewAny allowance | ✅ Targeted tests passed | ✅ BusinessAdmin same-tenant, cross-tenant denial, Employee/Client denial | ✅ Helpers extracted in test |
| 1.2 | `tests/Feature/Filament/UserResourceTest.php` | Integration | N/A (new) | ✅ Resource tests failed on unregistered/unsafe resource and Filament v5 form mismatch | ✅ Targeted tests passed | ✅ Listing, denied access, create tenant forcing, elevated role rejection, service scoping | ✅ Replaced invalid forbidden-table assertion with authorization + persistence check |
| 1.3 | Targeted command | Verification | ✅ Existing Filament baseline: 17 passed | ✅ `php artisan test tests/Unit/Policies/UserPolicyTest.php tests/Feature/Filament/UserResourceTest.php` failed before production changes | ✅ Same command passed: 7 tests, 57 assertions | ✅ Full suite passed: 213 tests, 744 assertions | ✅ `vendor/bin/pint --dirty` fixed style, `--test` passed |
| 2.1 | `tests/Unit/Policies/UserPolicyTest.php` | Unit | ✅ Existing Filament baseline: 17 passed | ✅ Employee/Client policy expectations failed against old policy | ✅ Targeted tests passed | ✅ Same-tenant allow + cross-tenant/non-admin deny cases | ✅ Simplified policy to BusinessAdmin same-tenant resource management |
| 2.2 | `tests/Feature/Filament/UserResourceTest.php` | Integration | ✅ Existing Filament baseline: 17 passed | ✅ Resource tests failed before scoping/role/service safeguards | ✅ Targeted tests passed | ✅ Active tenant list, assignable roles, active tenant service IDs, cross-tenant service validation | ✅ Aligned `UserResource` with Filament v5 `Schema`/action APIs |
| 2.3 | `tests/Feature/Filament/UserResourceTest.php` | Integration | ✅ Existing Filament baseline: 17 passed | ✅ Create test showed missing tenant-forcing behavior | ✅ Targeted tests passed | ✅ Allowed Employee create and elevated BusinessAdmin create rejection | ✅ Tenant assignment centralized through `UserResource::activeTenantId()` |
| 2.4 | `tests/Feature/Filament/UserResourceTest.php` | Integration | ✅ Existing Filament baseline: 17 passed | ✅ Edit test failed before elevated-role/service/self-role safeguards | ✅ Targeted tests passed | ✅ SuperAdmin rejection, cross-tenant service rejection, self-role change rejection | ✅ Used form validation and page save guard instead of UI-only hiding |
| 2.5 | Targeted + style commands | Verification | ✅ Existing Filament baseline: 17 passed | ✅ Registration test failed while `UserResource` was commented out | ✅ Targeted and relevant Filament/RBAC tests passed | ✅ Full `php artisan test` passed | ✅ `vendor/bin/pint --dirty --test` and `git diff --check` passed |

### Verification

- `php artisan test tests/Unit/Policies/UserPolicyTest.php tests/Feature/Filament/UserResourceTest.php` — PASS, 7 tests / 57 assertions.
- `php artisan test tests/Feature/Filament/BookingResourceTest.php tests/Feature/Filament/MultiCurrencyResourceTest.php tests/Unit/Policies/UserPolicyTest.php tests/Feature/Filament/UserResourceTest.php` — PASS, 24 tests / 141 assertions.
- `php artisan test` — PASS, 213 tests / 744 assertions.
- `vendor/bin/pint --dirty --test` — PASS after running `vendor/bin/pint --dirty` to fix two style issues.
- `git diff --check` — PASS.

### Remaining Tasks

- [x] Slice 2: Employee Schedule Management tasks 3.1-4.4 completed in the next section.
- [x] Final verification tasks 5.1-5.2 completed in the next section.

## Slice 1 Surgical Remediation — Tenant User Management

Status: complete
Delivery: stacked-to-main remediation inside Slice 1
Mode: Strict TDD

### Completed Tasks

- [x] SR-1 Add RED coverage proving BusinessAdmin cannot update/delete self or another BusinessAdmin, including Filament edit/action entry points.
- [x] SR-2 Restrict `UserPolicy::update/delete` to same-tenant Employee/Client targets so BusinessAdmins cannot demote or delete BusinessAdmins.
- [x] SR-3 Verify BusinessAdmin can still update safe fields/roles for Employee/Client users and delete Employee/Client users in the active tenant.
- [x] SR-4 Run targeted UserPolicy/UserResource tests, relevant Filament/RBAC tests, full `php artisan test`, Pint dirty test, and `git diff --check`.

### TDD Cycle Evidence

| Task | Test File | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|------|-----------|-------|------------|-----|-------|-------------|----------|
| SR-1 | `tests/Unit/Policies/UserPolicyTest.php`, `tests/Feature/Filament/UserResourceTest.php` | Unit + Integration | ✅ Existing targeted baseline: 7 tests / 57 assertions passing | ✅ New policy/resource tests failed because BusinessAdmin could update/delete BusinessAdmins | ✅ Targeted tests passed: 10 tests / 70 assertions | ✅ Covered self admin, other admin, Employee/Client allowed update/delete | ✅ Consolidated backend rule in `UserPolicy::canManageTenantUser()` |
| SR-2 | `app/Policies/UserPolicy.php` via policy/resource tests | Unit + Integration | ✅ Baseline captured before production changes | ✅ Policy expected denial failed against old same-tenant-only logic | ✅ Targeted and Filament/RBAC tests passed | ✅ Employee and Client positive paths prove rule is not over-restrictive | ✅ Private helper keeps update/delete enforcement identical |
| SR-3 | `tests/Feature/Filament/UserResourceTest.php` | Integration | ✅ Existing UserResource baseline passing | ✅ Safe Employee/Client mutation coverage added before policy change | ✅ Targeted tests passed | ✅ Employee role change to Client plus Client delete exercise separate safe paths | ✅ No extra resource changes needed; policy denial blocks unsafe edit pages |
| SR-4 | Verification commands | Verification | ✅ Safety net and RED/GREEN commands captured | ✅ RED command failed with 2 expected failures | ✅ Full suite passed: 216 tests / 757 assertions | ✅ Relevant Filament/RBAC suite passed: 39 tests / 186 assertions | ✅ `vendor/bin/pint --dirty --test` and `git diff --check` passed |

### Verification

- `php artisan test tests/Unit/Policies/UserPolicyTest.php tests/Feature/Filament/UserResourceTest.php` — PASS, 10 tests / 70 assertions.
- `php artisan test tests/Unit/Policies tests/Feature/Filament` — PASS, 39 tests / 186 assertions.
- `php artisan test` — PASS, 216 tests / 757 assertions.
- `vendor/bin/pint --dirty --test` — PASS, 7 dirty files checked.
- `git diff --check` — PASS.

### Deviations

- None from the Slice 1 remediation intent. The policy now treats BusinessAdmin records as non-manageable targets for BusinessAdmin actors, which prevents both deletion and edit-page demotion paths.

### Risks

- `EmployeeScheduleResource` remains intentionally unimplemented for Slice 2.

### Deviations

- None from the Slice 1 design intent. `UserResource` was also upgraded to the current Filament v5 `Schema`, `recordActions`, and `toolbarActions` APIs because registering the existing resource exposed an incompatible legacy method signature.

### Risks

- `EmployeeScheduleResource` remains intentionally unregistered and unimplemented for Slice 2.

## Slice 2 — Employee Schedule Management

Status: complete
Delivery: stacked-to-main, Slice 2 of 2
Mode: Strict TDD

### Completed Tasks

- [x] 3.1 Create `tests/Unit/Policies/EmployeeSchedulePolicyTest.php` for BusinessAdmin tenant CRUD and Employee own-schedule read-only access.
- [x] 3.2 Create `tests/Feature/Filament/EmployeeScheduleResourceTest.php` for tenant-only listings/options, cross-tenant denial, valid hours, invalid day/time, and Employee write denial.
- [x] 3.3 Run targeted RED tests and confirm failures cover schedule rules.
- [x] 4.1 Create `app/Policies/EmployeeSchedulePolicy.php` with BusinessAdmin same-tenant CRUD and Employee own-schedule `viewAny/view` only.
- [x] 4.2 Update `app/Filament/Resources/EmployeeScheduleResource.php` to scope query by role/tenant, filter employee options, validate `day_of_week` 0-6, and require `end_time` after `start_time`.
- [x] 4.3 Hide or deny schedule create/edit/delete actions for Employees in `app/Filament/Resources/EmployeeScheduleResource.php`.
- [x] 4.4 Register `EmployeeScheduleResource` in `app/Providers/Filament/TenantPanelProvider.php`; run schedule tests and `vendor/bin/pint --dirty`.
- [x] 5.1 Run relevant policies/Filament verification.
- [x] 5.2 Run `composer test` and confirm user-management, schedule-management, and rbac-roles scenarios pass.

### TDD Cycle Evidence

| Task | Test File | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|------|-----------|-------|------------|-----|-------|-------------|----------|
| 3.1 | `tests/Unit/Policies/EmployeeSchedulePolicyTest.php` | Unit | N/A (new) | ✅ Failed on missing `EmployeeSchedulePolicy` | ✅ Schedule targeted tests passed: 9 tests / 52 assertions | ✅ BusinessAdmin same-tenant allow, cross-tenant deny, Employee own read-only, Client deny | ✅ Shared tenant/user/schedule helpers keep policy matrix readable |
| 3.2 | `tests/Feature/Filament/EmployeeScheduleResourceTest.php` | Integration | ✅ Existing `UserResourceTest`: 6 tests / 48 assertions passing | ✅ Failed on unregistered resource, legacy Filament form signature, missing tenant helpers/safeguards | ✅ Schedule targeted tests passed: 9 tests / 52 assertions | ✅ Tenant listings/options, valid create, invalid input, cross-tenant direct access, employee read-only actions | ✅ Cross-tenant direct access assertion records query-level non-resolution instead of requiring a 403 page |
| 3.3 | Targeted command | Verification | ✅ Existing UserResource baseline passing | ✅ `php artisan test tests/Unit/Policies/EmployeeSchedulePolicyTest.php tests/Feature/Filament/EmployeeScheduleResourceTest.php` failed before production changes | ✅ Same command passed: 9 tests / 52 assertions | ✅ Unit and integration tests exercise separate policy/resource paths | ✅ No production refactor before RED evidence |
| 4.1 | `app/Policies/EmployeeSchedulePolicy.php` via policy tests | Unit | ✅ Existing UserResource baseline passing | ✅ Policy tests failed until policy existed | ✅ Targeted tests passed | ✅ CRUD allow/deny, employee own read-only, client deny | ✅ Centralized same-tenant admin logic in `canManageTenantSchedule()` |
| 4.2 | `app/Filament/Resources/EmployeeScheduleResource.php` | Integration | ✅ Existing UserResource baseline passing | ✅ Resource tests failed on unsafe/unavailable schedule behavior | ✅ Targeted tests passed | ✅ Active-tenant employee IDs, cross-tenant employee validation, day range, end-after-start | ✅ Upgraded resource to Filament v5 `Schema`, `recordActions`, and `toolbarActions` APIs |
| 4.3 | `app/Filament/Resources/EmployeeScheduleResource.php`, `Pages/ListSchedules.php` | Integration | ✅ Existing UserResource baseline passing | ✅ Employee action visibility/write denial tests failed before policy/resource restrictions | ✅ Targeted tests passed | ✅ Employee list own schedule, cannot see peer schedule, create/edit/delete hidden/forbidden | ✅ Header create action visibility now follows policy |
| 4.4 | `app/Providers/Filament/TenantPanelProvider.php` + verification | Integration | ✅ Existing UserResource baseline passing | ✅ Registration assertion failed while resource was not in the tenant panel | ✅ Targeted and relevant Filament/RBAC tests passed | ✅ Full suite and composer script confirmed panel integration | ✅ `vendor/bin/pint --dirty` fixed imports, `--test` passed |
| 5.1 | Verification commands | Verification | ✅ Schedule targeted tests passing | ✅ N/A — final verification task after implementation | ✅ Relevant suite passed: 28 tests / 187 assertions | ✅ Policies, user resource, schedule resource, booking resource all covered | ✅ No code changes required |
| 5.2 | `composer test` | Verification | ✅ `php artisan test` passed: 225 tests / 809 assertions | ✅ N/A — full-suite verification task | ✅ `composer test` passed: 225 tests / 809 assertions | ✅ Confirms config-clear + full suite path | ✅ `vendor/bin/pint --dirty --test` and `git diff --check` passed |

### Verification

- `php artisan test tests/Unit/Policies/EmployeeSchedulePolicyTest.php tests/Feature/Filament/EmployeeScheduleResourceTest.php` — RED failed before production changes on missing policy/registration/resource safeguards; GREEN PASS after remediation, 11 tests / 62 assertions.
- `php artisan test tests/Feature/Filament/UserResourceTest.php` — PASS safety net, 6 tests / 48 assertions.
- `php artisan test tests/Unit/Policies tests/Feature/Filament/UserResourceTest.php tests/Feature/Filament/EmployeeScheduleResourceTest.php tests/Feature/Filament/BookingResourceTest.php` — PASS after remediation, 30 tests / 197 assertions.
- `php artisan test` — PASS after remediation, 227 tests / 819 assertions.
- `composer test` — PASS after remediation, 227 tests / 819 assertions.
- `vendor/bin/pint --dirty --test` — initial FAIL on `EmployeeScheduleResource.php`; `vendor/bin/pint --dirty` fixed import/style issue; rerun PASS, 6 dirty files checked.
- `git diff --check` — PASS.

## Slice 2 Surgical Remediation — Employee Schedule Management

Status: complete
Delivery: stacked-to-main remediation inside Slice 2
Mode: Strict TDD

### Completed Tasks

- [x] SR-5 Add RED coverage proving BusinessAdmin can successfully edit same-tenant employee schedules through the Filament edit page.
- [x] SR-6 Add RED coverage proving BusinessAdmin can successfully delete same-tenant employee schedules through the EditSchedule header delete action.
- [x] SR-7 Verify tenant isolation and Employee read-only behavior still pass with targeted schedule, relevant policy/Filament, full suite, Pint dirty test, and `git diff --check`.

### TDD Cycle Evidence

| Task | Test File | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|------|-----------|-------|------------|-----|-------|-------------|----------|
| SR-5 | `tests/Feature/Filament/EmployeeScheduleResourceTest.php` | Integration | ✅ Existing schedule resource baseline: 5 tests / 33 assertions passing | ✅ New edit behavior test failed first on persisted time assertion mismatch, proving the save path ran and normalized time values | ✅ Targeted schedule resource test passed: 7 tests / 43 assertions | ✅ Create, edit, invalid input, cross-tenant denial, and Employee read-only paths now cover distinct schedule write outcomes | ✅ Assertion aligned to database-normalized `HH:MM:SS`; no production change required |
| SR-6 | `tests/Feature/Filament/EmployeeScheduleResourceTest.php` | Integration | ✅ Existing schedule resource baseline: 5 tests / 33 assertions passing | ✅ Header delete action behavior was added before any production change; delete wiring already passed under existing production code | ✅ Targeted schedule resource test passed: 7 tests / 43 assertions | ✅ Delete success is paired with existing cross-tenant delete denial and Employee hidden delete action coverage | ✅ Reused Filament `DeleteAction::class` pattern from `UserResourceTest` |
| SR-7 | Verification commands | Verification | ✅ Targeted schedule baseline and RED/GREEN commands captured | ✅ RED failure isolated to new edit persistence assertion; no production blocker found | ✅ Schedule + policy tests passed: 11 tests / 62 assertions; relevant Filament/RBAC tests passed: 30 tests / 197 assertions; full suite and `composer test` passed: 227 tests / 819 assertions | ✅ Successful BusinessAdmin edit/delete, cross-tenant denial, and Employee read-only behavior verified together | ✅ `vendor/bin/pint --dirty --test` and `git diff --check` passed |

### Verification

- `php artisan test tests/Feature/Filament/EmployeeScheduleResourceTest.php` — safety net PASS before remediation, 5 tests / 33 assertions; RED failed after adding edit/delete coverage on database time normalization; GREEN PASS after assertion correction, 7 tests / 43 assertions.
- `php artisan test tests/Unit/Policies/EmployeeSchedulePolicyTest.php tests/Feature/Filament/EmployeeScheduleResourceTest.php` — PASS, 11 tests / 62 assertions.
- `php artisan test tests/Unit/Policies tests/Feature/Filament/UserResourceTest.php tests/Feature/Filament/EmployeeScheduleResourceTest.php tests/Feature/Filament/BookingResourceTest.php` — PASS, 30 tests / 197 assertions.
- `php artisan test` — PASS, 227 tests / 819 assertions.
- `composer test` — PASS, 227 tests / 819 assertions.
- `vendor/bin/pint --dirty --test` — PASS, 6 dirty files checked.
- `git diff --check` — PASS.

### Deviations

- No production wiring change was required. Existing Filament edit and header delete actions were functional; the blocker was missing behavior coverage.

### Risks

- No known blocking risks for this remediation. Schedule exceptions/vacations remain intentionally out of scope.

### Deviations

- Cross-tenant direct edit access is denied by tenant-scoped resource query non-resolution (`ModelNotFoundException`) rather than rendering a forbidden edit page. This matches the spec requirement that the record is not displayed or mutated.
- `EmployeeScheduleResource` was upgraded from legacy Filament `Form`/table action APIs to Filament v5 `Schema`, `recordActions`, and `toolbarActions`, because registration exposed the stale method signature.

### Risks

- No known blocking risks. Schedule exceptions/vacations, notifications, hold expiry, README/Sail, reminders, tenant email config, and client history remain intentionally out of scope.

## Slice 2 Reliability Remediation — Employee Schedule Reassignment

Status: complete
Delivery: stacked-to-main remediation inside Slice 2
Mode: Strict TDD

### Completed Tasks

- [x] SR-8 Add RED coverage proving BusinessAdmin cannot update an existing same-tenant schedule by reassigning `employee_id` to another tenant's employee.
- [x] SR-9 Keep production unchanged because existing `EmployeeScheduleResource` validation already rejects the reassignment and preserves the original schedule data.
- [x] SR-10 Run targeted schedule, policy + schedule, relevant Filament/RBAC, full suite, Pint dirty test, and `git diff --check` verification.

### TDD Cycle Evidence

| Task | Test File | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|------|-----------|-------|------------|-----|-------|-------------|----------|
| SR-8 | `tests/Feature/Filament/EmployeeScheduleResourceTest.php` | Integration | ✅ Existing schedule resource baseline passed: 7 tests / 43 assertions | ✅ New reassignment test failed first on persisted time assertion mismatch after proving `employee_id` validation fired and the record was not reassigned | ✅ Targeted schedule resource test passed: 8 tests / 50 assertions | ✅ Existing create cross-tenant rejection, direct cross-tenant edit denial, Employee read-only denial, and new update reassignment denial cover distinct tenant-isolation paths | ✅ Assertion aligned to the test helper's stored `HH:MM` values; no production change required |
| SR-9 | `app/Filament/Resources/EmployeeScheduleResource.php` via resource tests | Integration | ✅ Baseline captured before production edits | ✅ RED showed the new behavior contract was exercised through `EditSchedule::save` | ✅ Existing `Rule::in(self::activeTenantEmployeeIds())` rejected the cross-tenant `employee_id`; production unchanged | ✅ Positive same-tenant edit still passes while cross-tenant reassignment fails | ✅ No production refactor needed |
| SR-10 | Verification commands | Verification | ✅ Targeted schedule baseline captured before new test | ✅ RED failure isolated to test assertion normalization, not production behavior | ✅ Schedule file, policy + schedule, relevant Filament/RBAC, and full suite all passed | ✅ New blocker verified alongside previous schedule CRUD/isolation coverage | ✅ `vendor/bin/pint --dirty --test` and `git diff --check` passed |

### Verification

- `php artisan test tests/Feature/Filament/EmployeeScheduleResourceTest.php` — safety net PASS before remediation, 7 tests / 43 assertions; RED failed after adding reassignment coverage on helper time assertion normalization; GREEN PASS, 8 tests / 50 assertions.
- `php artisan test tests/Unit/Policies/EmployeeSchedulePolicyTest.php tests/Feature/Filament/EmployeeScheduleResourceTest.php` — PASS, 12 tests / 69 assertions.
- `php artisan test tests/Unit/Policies tests/Feature/Filament/EmployeeScheduleResourceTest.php tests/Feature/Filament/UserResourceTest.php tests/Feature/Filament/BookingResourceTest.php` — PASS, 31 tests / 204 assertions.
- `php artisan test` — PASS, 228 tests / 826 assertions.
- `vendor/bin/pint --dirty --test` — PASS, 6 dirty files checked.
- `git diff --check` — PASS.

### Deviations

- No production wiring change was required. Existing `EmployeeScheduleResource` active-tenant employee validation already blocks cross-tenant `employee_id` reassignment during update.

### Risks

- No known blocker remains for this reliability gap. Schedule exceptions/vacations remain intentionally out of scope.
