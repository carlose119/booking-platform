<?php

namespace App\Support;

use InvalidArgumentException;

class Currency
{
    public static function normalize(?string $currency): string
    {
        $currency = strtolower(trim((string) $currency));

        return $currency !== '' ? $currency : self::default();
    }

    public static function default(): string
    {
        return config('currencies.default', 'usd');
    }

    public static function isSupported(?string $currency): bool
    {
        return array_key_exists(self::normalize($currency), self::supported());
    }

    public static function ensureSupportedForStripe(?string $currency): string
    {
        $currency = self::normalize($currency);
        $config = self::supported()[$currency] ?? null;

        if (! ($config['stripe'] ?? false)) {
            throw new InvalidArgumentException("Unsupported Stripe currency [{$currency}].");
        }

        return $currency;
    }

    public static function options(): array
    {
        return collect(self::supported())
            ->mapWithKeys(fn (array $config, string $code): array => [
                $code => sprintf('%s (%s)', $config['label'], strtoupper($code)),
            ])
            ->all();
    }

    public static function format(int $amountCents, ?string $currency): string
    {
        $currency = self::normalize($currency);
        $config = self::supported()[$currency] ?? self::supported()[self::default()];
        $minorUnits = $config['minor_units'] ?? 2;
        $amount = number_format($amountCents / (10 ** $minorUnits), $minorUnits);

        return ($config['symbol'] ?? strtoupper($currency).' ').$amount;
    }

    public static function symbol(?string $currency): string
    {
        $currency = self::normalize($currency);
        $config = self::supported()[$currency] ?? self::supported()[self::default()];

        return $config['symbol'] ?? strtoupper($currency).' ';
    }

    private static function supported(): array
    {
        return config('currencies.supported', []);
    }
}
