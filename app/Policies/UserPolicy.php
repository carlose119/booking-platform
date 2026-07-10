<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::BusinessAdmin;
    }

    public function view(User $user, User $model): bool
    {
        return $user->role === UserRole::BusinessAdmin && $user->tenant_id === $model->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::BusinessAdmin;
    }

    public function update(User $user, User $model): bool
    {
        return $this->canManageTenantUser($user, $model);
    }

    public function delete(User $user, User $model): bool
    {
        return $this->canManageTenantUser($user, $model);
    }

    public function restore(User $user, User $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }

    private function canManageTenantUser(User $user, User $model): bool
    {
        return $user->role === UserRole::BusinessAdmin
            && $user->tenant_id === $model->tenant_id
            && in_array($model->role, [UserRole::Employee, UserRole::Client], strict: true);
    }
}
