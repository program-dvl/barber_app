<?php

namespace App\Domain\Reporting\Services;

final class ReportMoney
{
    public static function digits(string $currency): int
    {
        if (class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter('en', \NumberFormatter::CURRENCY);
            $formatter->setTextAttribute(\NumberFormatter::CURRENCY_CODE, strtoupper($currency));

            return (int) $formatter->getAttribute(\NumberFormatter::FRACTION_DIGITS);
        }

        return in_array($currency, ['JPY', 'KRW', 'CLP', 'VND'], true) ? 0 : (in_array($currency, ['KWD', 'BHD', 'OMR', 'JOD', 'TND'], true) ? 3 : 2);
    }

    public static function decimal(int|float $minor, string $currency): string
    {
        $digits = self::digits($currency);

        return number_format($minor / (10 ** $digits), $digits, '.', '');
    }
}
