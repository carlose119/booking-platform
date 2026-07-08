# i18n Setup Specification

## Purpose

Configure English (EN) as the default language and Spanish (ES) as the first alternative, enabling localization of the application UI and Filament panel interface.

## Requirements

### Requirement: EN Default Locale

The system SHALL default to English (EN) for all application text, validation messages, and Filament UI elements.

#### Scenario: Application loads in English

- GIVEN no locale preference is set
- WHEN a user loads any page of the application
- THEN all text is displayed in English
- AND validation messages are in English

#### Scenario: Laravel locale is set to EN

- GIVEN the application is freshly booted
- WHEN `config/app.php` locale is inspected
- THEN the locale is set to `en`
- AND the fallback locale is also `en`

### Requirement: ES Locale Support

The system SHALL provide Spanish (ES) as a supported locale with translated language files.

#### Scenario: ES language files exist

- GIVEN the i18n setup is complete
- WHEN the `lang/es` directory is inspected
- THEN it contains at minimum a translation file for the application's base strings
- AND the `lang/en` equivalents exist for all ES keys

#### Scenario: Validation messages translate to ES

- GIVEN the application locale is set to `es`
- WHEN a form validation error occurs
- THEN the error message is displayed in Spanish
- AND the message is accurate and grammatically correct

### Requirement: Filament Panel Locale Configuration

The system SHALL configure Filament panels to respect the application locale setting.

#### Scenario: Filament UI respects locale

- GIVEN the application locale is set to `es`
- WHEN a Filament panel page loads
- THEN Filament's built-in UI strings (navigation, actions, etc.) are displayed in Spanish
- AND custom application strings follow the same locale

#### Scenario: Locale switch mechanism exists

- GIVEN a user wants to switch locales
- WHEN the locale configuration is modified
- THEN subsequent page loads reflect the new locale
- AND no session corruption or redirect loops occur

### Requirement: Translation File Coverage

The system SHALL provide base translation keys covering the core scaffold entities (Tenant, User, Service, Employee, Booking) in both EN and ES.

#### Scenario: Translation keys for entities exist

- GIVEN both EN and ES locale directories are present
- WHEN translation keys for entity names (Tenant, User, Service, Employee, Booking) are checked
- THEN keys exist in both `lang/en` and `lang/es`
- AND the translations are consistent with the entity field labels used in Filament resources

#### Scenario: Missing translation falls back to EN

- GIVEN a translation key exists in EN but not in ES
- WHEN the application locale is set to `es`
- THEN the EN translation is displayed as a fallback
- AND no raw key name (e.g., "tenants.name") is shown to the user
