<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Service extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'price_cents',
        'duration_minutes',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'duration_minutes' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'employee_services', 'service_id', 'employee_id')
            ->withTimestamps();
    }
}
