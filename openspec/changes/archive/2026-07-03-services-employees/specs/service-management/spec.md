# Service Management Specification

## Purpose

Provide per-tenant service catalog management through the Business Admin panel, enabling tenant administrators to create, edit, list, and delete services with price, duration, and active status.

## Requirements

### Requirement: Service CRUD in Tenant Panel

The system SHALL provide a Filament resource in the Tenant panel for creating, reading, updating, and deleting service records scoped to the active tenant.

#### Scenario: Create a new service under a tenant

- GIVEN a BusinessAdmin is authenticated in the Tenant panel for tenant T1
- WHEN they submit the service creation form with valid data (name, description, price in dollars, duration in minutes, active status)
- THEN a new Service record is created with `tenant_id` set to T1
- AND the price is stored in cents (price * 100)
- AND the service appears in the tenant's service list

#### Scenario: Read service list for a tenant

- GIVEN a BusinessAdmin is authenticated in the Tenant panel for tenant T1
- WHEN they view the service list
- THEN only services belonging to T1 are displayed
- AND services from other tenants are not visible
- AND the list is paginated and searchable

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

The system SHALL convert price from dollars to cents when storing.

#### Scenario: Dollar input stored as cents

- GIVEN a BusinessAdmin enters a price of $25.50
- WHEN the service is saved
- THEN `price_cents` is stored as 2550
- AND no floating-point precision issues occur

### Requirement: Active Toggle

The system SHALL allow toggling a service's active status.

#### Scenario: Deactivate a service

- GIVEN a service is active
- WHEN a BusinessAdmin toggles the active status to false
- THEN the service is marked as inactive
- AND the service is not available for new bookings