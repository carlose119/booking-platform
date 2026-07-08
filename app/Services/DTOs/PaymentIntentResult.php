<?php

namespace App\Services\DTOs;

readonly class PaymentIntentResult
{
    public function __construct(
        public string $id,
        public string $clientSecret,
        public int $amount,
        public string $status,
    ) {}
}
