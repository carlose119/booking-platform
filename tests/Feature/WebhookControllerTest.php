<?php

namespace Tests\Feature;

use App\Jobs\ProcessWebhook;
use App\Models\Tenant;
use App\Services\Stripe\StripeAccountResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class WebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Webhook Test Salon',
            'slug' => 'webhook-test-salon',
            'payment_policy' => '100upfront',
            'stripe_api_key' => 'sk_test_fake_key',
            'stripe_webhook_secret' => 'whsec_test_secret',
        ]);
    }

    // ─── Webhook with valid signature dispatches job ──────────────────────

    public function test_webhook_with_valid_signature_dispatches_job(): void
    {
        Bus::fake();

        $payload = json_encode([
            'id' => 'evt_test_123',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_456',
                    'amount' => 5000,
                    'status' => 'succeeded',
                ],
            ],
        ]);

        $signature = $this->generateStripeSignature($payload, 'whsec_test_secret');

        $response = $this->call(
            'POST',
            "/webhooks/stripe/{$this->tenant->slug}",
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => $signature,
            ],
            $payload
        );

        $response->assertStatus(200);
        $response->assertJson(['received' => true]);

        Bus::assertDispatched(ProcessWebhook::class, function ($job) {
            return $job->eventId === 'evt_test_123'
                && $job->tenantId === $this->tenant->id
                && $job->connectedAccountId === null
                && $job->accountMode === Tenant::PAYMENT_ACCOUNT_DIRECT;
        });
    }

    public function test_connect_webhook_resolves_connected_account_and_dispatches_scoped_job(): void
    {
        config(['services.stripe.connect_webhook_secret' => 'whsec_connect_secret']);
        Bus::fake();

        $tenant = Tenant::create([
            'name' => 'Connect Webhook Salon',
            'slug' => 'connect-webhook-salon',
            'payment_policy' => '100upfront',
            'payment_account_mode' => 'connect',
        ]);
        $tenant->syncStripeConnectAccount('acct_connect_123', true, false, true);

        $payload = json_encode([
            'id' => 'evt_connect_123',
            'account' => 'acct_connect_123',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_connect_456',
                    'amount' => 5000,
                    'status' => 'succeeded',
                ],
            ],
        ]);

        $signature = $this->generateStripeSignature($payload, 'whsec_connect_secret');

        $response = $this->call(
            'POST',
            '/webhooks/stripe/connect',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => $signature,
            ],
            $payload
        );

        $response->assertStatus(200);
        $response->assertJson(['received' => true]);

        Bus::assertDispatched(ProcessWebhook::class, function ($job) use ($tenant) {
            return $job->eventId === 'evt_connect_123'
                && $job->tenantId === $tenant->id
                && $job->connectedAccountId === 'acct_connect_123';
        });
    }

    public function test_connect_webhook_with_unknown_account_returns_400_without_dispatch(): void
    {
        config(['services.stripe.connect_webhook_secret' => 'whsec_connect_secret']);
        Bus::fake();
        Log::spy();

        $payload = json_encode([
            'id' => 'evt_connect_unknown',
            'account' => 'acct_missing',
            'type' => 'payment_intent.succeeded',
        ]);

        $signature = $this->generateStripeSignature($payload, 'whsec_connect_secret');

        $response = $this->call(
            'POST',
            '/webhooks/stripe/connect',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => $signature,
            ],
            $payload
        );

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Unresolved connected account']);

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Stripe Connect webhook account could not be resolved.', Mockery::on(
                fn (array $context) => $context['reason'] === 'unknown_account'
                    && $context['connected_account_id'] === 'acct_missing'
                    && $context['event_id'] === 'evt_connect_unknown'
            ));

        Bus::assertNotDispatched(ProcessWebhook::class);
    }

    public function test_connect_webhook_with_ambiguous_account_returns_400_without_dispatch(): void
    {
        config(['services.stripe.connect_webhook_secret' => 'whsec_connect_secret']);
        Bus::fake();
        Log::spy();

        $resolver = Mockery::mock(StripeAccountResolver::class);
        $resolver->shouldReceive('connectedAccountTenantCount')
            ->once()
            ->with('acct_duplicate')
            ->andReturn(2);
        $resolver->shouldReceive('tenantForConnectedAccount')
            ->never();
        $this->app->instance(StripeAccountResolver::class, $resolver);

        $payload = json_encode([
            'id' => 'evt_connect_ambiguous',
            'account' => 'acct_duplicate',
            'type' => 'payment_intent.succeeded',
        ]);

        $response = $this->call(
            'POST',
            '/webhooks/stripe/connect',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => $this->generateStripeSignature($payload, 'whsec_connect_secret'),
            ],
            $payload
        );

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Unresolved connected account']);

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Stripe Connect webhook account could not be resolved.', Mockery::on(
                fn (array $context) => $context['reason'] === 'ambiguous_account'
                    && $context['connected_account_id'] === 'acct_duplicate'
                    && $context['matching_tenant_count'] === 2
                    && $context['event_id'] === 'evt_connect_ambiguous'
            ));

        Bus::assertNotDispatched(ProcessWebhook::class);
    }

    // ─── Webhook with invalid signature returns 400 ──────────────────────

    public function test_webhook_with_invalid_signature_returns_400(): void
    {
        Bus::fake();

        $payload = json_encode([
            'id' => 'evt_test_123',
            'type' => 'payment_intent.succeeded',
        ]);

        $response = $this->call(
            'POST',
            "/webhooks/stripe/{$this->tenant->slug}",
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 'invalid_signature',
            ],
            $payload
        );

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Invalid signature']);

        Bus::assertNotDispatched(ProcessWebhook::class);
    }

    // ─── Webhook without configured secret returns 400 ───────────────────

    public function test_webhook_without_configured_secret_returns_400(): void
    {
        Bus::fake();

        $tenant = Tenant::create([
            'name' => 'No Secret Salon',
            'slug' => 'no-secret-salon',
            'payment_policy' => '100upfront',
            'stripe_api_key' => 'sk_test_fake_key',
            'stripe_webhook_secret' => null,
        ]);

        $payload = json_encode(['id' => 'evt_test_123']);

        $response = $this->call(
            'POST',
            "/webhooks/stripe/{$tenant->slug}",
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Webhook secret not configured']);

        Bus::assertNotDispatched(ProcessWebhook::class);
    }

    public function test_connect_webhook_without_configured_secret_logs_safe_context_and_returns_400(): void
    {
        config(['services.stripe.connect_webhook_secret' => null]);
        Bus::fake();
        Log::spy();

        $payload = json_encode([
            'id' => 'evt_connect_missing_secret',
            'account' => 'acct_connect_123',
            'type' => 'payment_intent.succeeded',
        ]);

        $response = $this->call(
            'POST',
            '/webhooks/stripe/connect',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 't=123,v1=fake_signature',
            ],
            $payload
        );

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Webhook secret not configured']);

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Stripe Connect webhook secret is not configured.', Mockery::on(
                fn (array $context) => $context['reason'] === 'missing_connect_webhook_secret'
                    && $context['endpoint'] === 'stripe_connect'
                    && $context['has_signature_header'] === true
                    && $context['payload_size'] === strlen($payload)
                    && ! str_contains(json_encode($context), 'whsec_')
            ));

        Bus::assertNotDispatched(ProcessWebhook::class);
    }

    // ─── Webhook for unknown tenant returns 404 ──────────────────────────

    public function test_webhook_for_unknown_tenant_returns_404(): void
    {
        $payload = json_encode(['id' => 'evt_test_123']);

        $response = $this->call(
            'POST',
            '/webhooks/stripe/nonexistent-tenant',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );

        $response->assertStatus(404);
    }

    /**
     * Generate a Stripe webhook signature for testing.
     */
    private function generateStripeSignature(string $payload, string $secret): string
    {
        $timestamp = time();
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, $secret);

        return "t={$timestamp},v1={$signature}";
    }
}
