<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::BusinessAdmin, UserRole::Employee]);
    }

    public function view(User $user, User $model): bool
    {
        if ($user->role === UserRole::BusinessAdmin) {
            return $user->tenant_id === $model->tenant_id;
        }

        if ($user->role === UserRole::Employee) {
            return $user->id === $model->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::BusinessAdmin;
    }

    public function update(User $user, User $model): bool
    {
        return $user->role === UserRole::BusinessAdmin && $user->tenant_id === $model->tenant_id;
    }

    public function delete(User $user, User $model): bool
    {
        return $user->role === UserRole::BusinessAdmin && $user->tenant_id === $model->tenant_id;
    }

    public function restore(User $user, User $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }
}
