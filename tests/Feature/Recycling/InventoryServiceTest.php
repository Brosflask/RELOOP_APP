<?php

namespace Tests\Feature\Recycling;

use App\Contracts\InventoryReservations;
use App\Enums\ListingStatus;
use App\Enums\MaterialLotStatus;
use App\Enums\MovementType;
use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\InventoryMovement;
use App\Models\Listing;
use App\Models\MaterialLot;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use LogicException;
use Tests\TestCase;

/** Developer 3. Real InventoryService together with Developer 4's real OrderService. */
class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $inventory;

    private OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake(); // Developer 4's order emails are not under test here
        $this->inventory = app(InventoryService::class);
        $this->orders = app(OrderService::class);
    }

    private function rawListing(string $kg = '500'): Listing
    {
        return Listing::factory()->rawMaterial(null, $kg)->create(['price' => '3.20'])->fresh(['rawMaterialDetail']);
    }

    public function test_the_contract_is_bound_to_the_real_service(): void
    {
        $this->assertInstanceOf(InventoryService::class, app(InventoryReservations::class));
    }

    public function test_a_processed_lot_has_its_output_in_stock(): void
    {
        $lot = MaterialLot::factory()->withStock('250.5')->create();

        $this->assertSame(['on_hand' => '250.500', 'reserved' => '0.000', 'available' => '250.500'], $this->inventory->balances($lot));
        $this->assertDatabaseHas('inventory_movements', [
            'material_lot_id' => $lot->id, 'movement_type' => MovementType::ProcessingOutput->value,
        ]);
    }

    public function test_buying_reserves_stock_and_cancelling_gives_it_back(): void
    {
        $listing = $this->rawListing('500');
        $order = $this->orders->buyAtListedPrice($listing, User::factory()->create(), '100', 'Kumasi');

        $this->assertSame('400.000', $this->inventory->availableQuantity($listing));
        $this->assertSame('400.000', (string) $listing->rawMaterialDetail->fresh()->available_quantity);

        $this->orders->transition($order, OrderStatus::Cancelled);

        $this->assertSame('500.000', $this->inventory->availableQuantity($listing));
        $this->assertSame(
            ['processing_output', 'reservation', 'reversal'],
            InventoryMovement::orderBy('id')->pluck('movement_type')->map->value->all()
        );
    }

    public function test_a_verified_sale_moves_the_kilograms_into_a_lot_owned_by_the_buyer(): void
    {
        $listing = $this->rawListing('500');
        $sellerLot = $listing->rawMaterialDetail->materialLot;
        $artisan = User::factory()->create();
        $order = $this->orders->buyAtListedPrice($listing, $artisan, '120', 'Accra');

        $this->orders->transition($order, OrderStatus::Paid);
        $this->inventory->commitSale($order->load('items'));
        $this->inventory->commitSale($order->load('items')); // a repeat call changes nothing

        $this->assertSame(['on_hand' => '380.000', 'reserved' => '0.000', 'available' => '380.000'], $this->inventory->balances($sellerLot));
        $this->assertSame(1, InventoryMovement::where('movement_type', 'sale')->count());

        $bought = MaterialLot::where('owner_id', $artisan->id)->sole();
        $this->assertSame($sellerLot->id, $bought->parent_lot_id);
        $this->assertSame($order->items->first()->id, $bought->source_order_item_id);
        $this->assertSame('120.000', $this->inventory->balances($bought)['available']);
        $this->assertStringStartsWith('LOT-', $bought->code);
    }

    public function test_selling_everything_marks_the_listing_sold_out_and_the_lot_used_up(): void
    {
        $listing = $this->rawListing('50');
        $order = $this->orders->buyAtListedPrice($listing, User::factory()->create(), '50', 'Tamale');
        $this->orders->transition($order, OrderStatus::Paid);
        $this->inventory->commitSale($order->load('items'));

        $this->assertSame(ListingStatus::SoldOut, $listing->fresh()->status);
        $this->assertSame(MaterialLotStatus::Depleted, $listing->rawMaterialDetail->materialLot->fresh()->status);
    }

    public function test_you_cannot_order_more_than_is_available(): void
    {
        $listing = $this->rawListing('30');
        $this->orders->buyAtListedPrice($listing, User::factory()->create(), '20', 'Accra');

        $this->expectException(BusinessRuleException::class);
        $this->orders->buyAtListedPrice($listing, User::factory()->create(), '15', 'Accra');
    }

    public function test_reserve_itself_refuses_short_stock(): void
    {
        $listing = $this->rawListing('10');
        $order = $this->orders->buyAtListedPrice($listing, User::factory()->create(), '10', 'Accra');
        $this->inventory->release($order->load('items'));
        $this->inventory->adjust($listing->rawMaterialDetail->materialLot, '-5', 'Recount');

        $this->expectException(BusinessRuleException::class);
        $this->inventory->reserve($order->load('items'));
    }

    public function test_finished_products_are_counted_in_pieces(): void
    {
        $listing = Listing::factory()->finishedProduct(8)->create(['price' => '45.00']);
        $order = $this->orders->buyAtListedPrice($listing, User::factory()->create(), '3', 'Cape Coast');

        $this->assertSame('5.000', $this->inventory->availableQuantity($listing));
        $this->orders->transition($order, OrderStatus::Paid);
        $this->inventory->commitSale($order->load('items'));

        $this->assertSame(['on_hand' => '5.000', 'reserved' => '0.000', 'available' => '5.000'], $this->inventory->balances($listing));
        $this->assertSame('5.000', (string) $listing->productDetail->fresh()->available_quantity);
    }

    public function test_an_adjustment_cannot_remove_reserved_stock(): void
    {
        $listing = $this->rawListing('100');
        $lot = $listing->rawMaterialDetail->materialLot;
        $this->orders->buyAtListedPrice($listing, User::factory()->create(), '80', 'Accra');

        $this->inventory->adjust($lot, '-15', 'Water damage');
        $this->assertSame('5.000', $this->inventory->balances($lot)['available']);

        $this->expectException(BusinessRuleException::class);
        $this->inventory->adjust($lot, '-10', 'Recount');
    }

    public function test_ledger_rows_cannot_be_edited_or_deleted(): void
    {
        $movement = MaterialLot::factory()->withStock('10')->create()->inventoryMovements()->first();

        $this->expectException(LogicException::class);
        $movement->update(['quantity_change' => '99']);
    }

    public function test_a_raw_listing_without_a_lot_has_nothing_available(): void
    {
        $listing = Listing::factory()->create(['listing_type' => 'raw_material']);

        $this->assertSame('0.000', $this->inventory->availableQuantity($listing));
    }
}
