# Design: initial-scaffold

## Technical Approach

Greenfield Laravel 13 + FilamentPHP 5 scaffold with single-database multi-tenancy. Two Filament panels (Super Admin global, Tenant scoped), 4-role enum-based RBAC via `canAccessPanel()`, 6 core Eloquent models, and Pest PHP test scaffolding. The `openspec/config.yaml` references Laravel 11 / Filament 3 — this design intentionally upgrades the stack per the proposal.

## Architecture Decisions

| Option | Tradeoff | Decision |
|--------|----------|----------|
| **Sail (Docker)** vs local PHP | Reproducible env across machines vs. Docker overhead (~200MB RAM). Sail is mandated by PRD §6.1. | **Sail** — non-negotiable per PRD and proposal. |
| **Two Filament panels** vs single panel with UI conditionals | Two providers = two login flows, more config. But native tenant scoping, cleaner separation of concerns. | **Two panels** — `SuperAdminPanelProvider` (`/super-admin`) + `TenantPanelProvider` (`/tenant`). |
| **Single DB, logical `tenant_id`** vs multi-DB or schema-per-tenant | Simpler ops, lower cost. Cross-tenant queries harder to隔离, but acceptable for this scale. | **Single DB + tenant_id FK** — matches proposal §Multi-Tenancy. |
| **Enum + policies** vs Spatie permissions | Spatie adds migration, migration overhead, permission table complexity. 4 fixed roles don't need dynamic permission assignment. | **PHP 8.1 enum + canAccessPanel() + Laravel policies** — lightweight, zero dependencies. |
| **Database queue** vs Redis | Redis faster, but adds infra. Proposal marks queue/worker as out-of-scope for this slice; database driver works for dev/test. | **Database driver** — already in config.yaml; Redis upgrade is trivial later. |
| **Laravel lang files** vs Spatie translations | Spatie adds package + DB overhead. Laravel 13 has first-class `lang/{locale}/` with JSON fallback. | **Laravel native lang files** — zero deps, Filament respects `App::getLocale()` natively. |

## Data Flow

