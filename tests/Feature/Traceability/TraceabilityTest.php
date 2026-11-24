<?php

namespace Tests\Feature\Traceability;

use App\Enums\RoleName;
use App\Models\BatchCollection;
use App\Models\CollectionRecord;
use App\Models\CollectionRequest;
use App\Models\Listing;
use App\Models\MaterialBatch;
use App\Models\MaterialLineage;
use App\Models\MaterialLot;
use App\Models\ProcessingRecord;
use App\Models\ProductMaterial;
use App\Models\User;
use App\Services\Recycling\ReceivedBatches;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Developer 2, step 3c. The whole chain through everyone's real routes:
 * household pickup -> collector -> batch -> recycler receipt -> Developer 3's processing
 * -> lot -> listing -> traceability page.
 */
class TraceabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $collector;

    private User $recycler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collector = User::factory()->create(['name' => 'Kofi Boateng'])->assignRole(RoleName::Collector);
        $this->recycler = User::factory()->create(['name' => 'Tamale Recycling Ltd'])->assignRole(RoleName::Recycler);
    }

    /** Two households in different areas give PET; it is batched, sent, received and processed. */
    private function processedLot(): MaterialLot
    {
        foreach ([['Sagnarigu', '12'], ['Kumbungu', '8']] as [$area, $kg]) {
            $household = User::factory()->create(['name' => 'Ama Mensah '.$area])->assignRole(RoleName::Household);
            $request = CollectionRequest::factory()->create(['household_id' => $household->id, 'area' => $area, 'pickup_address' => 'Secret street '.$area]);
            $this->actingAs($this->collector)->post(route('collector.jobs.accept', $request));
            $this->actingAs($this->collector)->post(route('collector.jobs.start', $request));
            $this->actingAs($this->collector)->post(route('collector.jobs.complete', $request), ['lines' => [['material_type' => 'pet', 'actual_weight_kg' => $kg]]]);
            $this->actingAs($household)->post(route('collection-records.confirm', $request->records()->first()));
        }

        $records = CollectionRecord::pluck('id')->all();
        $this->actingAs($this->collector)->post(route('collector.batches.store'), ['material_type' => 'pet', 'record_ids' => $records])->assertSessionHasNoErrors();
        $batch = MaterialBatch::sole();
        $this->actingAs($this->collector)->post(route('collector.batches.dispatch', $batch), ['recycler_id' => $this->recycler->id]);
        $this->actingAs($this->recycler)->post(route('recycler.batches.receive', $batch), ['received_weight_kg' => '19.5'])->assertSessionHasNoErrors();

        // Developer 3's code sees the received batch...
        $waiting = app(ReceivedBatches::class)->waitingFor($this->recycler);
        $this->assertCount(1, $waiting);
        $this->assertSame('19.500', $waiting[0]['remaining_weight_kg']);

        // ...and processes it through their real route.
        $this->actingAs($this->recycler)->post(route('recycler.processing.store'), [
            'material_batch_id' => $batch->id,
            'process_type' => 'shredding',
            'input_weight_kg' => '19.5',
            'processed_at' => now()->toDateString(),
            'lots' => [['material_type' => 'pet', 'form' => 'flakes', 'quantity_kg' => '18']],
        ])->assertSessionHasNoErrors();

        return MaterialLot::sole();
    }

    public function test_processing_writes_material_lineage_automatically(): void
    {
        $lot = $this->processedLot();

        $lineage = MaterialLineage::sole();
        $this->assertSame($lot->id, $lineage->material_lot_id);
        $this->assertSame(MaterialBatch::sole()->id, $lineage->material_batch_id);
        $this->assertSame($lot->processing_record_id, $lineage->processing_record_id);
        $this->assertSame('18.000', $lineage->contributed_weight_kg);
    }

    public function test_raw_material_listing_traces_back_to_household_areas(): void
    {
        $lot = $this->processedLot();
        $listing = Listing::factory()->rawMaterial($lot)->create();

        $response = $this->get(route('trace.listing', $listing)); // public, no login
        $response->assertInertia(fn (Assert $page) => $page->component('Traceability/Show')
            ->has('traces', 1)
            ->where('traces.0.lot.code', $lot->code)
            ->where('traces.0.processing.recycler', 'Tamale Recycling Ltd')
            ->where('traces.0.processing.loss_weight_kg', '1.500')
            ->where('traces.0.batches.0.collector', 'Kofi')
            ->where('traces.0.batches.0.received_weight_kg', '19.500')
            ->where('traces.0.batches.0.collections.count', 2)
            ->where('traces.0.batches.0.collections.households', 2)
            ->where('traces.0.batches.0.collections.total_kg', '20.000')
            ->where('traces.0.batches.0.collections.areas', ['Kumbungu', 'Sagnarigu']));

        // Privacy: no household names, addresses or phone numbers on the public page.
        $html = json_encode($response->viewData('page'));
        $this->assertStringNotContainsString('Ama Mensah', $html);
        $this->assertStringNotContainsString('Secret street', $html);
        $this->assertStringNotContainsString('Boateng', $html);
    }

    public function test_product_made_from_a_bought_lot_traces_through_the_seller(): void
    {
        $lot = $this->processedLot();
        $artisan = User::factory()->create(['name' => 'Accra Weavers'])->assignRole(RoleName::Artisan);
        // What Developer 3's InventoryService does when an artisan buys raw material: a child lot.
        $bought = MaterialLot::factory()->create(['owner_id' => $artisan->id, 'parent_lot_id' => $lot->id, 'processing_record_id' => null, 'initial_quantity' => '5']);
        $product = Listing::factory()->finishedProduct()->create(['seller_id' => $artisan->id, 'title' => 'Woven bag']);
        ProductMaterial::create(['listing_id' => $product->id, 'material_lot_id' => $bought->id, 'quantity_used' => '5', 'unit' => 'kg']);

        $this->get(route('trace.listing', $product))
            ->assertInertia(fn (Assert $page) => $page
                ->where('traces.0.lot.code', $bought->code)
                ->has('traces.0.ownership', 2)
                ->where('traces.0.ownership.0.code', $lot->code)
                ->where('traces.0.ownership.1.owner', 'Accra Weavers')
                ->where('traces.0.batches.0.collections.total_kg', '20.000'));
    }

    public function test_merged_batches_are_followed(): void
    {
        $small = MaterialBatch::factory()->create(['status' => 'consolidated']);
        BatchCollection::factory()->create(['material_batch_id' => $small->id, 'weight_kg' => '7']);
        $parent = MaterialBatch::factory()->receivedBy($this->recycler)->create();
        $small->update(['consolidated_into_id' => $parent->id]);
        $record = ProcessingRecord::factory()->create(['material_batch_id' => $parent->id, 'recycler_id' => $this->recycler->id]);
        $lot = MaterialLot::factory()->create(['owner_id' => $this->recycler->id, 'processing_record_id' => $record->id]);

        // No lineage row (made by a factory): falls back to the processing record's batch.
        $this->actingAs($this->recycler)->get(route('trace.lot', $lot))
            ->assertInertia(fn (Assert $page) => $page
                ->where('traces.0.batches.0.code', $parent->code)
                ->where('traces.0.batches.0.merged_batches', [$small->code])
                ->where('traces.0.batches.0.collections.count', 1));
    }

    public function test_hidden_listings_and_guests_on_lot_pages(): void
    {
        $draft = Listing::factory()->draft()->create();
        $this->get(route('trace.listing', $draft))->assertNotFound();

        $lot = MaterialLot::factory()->create();
        $this->get(route('trace.lot', $lot))->assertRedirect(route('login'));
    }
}
