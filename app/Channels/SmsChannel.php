<?php

namespace App\Channels;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Twilio\Rest\Client;

class SmsChannel
{
    /**
     * Send the given notification via SMS.
     */
    public function send(User $notifiable, Notification $notification): void
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

        if (! $notifiable->phone) {
            // User has no phone number — fail silently
            return;
        }

        $client = new Client($sid, $authToken);

        $client->messages->create(
            $notifiable->phone,
            [
                'from' => $fromNumber,
                'body' => $message->body,
            ]
        );
    }
}
