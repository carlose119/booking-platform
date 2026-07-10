## Exploration: complete-tenant-admin-resources

### Current State
The tenant panel provider still comments out `UserResource` and `EmployeeScheduleResource`, so neither resource is registered in the tenant Filament panel.

`UserResource` already exists, but it does not auto-fill `tenant_id`, and its employee services selector is not tenant-scoped. `UserPolicy` also allows `Employee` users to `viewAny`, which is broader than the user-management spec.

`EmployeeScheduleResource` also exists, but it has no policy, its employee selector is not tenant-scoped, and `day_of_week` is only constrained by select options, not explicit validation. `User::canAccessPanel()` already handles Filament v5 panel access correctly; no `Panel::canAccessPanel()` change is needed.

### Affected Areas
- `app/Providers/Filament/TenantPanelProvider.php` — register both tenant admin resources.
- `app/Filament/Resources/UserResource.php` — tenant-scoped CRUD, tenant assignment, scoped employee-service relation.
- `app/Policies/UserPolicy.php` — tighten authorization to match admin-only user management.
- `app/Filament/Resources/EmployeeScheduleResource.php` — tenant-scoped employee selection and explicit validation.
- `app/Policies/EmployeeSchedulePolicy.php` — likely needed for admin-only schedule management.
- `tests/Feature/Filament/*` — add resource coverage; no existing user/schedule Filament tests were found.

### Approaches
1. **Single combined change** — enable both resources, add authorization, and write all tests in one slice.
   - Pros: one coherent change, shared panel/provider work done once.
   - Cons: likely too much for the 400-line review budget; mixed concerns.
   - Effort: Medium

2. **Split into two slices** — ship user-management and employee-schedule management as separate implementation slices under the same parent change.
   - Pros: fits the review budget, isolates authorization/test risk, easier rollback.
   - Cons: requires a small coordination step between slices.
   - Effort: Medium

### Recommendation
Split it into two implementation slices, but keep one parent SDD change. Do the shared tenant-panel registration/authorization baseline first, then user management, then schedule management.

### Risks
- `UserPolicy` is currently too permissive for `viewAny`; once the resource is registered, employees may see the user list unless the policy is tightened.
- `EmployeeScheduleResource` currently lacks a policy and tenant-scoped employee filtering, so schedule admin access could leak across tenants if shipped as-is.
- Both resource slices need fresh Filament tests; there are no existing coverage files for either resource.

### Ready for Proposal
Yes — tell the user this is a valid follow-up change, but it should be split into two reviewable slices to stay under the 400-line budget.
