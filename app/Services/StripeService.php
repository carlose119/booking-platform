<?php

namespace App\Services;

use App\Services\DTOs\PaymentIntentResult;
use App\Services\DTOs\RefundResult;
use App\Support\Currency;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeService
{
    private StripeClient $client;

    public function __construct(string|StripeClient $apiKeyOrClient)
    {
        $this->client = $apiKeyOrClient instanceof StripeClient
            ? $apiKeyOrClient
            : new StripeClient($apiKeyOrClient);
    }

    /**
     * Retrieve a Stripe event by ID.
     *
     * @throws ApiErrorException
     */
    public function retrieveEvent(string $eventId, array $stripeOptions = []): object
    {
        if ($stripeOptions === []) {
            return $this->client->events->retrieve($eventId);
        }

        return $this->client->events->retrieve($eventId, $stripeOptions);
    }

    /**
     * Create a Stripe PaymentIntent.
     *
     * @throws ApiErrorException
     */
    public function createPaymentIntent(int $amountCents, string $currency, array $metadata = [], array $stripeOptions = []): PaymentIntentResult
    {
        $currency = Currency::ensureSupportedForStripe($currency);

        $params = [
            'amount' => $amountCents,
            'currency' => $currency,
            'metadata' => $metadata,
        ];

        $paymentIntent = $stripeOptions === []
            ? $this->client->paymentIntents->create($params)
            : $this->client->paymentIntents->create($params, $stripeOptions);

        return new PaymentIntentResult(
            id: $paymentIntent->id,
            clientSecret: $paymentIntent->client_secret,
            amount: $paymentIntent->amount,
            status: $paymentIntent->status,
        );
    }

    /**
     * Create a refund for a PaymentIntent.
     *
     * @throws ApiErrorException
     */
    public function createRefund(string $paymentIntentId, ?int $amountCents = null, array $stripeOptions = []): RefundResult
    {
        $params = ['payment_intent' => $paymentIntentId];

        if ($amountCents !== null) {
            $params['amount'] = $amountCents;
        }

        $refund = $stripeOptions === []
            ? $this->client->refunds->create($params)
            : $this->client->refunds->create($params, $stripeOptions);

        return new RefundResult(
            id: $refund->id,
            status: $refund->status,
            amount: $refund->amount,
        );
    }
}
