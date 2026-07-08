# Exploration: initial-scaffold

## Current State

Greenfield project — no code exists yet. The PRD defines a multi-tenant booking platform for local businesses (salons, clinics, sports courts) with:
- **Stack**: Laravel 11, FilamentPHP v3, MySQL 8.0, Stripe, Twilio SMS
- **Multi-tenancy**: Single database with logical `tenant_id` separation
- **4 RBAC roles**: Super Admin, Business Admin, Employee, Client (guest checkout)
- **Key modules**: Services catalog, Interactive Calendar, Stripe Payments, Notifications

The project has SDD context initialized (Engram memories, openspec config) but zero source code.

---

## Module Decomposition & Boundaries

| Module | Description | Dependencies | Risk |
|--------|-------------|--------------|------|
| **Core Scaffold** | Laravel + Sail + Filament + DB setup | None | Low |
| **Tenant Management** | CRUD for businesses, admin onboarding | Core Scaffold | Low |
| **RBAC & Auth** | 4-role system, Filament panel guards | Tenant Management | Medium |
| **Services & Duration** | Configurable service catalog | Tenant, Auth | Low |
| **Employees & Schedules** | Staff management, availability windows | Tenant, Auth | Low |
| **Interactive Calendar** | Public booking UI, time-slot calculation | Services, Employees | High |
| **Double-Booking Prevention** | Concurrent slot locking during payment | Calendar, Payments | **Critical** |
| **Stripe Payments** | PaymentIntents, webhooks, refunds | Tenant (Stripe keys) | **High** |
| **Notifications** | Async SMS/email via queues | Bookings, Payments | Medium |
| **Guest Checkout** | No-auth booking with contact capture | Calendar, Notifications | Medium |

---

## Data Model — Core Entities & Relationships

```
┌─────────────────────────────────────────────────────────────────┐
│                         TENANT (root)                           │
│  id, name, slug, stripe_account_id, payment_policy,            │
│  refund_window_hours, notification_preference                   │
└──────────┬──────────────────────────────────────────────────────┘
           │
           ├── has many ──► USERS (admin, employee, client)
           │                 id, tenant_id, name, email, phone,
           │                 role (enum), password?, notification_channel
           │
           ├── has many ──► SERVICES
           │                 id, tenant_id, name, description,
           │                 price_cents, duration_minutes, active
           │
           ├── has many ──► EMPLOYEES (→ User where role=employee)
           │                 [pivot: employee_services — which services each offers]
           │
           ├── has many ──► EMPLOYEE_SCHEDULES
           │                 id, employee_id, day_of_week, start_time, end_time
           │
           └── has many ──► BOOKINGS
                             id, tenant_id, service_id, employee_id,
                             client_id (nullable — guest), client_name,
                             client_email, client_phone,
                             date, start_time, end_time,
                             status (pending/confirmed/cancelled/completed),
                             payment_status (unpaid/paid/refunded),
                             stripe_payment_intent_id,
                             notification_channel, notes
```

**Key relationships:**
- `Tenant` 1:N `User`, `Service`, `Booking`
- `Employee` (User) N:M `Service` (via pivot)
- `Employee` 1:N `EmployeeSchedule`
- `Booking` N:1 `Service`, `Employee`, optionally `Client`
- `Booking` 1:1 `Payment` (implicit via `stripe_payment_intent_id`)

**Critical constraint**: All queries MUST include `tenant_id` scope. FilamentPHP handles this natively when using `->tenant(Tenant::class)` in the panel provider.

---

## Integration Points

### Stripe (Payments + Webhooks + Refunds)
- **PaymentIntents API**: Create intent with `amount`, `currency`, `metadata` (booking_id)
- **Webhook handler**: `payment_intent.succeeded` → confirm booking; `payment_intent.payment_failed` → mark failed
- **Refund API**: `Stripe\Refund::create()` with `payment_intent` param
- **Signature verification**: `Stripe\Webhook::constructEvent()` with raw body + secret
- **Per-tenant keys**: Each tenant stores their own `stripe_account_id` — requires Stripe Connect or shared-key model
- **PHP SDK**: Modern `StripeClient` service-based approach (v7.33+)

### Twilio (SMS)
- Laravel Notification channel via `twilio/sdk`
- Async via Laravel Queue (database driver)
- SMS templates: confirmation, reminder (24h), cancellation, reschedule

### Email (SMTP/Mailgun)
- Laravel Mail / Mailables
- Same async queue pattern as SMS
- Client chooses channel preference at checkout

---

## Complexity Assessment

