<?php

namespace App\Services\Stripe;

use App\Models\Booking;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;

class StripeAccountResolver
{
    public function forTenantCharges(Tenant $tenant): StripeAccountContext
    {
        if ($tenant->usesStripeConnect()) {
            return new StripeAccountContext(
                mode: Tenant::PAYMENT_ACCOUNT_CONNECT,
                apiKey: config('services.stripe.secret'),
                connectedAccountId: $tenant->stripe_connected_account_id,
                webhookSecret: config('services.stripe.connect_webhook_secret'),
                chargesReady: $tenant->hasActiveConnectCharges(),
            );
        }

        return new StripeAccountContext(
            mode: Tenant::PAYMENT_ACCOUNT_DIRECT,
            apiKey: $tenant->stripe_api_key,
            connectedAccountId: null,
            webhookSecret: $tenant->stripe_webhook_secret,
            chargesReady: $tenant->hasDirectStripeCredentials(),
        );
    }

    public function forTenantDirect(Tenant $tenant): StripeAccountContext
    {
        return new StripeAccountContext(
            mode: Tenant::PAYMENT_ACCOUNT_DIRECT,
            apiKey: $tenant->stripe_api_key,
            connectedAccountId: null,
            webhookSecret: $tenant->stripe_webhook_secret,
            chargesReady: $tenant->hasDirectStripeCredentials(),
        );
    }

    public function forBookingRefund(Booking $booking): StripeAccountContext
    {
        $booking->loadMissing('tenant');

        if ($booking->resolvedPaymentAccountMode() === Tenant::PAYMENT_ACCOUNT_CONNECT) {
            return new StripeAccountContext(
                mode: Tenant::PAYMENT_ACCOUNT_CONNECT,
                apiKey: config('services.stripe.secret'),
                connectedAccountId: $booking->resolvedStripeConnectedAccountId(),
                webhookSecret: config('services.stripe.connect_webhook_secret'),
                chargesReady: filled($booking->resolvedStripeConnectedAccountId()),
            );
        }

        return $this->forTenantDirect($booking->tenant);
    }

    public function forConnectedWebhook(Tenant $tenant, string $connectedAccountId): StripeAccountContext
    {
        return new StripeAccountContext(
            mode: Tenant::PAYMENT_ACCOUNT_CONNECT,
            apiKey: config('services.stripe.secret'),
            connectedAccountId: $connectedAccountId,
            webhookSecret: config('services.stripe.connect_webhook_secret'),
            chargesReady: filled($connectedAccountId),
        );
    }

    public function tenantForConnectedAccount(string $connectedAccountId): ?Tenant
    {
        $tenants = $this->tenantsForConnectedAccount($connectedAccountId);

        if ($tenants->count() !== 1) {
            return null;
        }

        return $tenants->first();
    }

    public function connectedAccountTenantCount(string $connectedAccountId): int
    {
        return $this->tenantsForConnectedAccount($connectedAccountId)->count();
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function tenantsForConnectedAccount(string $connectedAccountId): Collection
    {
        return Tenant::query()
            ->where('payment_account_mode', Tenant::PAYMENT_ACCOUNT_CONNECT)
            ->where('stripe_connected_account_id', $connectedAccountId)
            ->limit(2)
            ->get();
    }
}
