<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Tenant $otherTenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->tenant('tenant-one');
        $this->otherTenant = $this->tenant('tenant-two');
        $this->admin = $this->user($this->tenant, UserRole::BusinessAdmin, 'Admin User');
    }

    public function test_user_resource_is_registered_for_business_admins_and_scoped_to_active_tenant(): void
    {
        $ownEmployee = $this->user($this->tenant, UserRole::Employee, 'Own Employee');
        $otherEmployee = $this->user($this->otherTenant, UserRole::Employee, 'Other Employee');

        $this->actingAsTenantUser($this->admin);

        $this->assertContains(UserResource::class, Filament::getPanel('tenant')->getResources());

        Livewire::test(ListUsers::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$this->admin, $ownEmployee])
            ->assertCanNotSeeTableRecords([$otherEmployee]);
    }

    public function test_employee_cannot_access_tenant_user_management(): void
    {
        $employee = $this->user($this->tenant, UserRole::Employee, 'Employee User');
        $target = $this->user($this->tenant, UserRole::Client, 'Client User');

        $this->actingAsTenantUser($employee);

        $this->assertFalse(UserResource::shouldRegisterNavigation());

        Livewire::test(ListUsers::class)
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_business_admin_creates_only_allowed_roles_in_active_tenant(): void
    {
        $this->actingAsTenantUser($this->admin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'tenant_id' => $this->otherTenant->id,
                'name' => 'New Employee',
                'email' => 'new-employee@example.com',
                'role' => UserRole::Employee->value,
                'password' => 'secret-password',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'tenant_id' => $this->tenant->id,
            'email' => 'new-employee@example.com',
            'role' => UserRole::Employee->value,
        ]);
        $this->assertDatabaseMissing('users', [
            'tenant_id' => $this->otherTenant->id,
            'email' => 'new-employee@example.com',
        ]);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Elevated User',
                'email' => 'elevated@example.com',
                'role' => UserRole::BusinessAdmin->value,
                'password' => 'secret-password',
            ])
            ->call('create')
            ->assertHasFormErrors(['role']);

        $this->assertDatabaseMissing('users', ['email' => 'elevated@example.com']);
    }

    public function test_edit_rejects_elevated_roles_and_cross_tenant_services(): void
    {
        $employee = $this->user($this->tenant, UserRole::Employee, 'Employee User');
        $ownService = $this->service($this->tenant, 'Haircut');
        $otherService = $this->service($this->otherTenant, 'Massage');
        $this->actingAsTenantUser($this->admin);

        $this->assertSame([
            UserRole::Employee->value => 'Employee',
            UserRole::Client->value => 'Client',
        ], UserResource::assignableRoleOptions());
        $this->assertSame([$ownService->id], UserResource::tenantServiceIds()->all());

        Livewire::test(EditUser::class, ['record' => $employee->getKey()])
            ->fillForm([
                'name' => 'Employee User',
                'email' => $employee->email,
                'services' => [$ownService->id],
            ])
            ->set('data.role', UserRole::SuperAdmin->value)
            ->call('save')
            ->assertHasFormErrors(['role']);

        Livewire::test(EditUser::class, ['record' => $employee->getKey()])
            ->fillForm([
                'name' => 'Employee User',
                'email' => $employee->email,
                'role' => UserRole::Employee->value,
                'services' => [$otherService->id],
            ])
            ->call('save')
            ->assertHasFormErrors(['services.0']);

        $this->assertSame(UserRole::Employee, $employee->refresh()->role);
        $this->assertDatabaseMissing('employee_services', [
            'employee_id' => $employee->id,
            'service_id' => $otherService->id,
        ]);
    }

    public function test_business_admin_cannot_edit_or_delete_self_or_other_business_admins(): void
    {
        $otherAdmin = $this->user($this->tenant, UserRole::BusinessAdmin, 'Other Admin');

        $this->actingAsTenantUser($this->admin);

        Livewire::test(EditUser::class, ['record' => $this->admin->getKey()])
            ->assertForbidden();

        Livewire::test(EditUser::class, ['record' => $otherAdmin->getKey()])
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
        $this->assertDatabaseHas('users', ['id' => $otherAdmin->id]);
    }

    public function test_business_admin_can_update_and_delete_employee_and_client_users(): void
    {
        $employee = $this->user($this->tenant, UserRole::Employee, 'Employee User');
        $client = $this->user($this->tenant, UserRole::Client, 'Client User');
        $ownService = $this->service($this->tenant, 'Haircut');

        $this->actingAsTenantUser($this->admin);

        Livewire::test(EditUser::class, ['record' => $employee->getKey()])
            ->fillForm([
                'name' => 'Updated Employee',
                'email' => $employee->email,
                'role' => UserRole::Client->value,
                'services' => [$ownService->id],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $employee->refresh();

        $this->assertSame('Updated Employee', $employee->name);
        $this->assertSame(UserRole::Client, $employee->role);

        Livewire::test(EditUser::class, ['record' => $client->getKey()])
            ->callAction(DeleteAction::class)
            ->assertNotified()
            ->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $client->id]);
    }

    private function actingAsTenantUser(User $user): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('tenant'));
        Filament::setTenant($this->tenant);
    }

    private function tenant(string $slug): Tenant
    {
        return Tenant::create([
            'name' => str($slug)->headline()->toString(),
            'slug' => $slug,
        ]);
    }

    private function user(Tenant $tenant, UserRole $role, string $name): User
    {
        return User::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => $role,
        ]);
    }

    private function service(Tenant $tenant, string $name): Service
    {
        return Service::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'price_cents' => 3000,
            'duration_minutes' => 30,
            'active' => true,
        ]);
    }
}
