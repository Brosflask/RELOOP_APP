<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Services\Payments\Momo\MomoException;
use App\Services\Payments\Momo\MomoRequestResult;
use App\Services\Payments\Momo\MomoStatus;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Thin client for the MTN MoMo Collection API. It talks to MTN and normalises
 * responses. It holds no business rules: deciding what a status means for an
 * order is PaymentService's job.
 */
class MtnMomoService
{
    private const TOKEN_CACHE_KEY = 'mtn_momo.collection.token';

    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? config('mtn_momo');
    }

    public function accessToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $this->assertConfigured();

        try {
            $response = $this->baseRequest()
                ->withBasicAuth($this->config['api_user'], $this->config['api_key'])
                ->post('/collection/token/');
        } catch (ConnectionException $e) {
            throw new MomoException('Could not reach MTN MoMo for an access token.', previous: $e);
        }

        $token = $response->json('access_token');
        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new MomoException('MTN MoMo token request failed with HTTP '.$response->status().'.');
        }

        // Refresh a minute before MTN expires it.
        $ttl = max(60, ((int) $response->json('expires_in', 3600)) - 60);
        Cache::put(self::TOKEN_CACHE_KEY, $token, $ttl);

        return $token;
    }

    /**
     * Submit a RequestToPay. Never retried automatically: re-sending the same
     * reference is reported by MTN as a duplicate (409), which we treat as
     * "already submitted", not as a new charge.
     */
    public function requestToPay(Payment $payment, ?string $payerMessage = null, ?string $payeeNote = null): MomoRequestResult
    {
        $headers = [
            'X-Reference-Id' => $payment->transaction_reference,
            'X-Target-Environment' => $this->config['environment'],
        ];

        if ($callbackUrl = $this->callbackUrl()) {
            $headers['X-Callback-Url'] = $callbackUrl;
        }

        try {
            $response = $this->authorisedRequest()
                ->withHeaders($headers)
                ->post('/collection/v1_0/requesttopay', [
                    'amount' => $this->formatAmount((string) $payment->amount),
                    'currency' => $this->config['currency'],
                    'externalId' => $payment->external_id,
                    'payer' => [
                        'partyIdType' => 'MSISDN',
                        'partyId' => $payment->payer_msisdn,
                    ],
                    'payerMessage' => mb_substr($payerMessage ?? 'Reloop order '.$payment->external_id, 0, 160),
                    'payeeNote' => mb_substr($payeeNote ?? 'Reloop order '.$payment->external_id, 0, 160),
                ]);
        } catch (ConnectionException $e) {
            throw new MomoException('Could not reach MTN MoMo to request payment.', previous: $e);
        }

        if ($response->status() === 401) {
            Cache::forget(self::TOKEN_CACHE_KEY);
        }

        $body = $response->json();

        return new MomoRequestResult(
            accepted: $response->status() === 202 || $response->status() === 409,
            httpStatus: $response->status(),
            body: is_array($body) ? $body : null,
            duplicate: $response->status() === 409,
        );
    }

    /** The only source of truth for whether a payment succeeded. */
    public function getTransactionStatus(string $referenceId): MomoStatus
    {
        try {
            $response = $this->authorisedRequest()
                ->withHeaders(['X-Target-Environment' => $this->config['environment']])
                ->retry(2, 500, fn ($e) => $e instanceof ConnectionException, throw: false)
                ->get('/collection/v1_0/requesttopay/'.rawurlencode($referenceId));
        } catch (ConnectionException $e) {
            throw new MomoException('Could not reach MTN MoMo for a status check.', previous: $e);
        }

        if ($response->status() === 401) {
            Cache::forget(self::TOKEN_CACHE_KEY);
        }

        if (! $response->successful() && $response->status() !== 404) {
            throw new MomoException('MTN MoMo status check failed with HTTP '.$response->status().'.');
        }

        $body = $response->json();

        return MomoStatus::fromResponse($response->status(), is_array($body) ? $body : []);
    }

    public function callbackUrl(): ?string
    {
        $host = $this->config['callback_host'] ?? null;
        $token = $this->config['callback_token'] ?? null;

        if (! $host || ! $token) {
            return null;
        }

        return rtrim($host, '/').'/api/payments/momo/callback/'.$token;
    }

    public function isSandbox(): bool
    {
        return $this->config['environment'] === 'sandbox';
    }

    private function formatAmount(string $amount): string
    {
        // MTN expects a string amount. Drop a ".00" so whole amounts are sent as integers.
        return preg_replace('/\.00$/', '', number_format((float) $amount, 2, '.', ''));
    }

    private function baseRequest(): PendingRequest
    {
        return Http::baseUrl($this->config['base_url'])
            ->timeout($this->config['timeout'] ?? 10)
            ->acceptJson()
            ->withHeaders(['Ocp-Apim-Subscription-Key' => $this->config['subscription_key']]);
    }

    private function authorisedRequest(): PendingRequest
    {
        return $this->baseRequest()->withToken($this->accessToken());
    }

    private function assertConfigured(): void
    {
        foreach (['subscription_key', 'api_user', 'api_key'] as $key) {
            if (empty($this->config[$key])) {
                throw new MomoException("MTN MoMo is not configured: missing mtn_momo.$key.");
            }
        }
    }
}
