<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Jobs\CancelUnpaidOrders;
use App\Jobs\CompleteDeliveredOrders;
use App\Models\Complaint;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class FulfilmentTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpInventory();
    }

    public function test_seller_ships_and_buyer_confirms(): void
    {
        $order = $this->orderIn(OrderStatus::Paid);

        $this->actingAs($order->buyer)->post("/orders/{$order->id}/ship")->assertForbidden();
        $this->actingAs($order->seller)->post("/orders/{$order->id}/ship")->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::Shipped, $order->fresh()->status);

        $this->actingAs($order->seller)->post("/orders/{$order->id}/confirm-delivery")->assertForbidden();
        $this->actingAs($order->buyer)->post("/orders/{$order->id}/confirm-delivery")->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertNotNull($order->delivered_at);
        $this->assertNotNull($order->completed_at);
    }

    public function test_cannot_ship_unpaid_order(): void
    {
        $order = $this->orderIn();

        $this->actingAs($order->seller)->post("/orders/{$order->id}/ship")->assertSessionHasErrors('status');
        $this->assertSame(OrderStatus::AwaitingPayment, $order->fresh()->status);
    }

    public function test_unpaid_orders_are_cancelled_after_48_hours_unless_payment_pending(): void
    {
        $stale = $this->orderIn();
        $withPending = $this->orderIn();
        Payment::create([
            'order_id' => $withPending->id, 'payer_id' => $withPending->buyer_id, 'payee_id' => $withPending->seller_id,
            'provider' => 'mtn_momo', 'transaction_reference' => (string) \Illuminate\Support\Str::uuid(),
            'amount' => '20.00', 'currency' => 'GHS', 'payer_msisdn' => '233241234567', 'external_id' => $withPending->code,
        ]);

        $this->travel(47)->hours();
        app()->call([new CancelUnpaidOrders, 'handle']);
        $this->assertSame(OrderStatus::AwaitingPayment, $stale->fresh()->status);

        $this->travel(2)->hours();
        app()->call([new CancelUnpaidOrders, 'handle']);
        $this->assertSame(OrderStatus::Cancelled, $stale->fresh()->status);
        $this->assertTrue($this->inventory->called('release', $stale));
        $this->assertSame(OrderStatus::AwaitingPayment, $withPending->fresh()->status);
    }

    public function test_delivered_orders_complete_after_7_days_without_open_complaint(): void
    {
        $quiet = $this->orderIn(OrderStatus::Paid, OrderStatus::Shipped, OrderStatus::Delivered);
        $complained = $this->orderIn(OrderStatus::Paid, OrderStatus::Shipped, OrderStatus::Delivered);
        Complaint::create([
            'complainant_id' => $complained->buyer_id, 'order_id' => $complained->id,
            'category' => 'product_quality', 'subject' => 'Broken', 'description' => 'Arrived broken',
        ]);

        $this->travel(8)->days();
        app()->call([new CompleteDeliveredOrders, 'handle']);

        $this->assertSame(OrderStatus::Completed, $quiet->fresh()->status);
        $this->assertSame(OrderStatus::Delivered, $complained->fresh()->status);
    }
}
