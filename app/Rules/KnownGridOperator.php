<?php

namespace App\Rules;

use App\Models\GridOperator;
use App\Models\MeterPoint;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * BR-1: the first 8 characters of a metering point code must be the
 * identifier of an existing (not soft-deleted) grid operator.
 */
class KnownGridOperator implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $identifier = MeterPoint::gridOperatorIdentifierFrom((string) $value);

        if (! GridOperator::where('identifier', $identifier)->exists()) {
            $fail("No grid operator with identifier {$identifier} exists.");
        }
    }
}
