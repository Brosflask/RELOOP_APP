<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpInventory();
    }

    private function buyNow(string $quantity = '3'): array
    {
        $listing = $this->listing(['price' => '12.50']);
        $buyer = $this->user();
        $this->actingAs($buyer)->post('/orders', [
            'listing_id' => $listing->id, 'quantity' => $quantity, 'delivery_address' => '5 Palm Rd, Kumasi',
        ]);

        return [$listing, $buyer, Order::first()];
    }

    public function test_buy_now_creates_order_at_listed_price(): void
    {
        [$listing, $buyer, $order] = $this->buyNow();

        $this->assertNotNull($order);
        $this->assertSame('37.50', (string) $order->total_amount);
        $this->assertSame('12.50', (string) $order->agreement->unit_price);
        $this->assertNull($order->agreement->offer_id);
        $this->assertSame('piece', $order->items->first()->unit);
        $this->assertSame('7.000', $this->inventory->availableQuantity($listing));
    }

    public function test_raw_material_order_snapshots_unit_and_lot(): void
    {
        $listing = $this->listing(['listing_type' => 'raw_material', 'price' => '3.20'], '500');
        $lotId = \App\Models\MaterialLot::factory()->create(['code' => 'LOT-1'])->id;
        DB::table('raw_material_listing_details')->insert([
            'listing_id' => $listing->id, 'material_lot_id' => $lotId, 'material_type' => 'PET',
            'available_quantity' => 500, 'unit' => 'kg', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->user())->post('/orders', [
            'listing_id' => $listing->id, 'quantity' => '120.5', 'delivery_address' => 'Accra',
        ])->assertSessionHasNoErrors();

        $item = Order::firstOrFail()->items->first();
        $this->assertSame('kg', $item->unit);
        $this->assertSame($lotId, $item->material_lot_id);
        $this->assertSame('385.60', (string) $item->line_total);
    }

    public function test_cannot_buy_own_inactive_or_overstocked_listing(): void
    {
        $listing = $this->listing();
        $this->actingAs($listing->seller)->post('/orders', ['listing_id' => $listing->id, 'quantity' => '1', 'delivery_address' => 'x'])
            ->assertSessionHasErrors('listing_id');

        $draft = $this->listing(['status' => 'draft']);
        $this->actingAs($this->user())->post('/orders', ['listing_id' => $draft->id, 'quantity' => '1', 'delivery_address' => 'x'])
            ->assertSessionHasErrors('listing_id');

        $this->actingAs($this->user())->post('/orders', ['listing_id' => $listing->id, 'quantity' => '10.001', 'delivery_address' => 'x'])
            ->assertSessionHasErrors('quantity');

        $this->assertSame(0, Order::count());
    }

    public function test_only_participants_see_an_order(): void
    {
        [$listing, $buyer, $order] = $this->buyNow();

        $this->actingAs($buyer)->get("/orders/{$order->id}")->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Orders/Show')->where('viewer_role', 'buyer')->where('can.pay', true));
        $this->actingAs($listing->seller)->get("/orders/{$order->id}")->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('viewer_role', 'seller')->where('can.pay', false));
        $this->actingAs($this->user())->get("/orders/{$order->id}")->assertForbidden();
    }

    public function test_index_splits_buying_and_selling(): void
    {
        [$listing, $buyer] = $this->buyNow();

        $this->actingAs($buyer)->get('/orders')->assertInertia(fn (Assert $p) => $p->component('Orders/Index')->has('orders.data', 1));
        $this->actingAs($buyer)->get('/orders?tab=selling')->assertInertia(fn (Assert $p) => $p->has('orders.data', 0));
        $this->actingAs($listing->seller)->get('/orders?tab=selling')->assertInertia(fn (Assert $p) => $p->has('orders.data', 1));
    }

    public function test_cancel_before_payment_releases_stock(): void
    {
        [$listing, $buyer, $order] = $this->buyNow();

        $this->actingAs($this->user())->post("/orders/{$order->id}/cancel")->assertForbidden();
        $this->actingAs($buyer)->post("/orders/{$order->id}/cancel", ['reason' => 'Changed my mind'])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame('Changed my mind', $order->cancellation_reason);
        $this->assertNotNull($order->cancelled_at);
        $this->assertTrue($this->inventory->called('release', $order));
        $this->assertSame('10.000', $this->inventory->availableQuantity($listing));

        $this->actingAs($buyer)->post("/orders/{$order->id}/cancel")->assertSessionHasErrors('status');
    }

    public function test_paid_order_cannot_be_cancelled_and_history_is_kept(): void
    {
        [, $buyer, $order] = $this->buyNow();
        $service = app(OrderService::class);

        $service->transition($order, OrderStatus::Paid, null, 'Payment verified');
        $this->assertNotNull($order->fresh()->paid_at);

        $this->expectException(BusinessRuleException::class);
        try {
            $service->cancel($order, $buyer);
        } finally {
            $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
            $this->assertSame(
                [null, 'awaiting_payment'],
                [$order->statusHistories()->first()->from_status, $order->statusHistories()->first()->to_status->value],
            );
            $this->assertSame(2, $order->statusHistories()->count());
            $this->assertFalse($this->inventory->called('release', $order));
        }
    }
}
