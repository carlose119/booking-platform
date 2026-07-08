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
    public function retrieveEvent(string $eventId): object
    {
        return $this->client->events->retrieve($eventId);
    }

    /**
     * Create a Stripe PaymentIntent.
     *
     * @throws ApiErrorException
     */
    public function createPaymentIntent(int $amountCents, string $currency, array $metadata = []): PaymentIntentResult
    {
        $currency = Currency::ensureSupportedForStripe($currency);

        $paymentIntent = $this->client->paymentIntents->create([
            'amount' => $amountCents,
            'currency' => $currency,
            'metadata' => $metadata,
        ]);

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
    public function createRefund(string $paymentIntentId, ?int $amountCents = null): RefundResult
    {
        $params = ['payment_intent' => $paymentIntentId];

        if ($amountCents !== null) {
            $params['amount'] = $amountCents;
        }

        $refund = $this->client->refunds->create($params);

        return new RefundResult(
            id: $refund->id,
            status: $refund->status,
            amount: $refund->amount,
        );
    }
}
