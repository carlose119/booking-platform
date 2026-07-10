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

- [ ] Slice 2: Employee Schedule Management tasks 3.1-4.4.
- [ ] Final verification tasks 5.1-5.2 after Slice 2.

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