| Complexity | Module | Why |
|------------|--------|-----|
| **Critical** | Double-Booking Prevention | Requires DB-level locking or atomic transactions during payment processing. Race conditions are the #1 bug source in booking systems. |
| **High** | Stripe Integration | Webhook reliability, signature verification, idempotency, refund logic, per-tenant key management. |
| **High** | Interactive Calendar | Dynamic time-slot calculation based on service duration + employee schedules + existing bookings. Performance-sensitive. |
| **Medium** | Notifications | Async queue setup, multiple channels, template management, scheduling (24h reminder). |
| **Medium** | Guest Checkout | No-auth flow with contact capture, merge with registered users. |
| **Medium** | RBAC | 4 roles with different panel access levels. FilamentPHP has built-in support but needs careful config. |
| **Low** | Core Scaffold | Standard Laravel + Sail + Filament setup. |
| **Low** | Tenant Management | CRUD operations. |
| **Low** | Services & Duration | Simple catalog with configurable fields. |
| **Low** | Employee Schedules | CRUD with time validation. |

---

## First Slice Recommendation

### What should we build FIRST?

**Recommendation: Option A — Full Scaffold (Core Foundation)**

The first slice should get a **working Laravel app with Sail, Filament, multi-tenancy, and the complete data model running**. This is the foundation everything else depends on.

### Approach Comparison

| Approach | Description | Pros | Cons | Effort |
|----------|-------------|------|------|--------|
| **A. Full Scaffold** | Laravel + Sail + Filament + all migrations + basic Tenant CRUD + RBAC panel setup | Everything runs, all models/migrations ready, foundation for all future work | Takes longer upfront, no "feature" to demo | Medium (1-2 days) |
| **B. Data Model First** | Design all migrations before any code, no running app | Clean schema design, catches relationship issues early | No running app to validate against, hard to test without scaffold | Low (0.5 day) but blocks validation |
| **C. Vertical Slice** | One complete feature end-to-end (e.g., Tenant → Service → Booking) | Fastest to a "working feature", validates full stack early | Skips scaffold setup, may need rework, complex first task | High (2-3 days) |

### Why Option A Wins

1. **Dependency chain**: Everything depends on the scaffold. Without it, you can't test migrations, validate Filament panels, or run any code.
2. **FilamentPHP multi-tenancy**: Must be configured correctly from day one — it affects every panel and resource.
3. **Data model as living code**: Migrations + Eloquent models in place = instant validation. You can `tinker` through relationships immediately.
4. **Low risk**: Standard Laravel setup, well-documented, community-proven patterns.
5. **Unblocks parallel work**: Once scaffold is up, Services/Calendar/Notifications can be developed independently.

### Recommended First Slice Scope

```
initial-scaffold (First Slice)
├── Laravel 11 project via Sail (Docker)
├── MySQL 8.0 configured
├── FilamentPHP v3 installed with panel providers
├── Multi-tenancy configured (Tenant model, panel tenancy)
├── RBAC: 4 roles defined, Filament panel guards
├── Migrations: All core entities (Tenant, User, Service, Employee, EmployeeSchedule, Booking)
├── Eloquent models with relationships
├── Basic Tenant CRUD via Filament (Super Admin panel)
├── Basic User CRUD per tenant (Business Admin panel)
└── Seeders: sample tenant + users for testing
```

**NOT included in first slice** (deferred to subsequent changes):
- Stripe integration
- Calendar UI / time-slot calculation
- Double-booking prevention
- Notifications (SMS/Email)
- Guest checkout
- Public booking frontend

---

## Risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| **Stripe per-tenant key management** | High | Decide early: Stripe Connect (platform-managed) vs shared keys (tenant-provided). This affects Tenant model schema. |
| **Double-booking race conditions** | Critical | Use database-level row locking (`SELECT ... FOR UPDATE`) or atomic upserts. Must be designed into the Booking model from the start. |
| **FilamentPHP v3 multi-tenancy complexity** | Medium | Follow official docs strictly. Use `HasTenants` interface on User model. Test panel access per role early. |
| **Queue driver choice** | Low | Database driver is simplest for self-hosting. Redis adds complexity but better performance. Recommend database for MVP. |
| **Guest vs registered user merge** | Medium | Design the `client_id` as nullable FK on Booking. Guests get a transient User record or booking stores contact info directly. |

---

## Ready for Proposal

**Yes** — the exploration is complete. The orchestrator should:

1. Proceed to `sdd-propose` with the recommended approach (Option A: Full Scaffold)
2. Include the data model mapping and module decomposition in the proposal
3. Note the critical risk around double-booking prevention and Stripe key management for early design decisions
4. The first slice should be scoped as: Laravel scaffold + Sail + Filament + multi-tenancy + RBAC + all migrations + basic Tenant/User CRUD
