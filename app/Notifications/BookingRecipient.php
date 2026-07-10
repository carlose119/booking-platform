<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\Tenant;
use Illuminate\Notifications\Notifiable;

final class BookingRecipient
{
    use Notifiable;

    public function __construct(
        public readonly int $bookingId,
        public readonly Tenant $tenant,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly ?string $notification_channel,
    ) {}

    public static function fromBooking(Booking $booking): self
    {
        return new self(
            bookingId: $booking->id,
            tenant: $booking->tenant,
            email: $booking->client_email,
            phone: $booking->client_phone,
            notification_channel: $booking->notification_channel,
        );
    }

    public function getKey(): int
    {
        return $this->bookingId;
    }

    public function routeNotificationForMail(): ?string
    {
        return $this->email;
    }

    public function routeNotificationForSms(): ?string
    {
        return $this->phone;
    }
}
