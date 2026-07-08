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
    protected $fillable = [
        'name',
        'slug',
        'default_currency',
        'payment_policy',
        'deposit_percentage',
        'refund_window_hours',
        'stripe_api_key',
        'stripe_webhook_secret',
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
        ];
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
