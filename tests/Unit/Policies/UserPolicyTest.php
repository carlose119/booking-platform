<?php

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_admin_can_manage_users_from_their_tenant(): void
    {
        $tenant = $this->tenant('tenant-one');
        $admin = $this->user($tenant, UserRole::BusinessAdmin);
        $employee = $this->user($tenant, UserRole::Employee);
        $client = $this->user($tenant, UserRole::Client);
        $policy = new UserPolicy;

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->create($admin));
        $this->assertTrue($policy->view($admin, $employee));
        $this->assertTrue($policy->update($admin, $employee));
        $this->assertTrue($policy->delete($admin, $client));
    }

    public function test_business_admin_cannot_update_or_delete_self_or_other_business_admins(): void
    {
        $tenant = $this->tenant('tenant-one');
        $admin = $this->user($tenant, UserRole::BusinessAdmin);
        $otherAdmin = $this->user($tenant, UserRole::BusinessAdmin);
        $policy = new UserPolicy;

        $this->assertFalse($policy->update($admin, $admin));
        $this->assertFalse($policy->delete($admin, $admin));
        $this->assertFalse($policy->update($admin, $otherAdmin));
        $this->assertFalse($policy->delete($admin, $otherAdmin));
    }

    public function test_business_admin_cannot_manage_users_from_another_tenant(): void
    {
        $tenant = $this->tenant('tenant-one');
        $otherTenant = $this->tenant('tenant-two');
        $admin = $this->user($tenant, UserRole::BusinessAdmin);
        $otherEmployee = $this->user($otherTenant, UserRole::Employee);
        $policy = new UserPolicy;

        $this->assertFalse($policy->view($admin, $otherEmployee));
        $this->assertFalse($policy->update($admin, $otherEmployee));
        $this->assertFalse($policy->delete($admin, $otherEmployee));
    }

    public function test_employee_and_client_cannot_use_tenant_user_management(): void
    {
        $tenant = $this->tenant('tenant-one');
        $employee = $this->user($tenant, UserRole::Employee);
        $client = $this->user($tenant, UserRole::Client);
        $target = $this->user($tenant, UserRole::Client);
        $policy = new UserPolicy;

        foreach ([$employee, $client] as $actor) {
            $this->assertFalse($policy->viewAny($actor));
            $this->assertFalse($policy->create($actor));
            $this->assertFalse($policy->view($actor, $target));
            $this->assertFalse($policy->update($actor, $target));
            $this->assertFalse($policy->delete($actor, $target));
        }
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
}
