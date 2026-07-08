# Delta for Tenant Management

## MODIFIED Requirements

### Requirement: Tenant Data Model

The system SHALL store tenant records with at minimum a `name` and `slug` field, plus payment configuration fields: payment_policy (enum: 100upfront, fraction, nopayment), deposit_percentage (integer, nullable), refund_window_hours (integer), stripe_api_key (encrypted, nullable), stripe_webhook_secret (encrypted, nullable), AND notification configuration fields: twilio_sid (encrypted, nullable), twilio_auth_token (encrypted, nullable), twilio_phone_number (nullable), mailgun_domain (nullable), mailgun_secret (encrypted, nullable).

(Previously: Tenant table with only name and slug fields)

#### Scenario: Tenant has required fields

- GIVEN the tenants migration runs successfully
- WHEN a tenant record is inspected
- THEN it has `id`, `name`, `slug`, `payment_policy`, `deposit_percentage`, `refund_window_hours`, `stripe_api_key`, `stripe_webhook_secret`, `twilio_sid`, `twilio_auth_token`, `twilio_phone_number`, `mailgun_domain`, `mailgun_secret`, `created_at`, and `updated_at` columns
- AND `slug` is unique across all tenants

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

### Requirement: Tenant CRUD in Super Admin Panel

The system SHALL provide a Filament resource in the Super Admin panel for creating, reading, updating, and deleting tenant records. The resource MUST include payment configuration fields AND notification configuration fields in the form.

(Previously: Tenant CRUD with only name and slug fields)

#### Scenario: Create a new tenant

- GIVEN a SuperAdmin is authenticated in the Super Admin panel
- WHEN they submit the tenant creation form with valid data (name, slug, payment_policy, notification settings)
- THEN a new Tenant record is persisted to the database
- AND the tenant appears in the tenant list

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
