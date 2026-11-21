<?php

namespace App\Services\Payments\Momo;

/**
 * Result of submitting a RequestToPay. "Accepted" means MTN will ask the payer
 * to approve. It does NOT mean money moved: only a status check can say that.
 */
final readonly class MomoRequestResult
{
    public function __construct(
        public bool $accepted,
        public int $httpStatus,
        public ?array $body = null,
        public bool $duplicate = false,
    ) {
    }

    public function errorReason(): ?string
    {
        if ($this->accepted) {
            return null;
        }

        return $this->body['message'] ?? $this->body['code'] ?? 'HTTP '.$this->httpStatus;
    }
}
