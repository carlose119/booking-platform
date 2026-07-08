# Delta for Service Management

## MODIFIED Requirements

### Requirement: Service CRUD in Tenant Panel

The system SHALL provide a Filament resource in the Tenant panel for creating, reading, updating, and deleting service records scoped to the active tenant. Service prices MUST be entered and displayed in the active tenant's default currency; this slice MUST NOT provide per-service currency overrides.
(Previously: Service CRUD treated price input/display as dollars.)

#### Scenario: Create a new service under a tenant
- GIVEN a BusinessAdmin is authenticated for tenant T1 with default_currency=`eur`
- WHEN they submit valid service data with a price amount
- THEN the service is created with `tenant_id` T1 and price stored in minor units
- AND the UI labels/displays the amount as EUR for T1

#### Scenario: Read service list for a tenant
- GIVEN tenants T1 and T2 have different currencies
- WHEN a BusinessAdmin views T1 services
- THEN only T1 services are displayed with T1 currency
- AND T2 data and currency are not visible

### Requirement: Price Conversion

The system SHALL convert entered service price amounts to integer minor units when storing, using the tenant currency only for labeling and display. The system MUST NOT perform FX conversion.
(Previously: Price conversion was described specifically as dollars to cents.)

#### Scenario: Currency-labeled input stored as minor units
- GIVEN tenant T1 default_currency=`usd` and a BusinessAdmin enters 25.50
- WHEN the service is saved
- THEN `price_cents` is stored as 2550 with no floating-point precision issues

#### Scenario: Currency changes do not convert existing prices
- GIVEN tenant T1 has existing services
- WHEN T1 default_currency changes
- THEN stored price minor units are unchanged
- AND future displays use the new tenant currency label
