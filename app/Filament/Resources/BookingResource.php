<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\BookingResource\Pages;
use App\Models\Booking;
use App\Services\BookingService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Bookings';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('client_name')
                    ->label('Customer'),
                TextEntry::make('service.name')
                    ->label('Service'),
                TextEntry::make('date')
                    ->date(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('payment_status')
                    ->label('Payment')
                    ->badge(),
                TextEntry::make('cancellation_reason')
                    ->placeholder('Not cancelled')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Booking')
                    ->formatStateUsing(fn ($state) => '#'.$state)
                    ->sortable(),
                Tables\Columns\TextColumn::make('client_name')
                    ->label('Customer')
                    ->searchable(),
                Tables\Columns\TextColumn::make('service.name')
                    ->label('Service')
                    ->searchable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
                self::rescheduleAction(),
                self::cancelAction(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes()
            ->with(['service', 'employee'])
            ->where('tenant_id', auth()->user()->tenant_id);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
            'view' => Pages\ViewBooking::route('/{record}'),
        ];
    }

    public static function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label('Cancel')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Booking $record): bool => auth()->user()?->role === UserRole::BusinessAdmin
                && $record->status !== 'cancelled')
            ->schema([
                Textarea::make('reason')
                    ->label('Cancellation reason')
                    ->required()
                    ->maxLength(1000),
            ])
            ->action(function (Booking $record, array $data): void {
                app(BookingService::class)->cancelBooking(
                    bookingId: $record->id,
                    tenantId: Filament::getTenant()?->id ?? auth()->user()->tenant_id,
                    actorUserId: auth()->id(),
                    reason: $data['reason'],
                );

                Notification::make()
                    ->title('Booking cancelled')
                    ->success()
                    ->send();
            });
    }

    public static function rescheduleAction(): Action
    {
        return Action::make('reschedule')
            ->label('Reschedule')
            ->icon('heroicon-o-clock')
            ->color('warning')
            ->visible(fn (Booking $record): bool => auth()->user()?->role === UserRole::BusinessAdmin
                && auth()->user()?->tenant_id === $record->tenant_id
                && ! in_array($record->status, ['cancelled', 'completed'], true))
            ->schema([
                DatePicker::make('date')
                    ->label('Date')
                    ->required(),
                TimePicker::make('start_time')
                    ->label('Start time')
                    ->seconds(false)
                    ->required(),
                TimePicker::make('end_time')
                    ->label('End time')
                    ->seconds(false)
                    ->required(),
                Textarea::make('reason')
                    ->label('Reason')
                    ->maxLength(1000),
            ])
            ->action(function (Booking $record, array $data): void {
                app(BookingService::class)->rescheduleBooking(
                    bookingId: $record->id,
                    tenantId: Filament::getTenant()?->id ?? auth()->user()->tenant_id,
                    actorUserId: auth()->id(),
                    date: Carbon::parse($data['date'])->toDateString(),
                    startTime: Carbon::parse($data['start_time'])->format('H:i'),
                    endTime: Carbon::parse($data['end_time'])->format('H:i'),
                    reason: $data['reason'] ?? null,
                );

                Notification::make()
                    ->title('Booking rescheduled')
                    ->success()
                    ->send();
            });
    }
}
