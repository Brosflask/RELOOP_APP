<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A request that is well-formed but not allowed by the current state, for
 * example accepting an offer that has expired or shipping an unpaid order.
 * Inertia requests get the message back as a form error on `$field`;
 * JSON requests get 409.
 */
class BusinessRuleException extends RuntimeException
{
    public function __construct(string $message, public readonly string $field = 'general')
    {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage(), 'field' => $this->field], 409);
        }

        return back()->withErrors([$this->field => $this->getMessage()]);
    }
}
