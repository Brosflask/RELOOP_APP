<?php

namespace Tests\Feature\Payments;

use App\Models\Payment;
use App\Services\Payments\Momo\MomoException;
use App\Services\Payments\Momo\MomoStatus;
use App\Services\Payments\MtnMomoService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MtnMomoServiceTest extends TestCase
{
    private const BASE = 'https://momo.test';

    private function service(array $overrides = []): MtnMomoService
    {
        return new MtnMomoService(array_merge([
            'base_url' => self::BASE,
            'environment' => 'sandbox',
            'subscription_key' => 'sub-key',
            'api_user' => 'api-user',
            'api_key' => 'api-key',
            'currency' => 'EUR',
            'callback_host' => 'https://reloop.test',
            'callback_token' => 'secret-token',
            'timeout' => 5,
        ], $overrides));
    }

    private function payment(): Payment
    {
        return (new Payment)->forceFill([
            'transaction_reference' => '7f8d2c1e-1111-4a2b-9c3d-123456789abc',
            'amount' => '80.00',
            'currency' => 'GHS',
            'payer_msisdn' => '233241234567',
            'external_id' => 'ORD-2026-000001',
        ]);
    }

    private function fakeToken(): array
    {
        return [self::BASE.'/collection/token/' => Http::response(['access_token' => 'tok-123', 'token_type' => 'access_token', 'expires_in' => 3600])];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::preventStrayRequests();
    }

    public function test_access_token_uses_basic_auth_and_is_cached(): void
    {
        Http::fake($this->fakeToken());

        $service = $this->service();
        $this->assertSame('tok-123', $service->accessToken());
        $this->assertSame('tok-123', $service->accessToken());

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->hasHeader('Authorization', 'Basic '.base64_encode('api-user:api-key'))
            && $r->hasHeader('Ocp-Apim-Subscription-Key', 'sub-key'));
    }

    public function test_missing_credentials_throw_instead_of_calling_mtn(): void
    {
        $this->expectException(MomoException::class);

        $this->service(['api_key' => null])->accessToken();
    }

    public function test_failed_token_request_throws(): void
    {
        Http::fake([self::BASE.'/collection/token/' => Http::response(['error' => 'unauthorized'], 401)]);

        $this->expectException(MomoException::class);
        $this->service()->accessToken();
    }

    public function test_request_to_pay_sends_reference_callback_and_body(): void
    {
        Http::fake($this->fakeToken() + [self::BASE.'/collection/v1_0/requesttopay' => Http::response(null, 202)]);

        $result = $this->service()->requestToPay($this->payment());

        $this->assertTrue($result->accepted);
        $this->assertFalse($result->duplicate);
        Http::assertSent(function (Request $r) {
            return $r->url() === self::BASE.'/collection/v1_0/requesttopay'
                && $r->hasHeader('Authorization', 'Bearer tok-123')
                && $r->hasHeader('X-Reference-Id', '7f8d2c1e-1111-4a2b-9c3d-123456789abc')
                && $r->hasHeader('X-Target-Environment', 'sandbox')
                && $r->hasHeader('X-Callback-Url', 'https://reloop.test/api/payments/momo/callback/secret-token/7f8d2c1e-1111-4a2b-9c3d-123456789abc')
                && $r['amount'] === '80'
                && $r['currency'] === 'EUR'
                && $r['externalId'] === 'ORD-2026-000001'
                && $r['payer'] === ['partyIdType' => 'MSISDN', 'partyId' => '233241234567'];
        });
    }

    public function test_no_callback_header_when_callback_not_configured(): void
    {
        Http::fake($this->fakeToken() + [self::BASE.'/collection/v1_0/requesttopay' => Http::response(null, 202)]);

        $this->service(['callback_host' => null])->requestToPay($this->payment());

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/requesttopay') && ! $r->hasHeader('X-Callback-Url'));
    }

    public function test_duplicate_reference_is_treated_as_already_submitted(): void
    {
        Http::fake($this->fakeToken() + [self::BASE.'/collection/v1_0/requesttopay' => Http::response(['code' => 'RESOURCE_ALREADY_EXIST'], 409)]);

        $result = $this->service()->requestToPay($this->payment());

        $this->assertTrue($result->accepted);
        $this->assertTrue($result->duplicate);
    }

    public function test_rejected_request_to_pay_reports_reason(): void
    {
        Http::fake($this->fakeToken() + [self::BASE.'/collection/v1_0/requesttopay' => Http::response(['code' => 'PAYER_NOT_FOUND', 'message' => 'Payer not found'], 400)]);

        $result = $this->service()->requestToPay($this->payment());

        $this->assertFalse($result->accepted);
        $this->assertSame('Payer not found', $result->errorReason());
    }

    public function test_status_check_normalises_successful(): void
    {
        $ref = '7f8d2c1e-1111-4a2b-9c3d-123456789abc';
        Http::fake($this->fakeToken() + [self::BASE.'/collection/v1_0/requesttopay/'.$ref => Http::response([
            'amount' => '80', 'currency' => 'EUR', 'financialTransactionId' => '123456789',
            'externalId' => 'ORD-2026-000001', 'status' => 'SUCCESSFUL',
        ])]);

        $status = $this->service()->getTransactionStatus($ref);

        $this->assertSame(MomoStatus::SUCCESSFUL, $status->state);
        $this->assertSame('123456789', $status->financialTransactionId);
        $this->assertTrue($status->amountMatches('80.00'));
        $this->assertFalse($status->amountMatches('79.99'));
    }

    public function test_status_check_normalises_failed_pending_and_not_found(): void
    {
        Http::fake($this->fakeToken() + [
            self::BASE.'/collection/v1_0/requesttopay/failed' => Http::response(['status' => 'FAILED', 'reason' => 'APPROVAL_REJECTED']),
            self::BASE.'/collection/v1_0/requesttopay/pending' => Http::response(['status' => 'PENDING']),
            self::BASE.'/collection/v1_0/requesttopay/missing' => Http::response(['code' => 'RESOURCE_NOT_FOUND'], 404),
        ]);

        $service = $this->service();
        $failed = $service->getTransactionStatus('failed');

        $this->assertSame(MomoStatus::FAILED, $failed->state);
        $this->assertSame('APPROVAL_REJECTED', $failed->reason);
        $this->assertSame(MomoStatus::PENDING, $service->getTransactionStatus('pending')->state);
        $this->assertSame(MomoStatus::NOT_FOUND, $service->getTransactionStatus('missing')->state);
    }

    public function test_status_check_server_error_throws_rather_than_guessing(): void
    {
        Http::fake($this->fakeToken() + [self::BASE.'/collection/v1_0/requesttopay/abc' => Http::response('oops', 500)]);

        $this->expectException(MomoException::class);
        $this->service()->getTransactionStatus('abc');
    }

    public function test_unauthorised_response_clears_cached_token(): void
    {
        Http::fake($this->fakeToken() + [self::BASE.'/collection/v1_0/requesttopay' => Http::response(null, 401)]);

        $service = $this->service();
        $result = $service->requestToPay($this->payment());

        $this->assertFalse($result->accepted);
        $this->assertNull(Cache::get('mtn_momo.collection.token'));
    }
}
