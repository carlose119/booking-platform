<?php

namespace Tests\Feature\Database;

use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MultiCurrencySchemaTest extends TestCase
{
    public function test_tenants_table_has_default_currency_with_usd_default(): void
    {
        $this->assertTrue(Schema::hasColumn('tenants', 'default_currency'));

        $tenant = Tenant::create([
            'name' => 'Default Currency Salon',
            'slug' => 'default-currency-salon',
        ]);

        $this->assertSame('usd', $tenant->refresh()->default_currency);
    }

    public function test_bookings_table_has_nullable_payment_snapshot_fields(): void
    {
        $this->assertTrue(Schema::hasColumn('bookings', 'payment_amount_cents'));
        $this->assertTrue(Schema::hasColumn('bookings', 'payment_currency'));

        $tenant = Tenant::create([
            'name' => 'Snapshot Salon',
            'slug' => 'snapshot-salon',
        ]);
        $service = Service::create([
            'tenant_id' => $tenant->id,
            'name' => 'Haircut',
            'price_cents' => 5000,
            'duration_minutes' => 60,
            'active' => true,
        ]);

        $booking = Booking::create([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'client_name' => 'Jane Doe',
            'date' => '2026-07-10',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'payment_amount_cents' => 2500,
            'payment_currency' => 'eur',
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'tenant_id' => $tenant->id,
            'payment_amount_cents' => 2500,
            'payment_currency' => 'eur',
        ]);
    }
}
