<?php

namespace Tests\Unit;

use App\Channels\SmsChannel;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\BookingConfirmed;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Service $service;
    protected User $client;
    protected Booking $booking;
    protected NotificationService $notificationService;

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

        $this->client = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+15551234567',
            'password' => 'password',
            'notification_channel' => 'email',
        ]);

        $this->booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $this->service->id,
            'client_id' => $this->client->id,
            'client_name' => 'John Doe',
            'client_email' => 'john@example.com',
            'client_phone' => '+15551234567',
            'date' => now()->addDay(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'confirmed',
        ]);

        $this->notificationService = new NotificationService();
    }

    // ─── Channel routing tests ──────────────────────────────────────────

    public function test_email_notification_sent_when_user_prefers_email(): void
    {
        $this->client->update(['notification_channel' => 'email']);

        Notification::fake();

        $this->notificationService->sendBookingConfirmed($this->booking);

        Notification::assertSentTo(
            $this->client,
            BookingConfirmed::class,
            fn ($notification) => $notification->via($this->client) === ['mail']
        );
    }

    public function test_sms_notification_sent_when_user_prefers_sms(): void
    {
        $this->client->update(['notification_channel' => 'sms']);

        Notification::fake();

        $this->notificationService->sendBookingConfirmed($this->booking);

        Notification::assertSentTo(
            $this->client,
            BookingConfirmed::class,
            fn ($notification) => $notification->via($this->client) === [SmsChannel::class]
        );
    }

    public function test_both_channels_used_when_user_prefers_both(): void
    {
        $this->client->update(['notification_channel' => 'both']);

        Notification::fake();

        $this->notificationService->sendBookingConfirmed($this->booking);

        Notification::assertSentTo(
            $this->client,
            BookingConfirmed::class,
            fn ($notification) => $notification->via($this->client) === ['mail', SmsChannel::class]
        );
    }

    public function test_email_used_as_default_when_channel_not_set(): void
    {
        // notification_channel has a default of 'email' and is NOT NULL
        // so we test that unknown values fall back to email
        $this->client->update(['notification_channel' => 'unknown']);

        Notification::fake();

        $this->notificationService->sendBookingConfirmed($this->booking);

        Notification::assertSentTo(
            $this->client,
            BookingConfirmed::class,
            fn ($notification) => $notification->via($this->client) === ['mail']
        );
    }

    // ─── Guest booking tests ────────────────────────────────────────────

    public function test_no_notification_sent_for_guest_booking(): void
    {
        $guestBooking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $this->service->id,
            'client_name' => 'Guest User',
            'client_email' => 'guest@example.com',
            'client_phone' => '+15559876543',
            'date' => now()->addDay(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'confirmed',
        ]);

        Notification::fake();

        $this->notificationService->sendBookingConfirmed($guestBooking);

        Notification::assertNothingSent();
    }

    // ─── Multiple notification types ────────────────────────────────────

    public function test_reminder_notification_sent(): void
    {
        Notification::fake();

        $this->notificationService->sendBookingReminder($this->booking);

        Notification::assertSentTo($this->client, \App\Notifications\BookingReminder::class);
    }

    public function test_cancellation_notification_sent_with_reason(): void
    {
        Notification::fake();

        $this->notificationService->sendBookingCancelled($this->booking, 'Schedule conflict');

        Notification::assertSentTo($this->client, \App\Notifications\BookingCancelled::class);
    }

    public function test_reschedule_notification_sent(): void
    {
        Notification::fake();

        $this->notificationService->sendBookingRescheduled($this->booking, '2026-07-01', '10:00 AM');

        Notification::assertSentTo($this->client, \App\Notifications\BookingRescheduled::class);
    }
}
