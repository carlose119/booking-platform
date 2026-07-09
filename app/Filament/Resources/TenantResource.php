<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenantResource\Pages;
use App\Models\Tenant;
use App\Support\Currency;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->dehydrateStateUsing(fn (string $state) => strtolower($state)),

                Forms\Components\Select::make('default_currency')
                    ->label('Default Currency')
                    ->options(self::defaultCurrencyOptions())
                    ->default(Currency::default())
                    ->rules(self::defaultCurrencyRules())
                    ->required(),

                Section::make('Payment Configuration')
                    ->schema([
                        Forms\Components\Select::make('payment_policy')
                            ->options([
                                'nopayment' => 'No Payment Required',
                                '100upfront' => '100% Upfront',
                                'fraction' => 'Deposit (Fraction)',
                            ])
                            ->default('nopayment')
                            ->required()
                            ->reactive()
                            ->live(onBlur: true),

                        Forms\Components\TextInput::make('deposit_percentage')
                            ->label('Deposit Percentage')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->suffix('%')
                            ->visible(fn ($get) => $get('payment_policy') === 'fraction')
                            ->required(fn ($get) => $get('payment_policy') === 'fraction'),

                        Forms\Components\TextInput::make('refund_window_hours')
                            ->label('Refund Window (Hours)')
                            ->numeric()
                            ->default(24)
                            ->minValue(0)
                            ->required(),

                        Forms\Components\Select::make('payment_account_mode')
                            ->label('Payment Account Mode')
                            ->options(self::paymentAccountModeOptions())
                            ->default(Tenant::PAYMENT_ACCOUNT_DIRECT)
                            ->required()
                            ->reactive()
                            ->live(onBlur: true),

                        Forms\Components\TextInput::make('stripe_api_key')
                            ->label('Stripe API Key')
                            ->password()
                            ->revealable()
                            ->nullable()
                            ->visible(fn ($get) => $get('payment_account_mode') !== Tenant::PAYMENT_ACCOUNT_CONNECT)
                            ->rules(fn ($get) => self::directStripeCredentialRules($get('payment_account_mode'), $get('payment_policy')))
                            ->hint('sk_test_... or sk_live_...'),

                        Forms\Components\TextInput::make('stripe_webhook_secret')
                            ->label('Stripe Webhook Secret')
                            ->password()
                            ->revealable()
                            ->nullable()
                            ->visible(fn ($get) => $get('payment_account_mode') !== Tenant::PAYMENT_ACCOUNT_CONNECT)
                            ->hint('whsec_...'),

                        Forms\Components\TextInput::make('stripe_connected_account_id')
                            ->label('Connected Account ID')
                            ->disabled()
                            ->dehydrated(false)
                            ->nullable()
                            ->visible(fn ($get) => $get('payment_account_mode') === Tenant::PAYMENT_ACCOUNT_CONNECT)
                            ->hint('acct_...'),

                        Forms\Components\TextInput::make('stripe_connect_onboarding_status')
                            ->label('Connect Onboarding Status')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn ($get) => $get('payment_account_mode') === Tenant::PAYMENT_ACCOUNT_CONNECT),

                        Forms\Components\Toggle::make('stripe_connect_charges_enabled')
                            ->label('Charges Enabled')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn ($get) => $get('payment_account_mode') === Tenant::PAYMENT_ACCOUNT_CONNECT),

                        Forms\Components\Toggle::make('stripe_connect_payouts_enabled')
                            ->label('Payouts Enabled')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn ($get) => $get('payment_account_mode') === Tenant::PAYMENT_ACCOUNT_CONNECT),
                    ])
                    ->columns(2),

                Section::make('Notification Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('twilio_sid')
                            ->label('Twilio SID')
                            ->nullable()
                            ->hint('AC...'),
                        Forms\Components\TextInput::make('twilio_auth_token')
                            ->label('Twilio Auth Token')
                            ->password()
                            ->revealable()
                            ->nullable(),
                        Forms\Components\TextInput::make('twilio_phone_number')
                            ->label('Twilio Phone Number')
                            ->nullable()
                            ->hint('+1 234 567 8900'),
                        Forms\Components\TextInput::make('mailgun_domain')
                            ->label('Mailgun Domain')
                            ->nullable()
                            ->hint('mg.example.com'),
                        Forms\Components\TextInput::make('mailgun_secret')
                            ->label('Mailgun Secret')
                            ->password()
                            ->revealable()
                            ->nullable(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),
                Tables\Columns\TextColumn::make('default_currency')
                    ->label('Default Currency')
                    ->formatStateUsing(fn ($state) => self::formatDefaultCurrency($state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_account_mode')
                    ->label('Payment Mode')
                    ->formatStateUsing(fn (?string $state) => self::paymentAccountModeOptions()[$state ?? Tenant::PAYMENT_ACCOUNT_DIRECT])
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('stripe_connect_onboarding_status')
                    ->label('Connect Status')
                    ->formatStateUsing(fn (?string $state, Tenant $record) => self::connectStatusLabel($record))
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }

    public static function defaultCurrencyOptions(): array
    {
        return Currency::options();
    }

    public static function defaultCurrencyRules(): array
    {
        return ['in:'.implode(',', array_keys(self::defaultCurrencyOptions()))];
    }

    public static function paymentAccountModeOptions(): array
    {
        return [
            Tenant::PAYMENT_ACCOUNT_DIRECT => 'Direct API Keys',
            Tenant::PAYMENT_ACCOUNT_CONNECT => 'Stripe Connect',
        ];
    }

    public static function directStripeCredentialRules(?string $paymentAccountMode, ?string $paymentPolicy): array
    {
        return $paymentAccountMode === Tenant::PAYMENT_ACCOUNT_CONNECT || $paymentPolicy === 'nopayment'
            ? ['nullable']
            : ['required'];
    }

    public static function readOnlyStripeConnectFields(): array
    {
        return [
            ...Tenant::sensitiveStripeConnectFields(),
        ];
    }

    public static function connectStatusLabel(Tenant $tenant): string
    {
        if (! $tenant->usesStripeConnect()) {
            return 'Direct mode';
        }

        if ($tenant->hasActiveConnectCharges()) {
            return 'Ready for charges';
        }

        return 'Onboarding incomplete';
    }

    public static function formatDefaultCurrency(?string $currency): string
    {
        $currency = Currency::normalize($currency);

        return self::defaultCurrencyOptions()[$currency] ?? self::defaultCurrencyOptions()[Currency::default()];
    }
}
