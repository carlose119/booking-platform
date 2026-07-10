<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\EmployeeScheduleResource\Pages;
use App\Models\EmployeeSchedule;
use App\Models\User;
use Carbon\Carbon;
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

class EmployeeScheduleResource extends Resource
{
    protected static ?string $model = EmployeeSchedule::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected static string|\UnitEnum|null $navigationGroup = 'Schedules';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('employee_id')
                    ->relationship(
                        'employee',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query
                            ->where('tenant_id', self::activeTenantId())
                            ->where('role', UserRole::Employee),
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->rules([Rule::in(self::activeTenantEmployeeIds()->all())]),
                Forms\Components\Select::make('day_of_week')
                    ->options([
                        0 => 'Monday',
                        1 => 'Tuesday',
                        2 => 'Wednesday',
                        3 => 'Thursday',
                        4 => 'Friday',
                        5 => 'Saturday',
                        6 => 'Sunday',
                    ])
                    ->required()
                    ->rules(['integer', 'between:0,6']),
                Forms\Components\TimePicker::make('start_time')
                    ->required(),
                Forms\Components\TimePicker::make('end_time')
                    ->required()
                    ->after('start_time'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('day_of_week')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        0 => 'Monday',
                        1 => 'Tuesday',
                        2 => 'Wednesday',
                        3 => 'Thursday',
                        4 => 'Friday',
                        5 => 'Saturday',
                        6 => 'Sunday',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_time')
                    ->formatStateUsing(fn ($state) => Carbon::parse($state)->format('H:i')),
                Tables\Columns\TextColumn::make('end_time')
                    ->formatStateUsing(fn ($state) => Carbon::parse($state)->format('H:i')),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
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
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes()
            ->whereHas('employee', function (Builder $query) {
                $query->where('tenant_id', self::activeTenantId());
            });

        if (Auth::user()?->role === UserRole::Employee) {
            $query->where('employee_id', Auth::id());
        }

        return $query;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()?->can('viewAny', EmployeeSchedule::class) ?? false;
    }

    public static function activeTenantEmployeeIds(): Collection
    {
        return User::query()
            ->where('tenant_id', self::activeTenantId())
            ->where('role', UserRole::Employee)
            ->pluck('id');
    }

    public static function activeTenantId(): ?int
    {
        return Filament::getTenant()?->id ?? Auth::user()?->tenant_id;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSchedules::route('/'),
            'create' => Pages\CreateSchedule::route('/create'),
            'edit' => Pages\EditSchedule::route('/{record}/edit'),
        ];
    }
}
