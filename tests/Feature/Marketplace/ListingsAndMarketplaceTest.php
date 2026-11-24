<?php

namespace Tests\Feature\Marketplace;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\MaterialLot;
use App\Models\Order;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Developer 3. Listings, marketplace and the hand-off to Developer 4's orders, with the real InventoryService. */
class ListingsAndMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    private User $recycler;

    private User $artisan;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->recycler = User::factory()->create()->assignRole('recycler');
        $this->artisan = User::factory()->create()->assignRole('artisan');
    }

    private function lot(User $owner, string $kg = '500', string $type = 'pet'): MaterialLot
    {
        return MaterialLot::factory()->withStock($kg)->create(['owner_id' => $owner->id, 'material_type' => $type]);
    }

    private function rawPayload(MaterialLot $lot, array $overrides = []): array
    {
        return array_merge([
            'material_lot_id' => $lot->id, 'title' => 'Clean PET flakes', 'description' => 'Washed, 8mm.',
            'price' => '3.20', 'location' => 'Kumasi', 'minimum_order_quantity' => '50', 'publish' => true,
        ], $overrides);
    }

    public function test_recycler_publishes_a_lot_and_buyers_find_it(): void
    {
        $lot = $this->lot($this->recycler);
        $this->actingAs($this->recycler)->post(route('listings.store-raw-material'), $this->rawPayload($lot))
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $listing = Listing::sole();
        $this->assertSame(ListingStatus::Active, $listing->status);
        $this->assertSame('500.000', (string) $listing->rawMaterialDetail->available_quantity);

        $this->get(route('marketplace.index', ['type' => 'raw_material', 'material_type' => 'pet', 'q' => 'flakes', 'max_price' => 5]))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Marketplace/Index')->has('listings.data', 1)
                ->where('listings.data.0.unit', 'kg')->where('listings.data.0.available_quantity', '500.000'));
        $this->get(route('marketplace.index', ['material_type' => 'hdpe']))
            ->assertInertia(fn (Assert $p) => $p->has('listings.data', 0));
    }

    public function test_listing_page_shows_traceability_and_who_can_buy(): void
    {
        $lot = $this->lot($this->recycler);
        $this->actingAs($this->recycler)->post(route('listings.store-raw-material'), $this->rawPayload($lot));
        $listing = Listing::sole();

        $this->get(route('marketplace.show', $listing))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Marketplace/Show')
                ->where('listing.available_quantity', '500.000')->where('traceability.0.lot_code', $lot->code)->where('can.buy', false));
        $this->actingAs($this->artisan)->get(route('marketplace.show', $listing))
            ->assertInertia(fn (Assert $p) => $p->where('can.buy', true)->where('can.message', true)->where('can.edit', false));
        $this->actingAs($this->recycler)->get(route('marketplace.show', $listing))
            ->assertInertia(fn (Assert $p) => $p->where('can.buy', false)->where('can.edit', true));
    }

    public function test_buying_from_the_listing_page_reserves_real_stock(): void
    {
        $lot = $this->lot($this->recycler);
        $this->actingAs($this->recycler)->post(route('listings.store-raw-material'), $this->rawPayload($lot));
        $listing = Listing::sole();

        $this->actingAs($this->artisan)->post(route('orders.store'), [
            'listing_id' => $listing->id, 'quantity' => '120', 'delivery_address' => 'Accra',
        ])->assertSessionHasNoErrors();

        $this->assertSame($lot->id, Order::sole()->items->first()->material_lot_id);
        $this->assertSame('380.000', app(InventoryService::class)->availableQuantity($listing));
        $this->assertSame('380.000', (string) $listing->rawMaterialDetail->fresh()->available_quantity);

        $this->actingAs($this->artisan)->post(route('orders.store'), [
            'listing_id' => $listing->id, 'quantity' => '400', 'delivery_address' => 'Accra',
        ])->assertSessionHasErrors('quantity');
    }

    public function test_message_seller_opens_a_conversation(): void
    {
        $listing = Listing::factory()->rawMaterial($this->lot($this->recycler))->create();

        $this->actingAs($this->artisan)->post(route('messages.store'), ['listing_id' => $listing->id, 'body' => 'Can you deliver to Tema?'])
            ->assertRedirect();
        $this->assertDatabaseHas('conversations', ['listing_id' => $listing->id]);
    }

    public function test_you_cannot_list_a_lot_you_do_not_own_or_one_already_listed(): void
    {
        $theirs = $this->lot($this->artisan);
        $this->actingAs($this->recycler)->post(route('listings.store-raw-material'), $this->rawPayload($theirs))
            ->assertSessionHasErrors('material_lot_id');

        $mine = $this->lot($this->recycler);
        $this->actingAs($this->recycler)->post(route('listings.store-raw-material'), $this->rawPayload($mine));
        $this->actingAs($this->recycler)->post(route('listings.store-raw-material'), $this->rawPayload($mine))
            ->assertSessionHasErrors('material_lot_id');
        $this->assertSame(1, Listing::count());
    }

    public function test_only_the_right_role_can_create_each_kind(): void
    {
        $this->actingAs($this->artisan)->post(route('listings.store-raw-material'), $this->rawPayload($this->lot($this->artisan)))->assertForbidden();
        $this->actingAs($this->recycler)->get(route('listings.create', ['type' => 'finished_product']))->assertForbidden();
        $this->actingAs($this->recycler)->get(route('listings.create', ['type' => 'raw_material']))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Listings/Create')->where('type', 'raw_material'));
    }

    public function test_artisan_lists_a_product_linked_to_lots_they_own(): void
    {
        $lot = $this->lot($this->artisan, '40', 'hdpe');
        $payload = [
            'title' => 'Woven market basket', 'price' => '85', 'location' => 'Bolgatanga',
            'product_category' => 'baskets', 'product_condition' => 'new', 'production_quantity' => 12,
            'materials' => [['material_lot_id' => $lot->id, 'quantity_used' => '6']], 'publish' => true,
        ];

        $this->actingAs($this->artisan)->post(route('listings.store-product'), $payload)->assertSessionHasNoErrors();
        $listing = Listing::sole();
        $this->assertSame('12.000', app(InventoryService::class)->availableQuantity($listing));
        $this->assertSame([$lot->id], $listing->sourceLots->pluck('id')->all());

        $this->get(route('marketplace.index', ['material_type' => 'hdpe', 'category' => 'baskets']))
            ->assertInertia(fn (Assert $p) => $p->has('listings.data', 1)->where('listings.data.0.unit', 'piece'));
        $this->get(route('marketplace.show', $listing))
            ->assertInertia(fn (Assert $p) => $p->where('traceability.0.lot_code', $lot->code)->where('traceability.0.quantity_used', '6.000'));

        $payload['materials'] = [['material_lot_id' => $this->lot($this->recycler)->id]];
        $this->actingAs($this->artisan)->post(route('listings.store-product'), $payload)->assertSessionHasErrors('materials');
    }

    public function test_drafts_are_hidden_and_status_changes_follow_the_rules(): void
    {
        $lot = $this->lot($this->recycler);
        $this->actingAs($this->recycler)->post(route('listings.store-raw-material'), $this->rawPayload($lot, ['publish' => false]));
        $listing = Listing::sole();
        $this->assertSame(ListingStatus::Draft, $listing->status);

        $this->actingAs($this->artisan)->get(route('marketplace.show', $listing))->assertForbidden();
        $this->get(route('marketplace.index'))->assertInertia(fn (Assert $p) => $p->has('listings.data', 0));

        $this->actingAs($this->recycler)->patch(route('listings.status', $listing), ['status' => 'active'])->assertSessionHasNoErrors();
        $this->actingAs($this->recycler)->patch(route('listings.status', $listing), ['status' => 'paused'])->assertSessionHasNoErrors();
        $this->get(route('marketplace.index'))->assertInertia(fn (Assert $p) => $p->has('listings.data', 0));

        $this->actingAs($this->artisan)->post(route('orders.store'), ['listing_id' => $listing->id, 'quantity' => '1', 'delivery_address' => 'x'])
            ->assertSessionHasErrors('listing_id');

        $this->actingAs($this->recycler)->patch(route('listings.status', $listing), ['status' => 'closed'])->assertSessionHasNoErrors();
        $this->actingAs($this->recycler)->patch(route('listings.status', $listing), ['status' => 'active'])->assertSessionHasErrors('status');
        $this->actingAs($this->artisan)->patch(route('listings.status', $listing), ['status' => 'active'])->assertForbidden();
    }

    public function test_seller_edits_details_and_manages_photos(): void
    {
        Storage::fake('public');
        $listing = Listing::factory()->rawMaterial($this->lot($this->recycler))->create();

        $this->actingAs($this->recycler)->get(route('listings.edit', $listing))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Listings/Edit')->where('listing.lot.id', $listing->rawMaterialDetail->material_lot_id));

        $this->actingAs($this->recycler)->put(route('listings.update', $listing), [
            'title' => 'Hot-washed PET flakes', 'price' => '3.50', 'location' => 'Tema', 'minimum_order_quantity' => '25',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Hot-washed PET flakes', $listing->fresh()->title);
        $this->assertSame('25.000', (string) $listing->rawMaterialDetail->fresh()->minimum_order_quantity);

        $this->actingAs($this->recycler)->post(route('listings.images.store', $listing), ['images' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.png')]])
            ->assertSessionHasNoErrors();
        $image = $listing->images()->first();
        Storage::disk('public')->assertExists($image->path);

        $this->actingAs($this->artisan)->put(route('listings.update', $listing), ['title' => 'x', 'price' => '1'])->assertForbidden();
        $this->actingAs($this->recycler)->delete(route('listings.images.destroy', [$listing, $image]))->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($image->path);
    }

    public function test_seller_pages_and_artisan_dashboard_render(): void
    {
        Listing::factory()->finishedProduct(5)->create(['seller_id' => $this->artisan->id]);

        $this->actingAs($this->artisan)->get(route('listings.index'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Listings/Index')->has('listings.data', 1)->where('can.create_product', true));
        $this->actingAs($this->artisan)->get(route('artisan.dashboard'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Artisan/Dashboard')->where('stats.active_products', 1)->has('orders.counts'));
        $this->actingAs($this->recycler)->get(route('artisan.dashboard'))->assertForbidden();
    }

    public function test_guests_can_browse_but_not_see_drafts(): void
    {
        $live = Listing::factory()->rawMaterial($this->lot($this->recycler))->create();
        $draft = Listing::factory()->draft()->create();

        $this->get(route('marketplace.index'))->assertOk()->assertInertia(fn (Assert $p) => $p->has('listings.data', 1));
        $this->get(route('marketplace.show', $live))->assertOk()->assertInertia(fn (Assert $p) => $p->where('can.buy', false));
        $this->get(route('marketplace.show', $draft))->assertForbidden();
        $this->get(route('listings.index'))->assertRedirect(route('login'));
    }
}
