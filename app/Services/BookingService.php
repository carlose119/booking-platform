<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Jobs\SendBookingNotification;
use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Stripe\StripeAccountContext;
use App\Support\Currency;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BookingService
{
    /**
     * Create a hold on a time slot.
     *
     * Returns the BookingHold. Throws UniqueConstraintViolationException
     * if another active hold already exists for this slot.
     */
    public function createHold(
        int $tenantId,
        int $employeeId,
        int $serviceId,
        string $date,
        string $startTime,
        string $endTime,
        ?string $sessionId = null,
    ): BookingHold {
        $tenant = Tenant::findOrFail($tenantId);
        $holdTtlMinutes = $this->getHoldTtl($tenant);

        $attributes = [
            'tenant_id' => $tenantId,
            'employee_id' => $employeeId,
            'service_id' => $serviceId,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'session_id' => $sessionId ?? Str::random(40),
            'expires_at' => Carbon::now()->addMinutes($holdTtlMinutes),
        ];

        if (Schema::hasColumn('booking_holds', 'active_slot_key')) {
            $attributes['active_slot_key'] = BookingHold::ACTIVE_SLOT_KEY;
        }

        return BookingHold::create($attributes);
    }

    /**
     * Confirm a booking from an active hold.
     *
     * Branches based on tenant payment_policy:
     * - nopayment: status=confirmed, payment_status=unpaid (immediate confirmation)
     * - 100upfront / fraction: status=pending, payment_status=unpaid (awaiting payment)
     *
     * Returns the booking. Throws if the hold is expired or not found.
     */
    public function confirmBooking(
        int $holdId,
        int $tenantId,
        string $clientName,
        string $clientEmail,
        string $clientPhone,
        ?string $notificationChannel = null,
    ): Booking {
        $notificationChannel = $this->normalizeNotificationChannel($notificationChannel);

        $hold = BookingHold::where('tenant_id', $tenantId)
            ->findOrFail($holdId);

        if ($hold->expires_at->isPast()) {
            $hold->delete();
            abort(422, 'This hold has expired. Please select a new slot.');
        }

        $tenant = Tenant::findOrFail($tenantId);
        $service = Service::findOrFail($hold->service_id);

        $paymentStatus = $this->resolveInitialPaymentStatus($tenant);
        $status = $tenant->payment_policy === 'nopayment' ? 'confirmed' : 'pending';

        $booking = Booking::create([
            'tenant_id' => $hold->tenant_id,
            'service_id' => $hold->service_id,
            'employee_id' => $hold->employee_id,
            'client_name' => $clientName,
            'client_email' => $clientEmail,
            'client_phone' => $clientPhone,
            'date' => $hold->date,
            'start_time' => $hold->start_time,
            'end_time' => $hold->end_time,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'notification_channel' => $notificationChannel,
        ]);

        $hold->delete();

        // Dispatch confirmation notification for nopayment bookings
        if ($status === 'confirmed') {
            SendBookingNotification::dispatch($booking, 'confirmed');
        }

        return $booking;
    }

    /**
     * Calculate the payment amount in cents based on tenant policy.
     *
     * Returns null for nopayment tenants, or the amount to charge.
     */
    public function calculatePaymentAmount(Tenant $tenant, int $servicePriceCents): ?int
    {
        return match ($tenant->payment_policy) {
            'nopayment' => null,
            '100upfront' => $servicePriceCents,
            'fraction' => (int) ceil($servicePriceCents * ($tenant->deposit_percentage ?? 100) / 100),
            default => null,
        };
    }

    public function snapshotPaymentForStripe(Booking $booking, Tenant $tenant, Service $service, ?StripeAccountContext $accountContext = null): ?array
    {
        if ($booking->tenant_id !== $tenant->id || $service->tenant_id !== $tenant->id || $booking->service_id !== $service->id) {
            abort(404);
        }

        $amountCents = $this->calculatePaymentAmount($tenant, $service->price_cents);

        if ($amountCents === null) {
            return null;
        }

        $currency = Currency::normalize($tenant->currency());

        $snapshot = [
            'payment_amount_cents' => $amountCents,
            'payment_currency' => $currency,
        ];

        if ($accountContext !== null) {
            $snapshot['payment_account_mode'] = $accountContext->mode;
            $snapshot['stripe_connected_account_id'] = $accountContext->connectedAccountId;
        }

        $booking->update($snapshot);

        return [
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'stripe_options' => $accountContext?->stripeOptions() ?? [],
        ];
    }

    public function cancelBooking(
        int $bookingId,
        int $tenantId,
        int $actorUserId,
        string $reason,
    ): Booking {
        $reason = trim($reason);

        if ($reason === '') {
            abort(422, 'A cancellation reason is required.');
        }

        return DB::transaction(function () use ($bookingId, $tenantId, $actorUserId, $reason): Booking {
            $actor = User::where('tenant_id', $tenantId)->findOrFail($actorUserId);

            if ($actor->role === UserRole::Client) {
                throw new AuthorizationException('Clients cannot cancel bookings through the business cancellation workflow.');
            }

            $booking = Booking::where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->findOrFail($bookingId);

            if ($booking->status === 'cancelled') {
                return $booking;
            }

            $booking->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'cancelled_by_user_id' => $actor->id,
            ]);

            SendBookingNotification::dispatch($booking, 'cancelled', $reason);

            if (in_array($booking->payment_status, ['paid', 'partial'], true)) {
                Artisan::queue('booking:auto-refund');
            }

            return $booking->refresh();
        });
    }

    public function rescheduleBooking(
        int $bookingId,
        int $tenantId,
        int $actorUserId,
        string $date,
        string $startTime,
        string $endTime,
        ?string $reason = null,
    ): Booking {
        $reason = trim((string) $reason) ?: null;

        return DB::transaction(function () use ($bookingId, $tenantId, $actorUserId, $date, $startTime, $endTime, $reason): Booking {
            $actor = User::where('tenant_id', $tenantId)->findOrFail($actorUserId);

            if ($actor->role !== UserRole::BusinessAdmin) {
                throw new AuthorizationException('Only business admins can reschedule bookings.');
            }

            $booking = Booking::where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->findOrFail($bookingId);

            if (in_array($booking->status, ['cancelled', 'completed'], true)) {
                abort(422, 'Cancelled or completed bookings cannot be rescheduled.');
            }

            $originalDate = $booking->date->toDateString();
            $originalStart = $booking->start_time->format('H:i');
            $originalEnd = $booking->end_time->format('H:i');

            $availability = app(AvailabilityService::class)->getAvailableSlots(
                serviceId: $booking->service_id,
                date: $date,
                tenantId: $tenantId,
                excludeBookingId: $booking->id,
            );
            $employeeSlots = collect($availability[$booking->employee_id]['slots'] ?? []);
            $targetSlot = $employeeSlots->first(fn (array $slot): bool => $slot['start'] === $startTime && $slot['end'] === $endTime);

            if (! $targetSlot || ! ($targetSlot['available'] ?? false)) {
                abort(422, 'The selected time slot is not available.');
            }

            $booking->update([
                'previous_date' => $originalDate,
                'previous_start_time' => $originalStart,
                'previous_end_time' => $originalEnd,
                'date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'rescheduled_by' => $actor->id,
                'reschedule_reason' => $reason,
            ]);

            SendBookingNotification::dispatch($booking->refresh(), 'rescheduled', null, $originalDate, "{$originalStart} - {$originalEnd}");

            return $booking->refresh();
        });
    }

    /**
     * Delete all expired holds. Returns the number of holds deleted.
     */
    public function expireHolds(): int
    {
        return BookingHold::where('expires_at', '<', now())->delete();
    }

    /**
     * Get hold TTL in minutes based on tenant payment policy.
     * Payment-required holds get 15 minutes; nopayment gets default (10).
     */
    private function getHoldTtl(Tenant $tenant): int
    {
        if ($tenant->payment_policy !== 'nopayment') {
            return 15;
        }

        return config('booking.hold_ttl_minutes', 10);
    }

    /**
     * Resolve initial payment status for a new booking.
     */
    private function resolveInitialPaymentStatus(Tenant $tenant): string
    {
        return match ($tenant->payment_policy) {
            'nopayment' => 'unpaid', // no payment required, stays unpaid
            '100upfront', 'fraction' => 'unpaid', // awaiting payment via Stripe
            default => 'unpaid',
        };
    }

    private function normalizeNotificationChannel(?string $channel): string
    {
        if ($channel === null || trim($channel) === '') {
            return 'email';
        }

        $normalized = strtolower(trim($channel));

        if (! in_array($normalized, ['email', 'sms', 'both'], true)) {
            abort(422, 'Invalid notification channel.');
        }

        return $normalized;
    }
}
