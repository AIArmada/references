<?php

declare(strict_types=1);

namespace AIArmada\References\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class Isbn implements ValidationRule
{
    public static function normalize(string $value): string
    {
        return mb_strtoupper((string) preg_replace('/[\s-]+/u', '', $value));
    }

    public static function isValid(string $value): bool
    {
        $isbn = self::normalize($value);

        if (preg_match('/^\d{9}[\dX]$/', $isbn) === 1) {
            $sum = 0;

            for ($index = 0; $index < 10; $index++) {
                $digit = $isbn[$index] === 'X' ? 10 : (int) $isbn[$index];
                $sum += $digit * (10 - $index);
            }

            return $sum % 11 === 0;
        }

        if (preg_match('/^97[89]\d{10}$/', $isbn) !== 1) {
            return false;
        }

        $sum = 0;

        for ($index = 0; $index < 13; $index++) {
            $sum += (int) $isbn[$index] * ($index % 2 === 0 ? 1 : 3);
        }

        return $sum % 10 === 0;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isValid($value)) {
            $fail('The :attribute must be a valid ISBN-10 or ISBN-13.');
        }
    }
}
