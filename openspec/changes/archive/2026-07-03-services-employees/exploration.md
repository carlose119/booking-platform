# Exploration: Services & Employees Module

## Current State

The data layer is complete. Models (`Service`, `User`, `EmployeeSchedule`), migrations (`services`, `employee_schedules`, `employee_services` pivot), and a seeder that wires them together are all in place. What's missing is the **management UI** — no Filament resources exist for Service CRUD, EmployeeSchedule CRUD, or the Employee-Service association.

### What exists today
| Layer | Status | Files |
|-------|--------|-------|
| Models + Relations | ✅ Complete | `Service`, `User`, `EmployeeSchedule` — all with proper relationships |
| Migrations | ✅ Complete | `services`, `employee_schedules`, `employee_services` pivot |
| Seeder | ✅ Complete | Creates demo service, employee, schedules, and pivot association |
| Enums | ✅ Complete | `UserRole` (Employee, BusinessAdmin, etc.) |
| Filament Resources | ⚠️ Partial | `UserResource` (Tenant panel) — basic user CRUD only |
| Validation | ❌ Missing | No FormRequest or inline validation for services/schedules |
| Specs | ❌ Missing | Only `data-model` mentions Service/Schedule; no management specs |

## Affected Areas

- `app/Filament/Resources/UserResource.php` — needs employee-specific enhancements or a new EmployeeResource
- `app/Models/Service.php` — may need FormRequest integration
- `app/Models/EmployeeSchedule.php` — may need FormRequest integration
- `app/Providers/Filament/TenantPanelProvider.php` — must register new resources
- `openspec/specs/` — new domain spec(s) for service management and employee schedules

## Approaches

### 1. Separate Resources (ServiceResource + EmployeeScheduleResource + enhanced UserResource)

Create dedicated `ServiceResource` and `EmployeeScheduleResource`. Enhance `UserResource` with a `Repeater` for employee-service association when `role = Employee`.

| Pros | Cons |
|------|------|
| Single Responsibility — each resource owns one domain | More files to create (3 resources × pages) |
| Easy to test in isolation | Navigation might feel scattered for admins |
| Follows Filament conventions (one resource per model) | Schedule management requires context (which employee?) |
| Low coupling between modules | — |

**Effort**: Medium (~400-500 LOC across resources + pages)

### 2. Consolidated EmployeeResource (Sub-navigation approach)

Create a single `EmployeeResource` that manages user data, services, and schedules via tabs/sub-navigation. ServiceResource remains separate for the service catalog.

| Pros | Cons |
|------|------|
| Better UX — all employee management in one place | More complex resource with nested relations |
| Schedules and services are contextually grouped | Larger single resource (harder to review) |
| Natural for admins: "I'm managing this employee" | Sub-navigation in Filament adds boilerplate |
| — | Harder to reuse schedule logic independently |

**Effort**: Medium-High (~500-600 LOC)

### 3. Hybrid — ServiceResource + EmployeeRelationManager

Create `ServiceResource` for catalog management. Use Filament's `RelationManager` on `UserResource` for both schedules and services when the user is an Employee.

| Pros | Cons |
|------|------|
| Reuses existing UserResource — minimal new pages | Schedule management nested under user edit (extra click) |
| RelationManagers are idiomatic Filament | Less discoverable for "manage all schedules" use case |
| Clean separation: Service catalog vs employee config | Employee list is the entry point, not schedule-first |
| Low effort — RelationManagers are ~100 LOC each | — |

**Effort**: Low-Medium (~300-350 LOC)

## Recommendation

**Approach 1: Separate Resources** — recommended.

Rationale:
- The PRD treats "Services & Duration" and "Employees & Schedules" as separate concerns.
- A dedicated `ServiceResource` is non-negotiable — the admin needs a catalog view.
- `EmployeeScheduleResource` with employee filter/scope gives the most flexible management (admin can see all schedules, filter by employee).
- Enhancing `UserResource` with a `Repeater` for employee-service association when role=Employee keeps the relationship close to where the user is created.
- This stays within the 400-line PR review budget per resource.

**Scope for this change**:
1. `ServiceResource` (form + table + CRUD pages) in Tenant panel
2. `EmployeeScheduleResource` (form + table + CRUD pages) in Tenant panel, scoped to tenant
3. `UserResource` enhancement — add `Repeater` for services when role=Employee
4. Validation rules inline in forms (price > 0, duration > 0, end_time > start_time, day_of_week 0-6)
5. Tenant scoping on all new resources

**Deferred to future changes**:
- Calendar module (uses Service duration + EmployeeSchedule availability)
- Ad-hoc schedule overrides (blocking free hours)
- Bulk schedule management (copy week pattern)

## Risks

- **Schedule uniqueness**: EmployeeSchedule has no unique constraint on `(employee_id, day_of_week)` — two records for the same day are technically allowed. The UI should prevent duplicates, but the migration lacks a unique index. A follow-up migration may be needed.
- **Service deletion cascade**: Deleting a service cascades to `employee_services` pivot (good) but also to any future Booking FK. Must confirm booking migration has appropriate restrict behavior.
- **Price display**: `price_cents` is stored as integer. Forms must accept dollars and convert to cents. User error risk if conversion is inconsistent.

## Ready for Proposal

**Yes** — scope is clear, models/migrations exist, patterns are established via UserResource. Ready to proceed to `sdd-propose`.
