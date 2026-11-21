<?php

namespace Tests\Feature\Messaging;

use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Models\Conversation;
use App\Models\Offer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class OfferFlowTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpInventory();
    }

    private function conversation(): array
    {
        $listing = $this->listing(['price' => '45.00']);
        $buyer = $this->user();
        $this->actingAs($buyer)->post('/messages', ['listing_id' => $listing->id, 'body' => 'Hello'])->assertRedirect();

        return [$listing, $buyer, $listing->seller, Conversation::firstOrFail()];
    }

    public function test_buyer_opens_one_conversation_per_listing(): void
    {
        [$listing, $buyer] = $this->conversation();

        $this->actingAs($buyer)->post('/messages', ['listing_id' => $listing->id]);

        $this->assertSame(1, Conversation::count());
        $this->assertSame(1, Conversation::first()->messages()->count());
    }

    public function test_seller_cannot_open_conversation_on_own_listing(): void
    {
        $listing = $this->listing();

        $this->actingAs($listing->seller)->post('/messages', ['listing_id' => $listing->id])
            ->assertSessionHasErrors('listing_id');
        $this->assertSame(0, Conversation::count());
    }

    public function test_outsider_cannot_read_or_post(): void
    {
        [, , , $conversation] = $this->conversation();
        $outsider = $this->user();

        $this->actingAs($outsider)->get("/messages/{$conversation->id}")->assertForbidden();
        $this->actingAs($outsider)->post("/messages/{$conversation->id}/messages", ['body' => 'hi'])->assertForbidden();
        $this->actingAs($outsider)->post("/messages/{$conversation->id}/offers", ['unit_price' => '1', 'quantity' => '1'])->assertForbidden();
    }

    public function test_participant_sees_thread(): void
    {
        [, , $seller, $conversation] = $this->conversation();

        $this->actingAs($seller)->get("/messages/{$conversation->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Messages/Show')->has('timeline', 1)->where('can_participate', true));

        $this->assertNotNull($conversation->messages()->first()->fresh()->read_at);
    }

    public function test_offer_counter_accept_creates_order_and_reserves_stock(): void
    {
        [$listing, $buyer, $seller, $conversation] = $this->conversation();

        $this->actingAs($buyer)->post("/messages/{$conversation->id}/offers", ['unit_price' => '40', 'quantity' => '2'])
            ->assertSessionHasNoErrors();
        $first = Offer::firstOrFail();

        $this->actingAs($seller)->post("/messages/{$conversation->id}/offers", [
            'unit_price' => '42.50', 'quantity' => '2', 'parent_offer_id' => $first->id,
        ])->assertSessionHasNoErrors();
        $counter = Offer::latest('id')->firstOrFail();
        $this->assertSame(OfferStatus::Countered, $first->fresh()->status);

        // The maker cannot accept their own offer.
        $this->actingAs($seller)->post("/offers/{$counter->id}/accept")->assertForbidden();

        $response = $this->actingAs($buyer)->post("/offers/{$counter->id}/accept");

        $order = Order::firstOrFail();
        $response->assertRedirect(route('orders.show', $order));
        $this->assertSame(OrderStatus::AwaitingPayment, $order->status);
        $this->assertSame('85.00', (string) $order->total_amount);
        $this->assertSame($buyer->id, $order->buyer_id);
        $this->assertSame($seller->id, $order->seller_id);
        $this->assertSame($conversation->id, $order->conversation_id);
        $this->assertMatchesRegularExpression('/^ORD-\d{4}-\d{6}$/', $order->code);
        $this->assertSame($counter->id, $order->agreement->offer_id);
        $this->assertSame('2.000', (string) $order->items->first()->quantity);
        $this->assertSame(1, $order->statusHistories()->count());
        $this->assertSame(OfferStatus::Accepted, $counter->fresh()->status);
        $this->assertTrue($this->inventory->called('reserve', $order));
        $this->assertSame('8.000', $this->inventory->availableQuantity($listing));
    }

    public function test_accepting_twice_creates_one_order(): void
    {
        [, $buyer, $seller, $conversation] = $this->conversation();
        $this->actingAs($buyer)->post("/messages/{$conversation->id}/offers", ['unit_price' => '40', 'quantity' => '1']);
        $offer = Offer::firstOrFail();

        $this->actingAs($seller)->post("/offers/{$offer->id}/accept");
        $this->actingAs($seller)->post("/offers/{$offer->id}/accept")->assertSessionHasErrors('offer');

        $this->assertSame(1, Order::count());
    }

    public function test_only_one_open_offer_at_a_time(): void
    {
        [, $buyer, $seller, $conversation] = $this->conversation();
        $this->actingAs($buyer)->post("/messages/{$conversation->id}/offers", ['unit_price' => '40', 'quantity' => '1']);

        $this->actingAs($buyer)->post("/messages/{$conversation->id}/offers", ['unit_price' => '41', 'quantity' => '1'])
            ->assertSessionHasErrors('offer');
        $this->actingAs($seller)->post("/messages/{$conversation->id}/offers", ['unit_price' => '44', 'quantity' => '1'])
            ->assertSessionHasErrors('offer');

        $this->assertSame(1, Offer::count());
    }

    public function test_offer_over_available_quantity_is_rejected(): void
    {
        [, $buyer, , $conversation] = $this->conversation();

        $this->actingAs($buyer)->post("/messages/{$conversation->id}/offers", ['unit_price' => '40', 'quantity' => '11'])
            ->assertSessionHasErrors('quantity');
    }

    public function test_invalid_offer_values_fail_validation(): void
    {
        [, $buyer, , $conversation] = $this->conversation();

        $this->actingAs($buyer)->post("/messages/{$conversation->id}/offers", ['unit_price' => '-1', 'quantity' => '0'])
            ->assertSessionHasErrors(['unit_price', 'quantity']);
        $this->actingAs($buyer)->post("/messages/{$conversation->id}/offers", ['unit_price' => '1.234', 'quantity' => '1'])
            ->assertSessionHasErrors('unit_price');
    }

    public function test_expired_offer_cannot_be_accepted(): void
    {
        [, $buyer, $seller, $conversation] = $this->conversation();
        $this->actingAs($buyer)->post("/messages/{$conversation->id}/offers", ['unit_price' => '40', 'quantity' => '1']);
        $offer = Offer::firstOrFail();

        $this->travel(73)->hours();

        $this->actingAs($seller)->post("/offers/{$offer->id}/accept")->assertSessionHasErrors('offer');
        $this->assertSame(0, Order::count());
    }

    public function test_reject_and_withdraw(): void
    {
        [, $buyer, $seller, $conversation] = $this->conversation();
        $this->actingAs($buyer)->post("/messages/{$conversation->id}/offers", ['unit_price' => '40', 'quantity' => '1']);
        $offer = Offer::firstOrFail();

        $this->actingAs($seller)->post("/offers/{$offer->id}/withdraw")->assertForbidden();
        $this->actingAs($seller)->post("/offers/{$offer->id}/reject")->assertSessionHasNoErrors();
        $this->assertSame(OfferStatus::Rejected, $offer->fresh()->status);

        $this->actingAs($buyer)->post("/messages/{$conversation->id}/offers", ['unit_price' => '43', 'quantity' => '1']);
        $second = Offer::latest('id')->firstOrFail();
        $this->actingAs($buyer)->post("/offers/{$second->id}/withdraw")->assertSessionHasNoErrors();
        $this->assertSame(OfferStatus::Withdrawn, $second->fresh()->status);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/messages')->assertRedirect('/login');
    }
}
