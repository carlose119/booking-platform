<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeScheduleResource\Pages;
use App\Models\EmployeeSchedule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EmployeeScheduleResource extends Resource
{
    protected static ?string $model = EmployeeSchedule::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected static string|\UnitEnum|null $navigationGroup = 'Schedules';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('employee_id')
                    ->relationship('employee', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
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
                    ->required(),
                Forms\Components\TimePicker::make('start_time')
                    ->required(),
                Forms\Components\TimePicker::make('end_time')
                    ->required()
                    ->afterOrEqual('start_time'),
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
                    ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('H:i')),
                Tables\Columns\TextColumn::make('end_time')
                    ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('H:i')),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes()
            ->whereHas('employee', function ($query) {
                $query->where('tenant_id', auth()->user()->tenant_id);
            });
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
