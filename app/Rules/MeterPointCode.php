<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * BR-1: a metering point code is exactly 33 characters,
 * uppercase letters and digits only.
 * (Uniqueness is a separate `unique` rule plus the unique index.)
 */
class MeterPointCode implements ValidationRule
{
    public const LENGTH = 33;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // \A and \z anchor the whole string; `$` would also accept a trailing newline.
        if (! is_string($value) || preg_match('/\A[A-Z0-9]{'.self::LENGTH.'}\z/', $value) !== 1) {
            $fail('The :attribute must be exactly '.self::LENGTH.' characters: uppercase letters and digits only.');
        }
    }
}
