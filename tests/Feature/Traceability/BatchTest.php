<?php

namespace Tests\Feature\Traceability;

use App\Enums\BatchStatus;
use App\Enums\MaterialType;
use App\Enums\RoleName;
use App\Models\CollectionRecord;
use App\Models\CollectionRequest;
use App\Models\MaterialBatch;
use App\Models\User;
use App\Notifications\BatchUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Developer 2, step 3a: building batches and sending them to a recycler. */
class BatchTest extends TestCase
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

    /** Confirmed walk-in records of this collector. */
    private function records(int $count, string $kg = '10', MaterialType $type = MaterialType::Pet)
    {
        return CollectionRecord::factory()->count($count)->weighing($kg)->create([
            'collector_id' => $this->collector->id,
            'material_type' => $type,
        ]);
    }

    private function batchOf($records): MaterialBatch
    {
        $this->actingAs($this->collector)->post(route('collector.batches.store'), [
            'material_type' => 'pet',
            'record_ids' => $records->pluck('id')->all(),
        ])->assertSessionHasNoErrors();

        return MaterialBatch::latest('id')->first();
    }

    public function test_collector_creates_a_batch_from_confirmed_records(): void
    {
        $records = $this->records(3, '4.25');

        $this->actingAs($this->collector)->get(route('collector.batches.create', ['material_type' => 'pet']))
            ->assertInertia(fn (Assert $page) => $page->component('Collector/Batches/Create')->has('records', 3)->has('ready', 1));

        $batch = $this->batchOf($records);

        $this->assertSame(BatchStatus::Open, $batch->status);
        $this->assertSame('12.750', $batch->total_weight_kg);
        $this->assertCount(3, $batch->collectionRecords);
        $this->assertSame(0, CollectionRecord::unbatched()->count());
    }

    public function test_only_confirmed_unbatched_records_of_the_same_type_and_collector(): void
    {
        $pet = $this->records(1);
        $hdpe = $this->records(1, '5', MaterialType::Hdpe);
        $household = User::factory()->create();
        $request = CollectionRequest::factory()->completed($this->collector)->create(['household_id' => $household->id]);
        $waiting = CollectionRecord::factory()->forRequest($request)->create(['material_type' => MaterialType::Pet]);
        $someoneElses = CollectionRecord::factory()->create(['material_type' => MaterialType::Pet]);

        foreach ([$hdpe->first(), $waiting, $someoneElses] as $bad) {
            $this->actingAs($this->collector)->post(route('collector.batches.store'), [
                'material_type' => 'pet', 'record_ids' => [$pet->first()->id, $bad->id],
            ])->assertSessionHasErrors('record_ids');
        }
        $this->assertSame(0, MaterialBatch::count(), 'a failed batch is rolled back completely');

        $this->batchOf($pet);
        $this->actingAs($this->collector)->post(route('collector.batches.store'), [
            'material_type' => 'pet', 'record_ids' => [$pet->first()->id],
        ])->assertSessionHasErrors('record_ids');
        $this->assertSame(1, MaterialBatch::count());
    }

    public function test_records_can_be_added_and_removed_while_open(): void
    {
        $batch = $this->batchOf($this->records(2, '3'));
        $more = $this->records(1, '1.5');

        $this->actingAs($this->collector)->post(route('collector.batches.records.add', $batch), ['record_ids' => [$more->first()->id]])
            ->assertSessionHasNoErrors();
        $this->assertSame('7.500', $batch->fresh()->total_weight_kg);

        $this->actingAs($this->collector)->delete(route('collector.batches.records.remove', [$batch, $more->first()]))
            ->assertSessionHasNoErrors();
        $this->assertSame('6.000', $batch->fresh()->total_weight_kg);
        $this->assertSame(1, CollectionRecord::unbatched()->count());
    }

    public function test_dispatch_sends_the_batch_and_locks_it(): void
    {
        Notification::fake();
        $batch = $this->batchOf($this->records(2));

        $this->actingAs($this->collector)->get(route('batches.show', $batch))
            ->assertInertia(fn (Assert $page) => $page->component('Batches/Show')->where('can.dispatch', true)->has('recyclers', 1)->has('records', 2));

        $this->actingAs($this->collector)->post(route('collector.batches.dispatch', $batch), ['recycler_id' => $this->recycler->id])
            ->assertSessionHasNoErrors();

        $batch->refresh();
        $this->assertSame(BatchStatus::Dispatched, $batch->status);
        $this->assertSame($this->recycler->id, $batch->recycler_id);
        $this->assertNotNull($batch->dispatched_at);
        Notification::assertSentTo($this->recycler, BatchUpdated::class);

        // Locked now.
        $this->actingAs($this->collector)->post(route('collector.batches.records.add', $batch), ['record_ids' => [$this->records(1)->first()->id]])
            ->assertSessionHasErrors('general');
        $this->actingAs($this->collector)->post(route('collector.batches.dispatch', $batch), ['recycler_id' => $this->recycler->id])
            ->assertSessionHasErrors('general');
        $this->actingAs($this->collector)->get(route('batches.show', $batch))
            ->assertInertia(fn (Assert $page) => $page->where('can.manage', false)->where('can.dispatch', false));
    }

    public function test_dispatch_needs_a_recycler_and_some_weight(): void
    {
        $batch = $this->batchOf($this->records(1));
        $notRecycler = User::factory()->create();

        $this->actingAs($this->collector)->post(route('collector.batches.dispatch', $batch), ['recycler_id' => $notRecycler->id])
            ->assertSessionHasErrors('recycler_id');

        $empty = MaterialBatch::factory()->create(['collector_id' => $this->collector->id, 'total_weight_kg' => '0']);
        $this->actingAs($this->collector)->post(route('collector.batches.dispatch', $empty), ['recycler_id' => $this->recycler->id])
            ->assertSessionHasErrors('general');
    }

    public function test_open_batches_can_be_merged(): void
    {
        $a = $this->batchOf($this->records(2, '5'));
        $b = $this->batchOf($this->records(1, '2.5'));

        $this->actingAs($this->collector)->post(route('collector.batches.consolidate'), ['batch_ids' => [$a->id, $b->id]])
            ->assertSessionHasNoErrors();

        $parent = MaterialBatch::latest('id')->first();
        $this->assertSame('12.500', $parent->total_weight_kg);
        $this->assertSame(BatchStatus::Open, $parent->status);
        foreach ([$a, $b] as $child) {
            $child->refresh();
            $this->assertSame(BatchStatus::Consolidated, $child->status);
            $this->assertSame($parent->id, $child->consolidated_into_id);
        }

        // Adding a record to the merged batch counts on top of its children.
        $this->actingAs($this->collector)->post(route('collector.batches.records.add', $parent), ['record_ids' => [$this->records(1, '1')->first()->id]]);
        $this->assertSame('13.500', $parent->fresh()->total_weight_kg);
    }

    public function test_merge_rejects_mixed_types_and_other_peoples_batches(): void
    {
        $pet = $this->batchOf($this->records(1));
        $hdpe = MaterialBatch::factory()->ofType(MaterialType::Hdpe)->create(['collector_id' => $this->collector->id]);
        $other = MaterialBatch::factory()->create();

        $this->actingAs($this->collector)->post(route('collector.batches.consolidate'), ['batch_ids' => [$pet->id, $hdpe->id]])
            ->assertSessionHasErrors('batch_ids');
        $this->actingAs($this->collector)->post(route('collector.batches.consolidate'), ['batch_ids' => [$pet->id, $other->id]])
            ->assertSessionHasErrors('batch_ids');
        $this->assertSame(BatchStatus::Open, $pet->fresh()->status);
    }

    public function test_who_can_see_and_change_a_batch(): void
    {
        $batch = $this->batchOf($this->records(1));
        $otherCollector = User::factory()->create()->assignRole(RoleName::Collector);
        $admin = User::factory()->create()->assignRole(RoleName::Admin);

        $this->actingAs($otherCollector)->get(route('batches.show', $batch))->assertForbidden();
        $this->actingAs($otherCollector)->post(route('collector.batches.dispatch', $batch), ['recycler_id' => $this->recycler->id])->assertForbidden();
        $this->actingAs($this->recycler)->get(route('batches.show', $batch))->assertForbidden();
        $this->actingAs($this->recycler)->get(route('collector.batches.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('batches.show', $batch))->assertOk();

        $this->actingAs($this->collector)->post(route('collector.batches.dispatch', $batch), ['recycler_id' => $this->recycler->id]);
        $this->actingAs($this->recycler)->get(route('batches.show', $batch))->assertOk();
    }

    public function test_batches_list(): void
    {
        $this->batchOf($this->records(2));
        $this->records(1, '3', MaterialType::Ldpe);

        $this->actingAs($this->collector)->get(route('collector.batches.index'))
            ->assertInertia(fn (Assert $page) => $page->component('Collector/Batches/Index')
                ->has('batches.data', 1)->where('batches.data.0.records_count', 2)
                ->has('ready', 1)->where('ready.0.material_type', 'ldpe')->where('ready.0.kg', '3.000'));
    }
}
