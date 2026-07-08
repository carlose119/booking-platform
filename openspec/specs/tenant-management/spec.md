# Tenant Management Specification

## Purpose

Provide CRUD operations for tenant (business) records through the Super Admin panel, enabling onboarding of new businesses onto the platform.

## Requirements

### Requirement: Tenant CRUD in Super Admin Panel

The system SHALL provide a Filament resource in the Super Admin panel for creating, reading, updating, and deleting tenant records. The resource MUST include payment configuration fields, notification configuration fields, and a validated default currency field. If no currency is configured, the system MUST use `usd`.

(Previously: Tenant CRUD with only name and slug fields)

#### Scenario: Create a new tenant

- GIVEN a SuperAdmin is authenticated in the Super Admin panel
- WHEN they submit valid tenant data without a currency
- THEN a new Tenant record is persisted with default_currency=`usd`
- AND the tenant appears in the tenant list

#### Scenario: Update tenant currency

- GIVEN a SuperAdmin is authenticated in the Super Admin panel
- WHEN they set tenant T1 default_currency to a supported lowercase ISO currency
- THEN the updated currency is persisted for T1 only

#### Scenario: Reject unsupported currency

- GIVEN a SuperAdmin edits a tenant
- WHEN they submit an unsupported currency code
- THEN validation fails and existing tenant data is not modified

#### Scenario: Update tenant payment settings

- GIVEN a SuperAdmin is authenticated in the Super Admin panel
- WHEN they modify a tenant's payment_policy from nopayment to 100upfront
- THEN the updated payment_policy is persisted
- AND Stripe API key fields are required when payment_policy is not nopayment

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

- GIVEN a SuperAdmin is authenticated in the Super Admin panel
- WHEN they delete a tenant record
- THEN the tenant is removed from the database
- AND associated users and resources are handled per cascade policy

### Requirement: Tenant Data Model

The system SHALL store tenant records with `name`, `slug`, payment configuration, notification configuration, and `default_currency`. The currency MUST default to `usd`, MUST be tenant-scoped, and MUST NOT imply FX conversion or Stripe Connect behavior.

(Previously: Tenant table with only name and slug fields)

#### Scenario: Tenant has currency field

- GIVEN migrations run successfully
- WHEN a tenant record is inspected
- THEN `default_currency` exists and defaults to `usd`

#### Scenario: Existing tenants remain USD

- GIVEN existing tenant rows predate currency support
- WHEN currency-aware reads occur
- THEN missing or null currency is treated as `usd`

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
