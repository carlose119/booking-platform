# Tenant Management Specification

## Purpose

Provide CRUD operations for tenant (business) records through the Super Admin panel, enabling onboarding of new businesses onto the platform.

## Requirements

### Requirement: Tenant CRUD in Super Admin Panel

The system SHALL provide a Filament Super Admin resource for tenant CRUD. The resource MUST include payment policy, direct Stripe credentials, payment account mode, connected account ID, Connect onboarding/status fields, notification fields, and validated default currency. Direct credentials are required only when direct mode requires Stripe payment; Connect fields are required only for Connect mode readiness.

(Previously: Tenant CRUD with only name and slug fields)

#### Scenario: Create a new tenant

- GIVEN a SuperAdmin submits valid tenant data without currency or Connect data
- WHEN the tenant is created
- THEN default_currency=`usd` and payment account mode defaults to direct

#### Scenario: Update tenant payment account mode

- GIVEN a SuperAdmin edits tenant T1
- WHEN they select direct API keys or Stripe Connect mode
- THEN the selected mode is persisted for T1 only
- AND unrelated tenant payment settings are not modified

#### Scenario: Reject unsupported currency

- GIVEN a SuperAdmin edits a tenant
- WHEN they submit an unsupported currency code
- THEN validation fails and existing tenant data is not modified

#### Scenario: Read tenant Connect status

- GIVEN tenant T1 has connected account and capability status values
- WHEN SuperAdmin views T1
- THEN payment mode, connected account ID, and onboarding/status fields are displayed

#### Scenario: Update tenant notification settings

- GIVEN a SuperAdmin is authenticated in the Super Admin panel
- WHEN they modify a tenant's notification configuration (Twilio SID, auth token, phone number, Mailgun domain, secret)
- THEN the updated notification settings are persisted
- AND sensitive fields (Twilio auth token, Mailgun secret) are encrypted at rest

#### Scenario: Read tenant details

- GIVEN a SuperAdmin is authenticated in the Super Admin panel
- WHEN they view the tenant list or a specific tenant record
- THEN all tenant fields including payment and notification settings are displayed correctly
- AND the list is paginated and searchable

#### Scenario: Delete a tenant

- GIVEN a SuperAdmin is authenticated
- WHEN they delete a tenant record
- THEN tenant data is removed per cascade policy
- AND no Stripe account owned by another tenant is affected

### Requirement: Tenant Data Model

The system SHALL store tenant records with `name`, `slug`, payment configuration, notification configuration, `default_currency`, payment account mode, nullable connected account ID, and Connect onboarding/status fields. Currency MUST remain tenant-scoped and MUST NOT imply FX conversion.

(Previously: Tenant table with only name and slug fields)

#### Scenario: Connect fields have safe defaults

- GIVEN a tenant is created without Connect configuration
- WHEN the tenant record is inspected
- THEN payment account mode defaults to direct
- AND connected account/status fields are null or not onboarded

#### Scenario: Existing tenants remain direct mode

- GIVEN tenants predate Connect support
- WHEN payment account data is read
- THEN they resolve to direct API key mode
- AND existing Stripe API key behavior remains valid

#### Scenario: Tenant slug uniqueness

- GIVEN a tenant with slug "salon-123" exists
- WHEN a new tenant is created with slug "salon-123"
- THEN the creation fails with a uniqueness validation error
- AND the existing tenant data is not modified

#### Scenario: Payment settings have sensible defaults

- GIVEN a new tenant is created without explicit payment settings
- WHEN the tenant record is inspected
- THEN payment_policy defaults to nopayment
- AND deposit_percentage is NULL
- AND refund_window_hours defaults to 24
- AND stripe_api_key is NULL
- AND stripe_webhook_secret is NULL

#### Scenario: Notification settings have sensible defaults

- GIVEN a new tenant is created without explicit notification settings
- WHEN the tenant record is inspected
- THEN twilio_sid is NULL
- AND twilio_auth_token is NULL
- AND twilio_phone_number is NULL
- AND mailgun_domain is NULL
- AND mailgun_secret is NULL

### Requirement: Tenant Seeder

The system SHALL provide a seeder that creates a sample tenant with associated users for development and testing.

#### Scenario: Database seeder creates sample tenant

- GIVEN the database is fresh (migrated)
- WHEN `artisan db:seed` is executed
- THEN at least one sample tenant is created
- AND users for each role (BusinessAdmin, Employee, Client) are created under that tenant
- AND the seeded data is sufficient to test panel access for all roles
