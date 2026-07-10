<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Resources\EmployeeScheduleResource;
use App\Filament\Resources\EmployeeScheduleResource\Pages\CreateSchedule;
use App\Filament\Resources\EmployeeScheduleResource\Pages\EditSchedule;
use App\Filament\Resources\EmployeeScheduleResource\Pages\ListSchedules;
use App\Models\EmployeeSchedule;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeeScheduleResourceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Tenant $otherTenant;

    private User $admin;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->tenant('tenant-one');
        $this->otherTenant = $this->tenant('tenant-two');
        $this->admin = $this->user($this->tenant, UserRole::BusinessAdmin, 'Admin User');
        $this->employee = $this->user($this->tenant, UserRole::Employee, 'Employee User');
    }

    public function test_business_admin_schedule_resource_is_registered_and_scoped_to_active_tenant(): void
    {
        $ownSchedule = $this->schedule($this->employee, dayOfWeek: 1);
        $otherEmployee = $this->user($this->otherTenant, UserRole::Employee, 'Other Employee');
        $otherSchedule = $this->schedule($otherEmployee, dayOfWeek: 2);

        $this->actingAsTenantUser($this->admin);

        $this->assertContains(EmployeeScheduleResource::class, Filament::getPanel('tenant')->getResources());
        $this->assertSame([$this->employee->id], EmployeeScheduleResource::activeTenantEmployeeIds()->all());

        Livewire::test(ListSchedules::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$ownSchedule])
            ->assertCanNotSeeTableRecords([$otherSchedule]);
    }

    public function test_business_admin_creates_valid_recurring_schedule_for_active_tenant_employee(): void
    {
        $otherEmployee = $this->user($this->otherTenant, UserRole::Employee, 'Other Employee');

        $this->actingAsTenantUser($this->admin);

        $this->assertSame([$this->employee->id], EmployeeScheduleResource::activeTenantEmployeeIds()->all());
        $this->assertNotContains($otherEmployee->id, EmployeeScheduleResource::activeTenantEmployeeIds()->all());

        Livewire::test(CreateSchedule::class)
            ->fillForm([
                'employee_id' => $this->employee->id,
                'day_of_week' => 0,
                'start_time' => '09:00',
                'end_time' => '17:00',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('employee_schedules', [
            'employee_id' => $this->employee->id,
            'day_of_week' => 0,
        ]);
    }

    public function test_business_admin_edits_same_tenant_schedule(): void
    {
        $schedule = $this->schedule($this->employee, dayOfWeek: 1);

        $this->actingAsTenantUser($this->admin);

        Livewire::test(EditSchedule::class, ['record' => $schedule->getKey()])
            ->fillForm([
                'employee_id' => $this->employee->id,
                'day_of_week' => 4,
                'start_time' => '10:00',
                'end_time' => '18:00',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('employee_schedules', [
            'id' => $schedule->id,
            'employee_id' => $this->employee->id,
            'day_of_week' => 4,
            'start_time' => '10:00:00',
            'end_time' => '18:00:00',
        ]);
    }

    public function test_business_admin_cannot_reassign_schedule_to_cross_tenant_employee_on_update(): void
    {
        $schedule = $this->schedule($this->employee, dayOfWeek: 1);
        $otherEmployee = $this->user($this->otherTenant, UserRole::Employee, 'Other Employee');

        $this->actingAsTenantUser($this->admin);

        Livewire::test(EditSchedule::class, ['record' => $schedule->getKey()])
            ->fillForm([
                'employee_id' => $otherEmployee->id,
                'day_of_week' => 4,
                'start_time' => '10:00',
                'end_time' => '18:00',
            ])
            ->call('save')
            ->assertHasFormErrors(['employee_id']);

        $this->assertDatabaseHas('employee_schedules', [
            'id' => $schedule->id,
            'employee_id' => $this->employee->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $this->assertDatabaseMissing('employee_schedules', [
            'id' => $schedule->id,
            'employee_id' => $otherEmployee->id,
        ]);
    }

    public function test_business_admin_deletes_same_tenant_schedule(): void
    {
        $schedule = $this->schedule($this->employee, dayOfWeek: 1);

        $this->actingAsTenantUser($this->admin);

        Livewire::test(EditSchedule::class, ['record' => $schedule->getKey()])
            ->callAction(DeleteAction::class)
            ->assertNotified()
            ->assertRedirect();

        $this->assertDatabaseMissing('employee_schedules', ['id' => $schedule->id]);
    }

    public function test_schedule_form_rejects_cross_tenant_employee_invalid_day_and_invalid_time_order(): void
    {
        $otherEmployee = $this->user($this->otherTenant, UserRole::Employee, 'Other Employee');

        $this->actingAsTenantUser($this->admin);

        Livewire::test(CreateSchedule::class)
            ->fillForm([
                'employee_id' => $otherEmployee->id,
                'day_of_week' => 7,
                'start_time' => '17:00',
                'end_time' => '09:00',
            ])
            ->call('create')
            ->assertHasFormErrors(['employee_id', 'day_of_week', 'end_time']);

        $this->assertDatabaseMissing('employee_schedules', [
            'employee_id' => $otherEmployee->id,
            'day_of_week' => 7,
        ]);
    }

    public function test_business_admin_cannot_directly_edit_or_delete_cross_tenant_schedule(): void
    {
        $otherEmployee = $this->user($this->otherTenant, UserRole::Employee, 'Other Employee');
        $otherSchedule = $this->schedule($otherEmployee, dayOfWeek: 3);

        $this->actingAsTenantUser($this->admin);

        try {
            Livewire::test(EditSchedule::class, ['record' => $otherSchedule->getKey()]);
            $this->fail('Cross-tenant schedules must not resolve through the tenant resource query.');
        } catch (ModelNotFoundException $exception) {
            $this->assertSame(EmployeeSchedule::class, $exception->getModel());
        }

        $this->assertDatabaseHas('employee_schedules', ['id' => $otherSchedule->id]);
    }

    public function test_employee_can_view_only_own_schedule_and_has_no_write_actions(): void
    {
        $ownSchedule = $this->schedule($this->employee, dayOfWeek: 1);
        $peer = $this->user($this->tenant, UserRole::Employee, 'Peer Employee');
        $peerSchedule = $this->schedule($peer, dayOfWeek: 2);

        $this->actingAsTenantUser($this->employee);

        Livewire::test(ListSchedules::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$ownSchedule])
            ->assertCanNotSeeTableRecords([$peerSchedule])
            ->assertActionHidden(TestAction::make('create'))
            ->assertActionHidden(TestAction::make('edit')->table($ownSchedule))
            ->assertActionHidden(TestAction::make('delete')->table($ownSchedule));

        Livewire::test(EditSchedule::class, ['record' => $ownSchedule->getKey()])
            ->assertForbidden();
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

    private function schedule(User $employee, int $dayOfWeek): EmployeeSchedule
    {
        return EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'day_of_week' => $dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);
    }
}
