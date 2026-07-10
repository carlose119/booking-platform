<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\Booking;
use App\Notifications\Messages\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingCancelled extends Notification
{
    use Queueable;

    public function __construct(
        public Booking $booking,
        public ?string $reason = null,
    ) {}

    /**
     * Determine which notification channels to use based on user preference.
     */
    public function via(object $notifiable): array
    {
        return match ($this->normalizeNotificationChannel($notifiable->notification_channel ?? null)) {
            'email' => ['mail'],
            'sms' => [SmsChannel::class],
            'both' => ['mail', SmsChannel::class],
            default => [],
        };
    }

    private function normalizeNotificationChannel(?string $channel): ?string
    {
        if ($channel === null || trim($channel) === '') {
            return 'email';
        }

        $normalized = strtolower(trim($channel));

        return in_array($normalized, ['email', 'sms', 'both'], true) ? $normalized : null;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $service = $this->booking->service;
        $tenant = $this->booking->tenant;

        $message = (new MailMessage)
            ->subject("Booking Cancelled - {$tenant->name}")
            ->line('Your booking has been cancelled.')
            ->line("Service: {$service->name}")
            ->line("Date: {$this->booking->date->format('F j, Y')}")
            ->line("Time: {$this->booking->start_time->format('g:i A')} - {$this->booking->end_time->format('g:i A')}")
            ->line("Business: {$tenant->name}");

        if ($this->reason) {
            $message->line("Reason: {$this->reason}");
        }

        if (in_array($this->booking->payment_status, ['paid', 'partial'], true)) {
            $message->line('A refund will be processed to your original payment method.');
        }

        return $message->line('If you have any questions, please contact the business directly.');
    }

    /**
     * Get the SMS representation of the notification.
     */
    public function toSms(object $notifiable): SmsMessage
    {
        $service = $this->booking->service;
        $tenant = $this->booking->tenant;

        $body = "Booking Cancelled: {$service->name} on {$this->booking->date->format('M j, Y')} with {$tenant->name}";

        if ($this->reason) {
            $body .= ". Reason: {$this->reason}";
        }

        if (in_array($this->booking->payment_status, ['paid', 'partial'], true)) {
            $body .= '. Refund will be processed.';
        }

        return (new SmsMessage)->body($body);
    }
}
