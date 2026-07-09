<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Stripe\StripeAccountResolver;
use App\Services\StripeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessAutoRefunds extends Command
{
    protected $signature = 'booking:auto-refund';

    protected $description = 'Automatically refund paid bookings cancelled within the tenant refund window';

    public function handle(): int
    {
        $bookings = Booking::where('status', 'cancelled')
            ->whereIn('payment_status', ['paid', 'partial'])
            ->whereNotNull('stripe_payment_intent_id')
            ->where('cancelled_at', '>=', now()->subHours(24)) // Global safety: max 24h lookback
            ->with('tenant')
            ->get();

        $refunded = 0;

        foreach ($bookings as $booking) {
            $tenant = $booking->tenant;

            if (! $tenant) {
                continue;
            }

            $refundWindowHours = $tenant->refund_window_hours ?? 24;

            if ($booking->cancelled_at->diffInHours(now()) > $refundWindowHours) {
                continue;
            }

            try {
                $accountContext = app(StripeAccountResolver::class)->forBookingRefund($booking);

                if (blank($accountContext->apiKey)) {
                    continue;
                }

                $stripeService = app(StripeService::class, ['apiKeyOrClient' => $accountContext->apiKey]);
                $stripeOptions = array_merge(
                    $accountContext->stripeOptions(),
                    ['idempotency_key' => "booking:auto-refund:{$booking->id}:{$booking->stripe_payment_intent_id}"]
                );

                $stripeService->createRefund(
                    $booking->stripe_payment_intent_id,
                    null,
                    $stripeOptions,
                );

                $booking->update(['payment_status' => 'refunded']);
                $refunded++;
            } catch (\Exception $e) {
                Log::error("Auto-refund failed for booking {$booking->id}: {$e->getMessage()}");
            }
        }

        $this->info("Processed {$refunded} auto-refund(s).");

        return Command::SUCCESS;
    }
}