```
HTTP Request
  │
  ├─► Filament Middleware (auth, tenant resolution)
  │     │
  │     ├─► SuperAdminPanelProvider ──► canAccessPanel() checks role === SuperAdmin
  │     │     └─► TenantResource (global, no tenant_id scope)
  │     │
  │     └─► TenantPanelProvider ──► canAccessPanel() checks role ∈ {BusinessAdmin, Employee}
  │           └─► UserResource ──► Eloquent query scoped by tenant_id from resolved tenant
  │                 └─► MariaDB (single DB, logical separation)
  │
  └─► Response (Filament renders Livewire component)
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `app/Models/Tenant.php` | Create | Eloquent model, implements Filament `HasTenants` interface |
| `app/Models/User.php` | Create | Extends Authenticatable, implements `HasTenants`, `FilamentUser` |
| `app/Models/Service.php` | Create | Belongs to Tenant, many-to-many with Employee |
| `app/Models/Employee.php` | Create | Extends User (STI or separate model), has schedules + services |
| `app/Models/EmployeeSchedule.php` | Create | Belongs to Employee (User FK) |
| `app/Models/Booking.php` | Create | Belongs to Tenant + Service, nullable Employee + Client FKs |
| `app/Enums/UserRole.php` | Create | Enum: SuperAdmin, BusinessAdmin, Employee, Client |
| `app/Enums/BookingStatus.php` | Create | Enum: pending, confirmed, cancelled, completed |
| `app/Enums/PaymentStatus.php` | Create | Enum: unpaid, paid, refunded, partial |
| `app/Filament/SuperAdminPanelProvider.php` | Create | Panel at `/super-admin`, global scope, TenantResource |
| `app/Filament/TenantPanelProvider.php` | Create | Panel at `/tenant`, tenant-scoped, UserResource |
| `app/Filament/Resources/TenantResource.php` | Create | CRUD for tenants in Super Admin panel |
| `app/Filament/Resources/TenantResource/Pages/` | Create | ListTenants, CreateTenant, EditTenant |
| `app/Filament/Resources/UserResource.php` | Create | CRUD for users in Tenant panel |
| `app/Filament/Resources/UserResource/Pages/` | Create | ListUsers, CreateUser, EditUser |
| `app/Policies/TenantPolicy.php` | Create | Authorization for tenant CRUD |
| `app/Policies/UserPolicy.php` | Create | Authorization for user CRUD within tenant |
| `database/migrations/xxxx_create_tenants_table.php` | Create | id, name, slug (unique), timestamps |
| `database/migrations/xxxx_create_users_table.php` | Create | id, tenant_id FK, name, email (unique per tenant), role, password, timestamps |
| `database/migrations/xxxx_create_services_table.php` | Create | id, tenant_id FK, name, description, price_cents, duration_minutes, active, timestamps |
| `database/migrations/xxxx_create_employee_schedules_table.php` | Create | id, employee_id FK (users), day_of_week, start_time, end_time, timestamps |
| `database/migrations/xxxx_create_bookings_table.php` | Create | id, tenant_id FK, service_id FK, employee_id FK (nullable), client_id FK (nullable), client_name, client_email, client_phone, date, start_time, end_time, status, payment_status, stripe_payment_intent_id, notification_channel, notes, timestamps |
| `database/migrations/xxxx_create_employee_services_table.php` | Create | Pivot: employee_id FK, service_id FK, timestamps |
| `database/seeders/DatabaseSeeder.php` | Create | Sample tenant + 4 users (one per role) + sample service |
| `lang/en/app.php` | Create | EN base translations for entity names + fields |
| `lang/es/app.php` | Create | ES base translations for entity names + fields |
| `lang/en/validation.php` | Create | EN validation messages (extend defaults) |
| `lang/es/validation.php` | Create | ES validation messages |
| `tests/Pest.php` | Create | Pest PHP baseline config |
| `tests/TestCase.php` | Create | Base test case with Sail DB setup |
| `composer.json` | Modify | Add filament/filament, pestphp/pest dependencies |
| `.env` | Modify | Set DB_*=mariadb, APP_LOCALE=en |

## Interfaces / Contracts

```php
// app/Enums/UserRole.php
enum UserRole: string {
    case SuperAdmin = 'super_admin';
    case BusinessAdmin = 'business_admin';
    case Employee = 'employee';
    case Client = 'client';
}

// Model schemas — key relationships only
// Tenant: hasMany(User, Service, Booking)
// User: belongsTo(Tenant), canAccessPanel() checks role
// Service: belongsTo(Tenant), belongsToMany(Employee via pivot)
// EmployeeSchedule: belongsTo(User as employee)
// Booking: belongsTo(Tenant, Service), belongsTo(User as employee, nullable)
//                            belongsTo(User as client, nullable)
```

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Unit | Enum values, model casts, relationship methods | Pest `it()` + `actingAs()` for role checks |
| Integration | Filament panel access (canAccessPanel), tenant scoping of queries | `RefreshDatabase`, assert query results per tenant |
| E2E | Tenant CRUD lifecycle, User CRUD per tenant | Filament test helpers (form submit, table assertion) |

## Migration / Rollout

Migration order: tenants → users → services → employee_schedules → bookings → employee_services (pivot). Foreign keys require tenants first. Seeders run after all migrations.

## Resolved Questions

- **Employee model**: Employee IS a User with `role=Employee`. No separate `employees` table. `EmployeeSchedule.employee_id` FKs to `users.id`. The `employee_services` pivot uses `users.id`.
- **Tenant slug**: URL-safe slug (e.g., `mi-salon`), used in Filament panel path `/tenant/{slug}` for tenant resolution. Must be unique, lowercased, alphanumeric + hyphens.
