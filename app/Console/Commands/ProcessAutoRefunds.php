<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Tenant;
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

            if (! $tenant || ! $tenant->stripe_api_key) {
                continue;
            }

            $refundWindowHours = $tenant->refund_window_hours ?? 24;

            if ($booking->cancelled_at->diffInHours(now()) > $refundWindowHours) {
                continue;
            }

            try {
                $stripeService = app(StripeService::class, ['apiKeyOrClient' => $tenant->stripe_api_key]);
                $stripeService->createRefund($booking->stripe_payment_intent_id);

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
