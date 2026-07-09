<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class StripeConnectControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_business_admin_starts_standard_connect_oauth_for_their_tenant(): void
    {
        config(['services.stripe.secret' => 'sk_test_platform']);
        config(['services.stripe.client_id' => 'ca_test_client']);
        config(['services.stripe.connect_webhook_secret' => 'whsec_connect_secret']);
        [$tenant, $admin] = $this->tenantUser(UserRole::BusinessAdmin);

        $response = $this->actingAs($admin)->get(route('stripe.connect.start'));

        $response->assertRedirectContains('https://connect.stripe.com/oauth/authorize');
        $response->assertRedirectContains('response_type=code');
        $response->assertRedirectContains('client_id=ca_test_client');
        $response->assertRedirectContains('scope=read_write');

        $state = session('stripe_connect_oauth');
        $this->assertSame($tenant->id, $state['tenant_id']);
        $this->assertNotEmpty($state['state']);
        $response->assertRedirectContains('state='.$state['state']);
    }

    public function test_business_admin_is_redirected_when_connect_config_is_missing(): void
    {
        config([
            'services.stripe.secret' => null,
            'services.stripe.client_id' => 'ca_test_client',
        ]);
        [, $admin] = $this->tenantUser(UserRole::BusinessAdmin);

        $this->actingAs($admin)
            ->from('/tenant')
            ->get(route('stripe.connect.start'))
            ->assertRedirect('/tenant')
            ->assertSessionHas('stripe_connect_error', 'Stripe Connect is not configured. Contact the platform administrator.');

        $this->assertNull(session('stripe_connect_oauth'));
    }

    public function test_business_admin_is_redirected_when_connect_webhook_secret_is_missing(): void
    {
        config([
            'services.stripe.secret' => 'sk_test_platform',
            'services.stripe.client_id' => 'ca_test_client',
            'services.stripe.connect_webhook_secret' => null,
        ]);
        [, $admin] = $this->tenantUser(UserRole::BusinessAdmin);

        $this->actingAs($admin)
            ->from('/tenant')
            ->get(route('stripe.connect.start'))
            ->assertRedirect('/tenant')
            ->assertSessionHas('stripe_connect_error', 'Stripe Connect is not configured. Contact the platform administrator.');

        $this->assertNull(session('stripe_connect_oauth'));
    }

    public function test_non_business_admin_cannot_start_connect_onboarding(): void
    {
        $service = Mockery::mock(StripeService::class);
        $service->shouldNotReceive('exchangeConnectAuthorizationCode');
        $this->app->instance(StripeService::class, $service);

        [, $employee] = $this->tenantUser(UserRole::Employee);

        $this->actingAs($employee)
            ->get(route('stripe.connect.start'))
            ->assertForbidden();

        $this->assertNull(session('stripe_connect_oauth'));
    }

    public function test_callback_rejects_state_mismatch_without_persisting_connect_account(): void
    {
        [$tenant, $admin] = $this->tenantUser(UserRole::BusinessAdmin);
        session(['stripe_connect_oauth' => ['tenant_id' => $tenant->id, 'state' => 'expected-state']]);

        $service = Mockery::mock(StripeService::class);
        $service->shouldNotReceive('exchangeConnectAuthorizationCode');
        $this->app->instance(StripeService::class, $service);

        $this->actingAs($admin)
            ->get(route('stripe.connect.callback', ['code' => 'ac_test_code', 'state' => 'wrong-state']))
            ->assertForbidden();

        $this->assertNull($tenant->refresh()->stripe_connected_account_id);
    }

    public function test_callback_persists_connected_account_status_for_the_authenticated_tenant(): void
    {
        [$tenant, $admin] = $this->tenantUser(UserRole::BusinessAdmin);
        session(['stripe_connect_oauth' => ['tenant_id' => $tenant->id, 'state' => 'valid-state']]);

        $service = Mockery::mock(StripeService::class);
        $service->shouldReceive('exchangeConnectAuthorizationCode')
            ->once()
            ->with('ac_test_code')
            ->andReturn(['stripe_user_id' => 'acct_connected_123']);
        $service->shouldReceive('retrieveConnectAccount')
            ->once()
            ->with('acct_connected_123')
            ->andReturn((object) [
                'charges_enabled' => true,
                'payouts_enabled' => false,
                'details_submitted' => true,
            ]);
        $this->app->instance(StripeService::class, $service);

        $this->actingAs($admin)
            ->get(route('stripe.connect.callback', ['code' => 'ac_test_code', 'state' => 'valid-state']))
            ->assertRedirect('/tenant');

        $tenant->refresh();
        $this->assertSame(Tenant::PAYMENT_ACCOUNT_CONNECT, $tenant->payment_account_mode);
        $this->assertSame('acct_connected_123', $tenant->stripe_connected_account_id);
        $this->assertTrue($tenant->stripe_connect_charges_enabled);
        $this->assertFalse($tenant->stripe_connect_payouts_enabled);
        $this->assertSame('onboarded', $tenant->stripe_connect_onboarding_status);
        $this->assertNotNull($tenant->stripe_connect_onboarded_at);
    }

    public function test_callback_logs_and_redirects_when_token_exchange_fails_without_leaking_secrets(): void
    {
        Log::spy();
        [$tenant, $admin] = $this->tenantUser(UserRole::BusinessAdmin);
        session(['stripe_connect_oauth' => ['tenant_id' => $tenant->id, 'state' => 'valid-state']]);

        $service = Mockery::mock(StripeService::class);
        $service->shouldReceive('exchangeConnectAuthorizationCode')
            ->once()
            ->with('ac_test_code')
            ->andThrow(new RuntimeException('Stripe rejected sk_test_secret_value'));
        $service->shouldNotReceive('retrieveConnectAccount');
        $this->app->instance(StripeService::class, $service);

        $this->actingAs($admin)
            ->get(route('stripe.connect.callback', ['code' => 'ac_test_code', 'state' => 'valid-state']))
            ->assertRedirect('/tenant')
            ->assertSessionHas('stripe_connect_error', 'Stripe Connect onboarding could not be completed. Please restart onboarding and try again.');

        $this->assertNull($tenant->refresh()->stripe_connected_account_id);
        $this->assertStringNotContainsString('sk_test_secret_value', session('stripe_connect_error'));
        Log::shouldHaveReceived('warning')
            ->with('Stripe Connect OAuth callback failed.', Mockery::on(fn (array $context) => $context['tenant_id'] === $tenant->id
                && $context['stage'] === 'token_exchange'
                && ! str_contains(json_encode($context), 'sk_test_secret_value')))
            ->once();
    }

    public function test_callback_logs_and_redirects_when_account_retrieval_fails_without_activating_connect(): void
    {
        Log::spy();
        [$tenant, $admin] = $this->tenantUser(UserRole::BusinessAdmin);
        session(['stripe_connect_oauth' => ['tenant_id' => $tenant->id, 'state' => 'valid-state']]);

        $service = Mockery::mock(StripeService::class);
        $service->shouldReceive('exchangeConnectAuthorizationCode')
            ->once()
            ->with('ac_test_code')
            ->andReturn(['stripe_user_id' => 'acct_connected_123']);
        $service->shouldReceive('retrieveConnectAccount')
            ->once()
            ->with('acct_connected_123')
            ->andThrow(new RuntimeException('Stripe account retrieval failed'));
        $this->app->instance(StripeService::class, $service);

        $this->actingAs($admin)
            ->get(route('stripe.connect.callback', ['code' => 'ac_test_code', 'state' => 'valid-state']))
            ->assertRedirect('/tenant')
            ->assertSessionHas('stripe_connect_error', 'Stripe Connect onboarding could not be completed. Please restart onboarding and try again.');

        $tenant->refresh();

        $this->assertFalse($tenant->usesStripeConnect());
        $this->assertNull($tenant->stripe_connected_account_id);
        Log::shouldHaveReceived('warning')
            ->with('Stripe Connect OAuth callback failed.', Mockery::on(fn (array $context) => $context['tenant_id'] === $tenant->id
                && $context['stage'] === 'account_retrieval'
                && $context['connected_account_id'] === 'acct_connected_123'))
            ->once();
    }

    private function tenantUser(UserRole $role): array
    {
        $tenant = Tenant::create([
            'name' => fake()->company(),
            'slug' => fake()->unique()->slug(),
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => $role->value,
        ]);

        return [$tenant, $user];
    }
}
