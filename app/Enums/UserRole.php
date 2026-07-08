<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case BusinessAdmin = 'business_admin';
    case Employee = 'employee';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::BusinessAdmin => 'Business Admin',
            self::Employee => 'Employee',
            self::Client => 'Client',
        };
    }
}
