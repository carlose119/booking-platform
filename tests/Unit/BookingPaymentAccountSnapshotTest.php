<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use Tests\TestCase;

class BookingPaymentAccountSnapshotTest extends TestCase
{
    public function test_booking_uses_saved_connect_account_snapshot_for_refunds(): void
    {
        $tenant = $this->createTenant([
            'payment_account_mode' => 'connect',
            'stripe_connected_account_id' => 'acct_current',
            'stripe_connect_charges_enabled' => true,
        ]);
        $booking = $this->createBooking($tenant, [
            'payment_account_mode' => 'connect',
            'stripe_connected_account_id' => 'acct_original',
        ]);

        $this->assertSame('connect', $booking->resolvedPaymentAccountMode());
        $this->assertSame('acct_original', $booking->resolvedStripeConnectedAccountId());
    }

    public function test_legacy_booking_without_account_snapshot_falls_back_to_tenant_direct_mode(): void
    {
        $tenant = $this->createTenant([
            'payment_account_mode' => 'direct',
            'stripe_api_key' => 'sk_test_direct',
        ]);
        $booking = $this->createBooking($tenant);

        $this->assertSame('direct', $booking->resolvedPaymentAccountMode());
        $this->assertNull($booking->resolvedStripeConnectedAccountId());
    }

    public function test_legacy_booking_without_account_snapshot_stays_direct_after_tenant_migrates_to_connect(): void
    {
        $tenant = $this->createTenant([
            'payment_account_mode' => 'connect',
            'stripe_connected_account_id' => 'acct_current_after_migration',
            'stripe_connect_charges_enabled' => true,
        ]);
        $booking = $this->createBooking($tenant);

        $this->assertSame('direct', $booking->resolvedPaymentAccountMode());
        $this->assertNull($booking->resolvedStripeConnectedAccountId());
    }

    private function createTenant(array $overrides = []): Tenant
    {
        $connectValues = array_intersect_key($overrides, array_flip(Tenant::sensitiveStripeConnectFields()));
        $overrides = array_diff_key($overrides, array_flip(Tenant::sensitiveStripeConnectFields()));

        $tenant = Tenant::create(array_merge([
            'name' => fake()->unique()->company(),
            'slug' => fake()->unique()->slug(),
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

    private function createBooking(Tenant $tenant, array $overrides = []): Booking
    {
        $service = Service::create([
            'tenant_id' => $tenant->id,
            'name' => 'Haircut',
            'price_cents' => 5000,
            'duration_minutes' => 60,
            'active' => true,
        ]);

        return Booking::create(array_merge([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'client_name' => 'Jane Doe',
            'date' => '2026-07-10',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ], $overrides));
    }
}
