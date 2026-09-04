<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class OfferNotAvailableException extends Exception
{
    public function __construct(string $message = 'Offer is no longer available for reservation.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], 409);
    }
}
