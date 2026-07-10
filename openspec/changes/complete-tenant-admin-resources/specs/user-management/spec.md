# Delta for User Management

## ADDED Requirements

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
