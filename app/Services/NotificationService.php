<?php

namespace App\Services;

use App\Channels\SmsChannel;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingCancelled;
use App\Notifications\BookingConfirmed;
use App\Notifications\BookingRecipient;
use App\Notifications\BookingReminder;
use App\Notifications\BookingRescheduled;

class NotificationService
{
    /**
     * Send booking confirmation to the client.
     */
    public function sendBookingConfirmed(Booking $booking): void
    {
        $recipient = $this->resolveRecipient($booking);
        if (! $recipient) {
            return;
        }

        $this->dispatchNotification($recipient, new BookingConfirmed($booking));
    }

    /**
     * Send booking reminder to the client.
     */
    public function sendBookingReminder(Booking $booking): void
    {
        $recipient = $this->resolveRecipient($booking);
        if (! $recipient) {
            return;
        }

        $this->dispatchNotification($recipient, new BookingReminder($booking));
    }

    /**
     * Send booking cancellation to the client.
     */
    public function sendBookingCancelled(Booking $booking, ?string $reason = null): void
    {
        $recipient = $this->resolveRecipient($booking);
        if (! $recipient) {
            return;
        }

        $this->dispatchNotification($recipient, new BookingCancelled($booking, $reason));
    }

    /**
     * Send booking reschedule notification to the client.
     */
    public function sendBookingRescheduled(Booking $booking, string $originalDate, string $originalTime): void
    {
        $recipient = $this->resolveRecipient($booking);
        if (! $recipient) {
            return;
        }

        $this->dispatchNotification($recipient, new BookingRescheduled($booking, $originalDate, $originalTime));
    }

    /**
     * Dispatch notification to a recipient via available preferred channel(s).
     */
    protected function dispatchNotification(object $recipient, object $notification): void
    {
        $channels = $this->channelsFor($recipient);

        if ($channels === []) {
            return;
        }

        $recipient->notifyNow($notification, $channels);
    }

    /**
     * Resolve the notification recipient from booking.
     */
    protected function resolveRecipient(Booking $booking): User|BookingRecipient|null
    {
        if ($booking->client_id) {
            return $booking->client;
        }

        $recipient = BookingRecipient::fromBooking($booking);

        return $this->channelsFor($recipient) === [] ? null : $recipient;
    }

    protected function channelsFor(object $recipient): array
    {
        $channel = $this->normalizeNotificationChannel($recipient->notification_channel ?? null);

        if ($channel === null) {
            return [];
        }

        $channels = match ($channel) {
            'email' => ['mail'],
            'sms' => [SmsChannel::class],
            'both' => ['mail', SmsChannel::class],
        };

        if ($recipient instanceof BookingRecipient) {
            return array_values(array_filter(
                $channels,
                fn (string $channel): bool => match ($channel) {
                    'mail' => filled($recipient->routeNotificationForMail()),
                    SmsChannel::class => filled($recipient->routeNotificationForSms()),
                    default => true,
                },
            ));
        }

        return $channels;
    }

    private function normalizeNotificationChannel(?string $channel): ?string
    {
        if ($channel === null || trim($channel) === '') {
            return 'email';
        }

        $normalized = strtolower(trim($channel));

        return in_array($normalized, ['email', 'sms', 'both'], true) ? $normalized : null;
    }
}
