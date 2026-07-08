<?php

namespace App\Services\DTOs;

readonly class RefundResult
{
    public function __construct(
        public string $id,
        public string $status,
        public int $amount,
    ) {}
}
