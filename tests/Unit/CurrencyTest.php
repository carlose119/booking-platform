<?php

namespace Tests\Unit;

use App\Support\Currency;
use InvalidArgumentException;
use Tests\TestCase;

class CurrencyTest extends TestCase
{
    public function test_normalize_falls_back_to_usd_for_missing_currency_and_lowercases_supported_codes(): void
    {
        $this->assertSame('usd', Currency::normalize(null));
        $this->assertSame('eur', Currency::normalize(' EUR '));
    }

    public function test_options_returns_supported_currency_labels(): void
    {
        $this->assertSame('US Dollar (USD)', Currency::options()['usd']);
        $this->assertSame('Euro (EUR)', Currency::options()['eur']);
    }

    public function test_format_uses_minor_units_without_fx_conversion(): void
    {
        $this->assertSame('$12.34', Currency::format(1234, 'usd'));
        $this->assertSame('€12.34', Currency::format(1234, 'eur'));
    }

    public function test_unsupported_stripe_currency_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Currency::ensureSupportedForStripe('doge');
    }
}
