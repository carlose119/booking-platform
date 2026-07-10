<?php

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\EmployeeSchedule;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\EmployeeSchedulePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeSchedulePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_admin_can_manage_schedules_for_employees_in_their_tenant(): void
    {
        $tenant = $this->tenant('tenant-one');
        $admin = $this->user($tenant, UserRole::BusinessAdmin);
        $employee = $this->user($tenant, UserRole::Employee);
        $schedule = $this->schedule($employee);
        $policy = new EmployeeSchedulePolicy;

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->create($admin));
        $this->assertTrue($policy->view($admin, $schedule));
        $this->assertTrue($policy->update($admin, $schedule));
        $this->assertTrue($policy->delete($admin, $schedule));
    }

    public function test_business_admin_cannot_manage_schedules_for_another_tenant(): void
    {
        $tenant = $this->tenant('tenant-one');
        $otherTenant = $this->tenant('tenant-two');
        $admin = $this->user($tenant, UserRole::BusinessAdmin);
        $otherEmployee = $this->user($otherTenant, UserRole::Employee);
        $schedule = $this->schedule($otherEmployee);
        $policy = new EmployeeSchedulePolicy;

        $this->assertFalse($policy->view($admin, $schedule));
        $this->assertFalse($policy->update($admin, $schedule));
        $this->assertFalse($policy->delete($admin, $schedule));
    }

    public function test_employee_can_view_only_their_own_schedule_read_only(): void
    {
        $tenant = $this->tenant('tenant-one');
        $employee = $this->user($tenant, UserRole::Employee);
        $otherEmployee = $this->user($tenant, UserRole::Employee);
        $ownSchedule = $this->schedule($employee);
        $otherSchedule = $this->schedule($otherEmployee);
        $policy = new EmployeeSchedulePolicy;

        $this->assertTrue($policy->viewAny($employee));
        $this->assertTrue($policy->view($employee, $ownSchedule));
        $this->assertFalse($policy->view($employee, $otherSchedule));
        $this->assertFalse($policy->create($employee));
        $this->assertFalse($policy->update($employee, $ownSchedule));
        $this->assertFalse($policy->delete($employee, $ownSchedule));
    }

    public function test_client_cannot_access_employee_schedule_management(): void
    {
        $tenant = $this->tenant('tenant-one');
        $client = $this->user($tenant, UserRole::Client);
        $employee = $this->user($tenant, UserRole::Employee);
        $schedule = $this->schedule($employee);
        $policy = new EmployeeSchedulePolicy;

        $this->assertFalse($policy->viewAny($client));
        $this->assertFalse($policy->create($client));
        $this->assertFalse($policy->view($client, $schedule));
        $this->assertFalse($policy->update($client, $schedule));
        $this->assertFalse($policy->delete($client, $schedule));
    }

    private function tenant(string $slug): Tenant
    {
        return Tenant::create([
            'name' => str($slug)->headline()->toString(),
            'slug' => $slug,
        ]);
    }

    private function user(Tenant $tenant, UserRole $role): User
    {
        return User::create([
            'tenant_id' => $tenant->id,
            'name' => $role->label().' '.$tenant->slug,
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => $role,
        ]);
    }

    private function schedule(User $employee): EmployeeSchedule
    {
        return EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);
    }
}
