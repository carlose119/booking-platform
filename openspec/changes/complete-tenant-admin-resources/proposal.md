# Proposal: Complete Tenant Admin Resources

## Intent

Complete PRD/OpenSpec tenant-admin coverage by safely exposing tenant user management and recurring employee schedule management in the Tenant Panel without cross-tenant leakage or privilege escalation.

## Scope

### In Scope
- Register `UserResource` and `EmployeeScheduleResource` in the Tenant Panel.
- Let BusinessAdmin manage Employee and Client users in their tenant only.
- Define safe role rules: BusinessAdmin may create/update only Employee/Client accounts, may not assign SuperAdmin/BusinessAdmin, may not change their own role, and may not affect another tenant.
- Let Employees view their own schedule read-only; no schedule edits in this slice.
- Let BusinessAdmin manage simple recurring day/hour schedules by employee.
- Add Filament authorization, tenant-scoping, validation, and tests.

### Out of Scope
- Vacation/exception schedule rules, advanced recurrence, availability overrides.
- Employee schedule editing, client admin portal, notifications, or booking-history UI.
- Broad RBAC redesign beyond safe tenant admin resource access.

## Capabilities

### New Capabilities
- None.

### Modified Capabilities
- `user-management`: expose tenant-scoped user CRUD with safe role assignment and tenant-scoped employee services.
- `schedule-management`: expose tenant-scoped schedule CRUD for BusinessAdmin plus Employee own-schedule read-only access.
- `rbac-roles`: tighten Tenant Panel resource authorization for BusinessAdmin vs Employee.

## Approach

Use two implementation slices under one parent change: (1) tenant user management and shared authorization baseline, (2) employee schedule management. Keep policies/resource queries/form options tenant-scoped and add focused Filament feature tests per slice to protect the 400-line review budget.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `app/Providers/Filament/TenantPanelProvider.php` | Modified | Register resources after policies/scopes are safe. |
| `app/Filament/Resources/UserResource.php` | Modified | Auto-fill tenant, restrict roles, scope services. |
| `app/Policies/UserPolicy.php` | Modified | Admin-only management; employee self-view only. |
| `app/Filament/Resources/EmployeeScheduleResource.php` | Modified | Scope employee selection, validate day/time, employee read-only view. |
| `app/Policies/EmployeeSchedulePolicy.php` | New | Authorize BusinessAdmin CRUD and Employee own read-only schedule. |
| `tests/Feature/Filament/*` | New/Modified | Cover tenant isolation, roles, validation, and access denial. |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Cross-tenant data exposure | Med | Policy + query + form-option tests. |
| Privilege escalation | Med | Explicit role whitelist and self-role guard. |
| Oversized review | Med | Two reviewable implementation slices. |

## Rollback Plan

Re-comment resource registration in `TenantPanelProvider`, revert resource/policy changes, and remove new Filament tests. Existing booking and service resources remain unaffected.

## Dependencies

- Existing `user-management`, `schedule-management`, and `rbac-roles` specs.
- Filament v5 resource/policy behavior already present in the app.

## Success Criteria

- [ ] BusinessAdmin can manage only Employee/Client users inside the active tenant.
- [ ] Employee can view only their own schedule and cannot edit schedules or users.
- [ ] Schedule CRUD is tenant-scoped and validates day/time rules.
- [ ] Tests prove cross-tenant denial and safe role behavior.
- [ ] Work is split into two review slices if implementation exceeds the 400-line budget.
