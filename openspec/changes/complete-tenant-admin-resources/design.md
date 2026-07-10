# Design: Complete Tenant Admin Resources

## Technical Approach

Enable the existing tenant `UserResource` and `EmployeeScheduleResource` only after adding layered authorization: Filament panel access remains in `User::canAccessPanel()`, resource access is enforced by policies, and every resource query/form option is scoped to the active tenant. This maps to the user-management, schedule-management, and rbac-roles specs by preventing cross-tenant reads, role escalation, and employee write access.

## Architecture Decisions

| Decision | Choice | Alternatives considered | Rationale |
|---|---|---|---|
| Panel access | Keep `User::canAccessPanel()` and register resources in `TenantPanelProvider`; do not add `Panel::canAccessPanel()`. | Add panel-level closure or custom middleware. | Filament v5 documents panel access through `FilamentUser::canAccessPanel()`, while resource security belongs in policies. |
| User management | `UserPolicy::viewAny/create/update/delete` becomes BusinessAdmin-only; `UserResource` query remains tenant-scoped. | Let Employees view themselves through `UserResource`. | The spec says tenant user management is admin-only; employee self-profile is outside this resource. |
| Tenant assignment | Force `tenant_id` from `Filament::getTenant()`/authenticated user during create and never expose it in the form. | Trust hidden form input. | Server-side assignment is the only safe protection against tampering. |
| Role boundaries | Restrict form options and validation to Employee/Client; reject SuperAdmin/BusinessAdmin and BusinessAdmin self-role mutation. | Hide elevated roles only in UI. | UI hiding is insufficient; direct Livewire payloads must fail safely. |
| Schedule access | Add `EmployeeSchedulePolicy`: BusinessAdmin CRUD for active-tenant employees; Employee `viewAny/view` own schedules only; no create/update/delete. | Make schedule resource admin-only. | Specs require employee own schedule read-only access. |
| Review slicing | Implement as two slices: users + shared registration baseline, then schedules. | Single PR. | Two resources plus tests likely risks the 400-line review budget. |

## Data Flow

    TenantPanelProvider registers resources
        └─ Filament page checks policy viewAny()
             └─ Resource getEloquentQuery() scopes records
                  └─ Form relationship options scoped to same tenant
                       └─ Page mutate/validation enforces persisted tenant + role rules

For Employees on schedules, `viewAny()` allows the schedule list, `getEloquentQuery()` narrows to `employee_id = auth()->id()`, and policy denies all write actions.

## File Changes

| File | Action | Description |
|---|---|---|
| `app/Providers/Filament/TenantPanelProvider.php` | Modify | Import and register `UserResource` and `EmployeeScheduleResource`. |
| `app/Filament/Resources/UserResource.php` | Modify | Limit role options/filter to Employee/Client for tenant admin, tenant-scope query, scope service options, hide/disable unsafe actions if needed. |
| `app/Filament/Resources/UserResource/Pages/CreateUser.php` | Modify | Set `tenant_id` from active tenant before create. |
| `app/Filament/Resources/UserResource/Pages/EditUser.php` | Modify | Validate role transitions and prevent self-role escalation/demotion. |
| `app/Policies/UserPolicy.php` | Modify | Make tenant user resource BusinessAdmin-only and tenant-bound. |
| `app/Filament/Resources/EmployeeScheduleResource.php` | Modify | Tenant/employee-aware query, tenant-scoped employee selector, explicit day/time validation, employee read-only action visibility. |
| `app/Policies/EmployeeSchedulePolicy.php` | Create | Authorize BusinessAdmin tenant CRUD and Employee own-schedule view-only behavior. |
| `tests/Feature/Filament/UserResourceTest.php` | Create | Filament access, tenant isolation, role boundary, service option tests. |
| `tests/Feature/Filament/EmployeeScheduleResourceTest.php` | Create | Schedule access, validation, tenant isolation, employee read-only tests. |
| `tests/Unit/Policies/*PolicyTest.php` | Create/Modify | Direct policy matrix coverage for roles and tenant boundaries. |

## Interfaces / Contracts

```php
// EmployeeSchedulePolicy contract
viewAny(User $user): bool;        // BusinessAdmin or Employee
view(User $user, EmployeeSchedule $schedule): bool;
create/update/delete(...): bool;  // BusinessAdmin only, same tenant via employee
```

Resource invariants:
- `UserResource` MUST persist `tenant_id` from active tenant only.
- Assignable tenant roles MUST be exactly `employee` and `client`.
- `EmployeeScheduleResource` employee options MUST be active-tenant employees only.
- Schedule `day_of_week` MUST be integer 0-6 and `end_time` MUST be after `start_time`.

## Testing Strategy

| Layer | What to Test | Approach |
|---|---|---|
| Unit | `UserPolicy` and `EmployeeSchedulePolicy` role/tenant matrix. | PHPUnit/Pest policy tests with T1/T2 users and schedules. |
| Integration | Filament resources enforce queries, actions, form validation, and relationship options. | Livewire tests following `BookingResourceTest` patterns with `Filament::setCurrentPanel()` and `Filament::setTenant()`. |
| E2E | Not required. | Existing Filament feature tests cover the browser-level contract sufficiently for this slice. |

## Migration / Rollout

No migration required. Roll out slice 1 by registering user management after tests pass, then slice 2 for schedules. Rollback is re-commenting resource registration and reverting resource/policy/test changes.

## Open Questions

- [ ] None.
