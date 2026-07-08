# Tasks: initial-scaffold

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 1200–1400 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 → PR 2 → PR 3 (stacked) |
| Delivery strategy | ask-on-risk |
| Chain strategy | stacked-to-main |

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Foundation + Filament install + enums + config | PR 1 | base: main. ~50 lines. Sail config, composer deps, env, enums. |
| 2 | Migrations + models + relationships | PR 2 | base: PR 1 branch. ~600 lines. 6 migrations, 5 models, 2 policies. |
| 3 | Filament panels + resources + pages + seeder + i18n + tests | PR 3 | base: PR 2 branch. ~650 lines. Panel providers, CRUD resources, seeders, lang, Pest. |

## Phase 1: Foundation — Laravel + Sail + Filament + Enums

- [x] 1.1 Initialize Laravel 13 via `composer create-project laravel/laravel` and configure `composer.json` for Sail (`laravel/sail`)
- [x] 1.2 Update `.env`: set `DB_CONNECTION=mysql`, `DB_HOST=mariadb`, `DB_DATABASE=booking_platform`, `APP_LOCALE=en`, `APP_FALLBACK_LOCALE=en`
- [x] 1.3 Install FilamentPHP 5 via `composer require filament/filament:"^5.0"` and run `php artisan filament:install`
- [x] 1.4 Create `app/Enums/UserRole.php` — enum with SuperAdmin, BusinessAdmin, Employee, Client (string backed)
- [x] 1.5 Create `app/Enums/BookingStatus.php` — enum: pending, confirmed, cancelled, completed
- [x] 1.6 Create `app/Enums/PaymentStatus.php` — enum: unpaid, paid, refunded, partial

## Phase 2: Data Model — Migrations + Models

- [x] 2.1 Create `database/migrations/2026_07_03_171200_create_tenants_table.php` — id, name, slug (unique index), timestamps
- [x] 2.2 Create `database/migrations/0001_01_01_000000_create_users_table.php` — id, tenant_id FK, name, email (unique per tenant), role, password, notification_channel, timestamps
- [x] 2.3 Create `database/migrations/2026_07_03_171300_create_services_table.php` — id, tenant_id FK, name, description, price_cents, duration_minutes, active, timestamps
- [x] 2.4 Create `database/migrations/2026_07_03_171400_create_employee_schedules_table.php` — id, employee_id FK (users), day_of_week, start_time, end_time, timestamps
- [x] 2.5 Create `database/migrations/2026_07_03_171500_create_bookings_table.php` — id, tenant_id FK, service_id FK, employee_id FK (nullable), client_id FK (nullable), client_name, client_email, client_phone, date, start_time, end_time, status, payment_status, stripe_payment_intent_id, notification_channel, notes, timestamps
- [x] 2.6 Create `database/migrations/2026_07_03_171600_create_employee_services_table.php` — pivot: employee_id FK (users), service_id FK (services), timestamps
- [x] 2.7 Create `app/Models/Tenant.php` — hasMany(User, Service, Booking), implements Filament HasTenants
- [x] 2.8 Modify `app/Models/User.php` — extends Authenticatable, belongsTo(Tenant), implements HasTenants + FilamentUser, cast role to UserRole
- [x] 2.9 Create `app/Models/Service.php` — belongsTo(Tenant), belongsToMany(Employee via pivot)
- [x] 2.10 Create `app/Models/EmployeeSchedule.php` — belongsTo(User as employee)
- [x] 2.11 Create `app/Models/Booking.php` — belongsTo(Tenant, Service), belongsTo(User as employee nullable, client nullable)

## Phase 3: Filament — Panels + Resources + Policies

- [x] 3.1 Create `app/Filament/SuperAdminPanelProvider.php` — panel id=super-admin, path=/super-admin, canAccessPanel checks role===SuperAdmin
- [x] 3.2 Create `app/Filament/TenantPanelProvider.php` — panel id=tenant, path=/tenant, canAccessPanel checks role∈{BusinessAdmin, Employee}, tenant-scoped
- [x] 3.3 Create `app/Filament/Resources/TenantResource.php` — form: name, slug fields; table: name, slug, created_at columns
- [x] 3.4 Create `app/Filament/Resources/TenantResource/Pages/ListTenants.php`, `CreateTenant.php`, `EditTenant.php`
- [x] 3.5 Create `app/Filament/Resources/UserResource.php` — form: name, email, role select, password; table: name, email, role, created_at; tenant-scoped query
- [x] 3.6 Create `app/Filament/Resources/UserResource/Pages/ListUsers.php`, `CreateUser.php`, `EditUser.php`
- [x] 3.7 Create `app/Policies/TenantPolicy.php` — only SuperAdmin can manage
- [x] 3.8 Create `app/Policies/UserPolicy.php` — BusinessAdmin can manage within tenant, Employee read-only

## Phase 4: Seeders + i18n + Testing

- [x] 4.1 Modify `database/seeders/DatabaseSeeder.php` — create sample tenant (slug: demo-salon), 1 SuperAdmin, 1 BusinessAdmin, 1 Employee, 1 Client, 1 sample service
- [x] 4.2 Create `lang/en/app.php` — entity names + field labels (Tenant, User, Service, Employee, Booking)
- [x] 4.3 Create `lang/es/app.php` — Spanish translations for all EN keys
- [x] 4.4 Create `lang/en/validation.php` — extend Laravel defaults
- [x] 4.5 Create `lang/es/validation.php` — Spanish validation messages
- [x] 4.6 Create `tests/Pest.php` — Pest PHP baseline config with `uses(TestCase::class)->in('Feature')`
- [x] 4.7 Create `tests/TestCase.php` — base test case extending Orchestra with Sail DB config, RefreshDatabase trait
