<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Services\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class StripeConnectController extends Controller
{
    private const OAUTH_STATE_LENGTH = 40;

    private const CONFIGURATION_ERROR_MESSAGE = 'Stripe Connect is not configured. Contact the platform administrator.';

    private const ONBOARDING_ERROR_MESSAGE = 'Stripe Connect onboarding could not be completed. Please restart onboarding and try again.';

    public function start(Request $request): RedirectResponse
    {
        $tenant = $this->authorizedTenant($request);

        if (! $this->stripeConnectIsConfigured()) {
            return redirect('/tenant')
                ->with('stripe_connect_error', self::CONFIGURATION_ERROR_MESSAGE);
        }

        $state = Str::random(self::OAUTH_STATE_LENGTH);
        $request->session()->put('stripe_connect_oauth', [
            'tenant_id' => $tenant->id,
            'state' => $state,
        ]);

        return redirect()->away('https://connect.stripe.com/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.stripe.client_id'),
            'scope' => 'read_write',
            'state' => $state,
            'redirect_uri' => route('stripe.connect.callback'),
        ]));
    }

    public function callback(Request $request): RedirectResponse
    {
        $tenant = $this->authorizedTenant($request);
        $oauth = $request->session()->get('stripe_connect_oauth');

        abort_unless(
            is_array($oauth)
                && ($oauth['tenant_id'] ?? null) === $tenant->id
                && hash_equals((string) ($oauth['state'] ?? ''), (string) $request->query('state')),
            403,
        );

        $code = $request->query('code');
        abort_unless(is_string($code) && filled($code), 400, 'Missing Stripe Connect authorization code.');

        try {
            $token = $this->stripeService()->exchangeConnectAuthorizationCode($code);
        } catch (Throwable $exception) {
            return $this->failedCallbackRedirect($tenant, 'token_exchange', $exception);
        }

        $connectedAccountId = $token['stripe_user_id'] ?? null;
        abort_unless(is_string($connectedAccountId) && filled($connectedAccountId), 400, 'Stripe Connect account was not returned.');

        try {
            $account = $this->stripeService()->retrieveConnectAccount($connectedAccountId);
        } catch (Throwable $exception) {
            return $this->failedCallbackRedirect($tenant, 'account_retrieval', $exception, $connectedAccountId);
        }

        $chargesEnabled = (bool) data_get($account, 'charges_enabled', false);
        $payoutsEnabled = (bool) data_get($account, 'payouts_enabled', false);
        $detailsSubmitted = (bool) data_get($account, 'details_submitted', false);

        $tenant->syncStripeConnectAccount($connectedAccountId, $chargesEnabled, $payoutsEnabled, $detailsSubmitted);

        $request->session()->forget('stripe_connect_oauth');

        return redirect('/tenant');
    }

    private function authorizedTenant(Request $request): Tenant
    {
        $user = $request->user();

        abort_unless(
            $user !== null
                && $user->role === UserRole::BusinessAdmin
                && $user->tenant instanceof Tenant,
            403,
        );

        return $user->tenant;
    }

    private function stripeConnectIsConfigured(): bool
    {
        return filled(config('services.stripe.secret'))
            && filled(config('services.stripe.client_id'))
            && filled(config('services.stripe.connect_webhook_secret'));
    }

    private function failedCallbackRedirect(
        Tenant $tenant,
        string $stage,
        Throwable $exception,
        ?string $connectedAccountId = null,
    ): RedirectResponse {
        Log::warning('Stripe Connect OAuth callback failed.', array_filter([
            'tenant_id' => $tenant->id,
            'stage' => $stage,
            'connected_account_id' => $connectedAccountId,
            'exception' => $exception::class,
        ], fn ($value) => $value !== null));

        return redirect('/tenant')
            ->with('stripe_connect_error', self::ONBOARDING_ERROR_MESSAGE);
    }

    private function stripeService(): StripeService
    {
        if (app()->bound(StripeService::class)) {
            return app(StripeService::class);
        }

        abort_unless(filled(config('services.stripe.secret')), 500, 'Stripe secret key is not configured.');

        return new StripeService(config('services.stripe.secret'));
    }
}
