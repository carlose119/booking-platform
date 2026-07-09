<?php

namespace App\Services\Stripe;

use App\Models\Tenant;

final readonly class StripeAccountContext
{
    public function __construct(
        public string $mode,
        public ?string $apiKey,
        public ?string $connectedAccountId = null,
        public ?string $webhookSecret = null,
        public bool $chargesReady = false,
    ) {}

    public function stripeOptions(): array
    {
        if ($this->mode !== Tenant::PAYMENT_ACCOUNT_CONNECT || blank($this->connectedAccountId)) {
            return [];
        }

        return ['stripe_account' => $this->connectedAccountId];
    }

    public function isReadyForCharges(): bool
    {
        return $this->chargesReady;
    }
}
