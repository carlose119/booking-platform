<?php

namespace App\Models;

use App\Support\Currency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    protected $fillable = [
        'tenant_id',
        'service_id',
        'employee_id',
        'client_id',
        'client_name',
        'client_email',
        'client_phone',
        'date',
        'start_time',
        'end_time',
        'previous_date',
        'previous_start_time',
        'previous_end_time',
        'rescheduled_by',
        'reschedule_reason',
        'status',
        'payment_status',
        'payment_amount_cents',
        'payment_currency',
        'stripe_payment_intent_id',
        'notification_channel',
        'notes',
        'cancelled_at',
        'cancellation_reason',
        'cancelled_by_user_id',
        'reminded_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
            'previous_date' => 'date',
            'previous_start_time' => 'datetime:H:i',
            'previous_end_time' => 'datetime:H:i',
            'cancelled_at' => 'datetime',
            'reminded_at' => 'datetime',
            'payment_amount_cents' => 'integer',
            'payment_currency' => 'string',
        ];
    }

    public function setPaymentCurrencyAttribute(?string $value): void
    {
        $this->attributes['payment_currency'] = $value === null ? null : Currency::normalize($value);
    }

    public function resolvedPaymentCurrency(): string
    {
        $snapshotCurrency = Currency::normalize($this->payment_currency);

        if ($this->payment_currency !== null && Currency::isSupported($snapshotCurrency)) {
            return $snapshotCurrency;
        }

        return $this->tenant?->currency() ?? Currency::default();
    }

    public function resolvedPaymentAmountCents(): ?int
    {
        if ($this->payment_amount_cents !== null) {
            return $this->payment_amount_cents;
        }

        return $this->service?->price_cents;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function rescheduledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rescheduled_by');
    }

    /**
     * Scope: filter bookings by date.
     */
    public function scopeOnDate(Builder $query, string $date): Builder
    {
        return $query->where('date', $date);
    }

    /**
     * Scope: filter bookings for a specific employee.
     */
    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope: filter active bookings (not cancelled).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', 'cancelled');
    }
}
