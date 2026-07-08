<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Refunded = 'refunded';
    case Partial = 'partial';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
