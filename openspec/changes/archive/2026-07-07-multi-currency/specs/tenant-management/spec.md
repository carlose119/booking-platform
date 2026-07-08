# Delta for Tenant Management

## MODIFIED Requirements

### Requirement: Tenant CRUD in Super Admin Panel

The system SHALL provide a Filament resource in the Super Admin panel for creating, reading, updating, and deleting tenant records. The resource MUST include payment configuration fields, notification configuration fields, and a validated default currency field. If no currency is configured, the system MUST use `usd`.
(Previously: Tenant CRUD included payment and notification configuration, but no tenant currency setting.)

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

### Requirement: Tenant Data Model

The system SHALL store tenant records with `name`, `slug`, payment configuration, notification configuration, and `default_currency`. The currency MUST default to `usd`, MUST be tenant-scoped, and MUST NOT imply FX conversion or Stripe Connect behavior.
(Previously: Tenant model stored payment and notification fields, but no currency.)

#### Scenario: Tenant has currency field
- GIVEN migrations run successfully
- WHEN a tenant record is inspected
- THEN `default_currency` exists and defaults to `usd`

#### Scenario: Existing tenants remain USD
- GIVEN existing tenant rows predate currency support
- WHEN currency-aware reads occur
- THEN missing or null currency is treated as `usd`
