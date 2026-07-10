<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\EmployeeSchedule;
use App\Models\User;

class EmployeeSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::BusinessAdmin, UserRole::Employee], strict: true);
    }

    public function view(User $user, EmployeeSchedule $employeeSchedule): bool
    {
        if ($user->role === UserRole::Employee) {
            return $employeeSchedule->employee_id === $user->id;
        }

        return $this->canManageTenantSchedule($user, $employeeSchedule);
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::BusinessAdmin;
    }

    public function update(User $user, EmployeeSchedule $employeeSchedule): bool
    {
        return $this->canManageTenantSchedule($user, $employeeSchedule);
    }

    public function delete(User $user, EmployeeSchedule $employeeSchedule): bool
    {
        return $this->canManageTenantSchedule($user, $employeeSchedule);
    }

    public function restore(User $user, EmployeeSchedule $employeeSchedule): bool
    {
        return false;
    }

    public function forceDelete(User $user, EmployeeSchedule $employeeSchedule): bool
    {
        return false;
    }

    private function canManageTenantSchedule(User $user, EmployeeSchedule $employeeSchedule): bool
    {
        return $user->role === UserRole::BusinessAdmin
            && $employeeSchedule->employee?->tenant_id === $user->tenant_id;
    }
}
