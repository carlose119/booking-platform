<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use Tests\TestCase;

class BookingPaymentSnapshotTest extends TestCase
{
    public function test_booking_resolves_snapshot_amount_and_currency_first(): void
    {
        [$tenant, $service] = $this->createTenantAndService(defaultCurrency: 'eur', priceCents: 5000);
        $booking = $this->createBooking($tenant, $service, [
            'payment_amount_cents' => 1000,
            'payment_currency' => 'gbp',
        ]);

        $this->assertSame(1000, $booking->resolvedPaymentAmountCents());
        $this->assertSame('gbp', $booking->resolvedPaymentCurrency());
    }

    public function test_booking_falls_back_to_service_price_and_tenant_currency(): void
    {
        [$tenant, $service] = $this->createTenantAndService(defaultCurrency: 'cad', priceCents: 7500);
        $booking = $this->createBooking($tenant, $service);

        $this->assertSame(7500, $booking->resolvedPaymentAmountCents());
        $this->assertSame('cad', $booking->resolvedPaymentCurrency());
    }

    public function test_legacy_booking_without_tenant_currency_falls_back_to_usd(): void
    {
        [$tenant, $service] = $this->createTenantAndService(defaultCurrency: null, priceCents: 2500);
        $tenant->forceFill(['default_currency' => null])->save();
        $booking = $this->createBooking($tenant, $service);

        $this->assertSame(2500, $booking->resolvedPaymentAmountCents());
        $this->assertSame('usd', $booking->resolvedPaymentCurrency());
    }

    private function createTenantAndService(?string $defaultCurrency, int $priceCents): array
    {
        $tenant = Tenant::create(array_filter([
            'name' => fake()->unique()->company(),
            'slug' => fake()->unique()->slug(),
            'default_currency' => $defaultCurrency,
        ], fn ($value) => $value !== null));

        $service = Service::create([
            'tenant_id' => $tenant->id,
            'name' => 'Haircut',
            'price_cents' => $priceCents,
            'duration_minutes' => 60,
            'active' => true,
        ]);

        return [$tenant, $service];
    }

    private function createBooking(Tenant $tenant, Service $service, array $overrides = []): Booking
    {
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
