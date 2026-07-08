<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Models\Tenant;
use App\Services\StripeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public string $eventId,
        public int $tenantId,
    ) {}

    public function handle(): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);

        if (! $tenant->stripe_api_key || ! $tenant->stripe_webhook_secret) {
            Log::warning("Tenant {$this->tenantId} missing Stripe configuration for webhook {$this->eventId}");

            return;
        }

        $stripeService = app(StripeService::class, ['apiKeyOrClient' => $tenant->stripe_api_key]);

        try {
            $event = $stripeService->retrieveEvent($this->eventId);
        } catch (\Exception $e) {
            Log::error("Failed to retrieve Stripe event {$this->eventId}: {$e->getMessage()}");

            throw $e;
        }

        if ($event->type === 'payment_intent.succeeded') {
            $this->handlePaymentSucceeded($event->data->object);
        } elseif ($event->type === 'payment_intent.payment_failed') {
            $this->handlePaymentFailed($event->data->object);
        }
    }

    private function handlePaymentSucceeded(object $paymentIntent): void
    {
        $booking = Booking::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if (! $booking) {
            Log::warning("No booking found for payment_intent: {$paymentIntent->id}");

            return;
        }

        // Idempotency guard: skip if already paid
        if ($booking->payment_status === 'paid' || $booking->payment_status === 'partial') {
            return;
        }

        $tenant = Tenant::findOrFail($booking->tenant_id);
        $paymentStatus = $tenant->payment_policy === 'fraction' ? 'partial' : 'paid';

        $booking->update([
            'payment_status' => $paymentStatus,
            'status' => 'confirmed',
        ]);
    }

    private function handlePaymentFailed(object $paymentIntent): void
    {
        $booking = Booking::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if (! $booking) {
            Log::warning("No booking found for failed payment_intent: {$paymentIntent->id}");

            return;
        }

        Log::warning("Payment failed for booking {$booking->id}: {$paymentIntent->last_payment_error?->message}");
    }
}
