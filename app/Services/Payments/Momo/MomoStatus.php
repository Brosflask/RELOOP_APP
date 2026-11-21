<?php

namespace App\Services\Payments\Momo;

/** Normalised result of GET /collection/v1_0/requesttopay/{referenceId}. */
final readonly class MomoStatus
{
    public const PENDING = 'pending';
    public const SUCCESSFUL = 'successful';
    public const FAILED = 'failed';
    public const NOT_FOUND = 'not_found';

    public function __construct(
        public string $state,
        public ?string $amount = null,
        public ?string $currency = null,
        public ?string $financialTransactionId = null,
        public ?string $reason = null,
        public int $httpStatus = 200,
        public array $raw = [],
    ) {
    }

    public static function fromResponse(int $httpStatus, array $body): self
    {
        if ($httpStatus === 404) {
            return new self(self::NOT_FOUND, httpStatus: 404, raw: $body);
        }

        $state = match (strtoupper((string) ($body['status'] ?? ''))) {
            'SUCCESSFUL' => self::SUCCESSFUL,
            'FAILED', 'REJECTED', 'TIMEOUT' => self::FAILED,
            default => self::PENDING,
        };

        $reason = $body['reason'] ?? null;
        if (is_array($reason)) {
            $reason = $reason['message'] ?? $reason['code'] ?? json_encode($reason);
        }

        return new self(
            state: $state,
            amount: isset($body['amount']) ? (string) $body['amount'] : null,
            currency: $body['currency'] ?? null,
            financialTransactionId: isset($body['financialTransactionId']) ? (string) $body['financialTransactionId'] : null,
            reason: $reason,
            httpStatus: $httpStatus,
            raw: $body,
        );
    }

    /** Amount comparison in minor units, so "80", "80.0" and "80.00" match. */
    public function amountMatches(string $expected): bool
    {
        if ($this->amount === null || ! is_numeric($this->amount)) {
            return false;
        }

        return (int) round(((float) $this->amount) * 100) === (int) round(((float) $expected) * 100);
    }
}
