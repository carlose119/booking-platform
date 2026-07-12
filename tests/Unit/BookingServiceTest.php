<?php

namespace Tests\Unit;

use App\Jobs\SendBookingNotification;
use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\EmployeeSchedule;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\BookingRecipient;
use App\Notifications\BookingRescheduled;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Console\QueuedCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BookingServiceTest extends TestCase
{
    use RefreshDatabase;

    private BookingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BookingService;
    }

    /**
     * Helper: create a minimal tenant stack and return [tenant, employee, service].
     * Defaults to nopayment policy for backward compatibility.
     */
    private function createTenantStack(): array
    {
        $tenant = Tenant::create([
            'name' => 'Test Salon',
            'slug' => 'test-salon',
            'payment_policy' => 'nopayment',
        ]);

        $employee = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Jane Doe',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);

        $service = Service::create([
            'tenant_id' => $tenant->id,
            'name' => 'Haircut',
            'price_cents' => 5000,
            'duration_minutes' => 60,
            'active' => true,
        ]);

        $service->employees()->attach($employee->id);

        return [$tenant, $employee, $service];
    }

    private function createBooking(Tenant $tenant, User $employee, Service $service, array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'client_name' => 'John Doe',
            'client_email' => 'john@example.com',
            'client_phone' => '+1 234 567 890',
            'date' => '2026-07-10',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
        ], $overrides));
    }

    private function createBusinessAdmin(Tenant $tenant): User
    {
        return User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Business Admin',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'business_admin',
        ]);
    }

    // ─── 6.1: createHold creates record with correct TTL ──────────────────

    public function test_create_hold_creates_record_with_correct_ttl(): void
    {
        [$tenant, $employee, $service] = $this->createTenantStack();

        $before = Carbon::now();

        $hold = $this->service->createHold(
            tenantId: $tenant->id,
            employeeId: $employee->id,
            serviceId: $service->id,
            date: '2026-07-10',
            startTime: '10:00',
            endTime: '11:00',
        );

        $after = Carbon::now();

        $this->assertNotNull($hold->id);
        $this->assertEquals($tenant->id, $hold->tenant_id);
        $this->assertEquals($employee->id, $hold->employee_id);
        $this->assertEquals($service->id, $hold->service_id);
        $this->assertEquals('2026-07-10', $hold->date->toDateString());
        $this->assertEquals('10:00', $hold->start_time->format('H:i'));
        $this->assertEquals('11:00', $hold->end_time->format('H:i'));

        $expectedTtl = config('booking.hold_ttl_minutes', 10);
        $this->assertTrue(
            $hold->expires_at->gte($before->addMinutes($expectedTtl)->subSeconds(2)),
            'expires_at should be at least now + hold_ttl_minutes'
        );
        $this->assertTrue(
            $hold->expires_at->lte($after->addMinutes($expectedTtl)->addSeconds(2)),
            'expires_at should be at most now + hold_ttl_minutes'
        );
    }

    public function test_create_hold_persists_active_slot_key_when_column_exists(): void
    {
        [$tenant, $employee, $service] = $this->createTenantStack();

        $hold = $this->service->createHold(
            tenantId: $tenant->id,
            employeeId: $employee->id,
            serviceId: $service->id,
            date: '2026-07-10',
            startTime: '10:00',
            endTime: '11:00',
        );

        $this->assertSame(BookingHold::ACTIVE_SLOT_KEY, $hold->refresh()->active_slot_key);
        $this->assertDatabaseHas('booking_holds', [
            'id' => $hold->id,
            'active_slot_key' => BookingHold::ACTIVE_SLOT_KEY,
        ]);
    }

    public function test_create_hold_is_compatible_before_active_slot_key_migration_runs(): void
    {
        [$tenant, $employee, $service] = $this->createTenantStack();
        $this->simulateBookingHoldsTableBeforeActiveSlotKeyMigration();

        $hold = $this->service->createHold(
            tenantId: $tenant->id,
            employeeId: $employee->id,
            serviceId: $service->id,
            date: '2026-07-10',
            startTime: '10:00',
            endTime: '11:00',
        );

        $this->assertNotNull($hold->id);
        $this->assertDatabaseHas('booking_holds', [
            'id' => $hold->id,
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
        ]);
        $this->assertFalse(Schema::hasColumn('booking_holds', 'active_slot_key'));
    }

    public function test_cancel_booking_records_audit_fields_and_dispatches_notification(): void
    {
        Queue::fake();
        [$tenant, $employee, $service] = $this->createTenantStack();
        $actor = $this->createBusinessAdmin($tenant);
        $booking = $this->createBooking($tenant, $employee, $service);

        $cancelled = $this->service->cancelBooking(
            bookingId: $booking->id,
            tenantId: $tenant->id,
            actorUserId: $actor->id,
            reason: ' Staff unavailable ',
        );

        $this->assertEquals('cancelled', $cancelled->status);
        $this->assertEquals('Staff unavailable', $cancelled->cancellation_reason);
        $this->assertEquals($actor->id, $cancelled->cancelled_by_user_id);
        $this->assertTrue($cancelled->cancelledBy->is($actor));
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'tenant_id' => $tenant->id,
            'status' => 'cancelled',
            'cancellation_reason' => 'Staff unavailable',
            'cancelled_by_user_id' => $actor->id,
        ]);
        Queue::assertPushed(SendBookingNotification::class, fn (SendBookingNotification $job) => $job->booking->id === $booking->id
            && $job->event === 'cancelled'
            && $job->reason === 'Staff unavailable'
        );
    }

    public function test_cancel_booking_is_idempotent_for_already_cancelled_bookings(): void
    {
        Queue::fake();
        [$tenant, $employee, $service] = $this->createTenantStack();
        $actor = $this->createBusinessAdmin($tenant);
        $cancelledAt = Carbon::now()->subDay();
        $booking = $this->createBooking($tenant, $employee, $service, [
            'status' => 'cancelled',
            'cancelled_at' => $cancelledAt,
            'cancellation_reason' => 'Original reason',
            'cancelled_by_user_id' => $actor->id,
        ]);

        $result = $this->service->cancelBooking(
            bookingId: $booking->id,
            tenantId: $tenant->id,
            actorUserId: $actor->id,
            reason: 'New reason',
        );

        $this->assertEquals('cancelled', $result->status);
        $this->assertEquals('Original reason', $result->cancellation_reason);
        $this->assertEquals($cancelledAt->toDateTimeString(), $result->cancelled_at->toDateTimeString());
        Queue::assertNothingPushed();
    }

    public function test_cancel_booking_denies_cross_tenant_bookings(): void
    {
        Queue::fake();
        [$tenant, $employee, $service] = $this->createTenantStack();
        $actor = $this->createBusinessAdmin($tenant);
        $otherTenant = Tenant::create(['name' => 'Other Salon', 'slug' => 'other-salon', 'payment_policy' => 'nopayment']);
        $booking = $this->createBooking($tenant, $employee, $service);

        $this->expectException(ModelNotFoundException::class);

        try {
            $this->service->cancelBooking(
                bookingId: $booking->id,
                tenantId: $otherTenant->id,
                actorUserId: $actor->id,
                reason: 'Staff unavailable',
            );
        } finally {
            $booking->refresh();
            $this->assertEquals('confirmed', $booking->status);
            $this->assertNull($booking->cancelled_at);
            Queue::assertNothingPushed();
        }
    }

    public function test_cancel_booking_denies_customer_self_cancellation(): void
    {
        Queue::fake();
        [$tenant, $employee, $service] = $this->createTenantStack();
        $client = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Client User',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'client',
        ]);
        $booking = $this->createBooking($tenant, $employee, $service, [
            'client_id' => $client->id,
        ]);

        $this->expectException(AuthorizationException::class);

        try {
            $this->service->cancelBooking(
                bookingId: $booking->id,
                tenantId: $tenant->id,
                actorUserId: $client->id,
                reason: 'I changed my mind',
            );
        } finally {
            $booking->refresh();
            $this->assertEquals('confirmed', $booking->status);
            $this->assertNull($booking->cancelled_at);
            Queue::assertNothingPushed();
        }
    }

    public function test_cancel_booking_queues_auto_refund_for_paid_bookings(): void
    {
        Queue::fake();
        [$tenant, $employee, $service] = $this->createTenantStack();
        $actor = $this->createBusinessAdmin($tenant);
        $booking = $this->createBooking($tenant, $employee, $service, [
            'payment_status' => 'paid',
            'stripe_payment_intent_id' => 'pi_test_refund_123',
        ]);

        $this->service->cancelBooking(
            bookingId: $booking->id,
            tenantId: $tenant->id,
            actorUserId: $actor->id,
            reason: 'Staff unavailable',
        );

        Queue::assertPushed(QueuedCommand::class, fn (QueuedCommand $job) => $job->displayName() === 'booking:auto-refund');
    }

    // ─── 6.2: confirmBooking creates booking and deletes hold ─────────────

    public function test_confirm_booking_creates_booking_and_deletes_hold(): void
    {
        [$tenant, $employee, $service] = $this->createTenantStack();

        // Default tenant has nopayment policy, so booking should be confirmed
        $hold = $this->service->createHold(
            tenantId: $tenant->id,
            employeeId: $employee->id,
            serviceId: $service->id,
            date: '2026-07-10',
            startTime: '10:00',
            endTime: '11:00',
        );

        $booking = $this->service->confirmBooking(
            holdId: $hold->id,
            tenantId: $tenant->id,
            clientName: 'John Doe',
            clientEmail: 'john@example.com',
            clientPhone: '+1 234 567 890',
        );

        $this->assertNotNull($booking->id);
        // nopayment tenant → status=confirmed
        $this->assertEquals('confirmed', $booking->status);
        $this->assertEquals('unpaid', $booking->payment_status);
        $this->assertEquals('John Doe', $booking->client_name);
        $this->assertEquals('john@example.com', $booking->client_email);
        $this->assertEquals('+1 234 567 890', $booking->client_phone);
        $this->assertEquals($hold->date->toDateString(), $booking->date->toDateString());
        $this->assertEquals('10:00', $booking->start_time->format('H:i'));
        $this->assertEquals('11:00', $booking->end_time->format('H:i'));

        // Hold should be deleted
        $this->assertDatabaseMissing('booking_holds', ['id' => $hold->id]);
    }

    public function test_confirm_booking_rejects_invalid_notification_channel(): void
    {
        [$tenant, $employee, $service] = $this->createTenantStack();
        $hold = $this->service->createHold(
            tenantId: $tenant->id,
            employeeId: $employee->id,
            serviceId: $service->id,
            date: '2026-07-10',
            startTime: '10:00',
            endTime: '11:00',
        );

        $this->expectException(HttpException::class);

        try {
            $this->service->confirmBooking(
                holdId: $hold->id,
                tenantId: $tenant->id,
                clientName: 'John Doe',
                clientEmail: 'john@example.com',
                clientPhone: '+1 234 567 890',
                notificationChannel: 'fax',
            );
        } finally {
            $this->assertDatabaseHas('booking_holds', ['id' => $hold->id]);
            $this->assertDatabaseMissing('bookings', [
                'tenant_id' => $tenant->id,
                'client_email' => 'john@example.com',
                'notification_channel' => 'fax',
            ]);
        }
    }

    public function test_confirm_booking_normalizes_valid_notification_channel(): void
    {
        [$tenant, $employee, $service] = $this->createTenantStack();
        $hold = $this->service->createHold(
            tenantId: $tenant->id,
            employeeId: $employee->id,
            serviceId: $service->id,
            date: '2026-07-10',
            startTime: '10:00',
            endTime: '11:00',
        );

        $booking = $this->service->confirmBooking(
            holdId: $hold->id,
            tenantId: $tenant->id,
            clientName: 'John Doe',
            clientEmail: 'john@example.com',
            clientPhone: '+1 234 567 890',
            notificationChannel: ' SMS ',
        );

        $this->assertSame('sms', $booking->notification_channel);
    }

    // ─── 6.3: confirmBooking rejects expired hold ─────────────────────────

    public function test_confirm_booking_rejects_expired_hold(): void
    {
        [$tenant, $employee, $service] = $this->createTenantStack();

        $hold = BookingHold::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
            'date' => '2026-07-10',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'session_id' => 'test-session',
            'expires_at' => Carbon::now()->subMinutes(5),
        ]);

        $this->expectException(HttpException::class);

        $this->service->confirmBooking(
            holdId: $hold->id,
            tenantId: $tenant->id,
            clientName: 'John Doe',
            clientEmail: 'john@example.com',
            clientPhone: '+1 234 567 890',
        );
    }

    // ─── 6.4: CleanExpiredHolds command ────────────────────────────────────

    public function test_clean_expired_holds_command_deletes_expired_holds(): void
    {
        [$tenant, $employee, $service] = $this->createTenantStack();

        // Active hold — should NOT be deleted
        $activeHold = BookingHold::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
            'date' => '2026-07-10',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'session_id' => 'active-session',
            'expires_at' => Carbon::now()->addMinutes(5),
        ]);

        // Expired hold — should be deleted
        $expiredHold = BookingHold::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
            'date' => '2026-07-10',
            'start_time' => '11:00',
            'end_time' => '12:00',
            'session_id' => 'expired-session',
            'expires_at' => Carbon::now()->subMinutes(5),
        ]);

        $this->artisan('booking:clean-holds')
            ->expectsOutput('Cleaned 1 expired hold(s).');

        $this->assertDatabaseHas('booking_holds', ['id' => $activeHold->id]);
        $this->assertDatabaseMissing('booking_holds', ['id' => $expiredHold->id]);
    }

    // ─── 6.5: Unique constraint prevents double hold ──────────────────────

    public function test_unique_constraint_prevents_second_hold_on_same_slot(): void
    {
        [$tenant, $employee, $service] = $this->createTenantStack();

        $this->service->createHold(
            tenantId: $tenant->id,
            employeeId: $employee->id,
            serviceId: $service->id,
            date: '2026-07-10',
            startTime: '10:00',
            endTime: '11:00',
        );

        $this->expectException(QueryException::class);

        $this->service->createHold(
            tenantId: $tenant->id,
            employeeId: $employee->id,
            serviceId: $service->id,
            date: '2026-07-10',
            startTime: '10:00',
            endTime: '11:00',
        );
    }

    // ─── 6.6: AvailabilityService excludes active holds ────────────────────

    public function test_availability_service_excludes_slots_with_active_holds(): void
    {
        $futureMonday = Carbon::now()->addWeek()->startOfWeek();

        $tenant = Tenant::create(['name' => 'Test Salon', 'slug' => 'test-salon']);
        $employee = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Jane Doe',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);
        $service = Service::create([
            'tenant_id' => $tenant->id,
            'name' => 'Haircut',
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

        // Hold the 10:00-11:00 slot
        $this->service->createHold(
            tenantId: $tenant->id,
            employeeId: $employee->id,
            serviceId: $service->id,
            date: $futureMonday->toDateString(),
            startTime: '10:00',
            endTime: '11:00',
        );

        $availabilityService = app(AvailabilityService::class);
        $result = $availabilityService->getAvailableSlots(
            serviceId: $service->id,
            date: $futureMonday->toDateString(),
            tenantId: $tenant->id,
        );

        $this->assertArrayHasKey($employee->id, $result);
        $this->assertCount(3, $result[$employee->id]['slots']);
        $this->assertTrue($result[$employee->id]['slots'][0]['available']);   // 09:00-10:00
        $this->assertFalse($result[$employee->id]['slots'][1]['available']);  // 10:00-11:00 (held)
        $this->assertTrue($result[$employee->id]['slots'][2]['available']);   // 11:00-12:00
    }

    public function test_availability_service_excludes_current_booking_but_blocks_other_bookings_and_holds(): void
    {
        $futureMonday = Carbon::now()->addWeek()->startOfWeek();
        [$tenant, $employee, $service] = $this->createTenantStack();

        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'day_of_week' => $futureMonday->dayOfWeekIso,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        BookingHold::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
            'date' => $futureMonday->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'session_id' => 'held-slot',
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);
        $currentBooking = $this->createBooking($tenant, $employee, $service, [
            'date' => $futureMonday->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);
        $this->createBooking($tenant, $employee, $service, [
            'date' => $futureMonday->toDateString(),
            'start_time' => '11:00',
            'end_time' => '12:00',
        ]);

        $result = app(AvailabilityService::class)->getAvailableSlots(
            serviceId: $service->id,
            date: $futureMonday->toDateString(),
            tenantId: $tenant->id,
            excludeBookingId: $currentBooking->id,
        );

        $this->assertFalse($result[$employee->id]['slots'][0]['available']);
        $this->assertSame('held', $result[$employee->id]['slots'][0]['unavailable_reason']);
        $this->assertTrue($result[$employee->id]['slots'][1]['available']);
        $this->assertFalse($result[$employee->id]['slots'][2]['available']);
        $this->assertSame('booked', $result[$employee->id]['slots'][2]['unavailable_reason']);
    }

    public function test_reschedule_booking_moves_slot_records_audit_and_dispatches_notification(): void
    {
        Queue::fake();
        $futureMonday = Carbon::now()->addWeek()->startOfWeek();
        [$tenant, $employee, $service] = $this->createTenantStack();
        $actor = $this->createBusinessAdmin($tenant);

        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'day_of_week' => $futureMonday->dayOfWeekIso,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);
        $booking = $this->createBooking($tenant, $employee, $service, [
            'date' => $futureMonday->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $rescheduled = $this->service->rescheduleBooking(
            bookingId: $booking->id,
            tenantId: $tenant->id,
            actorUserId: $actor->id,
            date: $futureMonday->toDateString(),
            startTime: '11:00',
            endTime: '12:00',
            reason: 'Client requested later time',
        );

        $this->assertEquals($futureMonday->toDateString(), $rescheduled->date->toDateString());
        $this->assertEquals('11:00', $rescheduled->start_time->format('H:i'));
        $this->assertEquals('12:00', $rescheduled->end_time->format('H:i'));
        $this->assertEquals('confirmed', $rescheduled->status);
        $this->assertEquals('paid', $rescheduled->payment_status);
        $this->assertEquals($futureMonday->toDateString(), $rescheduled->previous_date->toDateString());
        $this->assertEquals('10:00', $rescheduled->previous_start_time->format('H:i'));
        $this->assertEquals('11:00', $rescheduled->previous_end_time->format('H:i'));
        $this->assertEquals($actor->id, $rescheduled->rescheduled_by);
        $this->assertEquals('Client requested later time', $rescheduled->reschedule_reason);
        $this->assertTrue($rescheduled->rescheduledBy->is($actor));
        Queue::assertPushed(SendBookingNotification::class, fn (SendBookingNotification $job) => $job->booking->id === $booking->id
            && $job->event === 'rescheduled'
            && $job->originalDate === $futureMonday->toDateString()
            && $job->originalTime === '10:00 - 11:00'
        );
        Queue::assertNotPushed(QueuedCommand::class);
    }

    public function test_reschedule_booking_without_client_keeps_changes_when_notification_job_runs(): void
    {
        Queue::fake();
        Notification::fake();
        $futureMonday = Carbon::now()->addWeek()->startOfWeek();
        [$tenant, $employee, $service] = $this->createTenantStack();
        $actor = $this->createBusinessAdmin($tenant);

        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'day_of_week' => $futureMonday->dayOfWeekIso,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);
        $booking = $this->createBooking($tenant, $employee, $service, [
            'date' => $futureMonday->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'payment_status' => 'partial',
            'client_id' => null,
        ]);

        $this->service->rescheduleBooking(
            bookingId: $booking->id,
            tenantId: $tenant->id,
            actorUserId: $actor->id,
            date: $futureMonday->toDateString(),
            startTime: '10:00',
            endTime: '11:00',
            reason: 'Move guest booking',
        );

        $job = null;
        Queue::assertPushed(SendBookingNotification::class, function (SendBookingNotification $queuedJob) use (&$job, $booking): bool {
            $job = $queuedJob;

            return $queuedJob->booking->id === $booking->id && $queuedJob->event === 'rescheduled';
        });

        $this->assertInstanceOf(SendBookingNotification::class, $job);
        $job->handle(app(NotificationService::class));

        $booking->refresh();

        $this->assertEquals($futureMonday->toDateString(), $booking->date->toDateString());
        $this->assertEquals('10:00', $booking->start_time->format('H:i'));
        $this->assertEquals('11:00', $booking->end_time->format('H:i'));
        $this->assertEquals('partial', $booking->payment_status);
        $this->assertEquals('confirmed', $booking->status);
        $this->assertEquals($actor->id, $booking->rescheduled_by);
        $this->assertEquals('Move guest booking', $booking->reschedule_reason);
        $this->assertNull($booking->cancelled_at);
        $this->assertNull($booking->cancellation_reason);
        Queue::assertNotPushed(QueuedCommand::class);
        Notification::assertSentTo(
            BookingRecipient::fromBooking($booking),
            BookingRescheduled::class,
            fn ($notification, array $channels): bool => $channels === ['mail']
        );
    }

    public function test_reschedule_booking_rejects_cancelled_and_completed_bookings(): void
    {
        Queue::fake();
        [$tenant, $employee, $service] = $this->createTenantStack();
        $actor = $this->createBusinessAdmin($tenant);

        foreach (['cancelled', 'completed'] as $status) {
            $booking = $this->createBooking($tenant, $employee, $service, ['status' => $status]);

            try {
                $this->service->rescheduleBooking($booking->id, $tenant->id, $actor->id, '2026-07-11', '11:00', '12:00');
                $this->fail("{$status} booking was not rejected.");
            } catch (HttpException $exception) {
                $this->assertSame(422, $exception->getStatusCode());
            }

            $booking->refresh();
            $this->assertEquals($status, $booking->status);
            $this->assertEquals('2026-07-10', $booking->date->toDateString());
        }

        Queue::assertNothingPushed();
    }

    public function test_reschedule_booking_denies_client_actor_and_cross_tenant_access(): void
    {
        Queue::fake();
        [$tenant, $employee, $service] = $this->createTenantStack();
        $client = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Client User',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'client',
        ]);
        $booking = $this->createBooking($tenant, $employee, $service, ['client_id' => $client->id]);

        $this->expectException(AuthorizationException::class);

        try {
            $this->service->rescheduleBooking($booking->id, $tenant->id, $client->id, '2026-07-11', '11:00', '12:00');
        } finally {
            $booking->refresh();
            $this->assertEquals('2026-07-10', $booking->date->toDateString());
            Queue::assertNothingPushed();
        }
    }

    public function test_reschedule_booking_denies_cross_tenant_booking(): void
    {
        Queue::fake();
        [$tenant, $employee, $service] = $this->createTenantStack();
        $actor = $this->createBusinessAdmin($tenant);
        $otherTenant = Tenant::create(['name' => 'Other Salon', 'slug' => 'other-reschedule', 'payment_policy' => 'nopayment']);
        $booking = $this->createBooking($tenant, $employee, $service);

        $this->expectException(ModelNotFoundException::class);

        try {
            $this->service->rescheduleBooking($booking->id, $otherTenant->id, $actor->id, '2026-07-11', '11:00', '12:00');
        } finally {
            $booking->refresh();
            $this->assertEquals('2026-07-10', $booking->date->toDateString());
            Queue::assertNothingPushed();
        }
    }

    public function test_reschedule_booking_rejects_conflicting_target_slot(): void
    {
        Queue::fake();
        $futureMonday = Carbon::now()->addWeek()->startOfWeek();
        [$tenant, $employee, $service] = $this->createTenantStack();
        $actor = $this->createBusinessAdmin($tenant);

        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'day_of_week' => $futureMonday->dayOfWeekIso,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);
        $booking = $this->createBooking($tenant, $employee, $service, [
            'date' => $futureMonday->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);
        $this->createBooking($tenant, $employee, $service, [
            'date' => $futureMonday->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        try {
            $this->service->rescheduleBooking($booking->id, $tenant->id, $actor->id, $futureMonday->toDateString(), '10:00', '11:00');
            $this->fail('Conflicting target slot was not rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $booking->refresh();
        $this->assertEquals('09:00', $booking->start_time->format('H:i'));
        Queue::assertNothingPushed();
    }

    // ─── 6.7: Full slot → confirm flow end-to-end ─────────────────────────

    public function test_full_slot_to_confirm_flow_creates_booking(): void
    {
        [$tenant, $employee, $service] = $this->createTenantStack();

        // 1. Create hold
        $hold = $this->service->createHold(
            tenantId: $tenant->id,
            employeeId: $employee->id,
            serviceId: $service->id,
            date: '2026-07-10',
            startTime: '10:00',
            endTime: '11:00',
        );

        // 2. Confirm booking
        $booking = $this->service->confirmBooking(
            holdId: $hold->id,
            tenantId: $tenant->id,
            clientName: 'Jane Smith',
            clientEmail: 'jane@example.com',
            clientPhone: '+1 555 123 456',
        );

        // 3. Assert booking exists with correct data
        // nopayment tenant → status=confirmed
        $this->assertDatabaseHas('bookings', [
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
            'client_name' => 'Jane Smith',
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
        ]);

        // 4. Assert hold was cleaned up
        $this->assertDatabaseMissing('booking_holds', ['id' => $hold->id]);
    }

    // ─── 7.1: confirmBooking with nopayment confirms immediately ──────────

    public function test_confirm_booking_nopayment_confirms_immediately(): void
    {
        $tenant = Tenant::create([
            'name' => 'NoPay Salon',
            'slug' => 'nopay-salon',
            'payment_policy' => 'nopayment',
        ]);

        $employee = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Jane Doe',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);

        $service = Service::create([
            'tenant_id' => $tenant->id,
            'name' => 'Haircut',
            'price_cents' => 5000,
            'duration_minutes' => 60,
            'active' => true,
        ]);

        $service->employees()->attach($employee->id);

        $hold = $this->service->createHold(
            tenantId: $tenant->id,
            employeeId: $employee->id,
            serviceId: $service->id,
            date: '2026-07-10',
            startTime: '10:00',
            endTime: '11:00',
        );

        $booking = $this->service->confirmBooking(
            holdId: $hold->id,
            tenantId: $tenant->id,
            clientName: 'John Doe',
            clientEmail: 'john@example.com',
            clientPhone: '+1 555 123 456',
        );

        $this->assertEquals('confirmed', $booking->status);
        $this->assertEquals('unpaid', $booking->payment_status);
    }

    // ─── 7.2: confirmBooking with 100upfront creates pending booking ──────

    public function test_confirm_booking_100upfront_creates_pending_booking(): void
    {
        $tenant = Tenant::create([
            'name' => 'Upfront Salon',
            'slug' => 'upfront-salon',
            'payment_policy' => '100upfront',
        ]);

        $employee = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Jane Doe',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);

        $service = Service::create([
            'tenant_id' => $tenant->id,
            'name' => 'Haircut',
            'price_cents' => 5000,
            'duration_minutes' => 60,
            'active' => true,
        ]);

        $service->employees()->attach($employee->id);

        $hold = $this->service->createHold(
            tenantId: $tenant->id,
            employeeId: $employee->id,
            serviceId: $service->id,
            date: '2026-07-10',
            startTime: '10:00',
            endTime: '11:00',
        );

        $booking = $this->service->confirmBooking(
            holdId: $hold->id,
            tenantId: $tenant->id,
            clientName: 'John Doe',
            clientEmail: 'john@example.com',
            clientPhone: '+1 555 123 456',
        );

        $this->assertEquals('pending', $booking->status);
        $this->assertEquals('unpaid', $booking->payment_status);
    }

    // ─── 7.3: calculatePaymentAmount returns correct values ───────────────

    public function test_calculate_payment_amount_nopayment_returns_null(): void
    {
        $tenant = Tenant::create(['name' => 'NoPay', 'slug' => 'nopay', 'payment_policy' => 'nopayment']);
        $this->assertNull($this->service->calculatePaymentAmount($tenant, 5000));
    }

    public function test_calculate_payment_amount_100upfront_returns_full_amount(): void
    {
        $tenant = Tenant::create(['name' => 'Upfront', 'slug' => 'upfront', 'payment_policy' => '100upfront']);
        $this->assertEquals(5000, $this->service->calculatePaymentAmount($tenant, 5000));
    }

    public function test_calculate_payment_amount_fraction_returns_deposit(): void
    {
        $tenant = Tenant::create([
            'name' => 'Fraction',
            'slug' => 'fraction',
            'payment_policy' => 'fraction',
            'deposit_percentage' => 20,
        ]);
        $this->assertEquals(1000, $this->service->calculatePaymentAmount($tenant, 5000));
    }

    // ─── 7.4: createHold uses extended TTL for payment tenants ────────────

    public function test_create_hold_uses_extended_ttl_for_payment_tenants(): void
    {
        $tenant = Tenant::create([
            'name' => 'Extended Salon',
            'slug' => 'extended-salon',
            'payment_policy' => '100upfront',
        ]);

        $employee = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Jane Doe',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);

        $service = Service::create([
            'tenant_id' => $tenant->id,
            'name' => 'Haircut',
            'price_cents' => 5000,
            'duration_minutes' => 60,
            'active' => true,
        ]);

        $service->employees()->attach($employee->id);

        $before = Carbon::now();

        $hold = $this->service->createHold(
            tenantId: $tenant->id,
            employeeId: $employee->id,
            serviceId: $service->id,
            date: '2026-07-10',
            startTime: '10:00',
            endTime: '11:00',
        );

        $after = Carbon::now();

        // 15 minutes for payment-required tenants
        $this->assertTrue(
            $hold->expires_at->gte($before->addMinutes(15)->subSeconds(2)),
            'expires_at should be at least now + 15 minutes'
        );
        $this->assertTrue(
            $hold->expires_at->lte($after->addMinutes(15)->addSeconds(2)),
            'expires_at should be at most now + 15 minutes'
        );
    }

    private function simulateBookingHoldsTableBeforeActiveSlotKeyMigration(): void
    {
        if (Schema::hasColumn('booking_holds', 'active_slot_key')) {
            Schema::table('booking_holds', function ($table): void {
                $table->dropUnique('booking_holds_unique_active_slot');
            });

            Schema::table('booking_holds', function ($table): void {
                $table->dropColumn('active_slot_key');
            });
        }

        Schema::table('booking_holds', function ($table): void {
            $table->unique(
                ['tenant_id', 'employee_id', 'date', 'start_time', 'end_time'],
                'booking_holds_unique_slot'
            );
        });
    }
}
