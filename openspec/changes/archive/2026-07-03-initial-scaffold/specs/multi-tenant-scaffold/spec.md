# Multi-Tenant Scaffold Specification

## Purpose

Establish the Laravel 13 foundation with Sail (Docker), MariaDB, and FilamentPHP 5 configured for multi-tenancy. This is the root dependency for every subsequent capability.

## Requirements

### Requirement: Laravel Project Initialization

The system SHALL be initialized as a Laravel 13 project running via Sail with a MariaDB database.

#### Scenario: Sail starts the application

- GIVEN the `.env` file is configured with Sail-compatible database credentials
- WHEN `./vendor/bin/sail up` is executed
- THEN the application starts and the MariaDB connection is established
- AND the default Laravel welcome page loads without errors

#### Scenario: Database connection is verified

- GIVEN the application is running via Sail
- WHEN a database connection test is performed
- THEN the connection to MariaDB succeeds using the configured credentials

### Requirement: FilamentPHP 5 Installation

The system SHALL have FilamentPHP 5 installed with two panel providers: Super Admin (global) and Tenant (scoped).

#### Scenario: Filament packages are installed

- GIVEN the Laravel project exists
- WHEN FilamentPHP 5 is installed via Composer
- THEN the `filament/filament` and `filament/forms` packages are present in `composer.json`
- AND `artisan filament:install` completes without errors

#### Scenario: Super Admin panel is accessible

- GIVEN FilamentPHP is installed and the Super Admin panel provider is registered
- WHEN the user navigates to `/super-admin`
- THEN the Filament login screen renders correctly
- AND the panel is accessible without tenant context

#### Scenario: Tenant panel requires tenant context

- GIVEN FilamentPHP is installed and the Tenant panel provider is registered
- WHEN the user navigates to `/tenant` without an active tenant
- THEN the system redirects or presents a tenant selection mechanism
- AND no tenant-scoped resources are visible

### Requirement: Multi-Tenancy Configuration

The system SHALL use single-database multi-tenancy with logical `tenant_id` column separation via Filament's native support.

#### Scenario: Tenant model implements multi-tenancy interface

- GIVEN the Tenant model exists
- WHEN the model is inspected for Filament tenancy contracts
- THEN it implements the `HasTenants` interface
- AND it is registered as the tenancy model in the Tenant panel provider

#### Scenario: User model supports tenant association

- GIVEN the User model exists
- WHEN the model is inspected for Filament tenancy contracts
- THEN it implements the `HasTenants` interface
- AND it can be associated with one or more tenants

### Requirement: Development Environment Consistency

The system SHALL provide a Docker-based development environment via Sail that is reproducible across developer machines.

#### Scenario: Fresh clone boots correctly

- GIVEN a developer clones the repository on a machine with Docker Desktop installed
- WHEN they run `./vendor/bin/sail up` and `./vendor/bin/sail artisan migrate`
- THEN the application starts with all services available
- AND no manual environment configuration beyond `.env` is required

#### Scenario: Pest PHP test suite runs

- GIVEN the application is booted via Sail
- WHEN `./vendor/bin/sail test` is executed
- THEN the Pest PHP test runner starts and completes with baseline config
- AND no failures occur from the scaffold setup itself
