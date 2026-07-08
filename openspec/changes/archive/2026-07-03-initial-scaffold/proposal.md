# Proposal: initial-scaffold

## Intent

Greenfield project with zero source code. This first slice establishes the working foundation everything else depends on: Laravel 13 + Sail + MariaDB, FilamentPHP 5 multi-tenancy, RBAC (4 roles), complete data model, and basic Tenant/User CRUD. Without this, no future module (Calendar, Stripe, Notifications) can be built or tested.

## Scope

### In Scope
- Laravel 13 project via Sail (Docker dev env, MariaDB)
- FilamentPHP 5 installed with two panel providers (Super Admin global + Tenant panel)
- Multi-tenancy: single DB, logical `tenant_id` separation via Filament's native support
- RBAC: 4 roles (Super Admin, Business Admin, Employee, Client) with Filament panel guards
- Migrations: Tenant, User, Service, Employee, EmployeeSchedule, Booking (all core entities)
- Eloquent models with full relationships
- Basic Tenant CRUD (Super Admin panel)
- Basic User CRUD per tenant (Business Admin panel)
- Seeders: sample tenant + users for testing
- i18n setup: EN default + ES
- Pest PHP test scaffolding (baseline config, no feature tests yet)

### Out of Scope
- Stripe integration (payments, webhooks, refunds)
- Calendar UI / time-slot calculation
- Double-booking prevention
- Notifications (SMS/Email)
- Guest checkout / public booking frontend
- Services, Employees, Bookings CRUD beyond models
- Queue/worker setup
- README self-hosting guide

## Capabilities

### New Capabilities
- `multi-tenant-scaffold`: Laravel 13 + Sail + MariaDB + FilamentPHP 5 setup with multi-tenancy config
- `rbac-roles`: 4-role RBAC system with Filament panel guards and authorization
- `tenant-management`: Tenant CRUD operations in Super Admin panel
- `user-management`: User CRUD per tenant in Business Admin panel
- `data-model`: Core migrations and Eloquent models (Tenant, User, Service, Employee, EmployeeSchedule, Booking)
- `i18n-setup`: EN/ES localization configuration and base translations

### Modified Capabilities
None — greenfield project, no existing specs.

## Approach

1. Initialize Laravel 13 via Sail with MariaDB, configure `.env`
2. Install FilamentPHP 5, create Super Admin and Tenant panel providers
3. Configure multi-tenancy: `Tenant` model implements `HasTenants`, User model implements `HasTenants` for Filament panel binding
4. Create RBAC enum (SuperAdmin, BusinessAdmin, Employee, Client) and Filament policies
5. Write migrations for all 6 core entities with proper foreign keys and tenant_id scopes
6. Build Eloquent models with relationships (hasMany, belongsToMany pivot for employee_services)
7. Scaffold Filament resources: TenantResource (Super Admin), UserResource (Tenant panel)
8. Create DatabaseSeeder with sample tenant + users per role
9. Set up i18n: Laravel lang files for EN/ES, Filament locale config
10. Initialize Pest PHP with baseline config

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `app/Models/` | New | Tenant, User, Service, Employee, EmployeeSchedule, Booking models |
| `database/migrations/` | New | All core entity migrations |
| `app/Filament/` | New | Panel providers, TenantResource, UserResource |
| `app/Enums/` | New | UserRole enum |
| `database/seeders/` | New | DatabaseSeeder with test data |
| `lang/` | New | EN + ES translation files |
| `tests/` | New | Pest PHP configuration |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| FilamentPHP 5 multi-tenancy API differs from v3 docs | Med | Pin to Filament 5.1.1, verify panel config against official v5 docs before coding |
| EmployeeSchedule + Booking FK design affects future calendar module | Low | Use nullable employee_id on Booking initially; calendar module refines later |
| MariaDB vs MySQL behavioral differences in transactions | Low | MariaDB is MySQL-compatible for basic CRUD; defer edge-case testing to Stripe phase |

## Rollback Plan

Delete `openspec/changes/initial-scaffold/` artifacts. Drop `booking_platform` database, re-run `composer remove` for Filament packages if needed. Git revert any committed changes.

## Dependencies

- Docker Desktop (for Sail)
- PHP 8.3+ runtime
- Composer 2.x

## Success Criteria

- [ ] `./vendor/bin/sail up` starts app with MariaDB accessible
- [ ] Super Admin panel login works, Tenant CRUD functional
- [ ] Tenant panel login works, User CRUD scoped to tenant
- [ ] All 6 migrations run without errors
- [ ] Eloquent relationships resolve correctly (tinker-validated)
- [ ] Pest PHP test suite runs (baseline config, no failures)
- [ ] i18n switchable between EN and ES
