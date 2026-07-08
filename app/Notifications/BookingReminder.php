<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\User;
use App\Notifications\Messages\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingReminder extends Notification
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
            ->subject("Booking Reminder - {$tenant->name}")
            ->line("This is a reminder for your upcoming booking!")
            ->line("Service: {$service->name}")
            ->line("Date: {$this->booking->date->format('F j, Y')}")
            ->line("Time: {$this->booking->start_time->format('g:i A')} - {$this->booking->end_time->format('g:i A')}")
            ->line("Business: {$tenant->name}")
            ->line("We look forward to seeing you tomorrow!");
    }

    /**
     * Get the SMS representation of the notification.
     */
    public function toSms(User $notifiable): SmsMessage
    {
        $service = $this->booking->service;
        $tenant = $this->booking->tenant;

        return (new SmsMessage)
            ->body("Reminder: {$service->name} tomorrow at {$this->booking->start_time->format('g:i A')} with {$tenant->name}. See you there!");
    }
}
