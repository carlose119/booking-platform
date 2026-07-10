<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\UserResource\Pages;
use App\Models\Service;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Team';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('role')
                    ->options(self::assignableRoleOptions())
                    ->required()
                    ->rules(['in:'.implode(',', array_keys(self::assignableRoleOptions()))]),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? bcrypt($state) : null)
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->maxLength(255),
                Forms\Components\CheckboxList::make('services')
                    ->relationship(
                        'services',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->where('tenant_id', self::activeTenantId()),
                    )
                    ->rules(['array'])
                    ->nestedRecursiveRules(['integer'])
                    ->nestedRecursiveRule(fn () => Rule::in(self::tenantServiceIds()->all()))
                    ->columns(3)
                    ->searchable()
                    ->visible(fn ($record, $get): bool => ($get('role') ?? $record?->role?->value) === UserRole::Employee->value),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('role')
                    ->formatStateUsing(fn ($state) => $state instanceof UserRole ? $state->label() : UserRole::tryFrom($state)?->label() ?? $state)
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options(collect(UserRole::cases())->mapWithKeys(fn ($role) => [$role->value => $role->label()])),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes()
            ->where('tenant_id', self::activeTenantId());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()?->can('viewAny', User::class) ?? false;
    }

    public static function assignableRoleOptions(): array
    {
        return [
            UserRole::Employee->value => UserRole::Employee->label(),
            UserRole::Client->value => UserRole::Client->label(),
        ];
    }

    public static function tenantServiceIds(): Collection
    {
        return Service::query()
            ->where('tenant_id', self::activeTenantId())
            ->pluck('id');
    }

    public static function activeTenantId(): ?int
    {
        return Filament::getTenant()?->id ?? Auth::user()?->tenant_id;
    }

    public static function tenantServiceRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $serviceIds = collect($value)->filter()->map(fn ($id): int => (int) $id);

            if ($serviceIds->diff(self::tenantServiceIds())->isNotEmpty()) {
                $fail('The selected services must belong to the active tenant.');
            }
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
