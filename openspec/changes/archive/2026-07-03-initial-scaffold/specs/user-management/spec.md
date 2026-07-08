# User Management Specification

## Purpose

Provide per-tenant user CRUD operations through the Business Admin panel, enabling tenant administrators to manage their staff and client accounts.

## Requirements

### Requirement: User CRUD in Tenant Panel

The system SHALL provide a Filament resource in the Tenant panel for creating, reading, updating, and deleting user records scoped to the active tenant.

#### Scenario: Create a new user under a tenant

- GIVEN a BusinessAdmin is authenticated in the Tenant panel for tenant T1
- WHEN they submit the user creation form with valid data (name, email, role)
- THEN a new User record is created with `tenant_id` set to T1
- AND the user appears in the tenant's user list

#### Scenario: Read user list for a tenant

- GIVEN a BusinessAdmin is authenticated in the Tenant panel for tenant T1
- WHEN they view the user list
- THEN only users belonging to T1 are displayed
- AND users from other tenants are not visible
- AND the list is paginated and searchable

#### Scenario: Update a user record

- GIVEN a BusinessAdmin is authenticated in the Tenant panel for tenant T1
- WHEN they modify a user's name or role and save
- THEN the updated values are persisted
- AND the change is reflected in the tenant's user list

#### Scenario: Delete a user record

- GIVEN a BusinessAdmin is authenticated in the Tenant panel for tenant T1
- WHEN they delete a user record
- THEN the user is removed from the database
- AND the user can no longer authenticate for tenant T1

### Requirement: Tenant Scoping

The system SHALL ensure all user queries are scoped to the active tenant. Users MUST NOT be visible across tenants.

#### Scenario: User list is tenant-scoped

- GIVEN tenants T1 and T2 each have users
- WHEN a BusinessAdmin of T1 views the user list
- THEN only T1 users are shown
- AND T2 users are not accessible via direct URL manipulation

#### Scenario: User creation assigns tenant automatically

- GIVEN a BusinessAdmin is authenticated in the Tenant panel for tenant T1
- WHEN they create a new user
- THEN the `tenant_id` field is automatically set to T1
- AND the admin cannot override the tenant assignment

### Requirement: Employee-to-Service Association

The system SHALL support associating employees with services via a many-to-many pivot table.

#### Scenario: Employee is linked to services

- GIVEN an Employee user exists under tenant T1
- WHEN the employee is associated with specific services
- THEN the `employee_services` pivot table records the associations
- AND the associations are retrievable from the Employee model

#### Scenario: Services are scoped to tenant

- GIVEN tenant T1 has services S1 and S2
- WHEN a BusinessAdmin associates an employee with services
- THEN only T1's services are available for selection
- AND services from other tenants are not selectable

### Requirement: User Seeder

The system SHALL create users with each role type under the sample tenant for testing.

#### Scenario: Seeded users cover all roles

- GIVEN the database is seeded
- WHEN user records are inspected for the sample tenant
- THEN at least one BusinessAdmin, one Employee, and one Client user exist
- AND each user has valid credentials for authentication testing
