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

The system SHALL support associating employees with services via a many-to-many pivot table, managed through a Repeater in the user form when the user's role is Employee. The Repeater SHALL be visible only when role=Employee and SHALL list tenant services as selectable items.

(Previously: The system SHALL support associating employees with services via a many-to-many pivot table.)

#### Scenario: Employee is linked to services

- GIVEN an Employee user exists under tenant T1
- WHEN the employee is associated with specific services via the user form Repeater
- THEN the `employee_services` pivot table records the associations
- AND the associations are retrievable from the Employee model

#### Scenario: Services are scoped to tenant

- GIVEN tenant T1 has services S1 and S2
- WHEN a BusinessAdmin associates an employee with services via the user form Repeater
- THEN only T1's services are available for selection
- AND services from other tenants are not selectable

#### Scenario: Repeater visibility based on role

- GIVEN a BusinessAdmin is editing a user with role=Employee
- WHEN the user form loads
- THEN the service association Repeater is visible
- AND the Repeater lists tenant services as selectable items

#### Scenario: Repeater hidden for non-employee roles

- GIVEN a BusinessAdmin is editing a user with role=Client
- WHEN the user form loads
- THEN the service association Repeater is not visible

### Requirement: Tenant User Administration Exposure

The system SHALL expose tenant user management only to BusinessAdmin users in the active tenant.

#### Scenario: BusinessAdmin opens tenant users

- GIVEN a BusinessAdmin belongs to tenant T1
- WHEN they open the Tenant panel user resource
- THEN the user list loads
- AND only T1 users are visible.

#### Scenario: Non-admin cannot access tenant users

- GIVEN an Employee or Client belongs to tenant T1
- WHEN they request any Tenant panel user resource page
- THEN access is denied or the navigation item is hidden
- AND no user records are exposed.

### Requirement: Safe Tenant User Creation and Roles

The system MUST assign new users to the active tenant and MUST allow BusinessAdmin users to create or update only Employee and Client accounts.

#### Scenario: Create allowed role in active tenant

- GIVEN a BusinessAdmin is active in tenant T1
- WHEN they create an Employee or Client user
- THEN the user is saved with `tenant_id` T1
- AND tenant assignment cannot be overridden.

#### Scenario: Prevent privilege escalation

- GIVEN a BusinessAdmin is editing a tenant user
- WHEN they submit SuperAdmin, BusinessAdmin, or their own role change
- THEN validation or authorization rejects the request
- AND the original role remains unchanged.

### Requirement: Tenant-Scoped Employee Services

The system SHALL restrict employee service assignment options to services owned by the active tenant.

#### Scenario: Service options are tenant-scoped

- GIVEN tenant T1 and T2 each have services
- WHEN a BusinessAdmin edits a T1 Employee
- THEN only T1 services are selectable.

#### Scenario: Cross-tenant service is rejected

- GIVEN a T1 BusinessAdmin submits a T2 service id for a T1 Employee
- WHEN the form is saved
- THEN the assignment is rejected
- AND no cross-tenant pivot record is created.

### Requirement: User Seeder

The system SHALL create users with each role type under the sample tenant for testing.

#### Scenario: Seeded users cover all roles

- GIVEN the database is seeded
- WHEN user records are inspected for the sample tenant
- THEN at least one BusinessAdmin, one Employee, and one Client user exist
- AND each user has valid credentials for authentication testing
