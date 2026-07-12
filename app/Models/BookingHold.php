<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingHold extends Model
{
    public const ACTIVE_SLOT_KEY = 'active';

    protected $fillable = [
        'tenant_id',
        'service_id',
        'employee_id',
        'date',
        'start_time',
        'end_time',
        'client_name',
        'client_email',
        'client_phone',
        'session_id',
        'expires_at',
        'active_slot_key',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
            'expires_at' => 'datetime',
        ];
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

    /**
     * Scope: filter active holds (not expired).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    public function scopeParticipatingInActiveSlotUniqueness(Builder $query): Builder
    {
        return $query->where('active_slot_key', self::ACTIVE_SLOT_KEY);
    }
}
