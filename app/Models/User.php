<?php

namespace App\Models;

use App\Enums\UserRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements HasTenants, FilamentUser
{
    use Notifiable;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'role',
        'password',
        'notification_channel',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    // --- Filament Contracts ---

    public function getTenants(Panel $panel): array|Collection
    {
        return collect([$this->tenant]);
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->tenant_id === $tenant->id;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'super-admin' => $this->role === UserRole::SuperAdmin,
            'tenant' => in_array($this->role, [UserRole::BusinessAdmin, UserRole::Employee]),
            default => false,
        };
    }

    // --- Relationships ---

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'employee_services', 'employee_id', 'service_id')
            ->withTimestamps();
    }

    public function schedules()
    {
        return $this->hasMany(EmployeeSchedule::class, 'employee_id');
    }

    public function employeeBookings()
    {
        return $this->hasMany(Booking::class, 'employee_id');
    }

    public function clientBookings()
    {
        return $this->hasMany(Booking::class, 'client_id');
    }
}
