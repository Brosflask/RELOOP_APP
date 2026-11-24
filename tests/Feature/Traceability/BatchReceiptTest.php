<?php

namespace Tests\Feature\Traceability;

use App\Enums\BatchStatus;
use App\Enums\RoleName;
use App\Events\BatchReceived;
use App\Models\BatchReceipt;
use App\Models\CollectionRecord;
use App\Models\MaterialBatch;
use App\Models\User;
use App\Notifications\BatchUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Developer 2, step 3b: recyclers receive or reject batches. */
class BatchReceiptTest extends TestCase
{
    use RefreshDatabase;

    private User $collector;

    private User $recycler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collector = User::factory()->create()->assignRole(RoleName::Collector);
        $this->recycler = User::factory()->create()->assignRole(RoleName::Recycler);
    }

    /** A 20 kg batch the collector has sent to the recycler. */
    private function sentBatch(): MaterialBatch
    {
        $records = CollectionRecord::factory()->count(2)->create(['collector_id' => $this->collector->id]);
        $this->actingAs($this->collector)->post(route('collector.batches.store'), ['material_type' => 'pet', 'record_ids' => $records->pluck('id')->all()]);
        $batch = MaterialBatch::latest('id')->first();
        $this->actingAs($this->collector)->post(route('collector.batches.dispatch', $batch), ['recycler_id' => $this->recycler->id]);

        return $batch->fresh();
    }

    public function test_recycler_sees_incoming_batches(): void
    {
        $batch = $this->sentBatch();

        $this->actingAs($this->recycler)->get(route('recycler.batches.index'))
            ->assertInertia(fn (Assert $page) => $page->component('Recycler/Batches/Index')
                ->has('incoming', 1)->where('incoming.0.code', $batch->code)->has('history.data', 0));
        $this->actingAs($this->recycler)->get(route('batches.show', $batch))
            ->assertInertia(fn (Assert $page) => $page->where('can.receive', true)->where('can.manage', false));
    }

    public function test_recycler_confirms_receipt_with_their_own_weight(): void
    {
        Notification::fake();
        Event::fake([BatchReceived::class]);
        $batch = $this->sentBatch();

        $this->actingAs($this->recycler)->post(route('recycler.batches.receive', $batch), ['received_weight_kg' => '19.4', 'condition_notes' => 'A bit wet'])
            ->assertRedirect(route('batches.show', $batch));

        $batch->refresh();
        $this->assertSame(BatchStatus::Received, $batch->status);
        $this->assertNotNull($batch->received_at);
        $receipt = $batch->receipt;
        $this->assertSame('20.000', $receipt->expected_weight_kg);
        $this->assertSame('19.400', $receipt->received_weight_kg);
        $this->assertSame('-0.600', $receipt->weight_difference_kg);
        $this->assertSame($this->recycler->id, $receipt->recycler_id);
        Notification::assertSentTo($this->collector, BatchUpdated::class);
        Event::assertDispatched(BatchReceived::class, fn ($e) => $e->batch->is($batch));

        // Only once.
        $this->actingAs($this->recycler)->post(route('recycler.batches.receive', $batch), ['received_weight_kg' => '19.4'])
            ->assertSessionHasErrors('general');
        $this->assertSame(1, BatchReceipt::count());

        $this->actingAs($this->recycler)->get(route('batches.show', $batch))
            ->assertInertia(fn (Assert $page) => $page->where('can.receive', false)->where('can.process', true)->where('receipt.received_weight_kg', '19.400'));
    }

    public function test_only_the_recycler_it_was_sent_to_can_receive_it(): void
    {
        $batch = $this->sentBatch();
        $otherRecycler = User::factory()->create()->assignRole(RoleName::Recycler);

        $this->actingAs($otherRecycler)->post(route('recycler.batches.receive', $batch), ['received_weight_kg' => '20'])->assertForbidden();
        $this->actingAs($this->collector)->post(route('recycler.batches.receive', $batch), ['received_weight_kg' => '20'])->assertForbidden();
        $this->actingAs($this->recycler)->post(route('recycler.batches.receive', $batch), ['received_weight_kg' => '0'])->assertSessionHasErrors('received_weight_kg');
        $this->assertSame(BatchStatus::Dispatched, $batch->fresh()->status);
    }

    public function test_an_open_batch_cannot_be_received(): void
    {
        $batch = MaterialBatch::factory()->create(['collector_id' => $this->collector->id, 'recycler_id' => $this->recycler->id]);

        $this->actingAs($this->recycler)->post(route('recycler.batches.receive', $batch), ['received_weight_kg' => '20'])
            ->assertSessionHasErrors('general');
    }

    public function test_rejected_batch_goes_back_to_the_collector(): void
    {
        Notification::fake();
        $batch = $this->sentBatch();

        $this->actingAs($this->recycler)->post(route('recycler.batches.reject', $batch), ['reason' => 'Mixed with PVC'])
            ->assertRedirect(route('recycler.batches.index'));
        $batch->refresh();
        $this->assertSame(BatchStatus::Rejected, $batch->status);
        $this->assertSame('Mixed with PVC', $batch->rejection_reason);
        Notification::assertSentTo($this->collector, BatchUpdated::class);

        $this->actingAs($this->collector)->get(route('batches.show', $batch))
            ->assertInertia(fn (Assert $page) => $page->where('can.reopen', true));
        $this->actingAs($this->collector)->post(route('collector.batches.reopen', $batch))->assertSessionHasNoErrors();

        $batch->refresh();
        $this->assertSame(BatchStatus::Open, $batch->status);
        $this->assertNull($batch->recycler_id);
        $this->assertStringContainsString('Mixed with PVC', $batch->notes);

        // It can be fixed and sent again.
        $this->actingAs($this->collector)->post(route('collector.batches.dispatch', $batch), ['recycler_id' => $this->recycler->id])
            ->assertSessionHasNoErrors();
        $this->assertSame(BatchStatus::Dispatched, $batch->fresh()->status);
    }

    public function test_reject_needs_a_reason(): void
    {
        $batch = $this->sentBatch();
        $this->actingAs($this->recycler)->post(route('recycler.batches.reject', $batch), ['reason' => ''])->assertSessionHasErrors('reason');
    }
}
