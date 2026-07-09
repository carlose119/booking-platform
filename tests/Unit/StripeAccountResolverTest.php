<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Services\Stripe\StripeAccountResolver;
use Tests\TestCase;

class StripeAccountResolverTest extends TestCase
{
    public function test_resolver_preserves_direct_tenant_credentials(): void
    {
        $tenant = $this->createTenant([
            'payment_account_mode' => 'direct',
            'stripe_api_key' => 'sk_test_direct',
            'stripe_webhook_secret' => 'whsec_direct',
        ]);

        $context = app(StripeAccountResolver::class)->forTenantCharges($tenant);

        $this->assertSame('direct', $context->mode);
        $this->assertSame('sk_test_direct', $context->apiKey);
        $this->assertSame('whsec_direct', $context->webhookSecret);
        $this->assertSame([], $context->stripeOptions());
        $this->assertTrue($context->isReadyForCharges());
    }

    public function test_resolver_uses_platform_key_and_connected_account_for_connect_tenant(): void
    {
        config(['services.stripe.secret' => 'sk_test_platform']);

        $tenant = $this->createTenant([
            'payment_account_mode' => 'connect',
            'stripe_connected_account_id' => 'acct_connect_123',
            'stripe_connect_charges_enabled' => true,
        ]);

        $context = app(StripeAccountResolver::class)->forTenantCharges($tenant);

        $this->assertSame('connect', $context->mode);
        $this->assertSame('sk_test_platform', $context->apiKey);
        $this->assertSame('acct_connect_123', $context->connectedAccountId);
        $this->assertSame(['stripe_account' => 'acct_connect_123'], $context->stripeOptions());
        $this->assertTrue($context->isReadyForCharges());
    }

    public function test_refund_context_uses_booking_original_connect_snapshot(): void
    {
        config(['services.stripe.secret' => 'sk_test_platform']);

        $tenant = $this->createTenant([
            'payment_account_mode' => 'connect',
            'stripe_connected_account_id' => 'acct_current',
            'stripe_connect_charges_enabled' => true,
        ]);
        $booking = $this->createBooking($tenant, [
            'payment_account_mode' => 'connect',
            'stripe_connected_account_id' => 'acct_original',
        ]);

        $context = app(StripeAccountResolver::class)->forBookingRefund($booking);

        $this->assertSame('connect', $context->mode);
        $this->assertSame('sk_test_platform', $context->apiKey);
        $this->assertSame(['stripe_account' => 'acct_original'], $context->stripeOptions());
    }

    public function test_refund_context_for_legacy_booking_stays_direct_after_tenant_migrates_to_connect(): void
    {
        config(['services.stripe.secret' => 'sk_test_platform']);

        $tenant = $this->createTenant([
            'payment_account_mode' => 'connect',
            'stripe_api_key' => 'sk_test_direct_original',
            'stripe_webhook_secret' => 'whsec_direct_original',
            'stripe_connected_account_id' => 'acct_current_after_migration',
            'stripe_connect_charges_enabled' => true,
        ]);
        $booking = $this->createBooking($tenant);

        $context = app(StripeAccountResolver::class)->forBookingRefund($booking);

        $this->assertSame('direct', $context->mode);
        $this->assertSame('sk_test_direct_original', $context->apiKey);
        $this->assertSame('whsec_direct_original', $context->webhookSecret);
        $this->assertNull($context->connectedAccountId);
        $this->assertSame([], $context->stripeOptions());
    }

    public function test_webhook_connect_account_resolution_is_tenant_scoped(): void
    {
        $tenantOne = $this->createTenant([
            'payment_account_mode' => 'connect',
            'stripe_connected_account_id' => 'acct_one',
        ]);

        $this->createTenant([
            'payment_account_mode' => 'connect',
            'stripe_connected_account_id' => 'acct_two',
        ]);

        $resolvedTenant = app(StripeAccountResolver::class)->tenantForConnectedAccount('acct_one');

        $this->assertSame($tenantOne->id, $resolvedTenant?->id);
    }

    private function createTenant(array $overrides = []): Tenant
    {
        return Tenant::create(array_merge([
            'name' => fake()->unique()->company(),
            'slug' => fake()->unique()->slug(),
        ], $overrides));
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
