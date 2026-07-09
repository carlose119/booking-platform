<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ServiceResource\Pages\CreateService;
use App\Filament\Resources\ServiceResource\Pages\ListServices;
use App\Filament\Resources\TenantResource;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Tests\TestCase;

class MultiCurrencyResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_tenant_with_supported_default_currency(): void
    {
        $tenant = Tenant::create([
            'name' => 'Euro Salon',
            'slug' => 'euro-salon',
            'default_currency' => 'eur',
        ]);

        $this->assertSame('eur', $tenant->refresh()->default_currency);
        $this->assertSame('Euro (EUR)', TenantResource::defaultCurrencyOptions()['eur']);
    }

    public function test_tenant_resource_rejects_unsupported_default_currency(): void
    {
        $validator = Validator::make(
            ['default_currency' => 'brl'],
            ['default_currency' => TenantResource::defaultCurrencyRules()],
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('default_currency', $validator->errors()->messages());
    }

    public function test_tenant_table_displays_default_currency_label(): void
    {
        $this->assertSame('Euro (EUR)', TenantResource::formatDefaultCurrency('eur'));
        $this->assertSame('US Dollar (USD)', TenantResource::formatDefaultCurrency(null));
    }

    public function test_tenant_resource_requires_direct_credentials_only_for_paid_direct_mode(): void
    {
        $rules = TenantResource::directStripeCredentialRules('direct', '100upfront');
        $validator = Validator::make(['stripe_api_key' => null], ['stripe_api_key' => $rules]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('stripe_api_key', $validator->errors()->messages());

        $connectRules = TenantResource::directStripeCredentialRules('connect', '100upfront');
        $connectValidator = Validator::make(['stripe_api_key' => null], ['stripe_api_key' => $connectRules]);

        $this->assertFalse($connectValidator->fails());
    }

    public function test_tenant_resource_formats_connect_status_from_tenant_readiness(): void
    {
        $readyTenant = Tenant::create([
            'name' => 'Ready Connect Salon',
            'slug' => 'ready-connect-salon',
            'payment_account_mode' => Tenant::PAYMENT_ACCOUNT_CONNECT,
        ]);
        $readyTenant->syncStripeConnectAccount('acct_ready_123', true, false, true);

        $pendingTenant = Tenant::create([
            'name' => 'Pending Connect Salon',
            'slug' => 'pending-connect-salon',
            'payment_account_mode' => Tenant::PAYMENT_ACCOUNT_CONNECT,
        ]);
        $pendingTenant->syncStripeConnectAccount('acct_pending_123', false, false, false);

        $this->assertSame('Ready for charges', TenantResource::connectStatusLabel($readyTenant));
        $this->assertSame('Onboarding incomplete', TenantResource::connectStatusLabel($pendingTenant));
    }

    public function test_tenant_resource_marks_connect_ownership_and_status_fields_read_only(): void
    {
        $this->assertSame([
            'stripe_connected_account_id',
            'stripe_connect_charges_enabled',
            'stripe_connect_payouts_enabled',
            'stripe_connect_onboarding_status',
            'stripe_connect_onboarded_at',
        ], TenantResource::readOnlyStripeConnectFields());
    }

    public function test_service_table_formats_price_with_active_tenant_currency(): void
    {
        [$tenant, $admin] = $this->tenantContext('eur');
        $service = Service::create([
            'tenant_id' => $tenant->id,
            'name' => 'Haircut',
            'price_cents' => 5000,
            'duration_minutes' => 30,
            'active' => true,
        ]);

        $this->actingAsTenantUser($tenant, $admin);

        Livewire::test(ListServices::class)
            ->assertCanSeeTableRecords([$service])
            ->assertSee('€50.00')
            ->assertDontSee('$50.00');
    }

    public function test_service_create_stores_minor_units_under_tenant_currency_without_conversion(): void
    {
        [$tenant, $admin] = $this->tenantContext('gbp');

        $this->actingAsTenantUser($tenant, $admin);

        Livewire::test(CreateService::class)
            ->fillForm([
                'name' => 'Massage',
                'description' => 'Relaxing service',
                'price_cents' => '12.34',
                'duration_minutes' => 45,
                'active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('services', [
            'tenant_id' => $tenant->id,
            'name' => 'Massage',
            'price_cents' => 1234,
        ]);
    }

    private function actingAsSuperAdmin(): User
    {
        $user = User::create([
            'name' => 'Super Admin',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('super-admin'));

        return $user;
    }

    private function tenantContext(string $currency): array
    {
        $tenant = Tenant::create([
            'name' => strtoupper($currency).' Salon',
            'slug' => $currency.'-salon',
            'default_currency' => $currency,
        ]);
        $admin = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin User',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'business_admin',
        ]);

        return [$tenant, $admin];
    }

    private function actingAsTenantUser(Tenant $tenant, User $user): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('tenant'));
        Filament::setTenant($tenant);
    }
}
