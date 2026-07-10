# Delta for RBAC Roles

## MODIFIED Requirements

### Requirement: Panel Guard Enforcement

The system SHALL enforce panel access at the Filament panel boundary and SHALL enforce resource/action authorization before restricted resource data is listed, viewed, created, updated, or deleted.

(Previously: Authorization was described as panel-provider-only, not per-resource.)

#### Scenario: Unauthenticated user is redirected to login

- GIVEN no user is authenticated
- WHEN any panel URL is accessed
- THEN the user is redirected to the Filament login screen.

#### Scenario: Role check happens before resource loading

- GIVEN an authenticated user with role Employee
- WHEN the user attempts to access a resource restricted to BusinessAdmin
- THEN authorization is denied before the resource query executes
- AND no data leakage occurs.

#### Scenario: Resource actions enforce role and tenant

- GIVEN a BusinessAdmin or Employee belongs to tenant T1
- WHEN they request a tenant resource or action outside their allowed role or tenant scope
- THEN the UI hides unavailable actions or returns authorization denial
- AND no cross-tenant records are exposed or changed.

## ADDED Requirements

### Requirement: Tenant Resource Authorization Matrix

The system MUST authorize Tenant panel resources by role: BusinessAdmin may manage tenant users and schedules; Employee may view only their own schedule; Client may not access Tenant panel admin resources.

#### Scenario: BusinessAdmin resource navigation

- GIVEN a BusinessAdmin belongs to tenant T1
- WHEN they open Tenant panel navigation
- THEN user and employee schedule resources are available
- AND actions are scoped to T1.

#### Scenario: Restricted role navigation

- GIVEN an Employee or Client authenticates
- WHEN Tenant panel resources are rendered
- THEN Employee sees no user management and only own schedule access
- AND Client receives admin access denial.

### Requirement: Safe Tenant Role Boundaries

The system MUST NOT allow tenant-level users to grant SuperAdmin or BusinessAdmin privileges through tenant user management.

#### Scenario: BusinessAdmin cannot assign elevated roles

- GIVEN a BusinessAdmin submits a user create or update request
- WHEN the requested role is SuperAdmin or BusinessAdmin
- THEN the request is rejected
- AND persisted roles remain unchanged.
