<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Models\Complaint;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class ReviewAndComplaintTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpInventory();
    }

    public function test_reviews_only_after_completion_and_once_each(): void
    {
        $order = $this->orderIn(OrderStatus::Paid);
        $this->actingAs($order->buyer)->post("/orders/{$order->id}/reviews", ['rating' => 5])->assertForbidden();

        $order = $this->orderIn(OrderStatus::Paid, OrderStatus::Shipped, OrderStatus::Delivered, OrderStatus::Completed);
        $this->actingAs($order->buyer)->post("/orders/{$order->id}/reviews", ['rating' => 4, 'comment' => 'Good'])->assertSessionHasNoErrors();
        $this->actingAs($order->buyer)->post("/orders/{$order->id}/reviews", ['rating' => 1])->assertForbidden();
        $this->actingAs($order->seller)->post("/orders/{$order->id}/reviews", ['rating' => 6])->assertSessionHasErrors('rating');
        $this->actingAs($order->seller)->post("/orders/{$order->id}/reviews", ['rating' => 5])->assertSessionHasNoErrors();
        $this->actingAs($this->user())->post("/orders/{$order->id}/reviews", ['rating' => 5])->assertForbidden();

        $buyerReview = Review::where('reviewer_id', $order->buyer_id)->firstOrFail();
        $this->assertSame($order->seller_id, $buyerReview->reviewee_id);
        $this->assertSame(2, Review::count());

        $this->get("/users/{$order->seller_id}/reviews")->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Reviews/Index')->where('review_count', 1)->where('average_rating', 4));
    }

    public function test_complaint_on_paid_order_disputes_it(): void
    {
        $order = $this->orderIn(OrderStatus::Paid, OrderStatus::Shipped);

        $response = $this->actingAs($order->buyer)->post('/complaints', [
            'order_id' => $order->id, 'category' => 'product_quality', 'subject' => 'Wrong colour', 'description' => 'Ordered green, got blue.',
        ]);

        $complaint = Complaint::firstOrFail();
        $response->assertRedirect(route('complaints.show', $complaint));
        $this->assertSame($order->seller_id, $complaint->against_user_id);
        $this->assertSame(OrderStatus::Disputed, $order->fresh()->status);
        $this->actingAs($order->seller)->get("/complaints/{$complaint->id}")->assertForbidden();
        $this->actingAs($order->buyer)->get("/complaints/{$complaint->id}")->assertOk();
    }

    public function test_complaint_on_unpaid_order_does_not_change_it(): void
    {
        $order = $this->orderIn();

        $this->actingAs($order->buyer)->post('/complaints', [
            'order_id' => $order->id, 'category' => 'user_conduct', 'subject' => 'Rude', 'description' => 'Rude messages.',
        ])->assertSessionHasNoErrors();

        $this->assertSame(OrderStatus::AwaitingPayment, $order->fresh()->status);
    }

    public function test_cannot_complain_about_someone_elses_order(): void
    {
        $order = $this->orderIn(OrderStatus::Paid);

        $this->actingAs($this->user())->post('/complaints', [
            'order_id' => $order->id, 'category' => 'other', 'subject' => 'x', 'description' => 'y',
        ])->assertSessionHasErrors('order_id');

        $this->assertSame(0, Complaint::count());
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }
}
