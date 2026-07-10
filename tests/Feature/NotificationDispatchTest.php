<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Jobs\SendBookingNotification;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\BookingCancelled;
use App\Notifications\BookingRecipient;
use App\Services\BookingService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class NotificationDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Service $service;

    protected User $client;

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
    }

    // ─── Queue job tests ────────────────────────────────────────────────

    public function test_send_booking_notification_job_is_queued(): void
    {
        Queue::fake();

        $booking = Booking::create([
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

        SendBookingNotification::dispatch($booking, 'confirmed');

        Queue::assertPushed(SendBookingNotification::class, function ($job) use ($booking) {
            return $job->booking->id === $booking->id
                && $job->event === 'confirmed';
        });
    }

    public function test_reminder_job_has_correct_event(): void
    {
        Queue::fake();

        $booking = Booking::create([
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

        SendBookingNotification::dispatch($booking, 'reminder');

        Queue::assertPushed(SendBookingNotification::class, function ($job) use ($booking) {
            return $job->booking->id === $booking->id
                && $job->event === 'reminder';
        });
    }

    public function test_cancellation_job_includes_reason(): void
    {
        Queue::fake();

        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $this->service->id,
            'client_id' => $this->client->id,
            'client_name' => 'John Doe',
            'client_email' => 'john@example.com',
            'client_phone' => '+15551234567',
            'date' => now()->addDay(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'cancelled',
        ]);

        SendBookingNotification::dispatch($booking, 'cancelled', 'Schedule conflict');

        Queue::assertPushed(SendBookingNotification::class, function ($job) use ($booking) {
            return $job->booking->id === $booking->id
                && $job->event === 'cancelled'
                && $job->reason === 'Schedule conflict';
        });
    }

    public function test_business_cancellation_queues_one_cancelled_notification_with_reason_and_refund_info(): void
    {
        Queue::fake();

        $admin = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Business Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => UserRole::BusinessAdmin,
        ]);

        $booking = Booking::create([
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
            'payment_status' => 'partial',
            'stripe_payment_intent_id' => 'pi_test_notification_refund',
        ]);

        app(BookingService::class)->cancelBooking(
            bookingId: $booking->id,
            tenantId: $this->tenant->id,
            actorUserId: $admin->id,
            reason: 'Staff unavailable',
        );

        Queue::assertPushed(SendBookingNotification::class, 1);
        Queue::assertPushed(SendBookingNotification::class, function ($job) use ($booking) {
            return $job->booking->id === $booking->id
                && $job->event === 'cancelled'
                && $job->reason === 'Staff unavailable'
                && $job->booking->payment_status === 'partial';
        });
    }

    public function test_duplicate_business_cancellation_does_not_queue_duplicate_cancelled_notification(): void
    {
        Queue::fake();

        $admin = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Business Admin',
            'email' => 'duplicate-admin@example.com',
            'password' => 'password',
            'role' => UserRole::BusinessAdmin,
        ]);

        $booking = Booking::create([
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
            'payment_status' => 'paid',
            'stripe_payment_intent_id' => 'pi_test_duplicate_notification',
        ]);

        $service = app(BookingService::class);
        $service->cancelBooking($booking->id, $this->tenant->id, $admin->id, 'Staff unavailable');
        $service->cancelBooking($booking->id, $this->tenant->id, $admin->id, 'Second reason ignored');

        Queue::assertPushed(SendBookingNotification::class, 1);
        Queue::assertPushed(SendBookingNotification::class, function ($job) {
            return $job->event === 'cancelled'
                && $job->reason === 'Staff unavailable';
        });
    }

    public function test_business_cancellation_without_notifiable_client_still_records_audit(): void
    {
        Queue::fake();
        Notification::fake();

        $admin = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Business Admin',
            'email' => 'no-client-admin@example.com',
            'password' => 'password',
            'role' => UserRole::BusinessAdmin,
        ]);

        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $this->service->id,
            'client_id' => null,
            'client_name' => 'Guest Client',
            'client_email' => 'guest@example.com',
            'client_phone' => '+15557654321',
            'date' => now()->addDay(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
        ]);

        $cancelled = app(BookingService::class)->cancelBooking(
            bookingId: $booking->id,
            tenantId: $this->tenant->id,
            actorUserId: $admin->id,
            reason: 'Staff unavailable',
        );

        $this->assertEquals('cancelled', $cancelled->status);
        $this->assertEquals('Staff unavailable', $cancelled->cancellation_reason);
        $this->assertEquals($admin->id, $cancelled->cancelled_by_user_id);

        Queue::assertPushed(SendBookingNotification::class, function ($job) use ($booking) {
            return $job->booking->id === $booking->id
                && $job->event === 'cancelled'
                && $job->reason === 'Staff unavailable';
        });

        (new SendBookingNotification($cancelled, 'cancelled', 'Staff unavailable'))
            ->handle(app(NotificationService::class));

        Notification::assertSentTo(
            BookingRecipient::fromBooking($cancelled),
            BookingCancelled::class,
            fn ($notification, array $channels): bool => $channels === ['mail']
        );
    }

    public function test_cancelled_notification_includes_refund_info_for_partial_payment(): void
    {
        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $this->service->id,
            'client_id' => $this->client->id,
            'client_name' => 'John Doe',
            'client_email' => 'john@example.com',
            'client_phone' => '+15551234567',
            'date' => now()->addDay(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'cancelled',
            'payment_status' => 'partial',
        ]);

        $notification = new BookingCancelled($booking, 'Staff unavailable');

        $mail = $notification->toMail($this->client);
        $sms = $notification->toSms($this->client);

        $this->assertContains('Reason: Staff unavailable', $mail->introLines);
        $this->assertContains('A refund will be processed to your original payment method.', $mail->introLines);
        $this->assertStringContainsString('Reason: Staff unavailable', $sms->body);
        $this->assertStringContainsString('Refund will be processed.', $sms->body);
    }

    public function test_reschedule_job_includes_original_details(): void
    {
        Queue::fake();

        $booking = Booking::create([
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

        SendBookingNotification::dispatch($booking, 'rescheduled', null, '2026-07-01', '10:00 AM');

        Queue::assertPushed(SendBookingNotification::class, function ($job) use ($booking) {
            return $job->booking->id === $booking->id
                && $job->event === 'rescheduled'
                && $job->originalDate === '2026-07-01'
                && $job->originalTime === '10:00 AM';
        });
    }

    // ─── Job execution tests ────────────────────────────────────────────

    public function test_job_executes_notification_service(): void
    {
        $booking = Booking::create([
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

        $service = Mockery::mock(NotificationService::class);
        $service->shouldReceive('sendBookingConfirmed')->once()->with($booking);

        $this->app->instance(NotificationService::class, $service);

        $job = new SendBookingNotification($booking, 'confirmed');
        $job->handle($service);
    }

    // ─── Job configuration tests ────────────────────────────────────────

    public function test_job_has_correct_retry_configuration(): void
    {
        $booking = Booking::create([
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

        $job = new SendBookingNotification($booking, 'confirmed');

        $this->assertEquals(3, $job->tries);
        $this->assertEquals([30, 120, 300], $job->backoff);
    }
}
