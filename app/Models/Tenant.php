<?php

namespace App\Models;

use App\Support\Currency;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model implements HasTenants
{
    public const PAYMENT_ACCOUNT_DIRECT = 'direct';

    public const PAYMENT_ACCOUNT_CONNECT = 'connect';

    protected $attributes = [
        'payment_account_mode' => self::PAYMENT_ACCOUNT_DIRECT,
    ];

    protected $fillable = [
        'name',
        'slug',
        'default_currency',
        'payment_policy',
        'deposit_percentage',
        'refund_window_hours',
        'stripe_api_key',
        'stripe_webhook_secret',
        'payment_account_mode',
        'stripe_connected_account_id',
        'stripe_connect_charges_enabled',
        'stripe_connect_payouts_enabled',
        'stripe_connect_onboarding_status',
        'stripe_connect_onboarded_at',
        'twilio_sid',
        'twilio_auth_token',
        'twilio_phone_number',
        'mailgun_domain',
        'mailgun_secret',
    ];

    protected function casts(): array
    {
        return [
            'stripe_api_key' => 'encrypted',
            'stripe_webhook_secret' => 'encrypted',
            'twilio_auth_token' => 'encrypted',
            'mailgun_secret' => 'encrypted',
            'default_currency' => 'string',
            'payment_account_mode' => 'string',
            'stripe_connect_charges_enabled' => 'boolean',
            'stripe_connect_payouts_enabled' => 'boolean',
            'stripe_connect_onboarded_at' => 'datetime',
        ];
    }

    public function setPaymentAccountModeAttribute(?string $value): void
    {
        $this->attributes['payment_account_mode'] = $value === self::PAYMENT_ACCOUNT_CONNECT
            ? self::PAYMENT_ACCOUNT_CONNECT
            : self::PAYMENT_ACCOUNT_DIRECT;
    }

    public function setDefaultCurrencyAttribute(?string $value): void
    {
        $this->attributes['default_currency'] = Currency::normalize($value);
    }

    public function currency(): string
    {
        $currency = Currency::normalize($this->default_currency);

        return Currency::isSupported($currency) ? $currency : Currency::default();
    }

    public function usesDirectStripe(): bool
    {
        return ($this->payment_account_mode ?? self::PAYMENT_ACCOUNT_DIRECT) === self::PAYMENT_ACCOUNT_DIRECT;
    }

    public function usesStripeConnect(): bool
    {
        return $this->payment_account_mode === self::PAYMENT_ACCOUNT_CONNECT;
    }

    public function hasDirectStripeCredentials(): bool
    {
        return filled($this->stripe_api_key);
    }

    public function hasActiveConnectCharges(): bool
    {
        return $this->usesStripeConnect()
            && filled($this->stripe_connected_account_id)
            && $this->stripe_connect_charges_enabled === true;
    }

    public function isPaymentAccountReady(): bool
    {
        if ($this->usesStripeConnect()) {
            return $this->hasActiveConnectCharges();
        }

        return $this->hasDirectStripeCredentials();
    }

    public function getTenants(Panel $panel): array|Collection
    {
        return collect([$this]);
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->id === $tenant->id;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function bookingHolds(): HasMany
    {
        return $this->hasMany(BookingHold::class);
    }
}
