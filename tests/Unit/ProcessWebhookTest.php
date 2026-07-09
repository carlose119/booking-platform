<?php

namespace Tests\Unit;

use App\Jobs\ProcessWebhook;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    private function createTenantWithStripe(): Tenant
    {
        return Tenant::create([
            'name' => 'Test Salon',
            'slug' => 'test-salon',
            'payment_policy' => 'nopayment',
            'stripe_api_key' => 'sk_test_fake_key',
            'stripe_webhook_secret' => 'whsec_test_secret',
        ]);
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

    public function test_connect_payment_succeeded_retrieves_event_with_account_options_and_scopes_booking_lookup(): void
    {
        config(['services.stripe.secret' => 'sk_test_platform']);

        $tenant = Tenant::create([
            'name' => 'Connect Salon',
            'slug' => 'connect-salon',
            'payment_policy' => '100upfront',
            'payment_account_mode' => 'connect',
            'stripe_connected_account_id' => 'acct_connect_123',
            'stripe_connect_charges_enabled' => true,
        ]);
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
            'stripe_connected_account_id' => 'acct_current_after_dispatch',
            'stripe_connect_charges_enabled' => true,
        ]);
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

        $tenant->update([
            'payment_account_mode' => 'connect',
            'stripe_connected_account_id' => 'acct_current_after_migration',
            'stripe_connect_charges_enabled' => true,
        ]);

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
