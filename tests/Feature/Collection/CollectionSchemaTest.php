<?php

namespace Tests\Feature\Collection;

use App\Enums\BatchStatus;
use App\Enums\CollectionRecordStatus;
use App\Enums\CollectionStatus;
use App\Enums\MaterialType;
use App\Models\BatchCollection;
use App\Models\BatchReceipt;
use App\Models\CollectionRecord;
use App\Models\CollectionRequest;
use App\Models\CollectionRequestItem;
use App\Models\MaterialBatch;
use App\Models\User;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Developer 2, step 1: tables, models, factories and the columns other developers rely on. */
class CollectionSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_request_gets_a_readable_reference_and_starts_pending(): void
    {
        $request = CollectionRequest::factory()->create();

        $this->assertMatchesRegularExpression('/^CR-\d{4}-\d{6}$/', $request->reference);
        $this->assertSame(CollectionStatus::Pending, $request->fresh()->status);
        $this->assertNull($request->collector_id);
        $this->assertTrue(CollectionRequest::unassigned()->whereKey($request->id)->exists());
    }

    public function test_a_request_lists_each_plastic_type_once(): void
    {
        $request = CollectionRequest::factory()->create();
        CollectionRequestItem::factory()->create(['collection_request_id' => $request->id, 'material_type' => MaterialType::Pet]);
        CollectionRequestItem::factory()->create(['collection_request_id' => $request->id, 'material_type' => MaterialType::Hdpe]);

        $this->assertSame(['pet', 'hdpe'], $request->items()->orderBy('id')->pluck('material_type')->map->value->all());

        $this->expectException(QueryException::class);
        CollectionRequestItem::factory()->create(['collection_request_id' => $request->id, 'material_type' => MaterialType::Pet]);
    }

    public function test_records_link_a_request_its_household_and_collector(): void
    {
        $collector = User::factory()->create();
        $request = CollectionRequest::factory()->inProgress($collector)->create();
        $record = CollectionRecord::factory()->forRequest($request)->weighing('7.250')->create();

        $this->assertMatchesRegularExpression('/^COL-\d{4}-\d{6}$/', $record->reference);
        $this->assertSame(CollectionRecordStatus::PendingConfirmation, $record->fresh()->status);
        $this->assertSame('7.250', $record->fresh()->actual_weight_kg);
        $this->assertTrue($record->household->is($request->household));
        $this->assertTrue($record->collector->is($collector));
        $this->assertTrue($request->records()->first()->is($record));
    }

    public function test_a_walk_in_record_needs_no_request_or_household(): void
    {
        $record = CollectionRecord::factory()->create();

        $this->assertNull($record->collection_request_id);
        $this->assertNull($record->household_id);
        $this->assertSame(CollectionRecordStatus::Confirmed, $record->fresh()->status);
    }

    public function test_a_batch_holds_records_and_a_record_can_be_in_only_one_batch(): void
    {
        $batch = MaterialBatch::factory()->create(['total_weight_kg' => '0']);
        $records = CollectionRecord::factory()->count(2)->weighing('4.500')->create();
        foreach ($records as $record) {
            $batch->collectionRecords()->attach($record->id, ['weight_kg' => $record->actual_weight_kg]);
        }

        $this->assertMatchesRegularExpression('/^BATCH-\d{4}-\d{6}$/', $batch->code);
        $this->assertSame(BatchStatus::Open, $batch->fresh()->status);
        $this->assertCount(2, $batch->collectionRecords);
        $this->assertSame('9.000', number_format((float) $batch->batchCollections()->sum('weight_kg'), 3, '.', ''));
        $this->assertTrue($records[0]->batch->is($batch));
        $this->assertSame(0, CollectionRecord::unbatched()->count());

        $this->expectException(QueryException::class);
        BatchCollection::factory()->create(['collection_record_id' => $records[0]->id]);
    }

    public function test_a_batch_can_be_received_only_once(): void
    {
        $recycler = User::factory()->create();
        $batch = MaterialBatch::factory()->receivedBy($recycler, '590')->create();

        $receipt = $batch->receipt;
        $this->assertSame(BatchStatus::Received, $batch->fresh()->status);
        $this->assertTrue($receipt->recycler->is($recycler));
        $this->assertSame('590.000', $receipt->received_weight_kg);
        $this->assertSame('-10.000', $receipt->weight_difference_kg);

        $this->expectException(QueryException::class);
        BatchReceipt::factory()->create(['material_batch_id' => $batch->id]);
    }

    public function test_small_batches_can_be_merged_into_a_bigger_one(): void
    {
        $big = MaterialBatch::factory()->create();
        $small = MaterialBatch::factory()->count(2)->create([
            'status' => BatchStatus::Consolidated,
            'consolidated_into_id' => $big->id,
        ]);

        $this->assertCount(2, $big->consolidatedBatches);
        $this->assertTrue($small[0]->consolidatedInto->is($big));
    }

    public function test_columns_other_developers_read_exist(): void
    {
        // Developer 3, App\Services\Recycling\ReceivedBatches
        $this->assertTrue(Schema::hasColumns('batch_receipts', ['material_batch_id', 'recycler_id', 'received_weight_kg', 'created_at']));
        // Developer 4, ReportService "kg collected"
        $this->assertTrue(Schema::hasColumns('collection_records', ['actual_weight_kg', 'created_at']));
    }

    public function test_developer_4_admin_report_counts_kg_collected(): void
    {
        CollectionRecord::factory()->weighing('12.500')->create();
        CollectionRecord::factory()->weighing('7.500')->create();

        $today = CarbonImmutable::now();
        $impact = app(ReportService::class)->impact($today, $today);

        $this->assertSame('20.000', $impact['kg_collected']);
    }
}
