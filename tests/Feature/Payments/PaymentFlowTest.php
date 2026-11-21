<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentEventType;
use App\Enums\PaymentStatus;
use App\Events\PaymentFailed;
use App\Events\PaymentSucceeded;
use App\Jobs\ReconcilePendingPayments;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    private const BASE = 'https://momo.test';

    /** What MTN's status endpoint answers next. */
    private array $mtnStatus = ['status' => 'PENDING'];

    private int $mtnStatusCode = 200;

    private int $requestToPayStatus = 202;

    private bool $requestToPayThrows = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpInventory();

        config(['mtn_momo' => [
            'base_url' => self::BASE,
            'environment' => 'sandbox',
            'subscription_key' => 'sub',
            'api_user' => 'user',
            'api_key' => 'key',
            'currency' => 'EUR',
            'callback_host' => 'https://reloop.test',
            'callback_token' => 'cb-secret',
            'timeout' => 5,
        ]]);

        Http::preventStrayRequests();
        Http::fake([
            self::BASE.'/collection/token/' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            self::BASE.'/collection/v1_0/requesttopay/*' => fn () => Http::response($this->mtnStatus, $this->mtnStatusCode),
            self::BASE.'/collection/v1_0/requesttopay' => function () {
                if ($this->requestToPayThrows) {
                    throw new ConnectionException('timeout');
                }

                return Http::response($this->requestToPayStatus === 202 ? null : ['message' => 'Payer not found'], $this->requestToPayStatus);
            },
        ]);
    }

    /** @return array{0: Order, 1: User} */
    private function order(string $price = '40.00', string $quantity = '2'): array
    {
        $listing = $this->listing(['price' => $price]);
        $buyer = $this->user();
        $this->actingAs($buyer)->post('/orders', [
            'listing_id' => $listing->id, 'quantity' => $quantity, 'delivery_address' => 'Kumasi',
        ]);

        return [Order::firstOrFail(), $buyer];
    }

    private function pay(Order $order, User $buyer): Payment
    {
        $this->actingAs($buyer)->post("/orders/{$order->id}/payments", ['msisdn' => '46733123453'])->assertSessionHasNoErrors();

        return Payment::latest('id')->firstOrFail();
    }

    private function mtnCallback(Payment $payment, string $token = 'cb-secret'): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/payments/momo/callback/{$token}/{$payment->transaction_reference}", [
            'financialTransactionId' => '999', 'status' => 'SUCCESSFUL', 'amount' => '80',
        ]);
    }

    private function requestToPayCount(): int
    {
        return Http::recorded(fn (Request $r) => $r->url() === self::BASE.'/collection/v1_0/requesttopay')->count();
    }

    public function test_pay_page_is_for_the_buyer_only(): void
    {
        [$order, $buyer] = $this->order();

        $this->actingAs($buyer)->get("/orders/{$order->id}/pay")->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Orders/Pay')->where('order.total_amount', '80.00')->where('sandbox', true));
        $this->actingAs($order->seller)->get("/orders/{$order->id}/pay")->assertForbidden();
    }

    public function test_initiating_creates_pending_payment_and_sends_request_to_pay(): void
    {
        [$order, $buyer] = $this->order();

        $response = $this->actingAs($buyer)->post("/orders/{$order->id}/payments", ['msisdn' => '46733123453']);

        $payment = Payment::firstOrFail();
        $response->assertRedirect(route('payments.show', $payment));
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame('80.00', (string) $payment->amount);
        $this->assertSame($buyer->id, $payment->payer_id);
        $this->assertSame($order->seller_id, $payment->payee_id);
        $this->assertSame($order->code, $payment->external_id);
        $this->assertTrue(\Illuminate\Support\Str::isUuid($payment->transaction_reference));
        Http::assertSent(fn (Request $r) => $r->url() === self::BASE.'/collection/v1_0/requesttopay'
            && $r->hasHeader('X-Reference-Id', $payment->transaction_reference)
            && $r->header('X-Callback-Url')[0] === "https://reloop.test/api/payments/momo/callback/cb-secret/{$payment->transaction_reference}");
        $this->assertSame(OrderStatus::AwaitingPayment, $order->fresh()->status);
    }

    public function test_double_submit_reuses_the_pending_payment(): void
    {
        [$order, $buyer] = $this->order();

        $first = $this->pay($order, $buyer);
        $second = $this->pay($order, $buyer);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Payment::count());
        $this->assertSame(1, $this->requestToPayCount());
    }

    public function test_only_the_buyer_can_pay_and_only_unpaid_orders(): void
    {
        [$order, $buyer] = $this->order();

        $this->actingAs($order->seller)->post("/orders/{$order->id}/payments", ['msisdn' => '46733123453'])->assertForbidden();
        $this->actingAs($this->user())->post("/orders/{$order->id}/payments", ['msisdn' => '46733123453'])->assertForbidden();

        $order->update(['status' => OrderStatus::Cancelled]);
        $this->actingAs($buyer)->post("/orders/{$order->id}/payments", ['msisdn' => '46733123453'])->assertSessionHasErrors('order');
        $this->assertSame(0, Payment::count());
    }

    public function test_ghana_number_is_normalised_in_production_mode(): void
    {
        config(['mtn_momo.environment' => 'mtnghana']);
        [$order, $buyer] = $this->order();

        $this->actingAs($buyer)->post("/orders/{$order->id}/payments", ['msisdn' => '024 123 4567'])->assertSessionHasNoErrors();
        $this->assertSame('233241234567', Payment::firstOrFail()->payer_msisdn);

        $this->actingAs($buyer)->post("/orders/{$order->id}/payments", ['msisdn' => '12345'])->assertSessionHasErrors('msisdn');
    }

    public function test_mtn_rejecting_the_request_fails_the_payment_and_allows_retry(): void
    {
        Event::fake([PaymentFailed::class]);
        $this->requestToPayStatus = 400;
        [$order, $buyer] = $this->order();

        $payment = $this->pay($order, $buyer);
        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertStringContainsString('Payer not found', $payment->failure_reason);
        $this->assertSame(OrderStatus::AwaitingPayment, $order->fresh()->status);
        Event::assertDispatched(PaymentFailed::class);

        $this->requestToPayStatus = 202;
        $retry = $this->pay($order, $buyer);
        $this->assertNotSame($payment->id, $retry->id);
        $this->assertSame(PaymentStatus::Pending, $retry->status);
    }

    public function test_network_error_leaves_payment_pending_for_reconciliation(): void
    {
        $this->requestToPayThrows = true;
        [$order, $buyer] = $this->order();

        $payment = $this->pay($order, $buyer);

        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertTrue($payment->events()->where('type', PaymentEventType::Error)->exists());
    }

    public function test_callback_with_wrong_token_is_rejected(): void
    {
        [$order, $buyer] = $this->order();
        $payment = $this->pay($order, $buyer);

        $this->mtnCallback($payment, 'wrong')->assertNotFound();
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_callback_alone_does_not_mark_paid_when_mtn_says_pending(): void
    {
        [$order, $buyer] = $this->order();
        $payment = $this->pay($order, $buyer);

        // The callback body claims SUCCESSFUL, but MTN's status endpoint still says PENDING.
        $this->mtnCallback($payment)->assertOk();

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertSame(OrderStatus::AwaitingPayment, $order->fresh()->status);
        $this->assertTrue($payment->events()->where('type', PaymentEventType::CallbackReceived)->exists());
    }

    public function test_verified_success_marks_order_paid_once(): void
    {
        Event::fake([PaymentSucceeded::class]);
        [$order, $buyer] = $this->order();
        $payment = $this->pay($order, $buyer);
        $this->mtnStatus = ['status' => 'SUCCESSFUL', 'amount' => '80', 'currency' => 'EUR', 'financialTransactionId' => '5551234'];

        $this->mtnCallback($payment)->assertOk();
        $this->mtnCallback($payment)->assertOk(); // duplicate callback

        $payment->refresh();
        $order->refresh();
        $this->assertSame(PaymentStatus::Successful, $payment->status);
        $this->assertSame('5551234', $payment->provider_reference);
        $this->assertSame($order->id, $payment->successful_order_id);
        $this->assertNotNull($payment->verified_at);
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertTrue($this->inventory->called('commitSale', $order));
        $this->assertSame(1, collect($this->inventory->calls)->where(0, 'commitSale')->count());
        $this->assertSame(1, $order->statusHistories()->where('to_status', 'paid')->count());
        Event::assertDispatchedTimes(PaymentSucceeded::class, 1);
    }

    public function test_amount_mismatch_is_never_accepted(): void
    {
        [$order, $buyer] = $this->order();
        $payment = $this->pay($order, $buyer);
        $this->mtnStatus = ['status' => 'SUCCESSFUL', 'amount' => '1', 'currency' => 'EUR', 'financialTransactionId' => '1'];

        $this->mtnCallback($payment);

        $payment->refresh();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertTrue($payment->requires_attention);
        $this->assertSame(OrderStatus::AwaitingPayment, $order->fresh()->status);
    }

    public function test_failed_status_fails_payment_but_keeps_order_open(): void
    {
        [$order, $buyer] = $this->order();
        $payment = $this->pay($order, $buyer);
        $this->mtnStatus = ['status' => 'FAILED', 'reason' => 'APPROVAL_REJECTED'];

        $this->mtnCallback($payment);

        $payment->refresh();
        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertSame('APPROVAL_REJECTED', $payment->failure_reason);
        $this->assertSame(OrderStatus::AwaitingPayment, $order->fresh()->status);

        // A late SUCCESSFUL answer can never flip a final payment.
        $this->mtnStatus = ['status' => 'SUCCESSFUL', 'amount' => '80', 'currency' => 'EUR'];
        $this->mtnCallback($payment);
        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
    }

    public function test_second_successful_payment_is_recorded_and_flagged(): void
    {
        [$order, $buyer] = $this->order();
        $first = $this->pay($order, $buyer);
        $second = Payment::create([
            'order_id' => $order->id, 'payer_id' => $buyer->id, 'payee_id' => $order->seller_id,
            'provider' => 'mtn_momo', 'transaction_reference' => (string) \Illuminate\Support\Str::uuid(),
            'amount' => '80.00', 'currency' => 'GHS', 'payer_msisdn' => '46733123453', 'external_id' => $order->code,
        ]);
        $this->mtnStatus = ['status' => 'SUCCESSFUL', 'amount' => '80', 'currency' => 'EUR', 'financialTransactionId' => '1'];

        $this->mtnCallback($first);
        $this->mtnCallback($second);

        $second->refresh();
        $this->assertSame(PaymentStatus::Successful, $second->status);
        $this->assertNull($second->successful_order_id);
        $this->assertTrue($second->requires_attention);
        $this->assertSame(1, $order->statusHistories()->where('to_status', 'paid')->count());
    }

    public function test_reconciliation_checks_old_pending_payments(): void
    {
        [$order, $buyer] = $this->order();
        $payment = $this->pay($order, $buyer);
        $this->mtnStatus = ['status' => 'SUCCESSFUL', 'amount' => '80', 'currency' => 'EUR', 'financialTransactionId' => '7'];

        // Too new: not checked yet.
        (new ReconcilePendingPayments)->handle();
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);

        $this->travel(2)->minutes();
        (new ReconcilePendingPayments)->handle();

        $this->assertSame(PaymentStatus::Successful, $payment->fresh()->status);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_unknown_reference_fails_only_after_grace_period(): void
    {
        [$order, $buyer] = $this->order();
        $payment = $this->pay($order, $buyer);
        $this->mtnStatus = ['code' => 'RESOURCE_NOT_FOUND'];
        $this->mtnStatusCode = 404;

        $this->travel(2)->minutes();
        (new ReconcilePendingPayments)->handle();
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);

        $this->travel(20)->minutes();
        (new ReconcilePendingPayments)->handle();
        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
    }

    public function test_status_endpoint_is_private_and_reports_progress(): void
    {
        [$order, $buyer] = $this->order();
        $payment = $this->pay($order, $buyer);

        $this->actingAs($this->user())->getJson("/payments/{$payment->id}/status")->assertForbidden();

        $this->mtnStatus = ['status' => 'SUCCESSFUL', 'amount' => '80', 'currency' => 'EUR', 'financialTransactionId' => '8'];
        $this->travel(10)->seconds();
        $this->actingAs($buyer)->getJson("/payments/{$payment->id}/status")
            ->assertOk()
            ->assertJson(['status' => 'successful', 'order' => ['status' => 'paid']]);

        $this->actingAs($order->seller)->get("/payments/{$payment->id}")->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Payments/Show'));
    }
}
