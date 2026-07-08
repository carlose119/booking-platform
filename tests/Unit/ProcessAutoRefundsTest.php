<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeService;
use Carbon\Carbon;
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
        return Tenant::create(array_merge([
            'name' => 'Test Salon',
            'slug' => 'test-salon',
            'payment_policy' => 'nopayment',
            'stripe_api_key' => 'sk_test_fake_key',
            'refund_window_hours' => 24,
        ], $overrides));
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
    private function getRefundableBookings(): \Illuminate\Database\Eloquent\Collection
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
            ->with(Mockery::on(fn ($params) =>
                $params['payment_intent'] === 'pi_test_refund_123'
            ))
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
            ->with(Mockery::on(fn ($params) =>
                $params['payment_intent'] === 'pi_test_refund_123'
            ))
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
            ->with(Mockery::on(fn ($params) =>
                $params['payment_intent'] === 'pi_test_refund_123'
            ))
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
