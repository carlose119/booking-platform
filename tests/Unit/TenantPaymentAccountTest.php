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
        ]);
        $tenant->syncStripeConnectAccount('acct_connect_123', true, false, true);

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
        ]);
        $tenant->syncStripeConnectAccount('acct_connect_456', false, false, false);

        $this->assertFalse($tenant->hasActiveConnectCharges());
        $this->assertFalse($tenant->isPaymentAccountReady());
    }

    public function test_connect_ownership_and_status_fields_are_not_mass_assignable(): void
    {
        $tenant = Tenant::create([
            'name' => 'Protected Tenant',
            'slug' => 'protected-tenant',
        ]);

        foreach (Tenant::sensitiveStripeConnectFields() as $field) {
            $this->assertFalse($tenant->isFillable($field), $field.' should not be mass assignable.');
        }
    }

    public function test_forged_mass_update_cannot_activate_arbitrary_connect_account(): void
    {
        $tenant = Tenant::create([
            'name' => 'Forged Update Tenant',
            'slug' => 'forged-update-tenant',
        ]);

        $tenant->update([
            'stripe_connected_account_id' => 'acct_attacker_123',
            'stripe_connect_charges_enabled' => true,
            'stripe_connect_payouts_enabled' => true,
            'stripe_connect_onboarding_status' => 'onboarded',
            'stripe_connect_onboarded_at' => now(),
        ]);

        $tenant->refresh();

        $this->assertNull($tenant->stripe_connected_account_id);
        $this->assertFalse($tenant->stripe_connect_charges_enabled ?? false);
        $this->assertFalse($tenant->stripe_connect_payouts_enabled ?? false);
        $this->assertNull($tenant->stripe_connect_onboarding_status);
        $this->assertNull($tenant->stripe_connect_onboarded_at);
    }

    public function test_controlled_connect_sync_updates_ownership_and_status_fields(): void
    {
        $tenant = Tenant::create([
            'name' => 'OAuth Tenant',
            'slug' => 'oauth-tenant',
        ]);

        $tenant->syncStripeConnectAccount(
            connectedAccountId: 'acct_oauth_123',
            chargesEnabled: true,
            payoutsEnabled: false,
            detailsSubmitted: true,
        );

        $tenant->refresh();

        $this->assertSame(Tenant::PAYMENT_ACCOUNT_CONNECT, $tenant->payment_account_mode);
        $this->assertSame('acct_oauth_123', $tenant->stripe_connected_account_id);
        $this->assertTrue($tenant->stripe_connect_charges_enabled);
        $this->assertFalse($tenant->stripe_connect_payouts_enabled);
        $this->assertSame('onboarded', $tenant->stripe_connect_onboarding_status);
        $this->assertNotNull($tenant->stripe_connect_onboarded_at);
    }
}
