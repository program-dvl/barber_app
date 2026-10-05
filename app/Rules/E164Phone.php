<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class E164Phone implements ValidationRule
{
    /** Remove display formatting without guessing a country code. */
    public static function normalize(?string $value): ?string
    {
        $value = preg_replace('/[\s().-]+/u', '', (string) $value);

        return $value === '' ? null : $value;
    }

    public static function isValid(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\+[1-9]\d{6,14}$/', $value) === 1;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid($value)) {
            $fail('Enter a valid international phone number.');
        }
    }
}
