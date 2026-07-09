<?php

namespace Tests\Unit;

use App\Models\Tenant;
use Tests\TestCase;

class TenantPaymentAccountTest extends TestCase
{
    public function test_tenant_defaults_to_direct_payment_account_mode(): void
    {
        $tenant = Tenant::create([
            'name' => 'Direct Tenant',
            'slug' => 'direct-tenant',
        ]);

        $this->assertTrue($tenant->usesDirectStripe());
        $this->assertFalse($tenant->usesStripeConnect());
        $this->assertSame('direct', $tenant->payment_account_mode);
    }

    public function test_direct_tenant_is_ready_for_charges_when_api_key_exists(): void
    {
        $tenant = Tenant::create([
            'name' => 'Ready Direct Tenant',
            'slug' => 'ready-direct-tenant',
            'payment_policy' => '100upfront',
            'stripe_api_key' => 'sk_test_direct',
        ]);

        $this->assertTrue($tenant->hasDirectStripeCredentials());
        $this->assertTrue($tenant->isPaymentAccountReady());
    }

    public function test_connect_tenant_requires_connected_account_and_active_charges(): void
    {
        $tenant = Tenant::create([
            'name' => 'Connect Tenant',
            'slug' => 'connect-tenant',
            'payment_policy' => '100upfront',
            'payment_account_mode' => 'connect',
            'stripe_connected_account_id' => 'acct_connect_123',
            'stripe_connect_charges_enabled' => true,
        ]);

        $this->assertTrue($tenant->usesStripeConnect());
        $this->assertTrue($tenant->hasActiveConnectCharges());
        $this->assertTrue($tenant->isPaymentAccountReady());
    }

    public function test_connect_tenant_without_active_charges_is_not_ready(): void
    {
        $tenant = Tenant::create([
            'name' => 'Unready Connect Tenant',
            'slug' => 'unready-connect-tenant',
            'payment_policy' => '100upfront',
            'payment_account_mode' => 'connect',
            'stripe_connected_account_id' => 'acct_connect_456',
            'stripe_connect_charges_enabled' => false,
        ]);

        $this->assertFalse($tenant->hasActiveConnectCharges());
        $this->assertFalse($tenant->isPaymentAccountReady());
    }
}
