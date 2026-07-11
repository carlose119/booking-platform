<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Jobs\SendBookingNotification;
use App\Models\Booking;
use App\Models\EmployeeSchedule;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\BookingCancelled;
use App\Notifications\BookingRecipient;
use App\Notifications\BookingRescheduled;
use App\Services\BookingService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
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

    public function test_business_cancellation_without_usable_guest_recipient_still_records_audit(): void
    {
        Queue::fake();
        Notification::fake();

        $admin = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Business Admin',
            'email' => 'no-recipient-admin@example.com',
            'password' => 'password',
            'role' => UserRole::BusinessAdmin,
        ]);

        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $this->service->id,
            'client_id' => null,
            'client_name' => 'Guest Client',
            'client_email' => '',
            'client_phone' => '',
            'notification_channel' => 'sms',
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

        Queue::assertPushed(SendBookingNotification::class, 1);

        (new SendBookingNotification($cancelled, 'cancelled', 'Staff unavailable'))
            ->handle(app(NotificationService::class));

        $this->assertEquals('cancelled', $cancelled->status);
        $this->assertEquals('Staff unavailable', $cancelled->cancellation_reason);
        $this->assertEquals($admin->id, $cancelled->cancelled_by_user_id);
        Notification::assertNothingSent();
    }

    public function test_cancelled_notification_includes_snapshot_refund_amount_and_processing_time_for_paid_guest(): void
    {
        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $this->service->id,
            'client_id' => null,
            'client_name' => 'Guest Client',
            'client_email' => 'guest@example.com',
            'client_phone' => '+15557654321',
            'notification_channel' => 'both',
            'date' => now()->addDay(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'cancelled',
            'payment_status' => 'partial',
            'payment_amount_cents' => 1250,
            'payment_currency' => 'usd',
        ]);

        $notification = new BookingCancelled($booking, 'Staff unavailable');
        $guest = BookingRecipient::fromBooking($booking);

        $mail = $notification->toMail($guest);
        $sms = $notification->toSms($guest);

        $this->assertContains('Reason: Staff unavailable', $mail->introLines);
        $this->assertContains('A refund of $12.50 will be processed to your original payment method within 5-10 business days.', $mail->introLines);
        $this->assertStringContainsString('Reason: Staff unavailable', $sms->body);
        $this->assertStringContainsString('Refund of $12.50 will be processed within 5-10 business days.', $sms->body);
    }

    public function test_cancelled_notification_explains_no_refund_for_unpaid_guest(): void
    {
        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $this->service->id,
            'client_id' => null,
            'client_name' => 'Guest Client',
            'client_email' => 'guest@example.com',
            'client_phone' => '+15557654321',
            'notification_channel' => 'both',
            'date' => now()->addDay(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'cancelled',
            'payment_status' => 'unpaid',
        ]);

        $notification = new BookingCancelled($booking, 'Staff unavailable');
        $guest = BookingRecipient::fromBooking($booking);

        $mail = $notification->toMail($guest);
        $sms = $notification->toSms($guest);

        $this->assertContains('No refund will be issued because no payment was received for this booking.', $mail->introLines);
        $this->assertStringContainsString('No refund will be issued because no payment was received.', $sms->body);
    }

    public function test_cancelled_notification_does_not_invent_a_refund_amount_when_paid_booking_has_no_snapshot(): void
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
            'payment_status' => 'paid',
        ]);

        $notification = new BookingCancelled($booking, 'Staff unavailable');

        $mail = $notification->toMail($this->client);
        $sms = $notification->toSms($this->client);

        $this->assertContains('A refund will be processed to your original payment method.', $mail->introLines);
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

    public function test_business_reschedule_sends_guest_notification_to_booking_email(): void
    {
        Queue::fake();
        Notification::fake();
        $futureMonday = Carbon::now()->addWeek()->startOfWeek();
        $admin = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Business Admin',
            'email' => 'reschedule-admin@example.com',
            'password' => 'password',
            'role' => UserRole::BusinessAdmin,
        ]);
        $employee = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Jane Doe',
            'email' => 'employee@example.com',
            'password' => 'password',
            'role' => UserRole::Employee,
        ]);
        $this->service->employees()->attach($employee->id);
        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'day_of_week' => $futureMonday->dayOfWeekIso,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);
        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $this->service->id,
            'employee_id' => $employee->id,
            'client_id' => null,
            'client_name' => 'Guest Client',
            'client_email' => 'guest@example.com',
            'client_phone' => '+15557654321',
            'notification_channel' => 'email',
            'date' => $futureMonday->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'confirmed',
        ]);

        app(BookingService::class)->rescheduleBooking(
            bookingId: $booking->id,
            tenantId: $this->tenant->id,
            actorUserId: $admin->id,
            date: $futureMonday->toDateString(),
            startTime: '10:00',
            endTime: '11:00',
            reason: 'Move guest booking',
        );

        $job = null;
        Queue::assertPushed(SendBookingNotification::class, function (SendBookingNotification $queuedJob) use (&$job, $booking): bool {
            $job = $queuedJob;

            return $queuedJob->booking->id === $booking->id
                && $queuedJob->event === 'rescheduled'
                && $queuedJob->originalDate === $booking->date->toDateString()
                && $queuedJob->originalTime === '09:00 - 10:00';
        });

        $this->assertInstanceOf(SendBookingNotification::class, $job);
        $job->handle(app(NotificationService::class));

        $booking->refresh();
        $this->assertEquals('10:00', $booking->start_time->format('H:i'));
        $this->assertEquals('11:00', $booking->end_time->format('H:i'));
        Notification::assertSentTo(
            BookingRecipient::fromBooking($booking),
            BookingRescheduled::class,
            fn ($notification, array $channels): bool => $channels === ['mail']
        );
    }

    public function test_business_reschedule_without_usable_guest_recipient_preserves_booking_changes(): void
    {
        Queue::fake();
        Notification::fake();
        $futureMonday = Carbon::now()->addWeek()->startOfWeek();
        $admin = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Business Admin',
            'email' => 'reschedule-empty-admin@example.com',
            'password' => 'password',
            'role' => UserRole::BusinessAdmin,
        ]);
        $employee = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Jane Doe',
            'email' => 'employee-empty@example.com',
            'password' => 'password',
            'role' => UserRole::Employee,
        ]);
        $this->service->employees()->attach($employee->id);
        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'day_of_week' => $futureMonday->dayOfWeekIso,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);
        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $this->service->id,
            'employee_id' => $employee->id,
            'client_id' => null,
            'client_name' => 'Guest Client',
            'client_email' => '',
            'client_phone' => '',
            'notification_channel' => 'sms',
            'date' => $futureMonday->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'confirmed',
        ]);

        app(BookingService::class)->rescheduleBooking(
            bookingId: $booking->id,
            tenantId: $this->tenant->id,
            actorUserId: $admin->id,
            date: $futureMonday->toDateString(),
            startTime: '10:00',
            endTime: '11:00',
            reason: 'Move guest booking',
        );

        $job = null;
        Queue::assertPushed(SendBookingNotification::class, function (SendBookingNotification $queuedJob) use (&$job, $booking): bool {
            $job = $queuedJob;

            return $queuedJob->booking->id === $booking->id
                && $queuedJob->event === 'rescheduled';
        });

        $this->assertInstanceOf(SendBookingNotification::class, $job);
        $job->handle(app(NotificationService::class));

        $booking->refresh();
        $this->assertEquals('10:00', $booking->start_time->format('H:i'));
        $this->assertEquals('11:00', $booking->end_time->format('H:i'));
        $this->assertEquals($admin->id, $booking->rescheduled_by);
        $this->assertEquals('Move guest booking', $booking->reschedule_reason);
        Notification::assertNothingSent();
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

    public function test_failed_job_logs_safe_context_when_notification_delivery_is_exhausted(): void
    {
        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $this->service->id,
            'client_id' => null,
            'client_name' => 'Guest Client',
            'client_email' => 'guest@example.com',
            'client_phone' => '+15551234567',
            'notification_channel' => 'email',
            'date' => now()->addDay(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'confirmed',
        ]);

        $rawProviderMessage = 'SMTP rejected recipient guest@example.com with phone +15551234567';

        Log::shouldReceive('error')
            ->once()
            ->with('Booking notification delivery exhausted retries', Mockery::on(function (array $context) use ($booking, $rawProviderMessage): bool {
                $this->assertSame($booking->id, $context['booking_id']);
                $this->assertSame($this->tenant->id, $context['tenant_id']);
                $this->assertSame('confirmed', $context['event']);
                $this->assertSame('email', $context['notification_channel']);
                $this->assertSame('RuntimeException', $context['exception_class']);
                $this->assertSame('notification_delivery_exhausted', $context['failure_code']);
                $this->assertArrayNotHasKey('exception_message', $context);
                $this->assertArrayNotHasKey('client_email', $context);
                $this->assertArrayNotHasKey('client_phone', $context);
                $encodedContext = json_encode($context, JSON_THROW_ON_ERROR);
                $this->assertStringNotContainsString($rawProviderMessage, $encodedContext);
                $this->assertStringNotContainsString('guest@example.com', $encodedContext);
                $this->assertStringNotContainsString('+15551234567', $encodedContext);

                return true;
            }));

        (new SendBookingNotification($booking, 'confirmed'))
            ->failed(new \RuntimeException($rawProviderMessage));
    }

    public function test_failed_job_logs_safe_context_for_non_confirmation_events(): void
    {
        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $this->service->id,
            'client_id' => null,
            'client_name' => 'Guest Client',
            'client_email' => 'guest@example.com',
            'client_phone' => '+15551234567',
            'notification_channel' => 'sms',
            'date' => now()->addDay(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'cancelled',
        ]);

        $rawProviderMessage = 'SMS provider exhausted for +15551234567 / guest@example.com token=secret-value';

        Log::shouldReceive('error')
            ->once()
            ->with('Booking notification delivery exhausted retries', Mockery::on(function (array $context) use ($booking, $rawProviderMessage): bool {
                $this->assertSame($booking->id, $context['booking_id']);
                $this->assertSame($this->tenant->id, $context['tenant_id']);
                $this->assertSame('cancelled', $context['event']);
                $this->assertSame('sms', $context['notification_channel']);
                $this->assertSame('LogicException', $context['exception_class']);
                $this->assertSame('notification_delivery_exhausted', $context['failure_code']);
                $this->assertArrayNotHasKey('exception_message', $context);
                $this->assertArrayNotHasKey('client_email', $context);
                $this->assertArrayNotHasKey('client_phone', $context);
                $encodedContext = json_encode($context, JSON_THROW_ON_ERROR);
                $this->assertStringNotContainsString($rawProviderMessage, $encodedContext);
                $this->assertStringNotContainsString('guest@example.com', $encodedContext);
                $this->assertStringNotContainsString('+15551234567', $encodedContext);
                $this->assertStringNotContainsString('secret-value', $encodedContext);

                return true;
            }));

        (new SendBookingNotification($booking, 'cancelled', 'Staff unavailable'))
            ->failed(new \LogicException($rawProviderMessage));
    }
}
