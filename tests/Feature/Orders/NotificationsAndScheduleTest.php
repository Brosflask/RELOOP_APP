<?php

namespace Tests\Feature\Orders;

use App\Console\Dev4Schedule;
use App\Enums\OrderStatus;
use App\Models\Conversation;
use App\Notifications\OfferReceived;
use App\Notifications\OrderUpdated;
use App\Services\OrderService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class NotificationsAndScheduleTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpInventory();
    }

    public function test_status_changes_notify_the_other_party_only(): void
    {
        Notification::fake();
        $order = $this->orderIn(OrderStatus::Paid);

        app(OrderService::class)->ship($order, $order->seller);

        Notification::assertSentTo($order->buyer, OrderUpdated::class, fn ($n) => $n->title === 'Order shipped');
        Notification::assertNotSentTo($order->seller, OrderUpdated::class, fn ($n) => $n->title === 'Order shipped');
        Notification::assertSentTo($order->seller, OrderUpdated::class, fn ($n) => $n->title === 'Payment confirmed');
    }

    public function test_offer_notifies_the_other_participant(): void
    {
        Notification::fake();
        $listing = $this->listing();
        $buyer = $this->user();
        $this->actingAs($buyer)->post('/messages', ['listing_id' => $listing->id]);
        $conversation = Conversation::firstOrFail();

        $this->actingAs($buyer)->post("/messages/{$conversation->id}/offers", ['unit_price' => '10', 'quantity' => '1']);

        Notification::assertSentTo($listing->seller, OfferReceived::class);
        Notification::assertNotSentTo($buyer, OfferReceived::class);
    }

    public function test_schedule_registers_all_dev4_jobs(): void
    {
        $schedule = new Schedule;
        Dev4Schedule::register($schedule);

        $names = collect($schedule->events())->map->description->all();

        $this->assertEqualsCanonicalizing(
            ['payments:reconcile', 'offers:expire', 'orders:cancel-unpaid', 'orders:auto-complete'],
            $names,
        );
    }
}
