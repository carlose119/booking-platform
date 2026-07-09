<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWebhook;
use App\Models\Tenant;
use App\Services\Stripe\StripeAccountResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class WebhookController extends Controller
{
    public function connect(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $secret = config('services.stripe.connect_webhook_secret');

        if (! $secret) {
            return response()->json(['error' => 'Webhook secret not configured'], 400);
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (SignatureVerificationException $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $connectedAccountId = $event->account ?? null;

        if (! $connectedAccountId) {
            Log::warning('Stripe Connect webhook account could not be resolved.', [
                'reason' => 'missing_account',
                'event_id' => $event->id ?? null,
            ]);

            return response()->json(['error' => 'Unresolved connected account'], 400);
        }

        $resolver = app(StripeAccountResolver::class);
        $matchingTenantCount = $resolver->connectedAccountTenantCount($connectedAccountId);

        if ($matchingTenantCount !== 1) {
            Log::warning('Stripe Connect webhook account could not be resolved.', [
                'reason' => $matchingTenantCount === 0 ? 'unknown_account' : 'ambiguous_account',
                'event_id' => $event->id ?? null,
                'connected_account_id' => $connectedAccountId,
                'matching_tenant_count' => $matchingTenantCount,
            ]);

            return response()->json(['error' => 'Unresolved connected account'], 400);
        }

        $tenant = $resolver->tenantForConnectedAccount($connectedAccountId);

        ProcessWebhook::dispatch($event->id, $tenant->id, $connectedAccountId);

        return response()->json(['received' => true]);
    }

    public function __invoke(Request $request, string $tenantSlug): JsonResponse
    {
        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        if (! $tenant->stripe_webhook_secret) {
            return response()->json(['error' => 'Webhook secret not configured'], 400);
        }

        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                $tenant->stripe_webhook_secret,
            );
        } catch (SignatureVerificationException $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        ProcessWebhook::dispatch($event->id, $tenant->id, null, Tenant::PAYMENT_ACCOUNT_DIRECT);

        return response()->json(['received' => true]);
    }
}
