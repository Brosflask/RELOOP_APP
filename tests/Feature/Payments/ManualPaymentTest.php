<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Jobs\ReconcilePendingPayments;
use App\Models\Order;
use App\Models\Payment;
use App\Notifications\OrderUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class ManualPaymentTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpInventory();
        config(['payments.driver' => 'manual']);
        Http::preventStrayRequests(); // manual payments never call MTN
    }

    private function submit(Order $order, string $reference = 'MP241124.1234.A56789'): void
    {
        $this->actingAs($order->buyer)->post("/orders/{$order->id}/payments", [
            'msisdn' => '024 123 4567', 'reference' => $reference,
        ])->assertSessionHasNoErrors();
    }

    public function test_pay_page_shows_manual_instructions(): void
    {
        $order = $this->orderIn();

        $this->actingAs($order->buyer)->get("/orders/{$order->id}/pay")->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Orders/Pay')->where('driver', 'manual')->where('sandbox', false));
    }

    public function test_submitting_creates_a_pending_payment_and_tells_the_seller(): void
    {
        Notification::fake();
        $order = $this->orderIn();

        $this->submit($order);

        $payment = Payment::firstOrFail();
        $this->assertSame(PaymentProvider::Manual, $payment->provider);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame('MP241124.1234.A56789', $payment->provider_reference);
        $this->assertSame('233241234567', $payment->payer_msisdn);
        $this->assertSame(OrderStatus::AwaitingPayment, $order->fresh()->status);
        Notification::assertSentTo($order->seller, OrderUpdated::class, fn ($n) => $n->title === 'Confirm a payment');
    }

    public function test_reference_is_required_and_second_submission_is_blocked(): void
    {
        $order = $this->orderIn();

        $this->actingAs($order->buyer)->post("/orders/{$order->id}/payments", ['msisdn' => '0241234567'])
            ->assertSessionHasErrors('reference');

        $this->submit($order);
        $this->actingAs($order->buyer)->post("/orders/{$order->id}/payments", ['msisdn' => '0241234567', 'reference' => 'ANOTHER123'])
            ->assertSessionHasErrors('payment');
        $this->assertSame(1, Payment::count());
    }

    public function test_only_seller_or_admin_can_confirm(): void
    {
        $order = $this->orderIn();
        $this->submit($order);
        $payment = Payment::firstOrFail();

        $this->actingAs($order->buyer)->post("/payments/{$payment->id}/confirm")->assertForbidden();
        $this->actingAs($this->user())->post("/payments/{$payment->id}/confirm")->assertForbidden();
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);

        $admin = $this->user()->assignRole(RoleName::Admin);
        $this->actingAs($admin)->get("/payments/{$payment->id}")->assertInertia(fn (Assert $p) => $p->where('can_settle', true));
    }

    public function test_seller_confirmation_marks_order_paid_once(): void
    {
        $order = $this->orderIn();
        $this->submit($order);
        $payment = Payment::firstOrFail();

        $this->actingAs($order->seller)->get("/payments/{$payment->id}")
            ->assertInertia(fn (Assert $p) => $p->component('Payments/Show')->where('can_settle', true));
        $this->actingAs($order->seller)->post("/payments/{$payment->id}/confirm")->assertSessionHasNoErrors();

        $payment->refresh();
        $this->assertSame(PaymentStatus::Successful, $payment->status);
        $this->assertSame($order->id, $payment->successful_order_id);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertTrue($this->inventory->called('commitSale', $order));

        $this->actingAs($order->seller)->post("/payments/{$payment->id}/confirm")->assertSessionHasErrors('payment');
        $this->assertSame(1, $order->statusHistories()->where('to_status', 'paid')->count());
    }

    public function test_seller_rejection_fails_payment_and_buyer_can_retry(): void
    {
        $order = $this->orderIn();
        $this->submit($order);
        $payment = Payment::firstOrFail();

        $this->actingAs($order->seller)->post("/payments/{$payment->id}/reject", [])->assertSessionHasErrors('reason');
        $this->actingAs($order->seller)->post("/payments/{$payment->id}/reject", ['reason' => 'Nothing received'])->assertSessionHasNoErrors();

        $payment->refresh();
        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertStringContainsString('Nothing received', $payment->failure_reason);
        $this->assertSame(OrderStatus::AwaitingPayment, $order->fresh()->status);

        $this->submit($order, 'SECONDTRY99');
        $this->assertSame(2, Payment::count());
    }

    public function test_manual_payments_are_never_sent_to_mtn(): void
    {
        $order = $this->orderIn();
        $this->submit($order);

        $this->travel(10)->minutes();
        (new ReconcilePendingPayments)->handle();
        $this->actingAs($order->buyer)->getJson('/payments/'.Payment::first()->id.'/status')->assertOk()->assertJson(['status' => 'pending']);

        Http::assertNothingSent();
    }

    public function test_mtn_payment_cannot_be_confirmed_by_hand(): void
    {
        $order = $this->orderIn();
        $payment = Payment::create([
            'order_id' => $order->id, 'payer_id' => $order->buyer_id, 'payee_id' => $order->seller_id,
            'provider' => 'mtn_momo', 'transaction_reference' => (string) \Illuminate\Support\Str::uuid(),
            'amount' => '20.00', 'currency' => 'GHS', 'payer_msisdn' => '233241234567', 'external_id' => $order->code,
        ]);

        $this->actingAs($order->seller)->post("/payments/{$payment->id}/confirm")->assertSessionHasErrors('payment');
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }
}
