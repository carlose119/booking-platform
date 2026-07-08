<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::SuperAdmin;
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $user->role === UserRole::SuperAdmin;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::SuperAdmin;
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $user->role === UserRole::SuperAdmin;
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->role === UserRole::SuperAdmin;
    }

    public function restore(User $user, Tenant $tenant): bool
    {
        return $user->role === UserRole::SuperAdmin;
    }

    public function forceDelete(User $user, Tenant $tenant): bool
    {
        return $user->role === UserRole::SuperAdmin;
    }
}
