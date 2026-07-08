<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWebhook;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class WebhookController extends Controller
{
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

        ProcessWebhook::dispatch($event->id, $tenant->id);

        return response()->json(['received' => true]);
    }
}
