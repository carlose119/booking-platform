<?php

namespace Tests\Unit;

use App\Jobs\SendBookingNotification;
use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BookingConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Service $service;
    protected User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Salon',
            'slug' => 'test-salon',
            'payment_policy' => 'nopayment',
        ]);

        $this->service = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Haircut',
            'price_cents' => 5000,
            'duration_minutes' => 60,
        ]);

        $this->employee = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Employee One',
            'email' => 'employee@example.com',
            'password' => 'password',
            'role' => 'employee',
        ]);
    }

    public function test_booking_confirmation_dispatches_notification_job(): void
    {
        Queue::fake();

        $hold = BookingHold::create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'session_id' => 'test-session',
            'expires_at' => now()->addMinutes(10),
        ]);

        $bookingService = app(BookingService::class);
        $booking = $bookingService->confirmBooking(
            holdId: $hold->id,
            tenantId: $this->tenant->id,
            clientName: 'John Doe',
            clientEmail: 'john@example.com',
            clientPhone: '+15551234567',
        );

        Queue::assertPushed(SendBookingNotification::class, function ($job) use ($booking) {
            return $job->booking->id === $booking->id
                && $job->event === 'confirmed';
        });
    }

    public function test_payment_required_booking_does_not_dispatch_notification(): void
    {
        Queue::fake();

        $this->tenant->update(['payment_policy' => '100upfront']);

        $hold = BookingHold::create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'session_id' => 'test-session',
            'expires_at' => now()->addMinutes(10),
        ]);

        $bookingService = app(BookingService::class);
        $booking = $bookingService->confirmBooking(
            holdId: $hold->id,
            tenantId: $this->tenant->id,
            clientName: 'John Doe',
            clientEmail: 'john@example.com',
            clientPhone: '+15551234567',
        );

        Queue::assertNotPushed(SendBookingNotification::class);
        $this->assertEquals('pending', $booking->status);
    }

    public function test_booking_hold_is_deleted_after_confirmation(): void
    {
        Queue::fake();

        $hold = BookingHold::create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'session_id' => 'test-session',
            'expires_at' => now()->addMinutes(10),
        ]);

        $holdId = $hold->id;

        $bookingService = app(BookingService::class);
        $bookingService->confirmBooking(
            holdId: $holdId,
            tenantId: $this->tenant->id,
            clientName: 'John Doe',
            clientEmail: 'john@example.com',
            clientPhone: '+15551234567',
        );

        $this->assertDatabaseMissing('booking_holds', ['id' => $holdId]);
    }
}
