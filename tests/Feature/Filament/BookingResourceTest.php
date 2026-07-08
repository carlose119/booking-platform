<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\BookingResource;
use App\Filament\Resources\BookingResource\Pages\ListBookings;
use App\Filament\Resources\BookingResource\Pages\ViewBooking;
use App\Filament\Widgets\QuickActionsWidget;
use App\Jobs\SendBookingNotification;
use App\Models\Booking;
use App\Models\EmployeeSchedule;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class BookingResourceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Tenant $otherTenant;

    private User $admin;

    private User $employee;

    private Service $service;

    private User $bookingEmployee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Test Salon', 'slug' => 'test-salon']);
        $this->otherTenant = Tenant::create(['name' => 'Other Salon', 'slug' => 'other-salon']);

        $this->admin = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'business_admin',
        ]);

        $this->employee = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Employee User',
            'email' => 'employee@example.com',
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);

        $this->service = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Haircut',
            'price_cents' => 3000,
            'duration_minutes' => 30,
            'active' => true,
        ]);

        $this->bookingEmployee = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Stylist',
            'email' => 'stylist@example.com',
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);

        $this->service->employees()->attach($this->bookingEmployee->id);
    }

    public function test_list_page_only_shows_bookings_for_the_active_tenant(): void
    {
        $ownBooking = $this->createBooking($this->tenant, clientName: 'Own Client');
        $otherBooking = $this->createBooking($this->otherTenant, clientName: 'Other Client');

        $this->actingAsTenantUser($this->admin);

        Livewire::test(ListBookings::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$ownBooking])
            ->assertCanNotSeeTableRecords([$otherBooking]);
    }

    public function test_business_admin_can_cancel_a_tenant_booking_with_a_reason(): void
    {
        Queue::fake();
        $booking = $this->createBooking($this->tenant, clientName: 'Cancelable Client');

        $this->actingAsTenantUser($this->admin);

        Livewire::test(ListBookings::class)
            ->assertActionVisible(TestAction::make('cancel')->table($booking))
            ->callAction(TestAction::make('cancel')->table($booking), data: [
                'reason' => 'Staff unavailable',
            ])
            ->assertHasNoFormErrors();

        $booking->refresh();

        $this->assertSame('cancelled', $booking->status);
        $this->assertSame('Staff unavailable', $booking->cancellation_reason);
        $this->assertSame($this->admin->id, $booking->cancelled_by_user_id);
        $this->assertNotNull($booking->cancelled_at);

        Queue::assertPushed(SendBookingNotification::class);
    }

    public function test_cancel_action_requires_a_non_empty_reason(): void
    {
        $booking = $this->createBooking($this->tenant, clientName: 'Validation Client');

        $this->actingAsTenantUser($this->admin);

        Livewire::test(ListBookings::class)
            ->callAction(TestAction::make('cancel')->table($booking), data: [
                'reason' => '',
            ])
            ->assertHasFormErrors(['reason' => 'required']);

        $this->assertSame('confirmed', $booking->refresh()->status);
    }

    public function test_employee_cannot_see_cancel_action(): void
    {
        $booking = $this->createBooking($this->tenant, clientName: 'Employee Hidden Client');

        $this->actingAsTenantUser($this->employee);

        Livewire::test(ListBookings::class)
            ->assertActionHidden(TestAction::make('cancel')->table($booking));
    }

    public function test_business_admin_can_reschedule_a_tenant_booking(): void
    {
        Queue::fake();
        $date = Carbon::now()->addWeek()->startOfWeek();
        $this->createSchedule($date);
        $booking = $this->createBooking($this->tenant, clientName: 'Reschedule Client', overrides: [
            'date' => $date->toDateString(),
            'start_time' => '10:00',
            'end_time' => '10:30',
            'payment_status' => 'paid',
        ]);

        $this->actingAsTenantUser($this->admin);

        Livewire::test(ListBookings::class)
            ->assertActionVisible(TestAction::make('reschedule')->table($booking))
            ->callAction(TestAction::make('reschedule')->table($booking), data: [
                'date' => $date->toDateString(),
                'start_time' => '11:00',
                'end_time' => '11:30',
                'reason' => 'Client requested a later time',
            ])
            ->assertHasNoFormErrors();

        $booking->refresh();

        $this->assertSame($date->toDateString(), $booking->date->toDateString());
        $this->assertSame('11:00', $booking->start_time->format('H:i'));
        $this->assertSame('11:30', $booking->end_time->format('H:i'));
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame($date->toDateString(), $booking->previous_date->toDateString());
        $this->assertSame('10:00', $booking->previous_start_time->format('H:i'));
        $this->assertSame('10:30', $booking->previous_end_time->format('H:i'));
        $this->assertSame($this->admin->id, $booking->rescheduled_by);
        $this->assertSame('Client requested a later time', $booking->reschedule_reason);
        Queue::assertPushed(SendBookingNotification::class, fn (SendBookingNotification $job) => $job->booking->id === $booking->id
            && $job->event === 'rescheduled'
            && $job->originalDate === $date->toDateString()
            && $job->originalTime === '10:00 - 10:30'
        );
    }

    public function test_reschedule_action_requires_date_and_times(): void
    {
        $booking = $this->createBooking($this->tenant, clientName: 'Validation Client');

        $this->actingAsTenantUser($this->admin);

        Livewire::test(ListBookings::class)
            ->callAction(TestAction::make('reschedule')->table($booking), data: [
                'date' => '',
                'start_time' => '',
                'end_time' => '',
                'reason' => 'Missing slot',
            ])
            ->assertHasFormErrors([
                'date' => 'required',
                'start_time' => 'required',
                'end_time' => 'required',
            ]);

        $this->assertSame('10:00', $booking->refresh()->start_time->format('H:i'));
    }

    public function test_reschedule_action_hidden_for_employee_and_cancelled_booking(): void
    {
        $booking = $this->createBooking($this->tenant, clientName: 'Hidden Client');
        $cancelledBooking = $this->createBooking($this->tenant, clientName: 'Cancelled Client', overrides: [
            'status' => 'cancelled',
        ]);

        $this->actingAsTenantUser($this->employee);

        Livewire::test(ListBookings::class)
            ->assertActionHidden(TestAction::make('reschedule')->table($booking));

        $this->actingAsTenantUser($this->admin);

        Livewire::test(ListBookings::class)
            ->assertActionHidden(TestAction::make('reschedule')->table($cancelledBooking));
    }

    public function test_view_page_shows_booking_details_for_active_tenant(): void
    {
        $booking = $this->createBooking($this->tenant, clientName: 'Detail Client');

        $this->actingAsTenantUser($this->admin);
        Livewire::test(ViewBooking::class, ['record' => $booking->getKey()])
            ->assertOk()
            ->assertSee('Detail Client')
            ->assertSee('Haircut')
            ->assertSee('confirmed')
            ->assertSee('unpaid');
    }

    public function test_quick_actions_widget_links_to_registered_booking_resource(): void
    {
        $this->actingAsTenantUser($this->admin);

        Livewire::test(QuickActionsWidget::class)
            ->assertSee(BookingResource::getUrl('index'), false);
    }

    private function actingAsTenantUser(User $user): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('tenant'));
        Filament::setTenant($this->tenant);
    }

    private function createBooking(Tenant $tenant, string $clientName, array $overrides = []): Booking
    {
        $service = $tenant->is($this->tenant)
            ? $this->service
            : Service::create([
                'tenant_id' => $tenant->id,
                'name' => 'Massage',
                'price_cents' => 5000,
                'duration_minutes' => 60,
                'active' => true,
            ]);

        $employee = $tenant->is($this->tenant)
            ? $this->bookingEmployee
            : User::create([
                'tenant_id' => $tenant->id,
                'name' => 'Other Stylist',
                'email' => 'other-stylist@example.com',
                'password' => bcrypt('password'),
                'role' => 'employee',
            ]);

        return Booking::create(array_merge([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'client_name' => $clientName,
            'client_email' => strtolower(str_replace(' ', '-', $clientName)).'@example.com',
            'client_phone' => '555-0100',
            'date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '10:30',
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
        ], $overrides));
    }

    private function createSchedule(Carbon $date): void
    {
        EmployeeSchedule::create([
            'employee_id' => $this->bookingEmployee->id,
            'day_of_week' => $date->dayOfWeekIso,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);
    }
}
