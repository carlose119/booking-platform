<?php

namespace App\Services;

use App\Channels\SmsChannel;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingCancelled;
use App\Notifications\BookingConfirmed;
use App\Notifications\BookingReminder;
use App\Notifications\BookingRescheduled;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    /**
     * Send booking confirmation to the client.
     */
    public function sendBookingConfirmed(Booking $booking): void
    {
        $client = $this->resolveClient($booking);
        if (! $client) {
            return;
        }

        $this->dispatchNotification($client, new BookingConfirmed($booking), $client->notification_channel ?? null);
    }

    /**
     * Send booking reminder to the client.
     */
    public function sendBookingReminder(Booking $booking): void
    {
        $client = $this->resolveClient($booking);
        if (! $client) {
            return;
        }

        $this->dispatchNotification($client, new BookingReminder($booking), $client->notification_channel ?? null);
    }

    /**
     * Send booking cancellation to the client.
     */
    public function sendBookingCancelled(Booking $booking, ?string $reason = null): void
    {
        $client = $this->resolveClient($booking);
        if (! $client) {
            return;
        }

        $this->dispatchNotification($client, new BookingCancelled($booking, $reason), $client->notification_channel ?? null);
    }

    /**
     * Send booking reschedule notification to the client.
     */
    public function sendBookingRescheduled(Booking $booking, string $originalDate, string $originalTime): void
    {
        $client = $this->resolveClient($booking);
        if (! $client) {
            return;
        }

        $this->dispatchNotification($client, new BookingRescheduled($booking, $originalDate, $originalTime), $client->notification_channel ?? null);
    }

    /**
     * Dispatch notification to user via their preferred channel(s).
     *
     * Channel routing is handled by each Notification class's via() method,
     * which reads the user's notification_channel preference.
     */
    protected function dispatchNotification(User $user, object $notification, ?string $channel = null): void
    {
        if ($channel) {
            $user->notify($notification, [$channel]);
        } else {
            $user->notify($notification);
        }
    }

    /**
     * Resolve the client User model from booking.
     */
    protected function resolveClient(Booking $booking): ?User
    {
        // If booking has a client_id, load the User model
        if ($booking->client_id) {
            return $booking->client;
        }

        // For guest bookings (no client_id), return null
        // Guest bookings don't have a User model to notify
        return null;
    }
}
