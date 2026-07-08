# Service Management Specification

## Purpose

Provide per-tenant service catalog management through the Business Admin panel, enabling tenant administrators to create, edit, list, and delete services with price, duration, and active status.

## Requirements

### Requirement: Service CRUD in Tenant Panel

The system SHALL provide a Filament resource in the Tenant panel for creating, reading, updating, and deleting service records scoped to the active tenant. Service prices MUST be entered and displayed in the active tenant's default currency; this slice MUST NOT provide per-service currency overrides.

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

#### Scenario: Update a service record

- GIVEN a BusinessAdmin is authenticated in the Tenant panel for tenant T1
- WHEN they modify a service's name, description, price, duration, or active status and save
- THEN the updated values are persisted
- AND the change is reflected in the tenant's service list

#### Scenario: Delete a service record

- GIVEN a BusinessAdmin is authenticated in the Tenant panel for tenant T1
- WHEN they delete a service record
- THEN the service is removed from the database
- AND the service can no longer be used for bookings

### Requirement: Service Validation

The system SHALL validate service input fields to ensure data integrity.

#### Scenario: Price must be positive

- GIVEN a BusinessAdmin is authenticated in the Tenant panel for tenant T1
- WHEN they enter a price of 0 or negative in the service creation form
- THEN the form displays a validation error
- AND the service is not created

#### Scenario: Duration must be positive

- GIVEN a BusinessAdmin is authenticated in the Tenant panel for tenant T1
- WHEN they enter a duration of 0 or negative in the service creation form
- THEN the form displays a validation error
- AND the service is not created

### Requirement: Price Conversion

The system SHALL convert entered service price amounts to integer minor units when storing, using the tenant currency only for labeling and display. The system MUST NOT perform FX conversion.

#### Scenario: Currency-labeled input stored as minor units

- GIVEN tenant T1 default_currency=`usd` and a BusinessAdmin enters 25.50
- WHEN the service is saved
- THEN `price_cents` is stored as 2550 with no floating-point precision issues

#### Scenario: Currency changes do not convert existing prices

- GIVEN tenant T1 has existing services
- WHEN T1 default_currency changes
- THEN stored price minor units are unchanged
- AND future displays use the new tenant currency label

### Requirement: Active Toggle

The system SHALL allow toggling a service's active status.

#### Scenario: Deactivate a service

- GIVEN a service is active
- WHEN a BusinessAdmin toggles the active status to false
- THEN the service is marked as inactive
- AND the service is not available for new bookings
