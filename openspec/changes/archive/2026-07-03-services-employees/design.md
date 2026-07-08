# Design: Services & Employees Management UI

## Technical Approach

Implement three Filament resources following the existing UserResource pattern: ServiceResource (CRUD), EmployeeScheduleResource (CRUD), and UserResource enhancement (conditional Repeater). All resources use tenant scoping via `getEloquentQuery()`. Price conversion handled in ServiceResource form.

## Architecture Decisions

### Decision: Separate Resources vs. Nested Resources

**Choice**: Standalone resources for ServiceResource and EmployeeScheduleResource
**Alternatives considered**: Nested resources under UserResource
**Rationale**: Follows exploration recommendation; cleaner separation of concerns; allows independent navigation and filtering

### Decision: Price Conversion Strategy

**Choice**: Convert dollars to cents in ServiceResource form using `dehydrateStateUsing`
**Alternatives considered**: Store dollars in DB, convert in model accessor
**Rationale**: Follows existing UserResource password hashing pattern; keeps conversion logic in resource layer; avoids floating-point precision issues in storage

### Decision: Repeater vs. Checkbox List for Service Association

**Choice**: Use Filament Repeater with nested Select/Checkbox for employee-service association
**Alternatives considered**: Separate pivot management page
**Rationale**: Keeps association within user edit flow; follows proposal spec; simpler UX for moderate number of services

## Data Flow

```
BusinessAdmin ──→ TenantPanel ──→ ServiceResource ──→ Service Model
                                     │                    │
                                     └── price_cents ←──┘

BusinessAdmin ──→ TenantPanel ──→ EmployeeScheduleResource ──→ EmployeeSchedule Model
                                     │                              │
                                     └── employee_id ←─────────────┘

BusinessAdmin ──→ TenantPanel ──→ UserResource ──→ User Model
                                     │                  │
                                     └── Repeater ────→ employee_services pivot
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `app/Filament/Resources/ServiceResource.php` | Create | Service CRUD resource with price conversion |
| `app/Filament/Resources/ServiceResource/Pages/ListServices.php` | Create | List page |
| `app/Filament/Resources/ServiceResource/Pages/CreateService.php` | Create | Create page |
| `app/Filament/Resources/ServiceResource/Pages/EditService.php` | Create | Edit page |
| `app/Filament/Resources/EmployeeScheduleResource.php` | Create | Schedule CRUD resource |
| `app/Filament/Resources/EmployeeScheduleResource/Pages/ListSchedules.php` | Create | List page |
| `app/Filament/Resources/EmployeeScheduleResource/Pages/CreateSchedule.php` | Create | Create page |
| `app/Filament/Resources/EmployeeScheduleResource/Pages/EditSchedule.php` | Create | Edit page |
| `app/Filament/Resources/UserResource.php` | Modify | Add conditional Repeater for services |
| `app/Providers/Filament/TenantPanelProvider.php` | Modify | Register new resources |

## Interfaces / Contracts

### ServiceResource Form Schema

```php
Forms\Components\TextInput::make('name')->required()->maxLength(255),
Forms\Components\Textarea::make('description')->maxLength(1000),
Forms\Components\TextInput::make('price')
    ->numeric()
    ->prefix('$')
    ->required()
    ->minValue(0.01)
    ->dehydrateStateUsing(fn (string $state): int => (int) round($state * 100))
    ->dehydrated(true),
Forms\Components\TextInput::make('duration_minutes')
    ->numeric()
    ->required()
    ->minValue(1),
Forms\Components\Toggle::make('active')->default(true),
```

### EmployeeScheduleResource Form Schema

```php
Forms\Components\Select::make('employee_id')
    ->relationship('employee', 'name')
    ->searchable()
    ->preload()
    ->required(),
Forms\Components\Select::make('day_of_week')
    ->options([
        0 => 'Monday', 1 => 'Tuesday', 2 => 'Wednesday',
        3 => 'Thursday', 4 => 'Friday', 5 => 'Saturday', 6 => 'Sunday',
    ])
    ->required(),
Forms\Components\TimePicker::make('start_time')->required(),
Forms\Components\TimePicker::make('end_time')
    ->required()
    ->afterOrEqual('start_time'),
```

### UserResource Repeater Addition

```php
Forms\Components\Repeater::make('services')
    ->relationship('services')
    ->schema([
        Forms\Components\CheckboxList::make('service_id')
            ->relationship('service', 'name')
            ->columns(3)
    ])
    ->visible(fn ($record) => $record?->role === UserRole::Employee),
```

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Unit | Price conversion logic | Test dehydrateStateUsing callback with edge cases (0.01, 99.99, rounding) |
| Unit | Form validation rules | Test required fields, min values, time ordering |
| Integration | Tenant scoping | Verify getEloquentQuery filters by tenant_id |
| E2E | CRUD workflows | Filament test helpers for resource creation/edit/list |

## Migration / Rollout

No migration required. Data layer already exists (models, migrations, seeder).

## Open Questions

- [ ] Should we add unique constraint on employee_schedules (employee_id, day_of_week) to prevent duplicates at DB level?
