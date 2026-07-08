<?php

namespace Tests\Unit;

use App\Models\Tenant;
use Tests\TestCase;

class TenantCurrencyTest extends TestCase
{
    public function test_tenant_currency_defaults_to_usd_when_missing(): void
    {
        $tenant = Tenant::create([
            'name' => 'Legacy Salon',
            'slug' => 'legacy-salon',
        ]);

        $this->assertSame('usd', $tenant->currency());
    }

    public function test_tenant_currency_normalizes_supported_lowercase_code(): void
    {
        $tenant = Tenant::create([
            'name' => 'Euro Salon',
            'slug' => 'euro-salon',
            'default_currency' => 'EUR',
        ]);

        $this->assertSame('eur', $tenant->currency());
        $this->assertSame('eur', $tenant->refresh()->default_currency);
    }
}
