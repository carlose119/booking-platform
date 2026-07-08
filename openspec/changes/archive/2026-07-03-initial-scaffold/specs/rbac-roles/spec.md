# RBAC Roles Specification

## Purpose

Define the 4-role authorization system (Super Admin, Business Admin, Employee, Client) with Filament panel guards that control which panels and resources each role can access.

## Requirements

### Requirement: Role Enumeration

The system SHALL define exactly four roles as an enum: SuperAdmin, BusinessAdmin, Employee, Client.

#### Scenario: All four roles exist

- GIVEN the role enum is defined
- WHEN the enum values are listed
- THEN SuperAdmin, BusinessAdmin, Employee, and Client are all present
- AND no additional roles exist in this slice

#### Scenario: Role is stored on User model

- GIVEN a User record exists
- WHEN the User is created or updated
- THEN the role field MUST be one of the four defined enum values
- AND the role field is stored in the `role` column on the `users` table

### Requirement: Super Admin Panel Access

The system SHALL restrict the Super Admin panel to users with the SuperAdmin role only.

#### Scenario: SuperAdmin can access Super Admin panel

- GIVEN a user with role SuperAdmin exists
- WHEN the user authenticates and navigates to `/super-admin`
- THEN the Super Admin dashboard loads
- AND the user can view and manage tenants

#### Scenario: Non-SuperAdmin cannot access Super Admin panel

- GIVEN a user with role BusinessAdmin exists
- WHEN the user navigates to `/super-admin`
- THEN access is denied
- AND the user is redirected to their own panel or a 403 response is returned

### Requirement: Tenant Panel Access

The system SHALL restrict the Tenant panel to users with BusinessAdmin or Employee roles who belong to the active tenant.

#### Scenario: BusinessAdmin can access Tenant panel

- GIVEN a user with role BusinessAdmin belongs to tenant T1
- WHEN the user authenticates and navigates to the Tenant panel for T1
- THEN the Tenant dashboard loads
- AND the user can view and manage resources scoped to T1

#### Scenario: Employee can access Tenant panel

- GIVEN a user with role Employee belongs to tenant T1
- WHEN the user authenticates and navigates to the Tenant panel for T1
- THEN the Tenant dashboard loads
- AND the user can view resources scoped to T1
- AND the user CANNOT manage tenants or other users

#### Scenario: Client cannot access Tenant panel as admin

- GIVEN a user with role Client exists
- WHEN the user navigates to the Tenant panel admin interface
- THEN access is denied
- AND the client-facing booking UI (future slice) is the intended entry point

### Requirement: Panel Guard Enforcement

The system SHALL enforce authorization at the Filament panel provider level, not per-resource.

#### Scenario: Unauthenticated user is redirected to login

- GIVEN no user is authenticated
- WHEN any panel URL is accessed
- THEN the user is redirected to the Filament login screen

#### Scenario: Role check happens before resource loading

- GIVEN an authenticated user with role Employee
- WHEN the user attempts to access a resource restricted to BusinessAdmin
- THEN authorization is denied before the resource query executes
- AND no data leakage occurs
