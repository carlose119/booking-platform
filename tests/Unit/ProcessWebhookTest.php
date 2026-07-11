<?php

namespace Tests\Unit;

use App\Jobs\ProcessWebhook;
use App\Jobs\SendBookingNotification;
use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BookingService;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Stripe\StripeClient;
use Tests\TestCase;

class ProcessWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createTenantWithStripe(string $paymentPolicy = 'nopayment'): Tenant
    {
        return Tenant::create([
            'name' => 'Test Salon',
            'slug' => 'test-salon-'.fake()->unique()->numberBetween(1000, 9999),
            'payment_policy' => $paymentPolicy,
            'stripe_api_key' => 'sk_test_fake_key',
            'stripe_webhook_secret' => 'whsec_test_secret',
        ]);
    }

    private function createPendingGuestBookingThroughService(Tenant $tenant): Booking
    {
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

        $hold = BookingHold::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
            'date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'session_id' => 'test-session',
            'expires_at' => now()->addMinutes(15),
        ]);

        return app(BookingService::class)->confirmBooking(
            holdId: $hold->id,
            tenantId: $tenant->id,
            clientName: 'Guest Client',
            clientEmail: 'guest@example.com',
            clientPhone: '+1234567890',
            notificationChannel: 'email',
        );
    }

    private function createBookingWithIntent(Tenant $tenant, string $paymentStatus = 'unpaid'): Booking
    {
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

        return Booking::create([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'client_name' => 'John Doe',
            'client_email' => 'john@example.com',
            'client_phone' => '+1234567890',
            'date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'pending',
            'payment_status' => $paymentStatus,
            'stripe_payment_intent_id' => 'pi_test_webhook_123',
        ]);
    }

    private function mockStripeEvent(string $eventId, string $eventType, object $paymentIntent): StripeClient
    {
        $mockEvents = Mockery::mock();
        $mockEvents->shouldReceive('retrieve')
            ->once()
            ->with($eventId)
            ->andReturn((object) [
                'type' => $eventType,
                'data' => (object) ['object' => $paymentIntent],
            ]);

        $mockClient = Mockery::mock(StripeClient::class);
        $mockClient->events = $mockEvents;

        return $mockClient;
    }

    // ─── payment_intent.succeeded marks booking paid + confirmed ──────────

    public function test_payment_succeeded_marks_booking_paid_and_confirmed(): void
    {
        $tenant = $this->createTenantWithStripe();
        $booking = $this->createBookingWithIntent($tenant);

        $mockClient = $this->mockStripeEvent('evt_test_123', 'payment_intent.succeeded', (object) [
            'id' => 'pi_test_webhook_123',
        ]);

        $this->app->bind(StripeService::class, fn () => new StripeService($mockClient));

        $job = new ProcessWebhook('evt_test_123', $tenant->id);
        $job->handle();

        $booking->refresh();
        $this->assertEquals('paid', $booking->payment_status);
        $this->assertEquals('confirmed', $booking->status);
    }

    public function test_payment_required_guest_booking_waits_for_success_webhook_before_confirmation_dispatch(): void
    {
        foreach ([
            ['100upfront', 'pi_test_webhook_123', 'evt_test_guest_success', 'paid'],
            ['fraction', 'pi_test_fraction_guest', 'evt_test_fraction_guest_success', 'partial'],
        ] as [$policy, $paymentIntentId, $eventId, $expectedPaymentStatus]) {
            Queue::fake();
            $tenant = $this->createTenantWithStripe($policy);
            $booking = $this->createPendingGuestBookingThroughService($tenant);
            $booking->update(['stripe_payment_intent_id' => $paymentIntentId]);

            Queue::assertNothingPushed();

            $mockClient = $this->mockStripeEvent($eventId, 'payment_intent.succeeded', (object) [
                'id' => $paymentIntentId,
            ]);

            $this->app->bind(StripeService::class, fn () => new StripeService($mockClient));

            (new ProcessWebhook($eventId, $tenant->id))->handle();

            $booking->refresh();
            $this->assertEquals($expectedPaymentStatus, $booking->payment_status);
            $this->assertEquals('confirmed', $booking->status);
            Queue::assertPushed(SendBookingNotification::class, 1);
            Queue::assertPushed(SendBookingNotification::class, fn (SendBookingNotification $job): bool => $job->booking->id === $booking->id
                && $job->event === 'confirmed'
            );
        }
    }

    public function test_duplicate_guest_success_webhook_does_not_dispatch_duplicate_confirmation(): void
    {
        Queue::fake();
        $tenant = $this->createTenantWithStripe('100upfront');
        $booking = $this->createBookingWithIntent($tenant, 'paid');
        $booking->update([
            'client_id' => null,
            'status' => 'confirmed',
            'notification_channel' => 'email',
        ]);

        $mockClient = $this->mockStripeEvent('evt_test_guest_duplicate', 'payment_intent.succeeded', (object) [
            'id' => 'pi_test_webhook_123',
        ]);

        $this->app->bind(StripeService::class, fn () => new StripeService($mockClient));

        (new ProcessWebhook('evt_test_guest_duplicate', $tenant->id))->handle();

        $booking->refresh();
        $this->assertEquals('paid', $booking->payment_status);
        $this->assertEquals('confirmed', $booking->status);
        Queue::assertNothingPushed();
    }

    public function test_duplicate_partial_guest_success_webhook_does_not_dispatch_duplicate_confirmation(): void
    {
        Queue::fake();
        $tenant = $this->createTenantWithStripe('fraction');
        $booking = $this->createBookingWithIntent($tenant, 'partial');
        $booking->update([
            'client_id' => null,
            'status' => 'confirmed',
            'notification_channel' => 'email',
        ]);

        $mockClient = $this->mockStripeEvent('evt_test_guest_partial_duplicate', 'payment_intent.succeeded', (object) [
            'id' => 'pi_test_webhook_123',
        ]);

        $this->app->bind(StripeService::class, fn () => new StripeService($mockClient));

        (new ProcessWebhook('evt_test_guest_partial_duplicate', $tenant->id))->handle();

        $booking->refresh();
        $this->assertEquals('partial', $booking->payment_status);
        $this->assertEquals('confirmed', $booking->status);
        Queue::assertNothingPushed();
    }

    public function test_guest_confirmation_enqueue_failure_rolls_back_payment_transition_for_retry(): void
    {
        $tenant = $this->createTenantWithStripe('100upfront');
        $booking = $this->createPendingGuestBookingThroughService($tenant);
        $booking->update(['stripe_payment_intent_id' => 'pi_retry_safe_guest']);

        $mockClient = $this->mockStripeEvent('evt_retry_safe_guest_failure', 'payment_intent.succeeded', (object) [
            'id' => 'pi_retry_safe_guest',
        ]);
        $this->app->bind(StripeService::class, fn () => new StripeService($mockClient));

        Queue::shouldReceive('connection')->andThrow(new \RuntimeException('queue unavailable'));

        try {
            (new ProcessWebhook('evt_retry_safe_guest_failure', $tenant->id))->handle();
            $this->fail('Expected queue failure to bubble so the webhook job can be retried.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('queue unavailable', $exception->getMessage());
        }

        $booking->refresh();
        $this->assertSame('unpaid', $booking->payment_status);
        $this->assertSame('pending', $booking->status);

        Queue::swap($this->app['queue']);
        Queue::fake();
        $retryClient = $this->mockStripeEvent('evt_retry_safe_guest_success', 'payment_intent.succeeded', (object) [
            'id' => 'pi_retry_safe_guest',
        ]);
        $this->app->bind(StripeService::class, fn () => new StripeService($retryClient));

        (new ProcessWebhook('evt_retry_safe_guest_success', $tenant->id))->handle();

        $booking->refresh();
        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame('confirmed', $booking->status);
        Queue::assertPushed(SendBookingNotification::class, 1);
    }

    public function test_payment_transition_guard_only_allows_one_stale_worker_to_trigger_confirmation(): void
    {
        $tenant = $this->createTenantWithStripe('100upfront');
        $booking = $this->createBookingWithIntent($tenant);
        $firstStaleWorker = Booking::findOrFail($booking->id);
        $secondStaleWorker = Booking::findOrFail($booking->id);
        $method = new \ReflectionMethod(ProcessWebhook::class, 'confirmPendingBookingPayment');

        $firstTransitioned = $method->invoke(new ProcessWebhook('evt_first_worker', $tenant->id), $firstStaleWorker, 'paid');
        $secondTransitioned = $method->invoke(new ProcessWebhook('evt_second_worker', $tenant->id), $secondStaleWorker, 'paid');

        $this->assertTrue($firstTransitioned);
        $this->assertFalse($secondTransitioned);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'payment_status' => 'paid',
            'status' => 'confirmed',
        ]);
    }

    public function test_registered_client_success_webhook_preserves_existing_no_confirmation_dispatch_behavior(): void
    {
        Queue::fake();
        $tenant = $this->createTenantWithStripe('100upfront');
        $client = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Registered Client',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'client',
        ]);
        $booking = $this->createBookingWithIntent($tenant);
        $booking->update(['client_id' => $client->id]);

        $mockClient = $this->mockStripeEvent('evt_test_registered_success', 'payment_intent.succeeded', (object) [
            'id' => 'pi_test_webhook_123',
        ]);

        $this->app->bind(StripeService::class, fn () => new StripeService($mockClient));

        (new ProcessWebhook('evt_test_registered_success', $tenant->id))->handle();

        $booking->refresh();
        $this->assertEquals('paid', $booking->payment_status);
        $this->assertEquals('confirmed', $booking->status);
        Queue::assertNothingPushed();
    }

    public function test_connect_payment_succeeded_retrieves_event_with_account_options_and_scopes_booking_lookup(): void
    {
        config(['services.stripe.secret' => 'sk_test_platform']);

        $tenant = Tenant::create([
            'name' => 'Connect Salon',
            'slug' => 'connect-salon',
            'payment_policy' => '100upfront',
            'payment_account_mode' => 'connect',
        ]);
        $tenant->syncStripeConnectAccount('acct_connect_123', true, false, true);
        $booking = $this->createBookingWithIntent($tenant);
        $booking->update([
            'payment_account_mode' => 'connect',
            'stripe_connected_account_id' => 'acct_connect_123',
        ]);

        $otherTenant = $this->createTenantWithStripe();
        $otherBooking = $this->createBookingWithIntent($otherTenant);

        $mockEvents = Mockery::mock();
        $mockEvents->shouldReceive('retrieve')
            ->once()
            ->with('evt_connect_123', ['stripe_account' => 'acct_connect_123'])
            ->andReturn((object) [
                'type' => 'payment_intent.succeeded',
                'data' => (object) ['object' => (object) ['id' => 'pi_test_webhook_123']],
            ]);

        $mockClient = Mockery::mock(StripeClient::class);
        $mockClient->events = $mockEvents;
        $this->app->bind(StripeService::class, fn () => new StripeService($mockClient));

        (new ProcessWebhook('evt_connect_123', $tenant->id, 'acct_connect_123'))->handle();

        $booking->refresh();
        $otherBooking->refresh();

        $this->assertEquals('paid', $booking->payment_status);
        $this->assertEquals('confirmed', $booking->status);
        $this->assertEquals('unpaid', $otherBooking->payment_status);
        $this->assertEquals('pending', $otherBooking->status);
    }

    public function test_connect_webhook_job_uses_original_connected_account_when_tenant_account_changes_before_processing(): void
    {
        config(['services.stripe.secret' => 'sk_test_platform']);

        $tenant = Tenant::create([
            'name' => 'Connect Salon Drift',
            'slug' => 'connect-salon-drift',
            'payment_policy' => '100upfront',
            'payment_account_mode' => 'connect',
        ]);
        $tenant->syncStripeConnectAccount('acct_current_after_dispatch', true, false, true);
        $booking = $this->createBookingWithIntent($tenant);
        $booking->update([
            'payment_account_mode' => 'connect',
            'stripe_connected_account_id' => 'acct_original_from_event',
        ]);

        $mockEvents = Mockery::mock();
        $mockEvents->shouldReceive('retrieve')
            ->once()
            ->with('evt_connect_original', ['stripe_account' => 'acct_original_from_event'])
            ->andReturn((object) [
                'type' => 'payment_intent.succeeded',
                'data' => (object) ['object' => (object) ['id' => 'pi_test_webhook_123']],
            ]);

        $mockClient = Mockery::mock(StripeClient::class);
        $mockClient->events = $mockEvents;
        $this->app->bind(StripeService::class, fn () => new StripeService($mockClient));

        (new ProcessWebhook('evt_connect_original', $tenant->id, 'acct_original_from_event'))->handle();

        $booking->refresh();

        $this->assertEquals('paid', $booking->payment_status);
        $this->assertEquals('confirmed', $booking->status);
    }

    public function test_direct_webhook_job_uses_direct_context_when_tenant_migrates_to_connect_before_processing(): void
    {
        config(['services.stripe.secret' => 'sk_test_platform']);

        $tenant = $this->createTenantWithStripe();
        $booking = $this->createBookingWithIntent($tenant);

        $job = new ProcessWebhook('evt_direct_before_migration', $tenant->id, null, Tenant::PAYMENT_ACCOUNT_DIRECT);

        $tenant->syncStripeConnectAccount('acct_current_after_migration', true, false, true);

        $mockEvents = Mockery::mock();
        $mockEvents->shouldReceive('retrieve')
            ->once()
            ->with('evt_direct_before_migration')
            ->andReturn((object) [
                'type' => 'payment_intent.succeeded',
                'data' => (object) ['object' => (object) ['id' => 'pi_test_webhook_123']],
            ]);

        $mockClient = Mockery::mock(StripeClient::class);
        $mockClient->events = $mockEvents;
        $this->app->bind(StripeService::class, fn () => new StripeService($mockClient));

        $job->handle();

        $booking->refresh();

        $this->assertEquals('paid', $booking->payment_status);
        $this->assertEquals('confirmed', $booking->status);
    }

    public function test_stripe_event_retrieval_failure_logs_safe_structured_context_without_raw_exception_text(): void
    {
        $tenant = $this->createTenantWithStripe();
        $sensitiveMessage = 'Stripe retrieval failed for jane@example.com +15551234567 sk_test_leaked_secret response={"secret":"value"}';

        $mockEvents = Mockery::mock();
        $mockEvents->shouldReceive('retrieve')
            ->once()
            ->with('evt_sensitive_failure')
            ->andThrow(new \RuntimeException($sensitiveMessage));

        $mockClient = Mockery::mock(StripeClient::class);
        $mockClient->events = $mockEvents;
        $this->app->bind(StripeService::class, fn () => new StripeService($mockClient));

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to retrieve Stripe event', Mockery::on(function (array $context) use ($tenant, $sensitiveMessage): bool {
                $encodedContext = json_encode($context, JSON_THROW_ON_ERROR);

                return $context === [
                    'event_id' => 'evt_sensitive_failure',
                    'tenant_id' => $tenant->id,
                    'has_connected_account' => false,
                    'connected_account_id' => null,
                    'exception_class' => 'RuntimeException',
                    'failure_code' => 'stripe_event_retrieval_failed',
                ]
                    && ! str_contains($encodedContext, $sensitiveMessage)
                    && ! str_contains($encodedContext, 'jane@example.com')
                    && ! str_contains($encodedContext, '+15551234567')
                    && ! str_contains($encodedContext, 'sk_test_leaked_secret')
                    && ! str_contains($encodedContext, 'response=');
            }));

        $this->expectException(\RuntimeException::class);

        (new ProcessWebhook('evt_sensitive_failure', $tenant->id))->handle();
    }

    public function test_connect_stripe_event_retrieval_failure_logs_safe_account_context_without_raw_exception_text(): void
    {
        config(['services.stripe.secret' => 'sk_test_platform']);

        $tenant = Tenant::create([
            'name' => 'Connect Retrieval Failure Salon',
            'slug' => 'connect-retrieval-failure-salon',
            'payment_policy' => '100upfront',
            'payment_account_mode' => 'connect',
        ]);
        $tenant->syncStripeConnectAccount('acct_safe_context_123', true, false, true);
        $sensitiveMessage = 'Provider body mentions guest@example.com +5491112345678 whsec_leaked_secret';

        $mockEvents = Mockery::mock();
        $mockEvents->shouldReceive('retrieve')
            ->once()
            ->with('evt_connect_sensitive_failure', ['stripe_account' => 'acct_safe_context_123'])
            ->andThrow(new \LogicException($sensitiveMessage));

        $mockClient = Mockery::mock(StripeClient::class);
        $mockClient->events = $mockEvents;
        $this->app->bind(StripeService::class, fn () => new StripeService($mockClient));

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to retrieve Stripe event', Mockery::on(function (array $context) use ($tenant, $sensitiveMessage): bool {
                $encodedContext = json_encode($context, JSON_THROW_ON_ERROR);

                return $context === [
                    'event_id' => 'evt_connect_sensitive_failure',
                    'tenant_id' => $tenant->id,
                    'has_connected_account' => true,
                    'connected_account_id' => 'acct_safe_context_123',
                    'exception_class' => 'LogicException',
                    'failure_code' => 'stripe_event_retrieval_failed',
                ]
                    && ! str_contains($encodedContext, $sensitiveMessage)
                    && ! str_contains($encodedContext, 'guest@example.com')
                    && ! str_contains($encodedContext, '+5491112345678')
                    && ! str_contains($encodedContext, 'whsec_leaked_secret')
                    && ! str_contains($encodedContext, 'Provider body');
            }));

        $this->expectException(\LogicException::class);

        (new ProcessWebhook('evt_connect_sensitive_failure', $tenant->id, 'acct_safe_context_123'))->handle();
    }

    // ─── payment_intent.payment_failed leaves booking unpaid ──────────────

    public function test_payment_failed_leaves_booking_unpaid(): void
    {
        $tenant = $this->createTenantWithStripe();
        $booking = $this->createBookingWithIntent($tenant);

        $mockClient = $this->mockStripeEvent('evt_test_456', 'payment_intent.payment_failed', (object) [
            'id' => 'pi_test_webhook_123',
            'last_payment_error' => (object) ['message' => 'Card declined'],
        ]);

        $this->app->bind(StripeService::class, fn () => new StripeService($mockClient));

        $job = new ProcessWebhook('evt_test_456', $tenant->id);
        $job->handle();

        $booking->refresh();
        $this->assertEquals('unpaid', $booking->payment_status);
        $this->assertEquals('pending', $booking->status);
    }

    // ─── idempotency: duplicate webhook is skipped ────────────────────────

    public function test_idempotent_webhook_is_skipped(): void
    {
        $tenant = $this->createTenantWithStripe();
        $booking = $this->createBookingWithIntent($tenant, 'paid');
        $booking->update(['status' => 'confirmed']);

        $mockClient = $this->mockStripeEvent('evt_test_789', 'payment_intent.succeeded', (object) [
            'id' => 'pi_test_webhook_123',
        ]);

        $this->app->bind(StripeService::class, fn () => new StripeService($mockClient));

        $job = new ProcessWebhook('evt_test_789', $tenant->id);
        $job->handle();

        $booking->refresh();
        $this->assertEquals('paid', $booking->payment_status);
        $this->assertEquals('confirmed', $booking->status);
    }

    // ─── unknown booking_id handled gracefully ────────────────────────────

    public function test_unknown_booking_handled_gracefully(): void
    {
        $tenant = $this->createTenantWithStripe();
        $bookingCountBefore = Booking::count();

        $mockClient = $this->mockStripeEvent('evt_test_unknown', 'payment_intent.succeeded', (object) [
            'id' => 'pi_nonexistent',
        ]);

        $this->app->bind(StripeService::class, fn () => new StripeService($mockClient));

        $job = new ProcessWebhook('evt_test_unknown', $tenant->id);

        // Should not throw — logs warning and returns
        $job->handle();

        $this->assertSame($bookingCountBefore, Booking::count());
        $this->assertDatabaseMissing('bookings', [
            'stripe_payment_intent_id' => 'pi_nonexistent',
            'payment_status' => 'paid',
        ]);
    }
}
