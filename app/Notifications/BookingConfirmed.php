<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use App\Notifications\Messages\SmsMessage;
use Illuminate\Notifications\Notification;

class BookingConfirmed extends Notification
{
    use Queueable;

    public function __construct(
        public Booking $booking,
    ) {}

    /**
     * Determine which notification channels to use based on user preference.
     */
    public function via(User $notifiable): array
    {
        return match ($notifiable->notification_channel ?? 'email') {
            'email' => ['mail'],
            'sms' => [\App\Channels\SmsChannel::class],
            'both' => ['mail', \App\Channels\SmsChannel::class],
            default => ['mail'],
        };
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(User $notifiable): MailMessage
    {
        $service = $this->booking->service;
        $tenant = $this->booking->tenant;

        return (new MailMessage)
            ->subject("Booking Confirmed - {$tenant->name}")
            ->line("Your booking has been confirmed!")
            ->line("Service: {$service->name}")
            ->line("Date: {$this->booking->date->format('F j, Y')}")
            ->line("Time: {$this->booking->start_time->format('g:i A')} - {$this->booking->end_time->format('g:i A')}")
            ->line("Business: {$tenant->name}")
            ->line("Confirmation #: {$this->booking->id}")
            ->line("Thank you for booking with us!");
    }

    /**
     * Get the SMS representation of the notification.
     */
    public function toSms(User $notifiable): SmsMessage
    {
        $service = $this->booking->service;
        $tenant = $this->booking->tenant;

        return (new SmsMessage)
            ->body("Booking Confirmed! {$service->name} on {$this->booking->date->format('M j, Y')} at {$this->booking->start_time->format('g:i A')} with {$tenant->name}. Conf #{$this->booking->id}");
    }
}
