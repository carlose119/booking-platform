# Delta for Tenant Management

## ADDED Requirements

### Requirement: Business Admin Connect Onboarding

The system SHALL provide a Business Admin action to start or resume Stripe Standard Connect onboarding for the current tenant. The action MUST create an onboarding link only for that tenant and MUST NOT support Express or Custom accounts.

#### Scenario: Business admin starts onboarding

- GIVEN BusinessAdmin belongs to tenant T1
- WHEN they request Stripe Connect onboarding
- THEN an onboarding link for T1's Standard connected account is returned
- AND no other tenant account is exposed

#### Scenario: Non-business user cannot onboard tenant

- GIVEN a user without tenant admin permission
- WHEN they request Connect onboarding
- THEN access is denied
- AND no account link is created

## MODIFIED Requirements

### Requirement: Tenant CRUD in Super Admin Panel

The system SHALL provide a Filament Super Admin resource for tenant CRUD. The resource MUST include payment policy, direct Stripe credentials, payment account mode, connected account ID, Connect onboarding/status fields, notification fields, and validated default currency. Direct credentials are required only when direct mode requires Stripe payment; Connect fields are required only for Connect mode readiness.
(Previously: Tenant CRUD included payment, notification, and currency fields but no payment account mode or Connect status.)

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

#### Scenario: Delete a tenant

- GIVEN a SuperAdmin is authenticated
- WHEN they delete a tenant record
- THEN tenant data is removed per cascade policy
- AND no Stripe account owned by another tenant is affected

### Requirement: Tenant Data Model

The system SHALL store tenant records with `name`, `slug`, payment configuration, notification configuration, `default_currency`, payment account mode, nullable connected account ID, and Connect onboarding/status fields. Currency MUST remain tenant-scoped and MUST NOT imply FX conversion.
(Previously: Tenant data model had payment, notification, and currency fields but no Connect account/status fields.)

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
