<?php

namespace Tests\Feature;

use App\Livewire\BookingCalendar;
use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\EmployeeSchedule;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BookingCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function createBookableSlot(string $paymentPolicy = 'nopayment'): array
    {
        $date = Carbon::now()->addWeek()->startOfWeek()->addDays(3);

        $tenant = Tenant::create([
            'name' => 'Checkout Salon',
            'slug' => fake()->unique()->slug(),
            'payment_policy' => $paymentPolicy,
        ]);

        $employee = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Checkout Pro',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);

        $service = Service::create([
            'tenant_id' => $tenant->id,
            'name' => 'Signature Massage',
            'price_cents' => 8000,
            'duration_minutes' => 60,
            'active' => true,
        ]);

        $service->employees()->attach($employee->id);

        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'day_of_week' => $date->dayOfWeekIso,
            'start_time' => '10:00',
            'end_time' => '13:00',
        ]);

        return [$tenant, $employee, $service, $date];
    }

    // ─── 5.7: Livewire component renders ───────────────────────────────────

    public function test_component_renders_and_shows_services(): void
    {
        $tenant = Tenant::create(['name' => 'Render Salon', 'slug' => 'render-salon']);

        Service::create([
            'tenant_id' => $tenant->id,
            'name' => 'Facial',
            'price_cents' => 4500,
            'duration_minutes' => 45,
            'active' => true,
        ]);

        Livewire::test(BookingCalendar::class, ['tenantId' => $tenant->id])
            ->assertSee('Book an Appointment')
            ->assertSee('Facial');
    }

    public function test_component_shows_available_slots_when_service_and_date_selected(): void
    {
        $futureThursday = Carbon::now()->addWeek()->startOfWeek()->addDays(3);

        $tenant = Tenant::create(['name' => 'Slot Salon', 'slug' => 'slot-salon']);

        $employee = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Lisa Park',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);

        $service = Service::create([
            'tenant_id' => $tenant->id,
            'name' => 'Pedicure',
            'price_cents' => 3500,
            'duration_minutes' => 60,
            'active' => true,
        ]);

        $service->employees()->attach($employee->id);

        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'day_of_week' => $futureThursday->dayOfWeekIso,
            'start_time' => '10:00',
            'end_time' => '13:00',
        ]);

        Livewire::test(BookingCalendar::class, ['tenantId' => $tenant->id])
            ->set('selectedService', $service->id)
            ->set('selectedDate', $futureThursday->toDateString())
            ->assertSee('Lisa Park')
            ->assertSee('10:00')
            ->assertSee('11:00')
            ->assertSee('12:00');
    }

    public function test_component_renders_mobile_first_slot_cards_with_loading_feedback(): void
    {
        $futureThursday = Carbon::now()->addWeek()->startOfWeek()->addDays(3);

        $tenant = Tenant::create(['name' => 'Mobile Slot Salon', 'slug' => 'mobile-slot-salon']);

        $employee = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Maya Stone',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);

        $service = Service::create([
            'tenant_id' => $tenant->id,
            'name' => 'Massage',
            'price_cents' => 8000,
            'duration_minutes' => 60,
            'active' => true,
        ]);

        $service->employees()->attach($employee->id);

        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'day_of_week' => $futureThursday->dayOfWeekIso,
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]);

        Livewire::test(BookingCalendar::class, ['tenantId' => $tenant->id])
            ->set('selectedService', $service->id)
            ->set('selectedDate', $futureThursday->toDateString())
            ->assertSee('Step 1 of')
            ->assertSee('Available times with Maya Stone')
            ->assertSee('Choose 10:00')
            ->assertSee('Loading times');
    }

    public function test_component_shows_no_slots_message_when_no_schedule(): void
    {
        $futureFriday = Carbon::now()->addWeek()->startOfWeek()->addDays(4);

        $tenant = Tenant::create(['name' => 'Empty Salon', 'slug' => 'empty-salon']);

        $employee = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'No Schedule Employee',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);

        $service = Service::create([
            'tenant_id' => $tenant->id,
            'name' => 'Waxing',
            'price_cents' => 6000,
            'duration_minutes' => 30,
            'active' => true,
        ]);

        $service->employees()->attach($employee->id);

        Livewire::test(BookingCalendar::class, ['tenantId' => $tenant->id])
            ->set('selectedService', $service->id)
            ->set('selectedDate', $futureFriday->toDateString())

            ->assertSee('No time slots available')
            ->assertSee('Try another date or service')
            ->assertSee('No booking has been created yet');

        $this->assertDatabaseCount('booking_holds', 0);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_component_shows_booked_slot_as_unavailable(): void
    {
        $futureMonday = Carbon::now()->addWeek()->startOfWeek();

        $tenant = Tenant::create(['name' => 'Booked Salon', 'slug' => 'booked-salon']);

        $employee = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Amy Chen',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);

        $service = Service::create([
            'tenant_id' => $tenant->id,
            'name' => 'Therapy',
            'price_cents' => 7000,
            'duration_minutes' => 60,
            'active' => true,
        ]);

        $service->employees()->attach($employee->id);

        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'day_of_week' => $futureMonday->dayOfWeekIso,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        Booking::create([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'client_name' => 'Client Y',
            'date' => $futureMonday->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'confirmed',
        ]);

        Livewire::test(BookingCalendar::class, ['tenantId' => $tenant->id])
            ->set('selectedService', $service->id)
            ->set('selectedDate', $futureMonday->toDateString())
            ->assertSee('Booked');
    }

    public function test_component_shows_active_hold_slot_as_unavailable(): void
    {
        $futureMonday = Carbon::now()->addWeek()->startOfWeek();

        $tenant = Tenant::create(['name' => 'Held Salon', 'slug' => 'held-salon']);

        $employee = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Nina Hold',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);

        $service = Service::create([
            'tenant_id' => $tenant->id,
            'name' => 'Consultation',
            'price_cents' => 5000,
            'duration_minutes' => 60,
            'active' => true,
        ]);

        $service->employees()->attach($employee->id);

        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'day_of_week' => $futureMonday->dayOfWeekIso,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        BookingHold::create([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'date' => $futureMonday->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'session_id' => 'livewire-hold-conflict-test',
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        Livewire::test(BookingCalendar::class, ['tenantId' => $tenant->id])
            ->set('selectedService', $service->id)
            ->set('selectedDate', $futureMonday->toDateString())
            ->assertSee('Temporarily held')
            ->assertSee('10:00');
    }

    // ─── 5.6 continued: Tenant isolation via Livewire ──────────────────────

    public function test_component_tenant_isolation_no_cross_tenant_services(): void
    {
        $futureMonday = Carbon::now()->addWeek()->startOfWeek();

        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $employeeA = User::create([
            'tenant_id' => $tenantA->id,
            'name' => 'Employee A',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);
        $serviceA = Service::create([
            'tenant_id' => $tenantA->id,
            'name' => 'Service A',
            'price_cents' => 5000,
            'duration_minutes' => 60,
            'active' => true,
        ]);
        $serviceA->employees()->attach($employeeA->id);
        EmployeeSchedule::create([
            'employee_id' => $employeeA->id,
            'day_of_week' => $futureMonday->dayOfWeekIso,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b']);
        Service::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Service B',
            'price_cents' => 6000,
            'duration_minutes' => 60,
            'active' => true,
        ]);

        Livewire::test(BookingCalendar::class, ['tenantId' => $tenantA->id])
            ->assertSee('Service A')
            ->assertDontSee('Service B');
    }

    // ─── 6.8: Livewire selectSlot + submitGuestForm ────────────────────────

    public function test_select_slot_creates_hold_and_moves_to_step_2(): void
    {
        $futureThursday = Carbon::now()->addWeek()->startOfWeek()->addDays(3);

        $tenant = Tenant::create(['name' => 'Flow Salon', 'slug' => 'flow-salon']);
        $employee = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Lisa Park',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);
        $service = Service::create([
            'tenant_id' => $tenant->id,
            'name' => 'Pedicure',
            'price_cents' => 3500,
            'duration_minutes' => 60,
            'active' => true,
        ]);
        $service->employees()->attach($employee->id);

        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'day_of_week' => $futureThursday->dayOfWeekIso,
            'start_time' => '10:00',
            'end_time' => '13:00',
        ]);

        Livewire::test(BookingCalendar::class, ['tenantId' => $tenant->id])
            ->set('selectedService', $service->id)
            ->set('selectedDate', $futureThursday->toDateString())
            ->call('selectSlot', $employee->id, '10:00', '11:00')
            ->assertSet('currentStep', 2)
            ->assertSet('holdId', fn ($id) => $id !== null)
            ->assertSee('Your Details');

        $this->assertDatabaseHas('booking_holds', [
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
        ]);
    }

    public function test_guest_form_renders_touch_friendly_help_text_and_loading_states(): void
    {
        [$tenant, $employee, $service, $date] = $this->createBookableSlot();

        Livewire::test(BookingCalendar::class, ['tenantId' => $tenant->id])
            ->set('selectedService', $service->id)
            ->set('selectedDate', $date->toDateString())
            ->call('selectSlot', $employee->id, '10:00', '11:00')
            ->assertSee('Your Details')
            ->assertSee('We will use these details to confirm your appointment.')
            ->assertSee('Full name')
            ->assertSee('Use the name you want on the booking.')
            ->assertSee('Where should we send booking updates?')
            ->assertSee('Email only')
            ->assertSee('Confirming...')
            ->assertSee('Releasing hold...');
    }

    public function test_guest_validation_errors_stay_near_fields_without_creating_records(): void
    {
        [$tenant, $employee, $service, $date] = $this->createBookableSlot();

        Livewire::test(BookingCalendar::class, ['tenantId' => $tenant->id])
            ->set('selectedService', $service->id)
            ->set('selectedDate', $date->toDateString())
            ->call('selectSlot', $employee->id, '10:00', '11:00')
            ->set('guestEmail', 'not-an-email')
            ->call('submitGuestForm')
            ->assertHasErrors(['guestName', 'guestEmail', 'guestPhone'])
            ->assertSee('Please enter your full name so the business knows who is coming.')
            ->assertSee('Please enter a valid email address for booking updates.')
            ->assertSee('Please enter a phone number in case the business needs to reach you.');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_submit_guest_form_creates_booking_and_moves_to_step_3(): void
    {
        $futureThursday = Carbon::now()->addWeek()->startOfWeek()->addDays(3);

        $tenant = Tenant::create([
            'name' => 'Confirm Salon',
            'slug' => 'confirm-salon',
            'payment_policy' => 'nopayment',
        ]);
        $employee = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Amy Chen',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);
        $service = Service::create([
            'tenant_id' => $tenant->id,
            'name' => 'Therapy',
            'price_cents' => 7000,
            'duration_minutes' => 60,
            'active' => true,
        ]);
        $service->employees()->attach($employee->id);

        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'day_of_week' => $futureThursday->dayOfWeekIso,
            'start_time' => '10:00',
            'end_time' => '13:00',
        ]);

        Livewire::test(BookingCalendar::class, ['tenantId' => $tenant->id])
            ->set('selectedService', $service->id)
            ->set('selectedDate', $futureThursday->toDateString())
            ->call('selectSlot', $employee->id, '10:00', '11:00')
            ->set('guestName', 'John Doe')
            ->set('guestEmail', 'john@example.com')
            ->set('guestPhone', '+1 555 123 456')
            ->call('submitGuestForm')
            // nopayment goes to step 4 (confirmation)
            ->assertSet('currentStep', 4)
            ->assertSee('Booking Confirmed!')
            ->assertSee('john@example.com');

        $this->assertDatabaseHas('bookings', [
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'client_name' => 'John Doe',
            'status' => 'confirmed',
        ]);

        $this->assertDatabaseMissing('booking_holds', [
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
        ]);
    }

    public function test_hold_expiry_blocks_confirmation(): void
    {
        $futureThursday = Carbon::now()->addWeek()->startOfWeek()->addDays(3);

        $tenant = Tenant::create(['name' => 'Expiry Salon', 'slug' => 'expiry-salon']);
        $employee = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Bob Lee',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);
        $service = Service::create([
            'tenant_id' => $tenant->id,
            'name' => 'Shave',
            'price_cents' => 3000,
            'duration_minutes' => 30,
            'active' => true,
        ]);
        $service->employees()->attach($employee->id);

        // Create an already-expired hold
        $hold = BookingHold::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
            'date' => $futureThursday->toDateString(),
            'start_time' => '10:00',
            'end_time' => '10:30',
            'session_id' => 'test-expired',
            'expires_at' => Carbon::now()->subMinutes(5),
        ]);

        Livewire::test(BookingCalendar::class, ['tenantId' => $tenant->id])
            ->set('holdId', $hold->id)
            ->set('guestName', 'John Doe')
            ->set('guestEmail', 'john@example.com')
            ->set('guestPhone', '+1 555 123 456')
            ->call('submitGuestForm')
            ->assertSet('currentStep', 1)
            ->assertSee('Your slot expired before we could confirm it.')
            ->assertSee('Please choose a new available time to continue.');

        $this->assertDatabaseMissing('bookings', [
            'tenant_id' => $tenant->id,
            'client_name' => 'John Doe',
        ]);
    }

    public function test_confirmation_summary_shows_service_time_contact_and_next_steps(): void
    {
        [$tenant, $employee, $service, $date] = $this->createBookableSlot();

        Livewire::test(BookingCalendar::class, ['tenantId' => $tenant->id])
            ->set('selectedService', $service->id)
            ->set('selectedDate', $date->toDateString())
            ->call('selectSlot', $employee->id, '10:00', '11:00')
            ->set('guestName', 'Jane Client')
            ->set('guestEmail', 'jane@example.com')
            ->set('guestPhone', '+1 555 777 888')
            ->set('guestNotificationChannel', 'both')
            ->call('submitGuestForm')
            ->assertSee('Booking Confirmed!')
            ->assertSee('Signature Massage')
            ->assertSee($date->format('M j, Y'))
            ->assertSee('10:00 – 11:00')
            ->assertSee('Jane Client')
            ->assertSee('jane@example.com')
            ->assertSee('You will receive booking updates by email and SMS.');
    }

    public function test_cancel_booking_resets_to_step_1(): void
    {
        $tenant = Tenant::create(['name' => 'Cancel Salon', 'slug' => 'cancel-salon']);
        $employee = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Sara Kim',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);
        $service = Service::create([
            'tenant_id' => $tenant->id,
            'name' => 'Facial',
            'price_cents' => 4500,
            'duration_minutes' => 45,
            'active' => true,
        ]);
        $service->employees()->attach($employee->id);

        $hold = BookingHold::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
            'date' => '2026-07-10',
            'start_time' => '10:00',
            'end_time' => '10:45',
            'session_id' => 'cancel-test',
            'expires_at' => Carbon::now()->addMinutes(5),
        ]);

        Livewire::test(BookingCalendar::class, ['tenantId' => $tenant->id])
            ->set('holdId', $hold->id)
            ->set('currentStep', 2)
            ->call('cancelBooking')
            ->assertSet('currentStep', 1)
            ->assertSet('holdId', null);

        // Hold should be expired (expires_at set to now)
        $holdRefreshed = BookingHold::find($hold->id);
        $this->assertTrue($holdRefreshed->expires_at->lte(now()));
    }
}
