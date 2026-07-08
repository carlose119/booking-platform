<?php

namespace Tests\Feature;

use App\Jobs\SendBookingNotification;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SendRemindersTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Salon',
            'slug' => 'test-salon',
        ]);

        $this->service = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Haircut',
            'price_cents' => 5000,
            'duration_minutes' => 60,
        ]);
    }

    public function test_command_sends_reminders_for_tomorrow_bookings(): void
    {
        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $this->service->id,
            'client_name' => 'John Doe',
            'client_email' => 'john@example.com',
            'client_phone' => '+15551234567',
            'date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'confirmed',
        ]);

        $this->assertNull($booking->reminded_at);

        Artisan::call('booking:send-reminders');

        $booking->refresh();
        $this->assertNotNull($booking->reminded_at);
    }

    public function test_command_skips_already_reminded_bookings(): void
    {
        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $this->service->id,
            'client_name' => 'John Doe',
            'client_email' => 'john@example.com',
            'client_phone' => '+15551234567',
            'date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'confirmed',
            'reminded_at' => now()->subHour(),
        ]);

        $originalRemindedAt = $booking->reminded_at->timestamp;

        Artisan::call('booking:send-reminders');

        $booking->refresh();
        $this->assertEquals($originalRemindedAt, $booking->reminded_at->timestamp);
    }

    public function test_command_skips_past_date_bookings(): void
    {
        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $this->service->id,
            'client_name' => 'John Doe',
            'client_email' => 'john@example.com',
            'client_phone' => '+15551234567',
            'date' => now()->subDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'confirmed',
        ]);

        Artisan::call('booking:send-reminders');

        $booking->refresh();
        $this->assertNull($booking->reminded_at);
    }

    public function test_command_skips_cancelled_bookings(): void
    {
        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $this->service->id,
            'client_name' => 'John Doe',
            'client_email' => 'john@example.com',
            'client_phone' => '+15551234567',
            'date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'cancelled',
        ]);

        Artisan::call('booking:send-reminders');

        $booking->refresh();
        $this->assertNull($booking->reminded_at);
    }

    public function test_command_handles_multiple_bookings(): void
    {
        $booking1 = Booking::create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $this->service->id,
            'client_name' => 'John Doe',
            'client_email' => 'john@example.com',
            'client_phone' => '+15551234567',
            'date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'confirmed',
        ]);

        $booking2 = Booking::create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $this->service->id,
            'client_name' => 'Jane Smith',
            'client_email' => 'jane@example.com',
            'client_phone' => '+15559876543',
            'date' => now()->addDay()->toDateString(),
            'start_time' => '14:00',
            'end_time' => '15:00',
            'status' => 'confirmed',
        ]);

        Artisan::call('booking:send-reminders');

        $booking1->refresh();
        $booking2->refresh();
        $this->assertNotNull($booking1->reminded_at);
        $this->assertNotNull($booking2->reminded_at);
    }
}
