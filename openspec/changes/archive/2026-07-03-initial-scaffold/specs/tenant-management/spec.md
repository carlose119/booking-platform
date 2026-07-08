# Tenant Management Specification

## Purpose

Provide CRUD operations for tenant (business) records through the Super Admin panel, enabling onboarding of new businesses onto the platform.

## Requirements

### Requirement: Tenant CRUD in Super Admin Panel

The system SHALL provide a Filament resource in the Super Admin panel for creating, reading, updating, and deleting tenant records.

#### Scenario: Create a new tenant

- GIVEN a SuperAdmin is authenticated in the Super Admin panel
- WHEN they submit the tenant creation form with valid data (name, slug)
- THEN a new Tenant record is persisted to the database
- AND the tenant appears in the tenant list

#### Scenario: Read tenant details

- GIVEN a SuperAdmin is authenticated in the Super Admin panel
- WHEN they view the tenant list or a specific tenant record
- THEN all tenant fields are displayed correctly
- AND the list is paginated and searchable

#### Scenario: Update an existing tenant

- GIVEN a SuperAdmin is authenticated in the Super Admin panel
- WHEN they modify a tenant's name or slug and save
- THEN the updated values are persisted
- AND the change is reflected in the tenant list

#### Scenario: Delete a tenant

- GIVEN a SuperAdmin is authenticated in the Super Admin panel
- WHEN they delete a tenant record
- THEN the tenant is removed from the database
- AND associated users and resources are handled per cascade policy

### Requirement: Tenant Data Model

The system SHALL store tenant records with at minimum a `name` and `slug` field.

#### Scenario: Tenant has required fields

- GIVEN the tenants migration runs successfully
- WHEN a tenant record is inspected
- THEN it has `id`, `name`, `slug`, `created_at`, and `updated_at` columns
- AND `slug` is unique across all tenants

#### Scenario: Tenant slug uniqueness

- GIVEN a tenant with slug "salon-123" exists
- WHEN a new tenant is created with slug "salon-123"
- THEN the creation fails with a uniqueness validation error
- AND the existing tenant data is not modified

### Requirement: Tenant Seeder

The system SHALL provide a seeder that creates a sample tenant with associated users for development and testing.

#### Scenario: Database seeder creates sample tenant

- GIVEN the database is fresh (migrated)
- WHEN `artisan db:seed` is executed
- THEN at least one sample tenant is created
- AND users for each role (BusinessAdmin, Employee, Client) are created under that tenant
- AND the seeded data is sufficient to test panel access for all roles
