<?php

namespace Tests\Feature\Recycling;

use App\Events\MaterialLotsCreated;
use App\Models\InventoryMovement;
use App\Models\MaterialBatch;
use App\Models\MaterialLot;
use App\Models\ProcessingRecord;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Developer 3. Processing records, lots and stock adjustments through the real routes. */
class ProcessingAndLotsTest extends TestCase
{
    use RefreshDatabase;

    private User $recycler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->recycler = User::factory()->create()->assignRole('recycler');
    }

    /** A batch this recycler received, using Developer 2's factory (writes the batch_receipts row). */
    private function receivedBatch(string $kg = '600', ?User $recycler = null): int
    {
        return MaterialBatch::factory()->receivedBy($recycler ?? $this->recycler, $kg)->create()->id;
    }

    private function payload(int $batchId, array $overrides = []): array
    {
        return array_merge([
            'material_batch_id' => $batchId,
            'process_type' => 'shredding',
            'input_weight_kg' => '600',
            'processed_at' => now()->toDateString(),
            'lots' => [
                ['material_type' => 'pet', 'form' => 'flakes', 'grade' => 'A', 'quantity_kg' => '420.5'],
                ['material_type' => 'pp', 'form' => 'regrind', 'grade' => null, 'quantity_kg' => '120'],
            ],
        ], $overrides);
    }

    public function test_recording_processing_creates_lots_with_stock_and_works_out_the_loss(): void
    {
        Event::fake([MaterialLotsCreated::class]);
        $batch = $this->receivedBatch();

        $response = $this->actingAs($this->recycler)->post(route('recycler.processing.store'), $this->payload($batch));

        $record = ProcessingRecord::sole();
        $response->assertRedirect(route('recycler.processing.show', $record))->assertSessionHas('success');
        $this->assertSame('540.500', (string) $record->output_weight_kg);
        $this->assertSame('59.500', (string) $record->loss_weight_kg);
        $this->assertStringStartsWith('PR-', $record->reference);

        $lots = MaterialLot::orderBy('id')->get();
        $this->assertCount(2, $lots);
        $this->assertSame('420.500', app(InventoryService::class)->balances($lots[0])['available']);
        $this->assertSame(2, InventoryMovement::where('movement_type', 'processing_output')->count());
        Event::assertDispatched(MaterialLotsCreated::class);
    }

    public function test_a_loss_sent_from_the_form_is_ignored(): void
    {
        $batch = $this->receivedBatch();
        $this->actingAs($this->recycler)->post(route('recycler.processing.store'), $this->payload($batch, ['loss_weight_kg' => '0']));

        $this->assertSame('59.500', (string) ProcessingRecord::sole()->loss_weight_kg);
    }

    public function test_you_cannot_process_a_batch_you_did_not_receive(): void
    {
        $batch = $this->receivedBatch('600', User::factory()->create());

        $this->actingAs($this->recycler)->post(route('recycler.processing.store'), $this->payload($batch))
            ->assertSessionHasErrors('material_batch_id');
        $this->assertSame(0, ProcessingRecord::count());
    }

    public function test_input_cannot_exceed_what_is_left_of_the_batch(): void
    {
        $batch = $this->receivedBatch('600');
        $this->actingAs($this->recycler)->post(route('recycler.processing.store'), $this->payload($batch, [
            'input_weight_kg' => '500', 'lots' => [['material_type' => 'pet', 'quantity_kg' => '450']],
        ]));

        $this->actingAs($this->recycler)->post(route('recycler.processing.store'), $this->payload($batch, [
            'input_weight_kg' => '150', 'lots' => [['material_type' => 'pet', 'quantity_kg' => '100']],
        ]))->assertSessionHasErrors('input_weight_kg');
        $this->assertSame(1, ProcessingRecord::count());
    }

    public function test_output_cannot_be_more_than_input(): void
    {
        $batch = $this->receivedBatch();
        $this->actingAs($this->recycler)->post(route('recycler.processing.store'), $this->payload($batch, ['input_weight_kg' => '500']))
            ->assertSessionHasErrors('lots');
        $this->assertSame(0, MaterialLot::count());
    }

    public function test_invalid_values_fail_validation(): void
    {
        $batch = $this->receivedBatch();
        $this->actingAs($this->recycler)->post(route('recycler.processing.store'), $this->payload($batch, [
            'input_weight_kg' => '-5', 'lots' => [['material_type' => 'plastic?', 'quantity_kg' => '0']],
        ]))->assertSessionHasErrors(['input_weight_kg', 'lots.0.material_type', 'lots.0.quantity_kg']);
    }

    public function test_only_recyclers_reach_recycler_pages(): void
    {
        $this->get(route('recycler.dashboard'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('recycler.dashboard'))->assertForbidden();
    }

    public function test_dashboard_shows_stock_and_waiting_batches(): void
    {
        $batch = $this->receivedBatch('600');
        $this->actingAs($this->recycler)->post(route('recycler.processing.store'), $this->payload($batch, [
            'input_weight_kg' => '400', 'lots' => [['material_type' => 'pet', 'quantity_kg' => '380']],
        ]));

        $this->actingAs($this->recycler)->get(route('recycler.dashboard'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Recycler/Dashboard')
                ->where('stats.available_kg', '380.000')
                ->where('stats.lots_in_stock', 1)
                ->where('stats.loss_rate_percent', 5)
                ->where('batches_waiting.0.remaining_weight_kg', '200.000')
                ->has('orders.counts'));
    }

    public function test_create_and_show_pages_render(): void
    {
        $batch = $this->receivedBatch();
        $this->actingAs($this->recycler)->get(route('recycler.processing.create', ['batch' => $batch]))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Recycler/Processing/Create')->where('selected_batch_id', $batch)->has('batches', 1));

        $this->actingAs($this->recycler)->post(route('recycler.processing.store'), $this->payload($batch));
        $record = ProcessingRecord::sole();
        $this->actingAs($this->recycler)->get(route('recycler.processing.show', $record))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Recycler/Processing/Show')->has('lots', 2));
        $this->actingAs($this->recycler)->get(route('recycler.processing.index'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('records.data', 1));

        $other = User::factory()->create()->assignRole('recycler');
        $this->actingAs($other)->get(route('recycler.processing.show', $record))->assertForbidden();
    }

    public function test_lots_are_private_to_their_owner(): void
    {
        $mine = MaterialLot::factory()->withStock('50')->create(['owner_id' => $this->recycler->id]);
        $theirs = MaterialLot::factory()->withStock('70')->create();

        $this->actingAs($this->recycler)->get(route('lots.index'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Inventory/Lots/Index')->has('lots.data', 1)->where('lots.data.0.id', $mine->id)
                ->where('lots.data.0.available_quantity', '50.000'));
        $this->actingAs($this->recycler)->get(route('lots.show', $mine))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('balances.available', '50.000')->has('history.data', 1)->where('can.adjust', true));
        $this->actingAs($this->recycler)->get(route('lots.show', $theirs))->assertForbidden();
    }

    public function test_owner_can_adjust_stock_and_others_cannot(): void
    {
        $lot = MaterialLot::factory()->withStock('50')->create(['owner_id' => $this->recycler->id]);

        $this->actingAs($this->recycler)->post(route('lots.adjust', $lot), ['quantity' => '-2.5', 'reason' => 'Water damage'])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('47.500', app(InventoryService::class)->balances($lot)['available']);

        $this->actingAs($this->recycler)->post(route('lots.adjust', $lot), ['quantity' => '-100', 'reason' => 'Oops'])
            ->assertSessionHasErrors('quantity');
        $this->actingAs(User::factory()->create())->post(route('lots.adjust', $lot), ['quantity' => '5', 'reason' => 'x'])
            ->assertForbidden();
    }
}
