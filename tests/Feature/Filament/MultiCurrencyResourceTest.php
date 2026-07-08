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
