<?php

namespace Tests\Feature\Admin;

use App\Enums\ComplaintStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class AdminModuleTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpInventory();
        $this->admin = $this->user()->assignRole(RoleName::Admin);
    }

    private function payment(Order $order, array $state = []): Payment
    {
        $payment = Payment::create([
            'order_id' => $order->id, 'payer_id' => $order->buyer_id, 'payee_id' => $order->seller_id,
            'provider' => 'mtn_momo', 'transaction_reference' => (string) Str::uuid(),
            'amount' => $order->total_amount, 'currency' => 'GHS', 'payer_msisdn' => '233241234567', 'external_id' => $order->code,
        ]);
        $payment->forceFill($state)->save();

        return $payment;
    }

    public function test_every_admin_page_is_blocked_for_non_admins(): void
    {
        $order = $this->orderIn(OrderStatus::Paid, OrderStatus::Disputed);
        $payment = $this->payment($order);
        $user = $this->user();

        foreach (['/admin/reports', '/admin/reports/transactions.csv', '/admin/transactions', "/admin/transactions/{$payment->id}", '/admin/complaints', '/admin/disputes', '/admin/reviews'] as $url) {
            $this->actingAs($user)->get($url)->assertForbidden();
        }
        $this->actingAs($user)->post("/admin/transactions/{$payment->id}/recheck")->assertForbidden();
        $this->actingAs($user)->post("/admin/disputes/{$order->id}/resolve", ['outcome' => 'complete', 'note' => 'x'])->assertForbidden();
        $this->assertSame(OrderStatus::Disputed, $order->fresh()->status);
    }

    public function test_reports_count_only_verified_payments(): void
    {
        $paid = $this->orderIn(OrderStatus::Paid);
        $this->payment($paid, ['status' => PaymentStatus::Successful, 'successful_order_id' => $paid->id, 'verified_at' => now()]);
        $unpaid = $this->orderIn();
        $this->payment($unpaid, ['status' => PaymentStatus::Failed, 'verified_at' => now()]);
        $this->payment($unpaid); // pending

        $this->actingAs($this->admin)->get('/admin/reports')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Reports/Index')
                ->where('summary.gmv', '20.00')
                ->where('summary.paid_orders', 1)
                ->where('summary.failed_payment_rate', 50)
                ->where('summary.payments_by_status.pending', 1)
                ->has('sales_by_day', 1)
                ->where('sales_by_day.0.finished_product', '20.00')
                ->where('top_sellers.0.id', $paid->seller_id)
                ->where('impact.kg_collected', null));
    }

    public function test_transactions_export_csv(): void
    {
        $order = $this->orderIn();
        $payment = $this->payment($order);

        $response = $this->actingAs($this->admin)->get('/admin/reports/transactions.csv');

        $response->assertOk();
        $this->assertStringContainsString($payment->transaction_reference, $response->streamedContent());
    }

    public function test_transactions_filter_and_detail(): void
    {
        $order = $this->orderIn();
        $flagged = $this->payment($order, ['requires_attention' => true, 'attention_reason' => 'Amount mismatch']);
        $this->payment($this->orderIn());

        $this->actingAs($this->admin)->get('/admin/transactions?attention=1')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Transactions/Index')->has('payments.data', 1)->where('payments.data.0.id', $flagged->id));
        $this->actingAs($this->admin)->get('/admin/transactions?search='.$order->code)
            ->assertInertia(fn (Assert $p) => $p->has('payments.data', 1));
        $this->actingAs($this->admin)->get("/admin/transactions/{$flagged->id}")->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Transactions/Show')->where('can_recheck', true));

        $this->actingAs($this->admin)->post("/admin/transactions/{$flagged->id}/acknowledge", ['note' => 'Refunded by phone'])->assertSessionHasNoErrors();
        $this->assertFalse($flagged->fresh()->requires_attention);
    }

    public function test_recheck_uses_mtn_verification(): void
    {
        config(['mtn_momo' => [
            'base_url' => 'https://momo.test', 'environment' => 'sandbox', 'subscription_key' => 's', 'api_user' => 'u',
            'api_key' => 'k', 'currency' => 'EUR', 'callback_host' => null, 'callback_token' => null, 'timeout' => 5,
        ]]);
        Http::fake([
            'momo.test/collection/token/' => Http::response(['access_token' => 't', 'expires_in' => 3600]),
            'momo.test/collection/v1_0/requesttopay/*' => Http::response(['status' => 'SUCCESSFUL', 'amount' => '20', 'currency' => 'EUR', 'financialTransactionId' => '42']),
        ]);
        $order = $this->orderIn();
        $payment = $this->payment($order);

        $this->actingAs($this->admin)->post("/admin/transactions/{$payment->id}/recheck")->assertSessionHasNoErrors();

        $this->assertSame(PaymentStatus::Successful, $payment->fresh()->status);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_admin_resolves_complaints(): void
    {
        $complaint = Complaint::create([
            'complainant_id' => $this->user()->id, 'category' => 'other', 'subject' => 'Spam', 'description' => 'Spam messages',
        ]);

        $this->actingAs($this->admin)->get('/admin/complaints')->assertInertia(fn (Assert $p) => $p->has('complaints.data', 1));
        $this->actingAs($this->admin)->patch("/admin/complaints/{$complaint->id}", ['status' => 'resolved'])->assertSessionHasErrors('resolution');
        $this->actingAs($this->admin)->patch("/admin/complaints/{$complaint->id}", ['status' => 'resolved', 'resolution' => 'User warned.'])->assertSessionHasNoErrors();

        $complaint->refresh();
        $this->assertSame(ComplaintStatus::Resolved, $complaint->status);
        $this->assertSame($this->admin->id, $complaint->resolved_by);
        $this->assertNotNull($complaint->resolved_at);
    }

    public function test_dispute_cancel_flags_payment_for_refund_and_complete_releases(): void
    {
        $cancelMe = $this->orderIn(OrderStatus::Paid, OrderStatus::Disputed);
        $payment = $this->payment($cancelMe, ['status' => PaymentStatus::Successful, 'successful_order_id' => $cancelMe->id]);
        $completeMe = $this->orderIn(OrderStatus::Paid, OrderStatus::Disputed);

        $this->actingAs($this->admin)->get('/admin/disputes')->assertInertia(fn (Assert $p) => $p->has('orders.data', 2));

        $this->actingAs($this->admin)->post("/admin/disputes/{$cancelMe->id}/resolve", ['outcome' => 'cancel', 'note' => 'Never delivered'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post("/admin/disputes/{$completeMe->id}/resolve", ['outcome' => 'complete', 'note' => 'Delivered, photo proof'])->assertSessionHasNoErrors();

        $this->assertSame(OrderStatus::Cancelled, $cancelMe->fresh()->status);
        $this->assertTrue($payment->fresh()->requires_attention);
        $this->assertSame(OrderStatus::Completed, $completeMe->fresh()->status);

        // Resolving again is rejected by the state machine.
        $this->actingAs($this->admin)->post("/admin/disputes/{$completeMe->id}/resolve", ['outcome' => 'cancel', 'note' => 'x'])->assertSessionHasErrors('status');
    }

    public function test_hidden_reviews_drop_out_of_public_page(): void
    {
        $order = $this->orderIn(OrderStatus::Paid, OrderStatus::Shipped, OrderStatus::Delivered, OrderStatus::Completed);
        $review = Review::create(['order_id' => $order->id, 'reviewer_id' => $order->buyer_id, 'reviewee_id' => $order->seller_id, 'rating' => 1, 'comment' => 'abuse']);

        $this->actingAs($this->admin)->patch("/admin/reviews/{$review->id}", ['is_hidden' => true])->assertSessionHasNoErrors();

        $this->get("/users/{$order->seller_id}/reviews")->assertInertia(fn (Assert $p) => $p->where('review_count', 0));
    }
}
