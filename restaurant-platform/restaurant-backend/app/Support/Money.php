<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Every public API price is rendered as {amount_minor, formatted, currency}
 * — see docs/API_MENU.md. `amount_minor` is always the raw integer stored
 * in the database (fils/cents); `formatted` divides it by the currency's
 * minor-unit exponent (ISO 4217), which is 3 decimal places for JOD/KWD/BHD/
 * OMR, 0 for JPY/KRW, and 2 for everything else (USD, EUR, SAR, AED, ...).
 */
final class Money
{
    /** @var array<string, int> */
    private const array MINOR_UNIT_DECIMALS = [
        'JOD' => 3,
        'KWD' => 3,
        'BHD' => 3,
        'OMR' => 3,
        'JPY' => 0,
        'KRW' => 0,
    ];

    /**
     * @return array{amount_minor: int, formatted: string, currency: string}
     */
    public static function format(int $amountMinor, string $currencyCode): array
    {
        $decimals = self::MINOR_UNIT_DECIMALS[$currencyCode] ?? 2;

        return [
            'amount_minor' => $amountMinor,
            'formatted' => number_format($amountMinor / (10 ** $decimals), $decimals),
            'currency' => $currencyCode,
        ];
    }
}
