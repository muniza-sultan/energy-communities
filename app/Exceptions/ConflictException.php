<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A request that is allowed in principle but conflicts with the current
 * state of the data.
 * Rendered as 409; policies (who may do it) stay 403.
 */
class ConflictException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
