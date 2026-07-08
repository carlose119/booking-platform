<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceResource\Pages;
use App\Models\Service;
use App\Support\Currency;
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

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wrench';

    protected static string|\UnitEnum|null $navigationGroup = 'Services';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Hidden::make('tenant_id')
                    ->default(fn (): ?int => Filament::getTenant()?->id ?? auth()->user()?->tenant_id),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->maxLength(1000),
                Forms\Components\TextInput::make('price_cents')
                    ->label(fn (): string => 'Price ('.strtoupper(self::activeCurrency()).')')
                    ->numeric()
                    ->prefix(fn (): string => Currency::symbol(self::activeCurrency()))
                    ->required()
                    ->minValue(0.01)
                    ->formatStateUsing(fn ($state): ?string => $state === null ? null : number_format(((int) $state) / 100, 2, '.', ''))
                    ->dehydrateStateUsing(fn (string|int|float $state): int => (int) round(((float) $state) * 100))
                    ->dehydrated(true),
                Forms\Components\TextInput::make('duration_minutes')
                    ->numeric()
                    ->required()
                    ->minValue(1),
                Forms\Components\Toggle::make('active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('price_cents')
                    ->label('Price')
                    ->formatStateUsing(fn ($state) => Currency::format((int) $state, self::activeCurrency()))
                    ->sortable(),
                Tables\Columns\TextColumn::make('duration_minutes')
                    ->label('Duration')
                    ->formatStateUsing(fn ($state) => $state.' min')
                    ->sortable(),
                Tables\Columns\IconColumn::make('active')
                    ->boolean(),
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
        return parent::getEloquentQuery()
            ->withoutGlobalScopes()
            ->where('tenant_id', auth()->user()->tenant_id);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServices::route('/'),
            'create' => Pages\CreateService::route('/create'),
            'edit' => Pages\EditService::route('/{record}/edit'),
        ];
    }

    private static function activeCurrency(): string
    {
        $tenant = Filament::getTenant() ?? auth()->user()?->tenant;

        return $tenant?->currency() ?? Currency::default();
    }
}
