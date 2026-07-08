<?php

namespace Tests\Unit;

use App\Services\DTOs\PaymentIntentResult;
use App\Services\DTOs\RefundResult;
use App\Services\StripeService;
use InvalidArgumentException;
use Mockery;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\StripeClient;
use Tests\TestCase;

class StripeServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ─── createPaymentIntent returns correct DTO ──────────────────────────

    public function test_create_payment_intent_returns_correct_dto(): void
    {
        $paymentIntentData = [
            'id' => 'pi_test_123',
            'client_secret' => 'secret_test_123',
            'amount' => 5000,
            'status' => 'requires_payment_method',
        ];

        $mockPaymentIntents = Mockery::mock();
        $mockPaymentIntents->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn ($params) => $params['amount'] === 5000
                && $params['currency'] === 'usd'
                && $params['metadata'] === ['booking_id' => 42]
            ))
            ->andReturn(PaymentIntent::constructFrom($paymentIntentData));

        $mockClient = Mockery::mock(StripeClient::class);
        $mockClient->paymentIntents = $mockPaymentIntents;

        $service = new StripeService($mockClient);

        $result = $service->createPaymentIntent(5000, 'usd', ['booking_id' => 42]);

        $this->assertInstanceOf(PaymentIntentResult::class, $result);
        $this->assertEquals('pi_test_123', $result->id);
        $this->assertEquals('secret_test_123', $result->clientSecret);
        $this->assertEquals(5000, $result->amount);
        $this->assertEquals('requires_payment_method', $result->status);
    }

    public function test_create_payment_intent_normalizes_supported_currency(): void
    {
        $paymentIntentData = [
            'id' => 'pi_test_eur',
            'client_secret' => 'secret_test_eur',
            'amount' => 5000,
            'status' => 'requires_payment_method',
        ];

        $mockPaymentIntents = Mockery::mock();
        $mockPaymentIntents->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn ($params) => $params['amount'] === 5000
                && $params['currency'] === 'eur'
                && $params['metadata'] === ['booking_id' => 42]
            ))
            ->andReturn(PaymentIntent::constructFrom($paymentIntentData));

        $mockClient = Mockery::mock(StripeClient::class);
        $mockClient->paymentIntents = $mockPaymentIntents;

        $service = new StripeService($mockClient);

        $result = $service->createPaymentIntent(5000, 'EUR', ['booking_id' => 42]);

        $this->assertEquals('pi_test_eur', $result->id);
    }

    public function test_create_payment_intent_rejects_unsupported_currency_before_stripe_call(): void
    {
        $mockPaymentIntents = Mockery::mock();
        $mockPaymentIntents->shouldNotReceive('create');

        $mockClient = Mockery::mock(StripeClient::class);
        $mockClient->paymentIntents = $mockPaymentIntents;

        $service = new StripeService($mockClient);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported Stripe currency [brl].');

        $service->createPaymentIntent(5000, 'brl', ['booking_id' => 42]);
    }

    // ─── createRefund returns correct DTO ─────────────────────────────────

    public function test_create_refund_returns_correct_dto(): void
    {
        $refundData = [
            'id' => 're_test_456',
            'status' => 'succeeded',
            'amount' => 3000,
        ];

        $mockRefunds = Mockery::mock();
        $mockRefunds->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn ($params) => $params['payment_intent'] === 'pi_test_123'
            ))
            ->andReturn(Refund::constructFrom($refundData));

        $mockClient = Mockery::mock(StripeClient::class);
        $mockClient->refunds = $mockRefunds;

        $service = new StripeService($mockClient);

        $result = $service->createRefund('pi_test_123');

        $this->assertInstanceOf(RefundResult::class, $result);
        $this->assertEquals('re_test_456', $result->id);
        $this->assertEquals('succeeded', $result->status);
        $this->assertEquals(3000, $result->amount);
    }

    // ─── createRefund with partial amount ─────────────────────────────────

    public function test_create_refund_with_partial_amount(): void
    {
        $refundData = [
            'id' => 're_test_789',
            'status' => 'succeeded',
            'amount' => 1000,
        ];

        $mockRefunds = Mockery::mock();
        $mockRefunds->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn ($params) => $params['payment_intent'] === 'pi_test_123'
                && $params['amount'] === 1000
            ))
            ->andReturn(Refund::constructFrom($refundData));

        $mockClient = Mockery::mock(StripeClient::class);
        $mockClient->refunds = $mockRefunds;

        $service = new StripeService($mockClient);

        $result = $service->createRefund('pi_test_123', 1000);

        $this->assertEquals(1000, $result->amount);
    }
}
