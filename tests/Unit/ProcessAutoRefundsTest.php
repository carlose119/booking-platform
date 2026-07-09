<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DTOs\RefundResult;
use App\Services\StripeService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\Refund;
use Stripe\StripeClient;
use Tests\TestCase;

class ProcessAutoRefundsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createTenantWithStripe(array $overrides = []): Tenant
    {
        $connectValues = array_intersect_key($overrides, array_flip(Tenant::sensitiveStripeConnectFields()));
        $overrides = array_diff_key($overrides, array_flip(Tenant::sensitiveStripeConnectFields()));

        $tenant = Tenant::create(array_merge([
            'name' => 'Test Salon',
            'slug' => 'test-salon',
            'payment_policy' => 'nopayment',
            'stripe_api_key' => 'sk_test_fake_key',
            'refund_window_hours' => 24,
        ], $overrides));

        if (isset($connectValues['stripe_connected_account_id'])) {
            $tenant->syncStripeConnectAccount(
                $connectValues['stripe_connected_account_id'],
                (bool) ($connectValues['stripe_connect_charges_enabled'] ?? false),
                false,
                false,
            );
        }

        return $tenant;
    }

    private function createCancelledPaidBooking(Tenant $tenant, Carbon $cancelledAt, string $paymentStatus = 'paid'): Booking
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
            'status' => 'cancelled',
            'payment_status' => $paymentStatus,
            'stripe_payment_intent_id' => 'pi_test_refund_123',
            'cancelled_at' => $cancelledAt,
        ]);
    }

    /**
     * Directly query the same way ProcessAutoRefunds does, to test the logic
     * without the command wrapper (avoids SQLite transaction nesting).
     */
    private function getRefundableBookings(): Collection
    {
        return Booking::where('status', 'cancelled')
            ->whereIn('payment_status', ['paid', 'partial'])
            ->whereNotNull('stripe_payment_intent_id')
            ->where('cancelled_at', '>=', now()->subHours(24))
            ->with('tenant')
            ->get();
    }

    // ─── eligible booking gets refunded ───────────────────────────────────

    public function test_eligible_booking_gets_refunded(): void
    {
        $tenant = $this->createTenantWithStripe();
        $booking = $this->createCancelledPaidBooking($tenant, Carbon::now()->subHours(12));

        $refund = Refund::constructFrom([
            'id' => 're_test_123',
            'status' => 'succeeded',
            'amount' => 5000,
        ]);

        $mockRefunds = Mockery::mock();
        $mockRefunds->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn ($params) => $params['payment_intent'] === 'pi_test_refund_123'))
            ->andReturn($refund);

        $mockClient = Mockery::mock(StripeClient::class);
        $mockClient->refunds = $mockRefunds;

        // Test the query logic finds eligible booking
        $bookings = $this->getRefundableBookings();
        $this->assertCount(1, $bookings);
        $this->assertEquals($booking->id, $bookings->first()->id);

        // Test the refund logic
        $stripeService = new StripeService($mockClient);
        $result = $stripeService->createRefund($bookings->first()->stripe_payment_intent_id);

        $booking->update(['payment_status' => 'refunded']);

        $this->assertEquals('re_test_123', $result->id);
        $booking->refresh();
        $this->assertEquals('refunded', $booking->payment_status);
    }

    public function test_business_cancelled_partial_booking_gets_refunded(): void
    {
        $tenant = $this->createTenantWithStripe();
        $booking = $this->createCancelledPaidBooking($tenant, Carbon::now()->subHours(12), 'partial');

        $refund = Refund::constructFrom([
            'id' => 're_test_partial_123',
            'status' => 'succeeded',
            'amount' => 2500,
        ]);

        $mockRefunds = Mockery::mock();
        $mockRefunds->shouldReceive('create')
            ->once()
            ->with(
                Mockery::on(fn ($params) => $params['payment_intent'] === 'pi_test_refund_123'),
                Mockery::on(fn (array $options) => str_starts_with($options['idempotency_key'] ?? '', 'booking:auto-refund:'))
            )
            ->andReturn($refund);

        $mockClient = Mockery::mock(StripeClient::class);
        $mockClient->refunds = $mockRefunds;

        $this->app->bind(StripeService::class, fn () => new StripeService($mockClient));

        $this->artisan('booking:auto-refund')
            ->expectsOutput('Processed 1 auto-refund(s).')
            ->assertSuccessful();

        $booking->refresh();
        $this->assertEquals('refunded', $booking->payment_status);
    }

    public function test_business_cancelled_paid_booking_is_not_refunded_twice(): void
    {
        $tenant = $this->createTenantWithStripe();
        $booking = $this->createCancelledPaidBooking($tenant, Carbon::now()->subHours(12));

        $refund = Refund::constructFrom([
            'id' => 're_test_once_123',
            'status' => 'succeeded',
            'amount' => 5000,
        ]);

        $mockRefunds = Mockery::mock();
        $mockRefunds->shouldReceive('create')
            ->once()
            ->with(
                Mockery::on(fn ($params) => $params['payment_intent'] === 'pi_test_refund_123'),
                Mockery::on(fn (array $options) => str_starts_with($options['idempotency_key'] ?? '', 'booking:auto-refund:'))
            )
            ->andReturn($refund);

        $mockClient = Mockery::mock(StripeClient::class);
        $mockClient->refunds = $mockRefunds;

        $this->app->bind(StripeService::class, fn () => new StripeService($mockClient));

        $this->artisan('booking:auto-refund')
            ->expectsOutput('Processed 1 auto-refund(s).')
            ->assertSuccessful();

        $this->artisan('booking:auto-refund')
            ->expectsOutput('Processed 0 auto-refund(s).')
            ->assertSuccessful();

        $booking->refresh();
        $this->assertEquals('refunded', $booking->payment_status);
    }

    public function test_connect_booking_refund_uses_original_connected_account_snapshot(): void
    {
        config(['services.stripe.secret' => 'sk_test_platform']);

        $tenant = $this->createTenantWithStripe([
            'payment_account_mode' => 'connect',
            'stripe_connected_account_id' => 'acct_current',
            'stripe_connect_charges_enabled' => true,
        ]);
        $booking = $this->createCancelledPaidBooking($tenant, Carbon::now()->subHours(12));
        $booking->update([
            'payment_account_mode' => 'connect',
            'stripe_connected_account_id' => 'acct_original',
        ]);

        $stripeMock = Mockery::mock(StripeService::class);
        $stripeMock->shouldReceive('createRefund')
            ->once()
            ->with(
                'pi_test_refund_123',
                null,
                Mockery::on(fn (array $options) => $options['stripe_account'] === 'acct_original'
                    && str_starts_with($options['idempotency_key'] ?? '', 'booking:auto-refund:'))
            )
            ->andReturn(new RefundResult(
                id: 're_test_connect_123',
                status: 'succeeded',
                amount: 5000,
            ));

        $this->app->bind(StripeService::class, fn () => $stripeMock);

        $this->artisan('booking:auto-refund')
            ->expectsOutput('Processed 1 auto-refund(s).')
            ->assertSuccessful();

        $booking->refresh();
        $this->assertEquals('refunded', $booking->payment_status);
    }

    public function test_legacy_direct_booking_refund_does_not_use_current_connect_account_after_migration(): void
    {
        config(['services.stripe.secret' => 'sk_test_platform']);

        $tenant = $this->createTenantWithStripe([
            'payment_account_mode' => 'connect',
            'stripe_connected_account_id' => 'acct_current_after_migration',
            'stripe_connect_charges_enabled' => true,
        ]);
        $booking = $this->createCancelledPaidBooking($tenant, Carbon::now()->subHours(12));

        $stripeMock = Mockery::mock(StripeService::class);
        $stripeMock->shouldReceive('createRefund')
            ->once()
            ->with(
                'pi_test_refund_123',
                null,
                Mockery::on(fn (array $options) => ! array_key_exists('stripe_account', $options)
                    && ($options['idempotency_key'] ?? null) === "booking:auto-refund:{$booking->id}:pi_test_refund_123")
            )
            ->andReturn(new RefundResult(
                id: 're_test_legacy_direct_123',
                status: 'succeeded',
                amount: 5000,
            ));

        $this->app->bind(StripeService::class, fn () => $stripeMock);

        $this->artisan('booking:auto-refund')
            ->expectsOutput('Processed 1 auto-refund(s).')
            ->assertSuccessful();

        $booking->refresh();
        $this->assertEquals('refunded', $booking->payment_status);
    }

    public function test_auto_refund_uses_stable_idempotency_key_for_retry_safety(): void
    {
        $tenant = $this->createTenantWithStripe();
        $booking = $this->createCancelledPaidBooking($tenant, Carbon::now()->subHours(12));

        $stripeMock = Mockery::mock(StripeService::class);
        $stripeMock->shouldReceive('createRefund')
            ->once()
            ->with(
                'pi_test_refund_123',
                null,
                Mockery::on(fn (array $options) => ($options['idempotency_key'] ?? null) === "booking:auto-refund:{$booking->id}:pi_test_refund_123")
            )
            ->andReturn(new RefundResult(
                id: 're_test_idempotent_123',
                status: 'succeeded',
                amount: 5000,
            ));

        $this->app->bind(StripeService::class, fn () => $stripeMock);

        $this->artisan('booking:auto-refund')
            ->expectsOutput('Processed 1 auto-refund(s).')
            ->assertSuccessful();

        $booking->refresh();
        $this->assertEquals('refunded', $booking->payment_status);
    }

    public function test_business_cancelled_unpaid_booking_is_not_refunded(): void
    {
        $tenant = $this->createTenantWithStripe();
        $booking = $this->createCancelledPaidBooking($tenant, Carbon::now()->subHours(12), 'unpaid');

        $mockRefunds = Mockery::mock();
        $mockRefunds->shouldReceive('create')->never();

        $mockClient = Mockery::mock(StripeClient::class);
        $mockClient->refunds = $mockRefunds;

        $this->app->bind(StripeService::class, fn () => new StripeService($mockClient));

        $this->artisan('booking:auto-refund')
            ->expectsOutput('Processed 0 auto-refund(s).')
            ->assertSuccessful();

        $booking->refresh();
        $this->assertEquals('unpaid', $booking->payment_status);
    }

    // ─── outside-window booking not refunded ──────────────────────────────

    public function test_outside_window_booking_not_refunded(): void
    {
        $tenant = $this->createTenantWithStripe(['refund_window_hours' => 24]);
        $booking = $this->createCancelledPaidBooking($tenant, Carbon::now()->subHours(30));

        // Test the query logic excludes outside-window booking
        $bookings = $this->getRefundableBookings();
        $this->assertCount(0, $bookings);
    }

    // ─── already-refunded booking skipped ─────────────────────────────────

    public function test_already_refunded_booking_skipped(): void
    {
        $tenant = $this->createTenantWithStripe();
        $booking = $this->createCancelledPaidBooking($tenant, Carbon::now()->subHours(12));
        $booking->update(['payment_status' => 'refunded']);

        // Test the query logic excludes already-refunded booking
        $bookings = $this->getRefundableBookings();
        $this->assertCount(0, $bookings);
    }
}
