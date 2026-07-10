<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;
use Twilio\Rest\Client;

class SmsChannel
{
    /**
     * Send the given notification via SMS.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        $message = $notification->toSms($notifiable);

        $tenant = $notifiable->tenant;

        // Get Twilio credentials from tenant config, falling back to global config
        $sid = $tenant->twilio_sid ?? config('services.twilio.sid');
        $authToken = $tenant->twilio_auth_token ?? config('services.twilio.auth_token');
        $fromNumber = $tenant->twilio_phone_number ?? config('services.twilio.phone_number');

        if (! $sid || ! $authToken || ! $fromNumber) {
            // Twilio not configured for this tenant — fail silently
            return;
        }

        $phone = method_exists($notifiable, 'routeNotificationForSms')
            ? $notifiable->routeNotificationForSms()
            : ($notifiable->phone ?? null);

        if (! $phone) {
            // User has no phone number — fail silently
            return;
        }

        $this->sendSmsMessage($phone, [
            'from' => $fromNumber,
            'body' => $message->body,
        ], $sid, $authToken);
    }

    protected function sendSmsMessage(string $phone, array $payload, string $sid, string $authToken): void
    {
        (new Client($sid, $authToken))->messages->create($phone, $payload);
    }
}
