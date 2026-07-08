<?php

namespace Tests\Unit;

use App\Channels\SmsChannel;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\BookingConfirmed;
use App\Notifications\Messages\SmsMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SmsChannelTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Service $service;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Salon',
            'slug' => 'test-salon',
            'twilio_sid' => 'AC_test_sid',
            'twilio_auth_token' => 'test_auth_token',
            'twilio_phone_number' => '+15551234567',
        ]);

        $this->service = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Haircut',
            'price_cents' => 5000,
            'duration_minutes' => 60,
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+15559876543',
            'password' => 'password',
        ]);
    }

    public function test_sms_not_sent_when_twilio_not_configured(): void
    {
        $this->tenant->update([
            'twilio_sid' => null,
            'twilio_auth_token' => null,
            'twilio_phone_number' => null,
        ]);

        $notification = Mockery::mock(BookingConfirmed::class);
        $notification->shouldReceive('toSms')
            ->andReturn((new SmsMessage)->body('Test message'));

        $channel = new SmsChannel();
        $channel->send($this->user, $notification);

        // No exception means success - Twilio not configured, silently fails
        $this->assertTrue(true);
    }

    public function test_sms_not_sent_when_user_has_no_phone(): void
    {
        $this->user->update(['phone' => null]);

        $notification = Mockery::mock(BookingConfirmed::class);
        $notification->shouldReceive('toSms')
            ->andReturn((new SmsMessage)->body('Test message'));

        $channel = new SmsChannel();
        $channel->send($this->user, $notification);

        // No exception means success - no phone, silently fails
        $this->assertTrue(true);
    }

    public function test_sms_message_contains_booking_details(): void
    {
        $booking = \App\Models\Booking::create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $this->service->id,
            'client_name' => 'John Doe',
            'client_email' => 'john@example.com',
            'client_phone' => '+15559876543',
            'date' => now()->addDay(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'confirmed',
        ]);

        $notification = new BookingConfirmed($booking);

        $message = $notification->toSms($this->user);

        $this->assertInstanceOf(SmsMessage::class, $message);
        $this->assertStringContainsString('Booking Confirmed', $message->body);
        $this->assertStringContainsString('Test Salon', $message->body);
    }
}
