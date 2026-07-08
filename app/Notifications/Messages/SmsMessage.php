<?php

namespace App\Notifications\Messages;

class SmsMessage
{
    public string $body;

    /**
     * Set the message body.
     */
    public function body(string $body): self
    {
        $this->body = $body;

        return $this;
    }
}
