<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Services\Orders\OrderSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class BuyerDashboardTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpInventory();
    }

    public function test_requires_buyer_role(): void
    {
        $this->actingAs($this->user())->get('/buyer/dashboard')->assertForbidden();
    }

    public function test_shows_the_buyers_orders(): void
    {
        $order = $this->orderIn(OrderStatus::Paid);
        $buyer = $order->buyer->assignRole(RoleName::Buyer);

        $this->actingAs($buyer)->get('/buyer/dashboard')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Buyer/Dashboard')
                ->where('counts.paid', 1)
                ->has('in_progress', 1)
                ->has('awaiting_payment', 0));
    }

    public function test_seller_summary_lists_orders_to_fulfil(): void
    {
        $order = $this->orderIn(OrderStatus::Paid);

        $summary = OrderSummary::forSeller($order->seller);

        $this->assertSame(1, $summary['counts']['paid']);
        $this->assertSame($order->code, $summary['to_fulfil'][0]['code']);
    }
}
