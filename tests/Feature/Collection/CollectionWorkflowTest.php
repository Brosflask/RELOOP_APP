<?php

namespace Tests\Feature\Collection;

use App\Enums\CollectionRecordStatus;
use App\Enums\CollectionStatus;
use App\Enums\RoleName;
use App\Jobs\ConfirmStaleCollectionRecords;
use App\Models\CollectionRecord;
use App\Models\CollectionRequest;
use App\Models\User;
use App\Notifications\CollectionUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Developer 2, step 2: the pickup workflow from request to confirmed weights. */
class CollectionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $household;

    private User $collector;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->household = User::factory()->create()->assignRole(RoleName::Household);
        $this->collector = User::factory()->create()->assignRole(RoleName::Collector);
        $this->admin = User::factory()->create()->assignRole(RoleName::Admin);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'pickup_address' => 'House 12, Gumani Road',
            'area' => 'Sagnarigu',
            'gps_address' => 'nt-123-4567',
            'contact_phone' => '024 123 4567',
            'preferred_date' => now()->addDay()->toDateString(),
            'preferred_time_slot' => 'morning',
            'items' => [
                ['material_type' => 'pet', 'estimated_weight_kg' => '5'],
                ['material_type' => 'hdpe', 'estimated_weight_kg' => '2.5'],
            ],
        ], $overrides);
    }

    private function inProgressRequest(): CollectionRequest
    {
        return CollectionRequest::factory()->inProgress($this->collector)->create(['household_id' => $this->household->id]);
    }

    public function test_household_requests_a_pickup(): void
    {
        $this->actingAs($this->household)
            ->post(route('household.collections.store'), $this->payload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $request = CollectionRequest::with('items')->sole();
        $this->assertSame(CollectionStatus::Pending, $request->status);
        $this->assertSame('NT-123-4567', $request->gps_address);
        $this->assertSame('0241234567', $request->contact_phone);
        $this->assertSame('7.500', $request->estimated_weight_kg);
        $this->assertCount(2, $request->items);
    }

    public function test_pickup_request_is_validated(): void
    {
        $this->actingAs($this->household)
            ->post(route('household.collections.store'), $this->payload([
                'contact_phone' => '12345',
                'gps_address' => 'nowhere',
                'preferred_date' => now()->subDay()->toDateString(),
                'items' => [['material_type' => 'pet'], ['material_type' => 'pet']],
            ]))
            ->assertSessionHasErrors(['contact_phone', 'gps_address', 'preferred_date', 'items.1.material_type']);

        $this->actingAs($this->household)
            ->post(route('household.collections.store'), $this->payload(['items' => []]))
            ->assertSessionHasErrors('items');

        $this->assertSame(0, CollectionRequest::count());
    }

    public function test_only_households_can_request_and_pages_need_the_right_role(): void
    {
        $this->actingAs($this->collector)->post(route('household.collections.store'), $this->payload())->assertForbidden();
        $this->actingAs($this->household)->get(route('collector.dashboard'))->assertForbidden();
        $this->actingAs($this->household)->get(route('admin.collections.index'))->assertForbidden();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->post(route('household.collections.store'), $this->payload())->assertRedirect(route('login'));
        $this->get(route('collector.dashboard'))->assertRedirect(route('login'));
    }

    public function test_household_pages_render(): void
    {
        $request = CollectionRequest::factory()->create(['household_id' => $this->household->id]);

        $this->actingAs($this->household)->get(route('household.collections.index'))
            ->assertInertia(fn (Assert $page) => $page->component('Household/Collections/Index')->has('requests.data', 1)->where('open_count', 1));
        $this->actingAs($this->household)->get(route('household.collections.create'))
            ->assertInertia(fn (Assert $page) => $page->component('Household/Collections/Form')->where('defaults.area', $request->area));
        $this->actingAs($this->household)->get(route('collections.show', $request))
            ->assertInertia(fn (Assert $page) => $page->component('Collections/Show')->where('can.edit', true)->where('can.accept', false));
    }

    public function test_household_can_edit_only_while_pending_and_only_their_own(): void
    {
        $request = CollectionRequest::factory()->create(['household_id' => $this->household->id]);
        $this->actingAs($this->household)
            ->put(route('household.collections.update', $request), $this->payload(['pickup_address' => 'New place', 'items' => [['material_type' => 'ldpe']]]))
            ->assertSessionHasNoErrors();
        $this->assertSame('New place', $request->fresh()->pickup_address);
        $this->assertSame(['ldpe'], $request->items()->pluck('material_type')->map->value->all());

        $other = User::factory()->create()->assignRole(RoleName::Household);
        $this->actingAs($other)->put(route('household.collections.update', $request), $this->payload())->assertForbidden();
        $this->actingAs($other)->get(route('collections.show', $request))->assertForbidden();

        $request->update(['status' => CollectionStatus::Assigned, 'collector_id' => $this->collector->id]);
        $this->actingAs($this->household)->put(route('household.collections.update', $request), $this->payload())
            ->assertSessionHasErrors('general');
    }

    public function test_full_pickup_from_accept_to_confirmed_weights(): void
    {
        Notification::fake();
        $request = CollectionRequest::factory()->create(['household_id' => $this->household->id]);

        $this->actingAs($this->collector)->get(route('collector.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->component('Collector/Dashboard')->has('available', 1)->has('mine', 0));

        $this->actingAs($this->collector)->post(route('collector.jobs.accept', $request))->assertSessionHasNoErrors();
        $this->assertSame(CollectionStatus::Assigned, $request->fresh()->status);
        Notification::assertSentTo($this->household, CollectionUpdated::class);

        $this->actingAs($this->collector)->post(route('collector.jobs.start', $request))->assertSessionHasNoErrors();
        $this->actingAs($this->collector)->post(route('collector.jobs.complete', $request), [
            'lines' => [
                ['material_type' => 'pet', 'actual_weight_kg' => '4.25'],
                ['material_type' => 'pet', 'actual_weight_kg' => '1'],
                ['material_type' => 'hdpe', 'actual_weight_kg' => '2.1'],
                ['material_type' => 'pp', 'actual_weight_kg' => '0'],
            ],
        ])->assertSessionHasNoErrors();

        $request->refresh();
        $this->assertSame(CollectionStatus::Completed, $request->status);
        $this->assertNotNull($request->completed_at);
        $records = $request->records()->orderBy('id')->get();
        $this->assertSame(['pet' => '5.250', 'hdpe' => '2.100'], $records->mapWithKeys(fn ($r) => [$r->material_type->value => $r->actual_weight_kg])->all());
        $this->assertTrue($records->every(fn ($r) => $r->status === CollectionRecordStatus::PendingConfirmation && $r->household_id === $this->household->id));

        $this->actingAs($this->household)->post(route('collection-records.confirm', $records[0]))->assertSessionHasNoErrors();
        $this->actingAs($this->household)->post(route('collection-records.dispute', $records[1]), ['reason' => 'It was at least 4 kg'])->assertSessionHasNoErrors();
        $this->assertSame(CollectionRecordStatus::Confirmed, $records[0]->fresh()->status);
        $this->assertSame(CollectionRecordStatus::Disputed, $records[1]->fresh()->status);
        Notification::assertSentTo($this->collector, CollectionUpdated::class);

        // Confirming twice is refused.
        $this->actingAs($this->household)->post(route('collection-records.confirm', $records[0]))->assertSessionHasErrors('general');
    }

    public function test_two_collectors_cannot_take_the_same_job(): void
    {
        $request = CollectionRequest::factory()->create(['household_id' => $this->household->id]);
        $second = User::factory()->create()->assignRole(RoleName::Collector);

        $this->actingAs($this->collector)->post(route('collector.jobs.accept', $request))->assertSessionHasNoErrors();
        $this->actingAs($second)->post(route('collector.jobs.accept', $request))->assertSessionHasErrors('general');
        $this->assertSame($this->collector->id, $request->fresh()->collector_id);

        // ...and the second collector can't work on it either.
        $this->actingAs($second)->post(route('collector.jobs.start', $request))->assertForbidden();
    }

    public function test_steps_cannot_be_skipped(): void
    {
        $request = CollectionRequest::factory()->assignedTo($this->collector)->create(['household_id' => $this->household->id]);

        $this->actingAs($this->collector)->post(route('collector.jobs.complete', $request), [
            'lines' => [['material_type' => 'pet', 'actual_weight_kg' => '3']],
        ])->assertSessionHasErrors('general');

        $this->actingAs($this->collector)->post(route('collector.jobs.start', $request));
        $this->actingAs($this->collector)->post(route('collector.jobs.complete', $request), [
            'lines' => [['material_type' => 'pet', 'actual_weight_kg' => '0']],
        ])->assertSessionHasErrors('lines');

        $this->assertSame(0, CollectionRecord::count());
        $this->assertSame(CollectionStatus::InProgress, $request->fresh()->status);
    }

    public function test_collector_can_give_a_job_back(): void
    {
        $request = CollectionRequest::factory()->assignedTo($this->collector)->create(['household_id' => $this->household->id]);

        $this->actingAs($this->collector)->post(route('collector.jobs.release', $request))->assertRedirect(route('collector.dashboard'));

        $request->refresh();
        $this->assertSame(CollectionStatus::Pending, $request->status);
        $this->assertNull($request->collector_id);
    }

    public function test_household_cancels_until_pickup_starts_admin_until_completed(): void
    {
        $assigned = CollectionRequest::factory()->assignedTo($this->collector)->create(['household_id' => $this->household->id]);
        $this->actingAs($this->household)->post(route('collections.cancel', $assigned), ['reason' => 'Moved house'])->assertSessionHasNoErrors();
        $this->assertSame(CollectionStatus::Cancelled, $assigned->fresh()->status);
        $this->assertSame($this->household->id, $assigned->fresh()->cancelled_by);

        $started = $this->inProgressRequest();
        $this->actingAs($this->household)->post(route('collections.cancel', $started))->assertSessionHasErrors('general');
        $this->actingAs($this->collector)->post(route('collections.cancel', $started))->assertForbidden();
        $this->actingAs($this->admin)->post(route('collections.cancel', $started))->assertSessionHasNoErrors();
        $this->assertSame(CollectionStatus::Cancelled, $started->fresh()->status);

        $done = CollectionRequest::factory()->completed($this->collector)->create(['household_id' => $this->household->id]);
        $this->actingAs($this->admin)->post(route('collections.cancel', $done))->assertSessionHasErrors('general');
    }

    public function test_only_the_household_can_confirm_a_record(): void
    {
        $record = CollectionRecord::factory()->forRequest($this->inProgressRequest())->create();

        $this->actingAs($this->collector)->post(route('collection-records.confirm', $record))->assertForbidden();
        $this->actingAs($this->admin)->post(route('collection-records.dispute', $record), ['reason' => 'Looks wrong'])->assertForbidden();
        $this->actingAs($this->household)->post(route('collection-records.dispute', $record), ['reason' => ''])->assertSessionHasErrors('reason');
    }

    public function test_collector_records_a_walk_in_drop_off(): void
    {
        $this->actingAs($this->collector)->post(route('collector.records.store'), [
            'material_type' => 'ldpe', 'actual_weight_kg' => '3.4',
        ])->assertRedirect(route('collector.records.index'));

        $record = CollectionRecord::sole();
        $this->assertNull($record->collection_request_id);
        $this->assertSame(CollectionRecordStatus::Confirmed, $record->status);
        $this->assertSame('3.400', $record->actual_weight_kg);

        $this->actingAs($this->collector)->get(route('collector.records.index', ['status' => 'confirmed']))
            ->assertInertia(fn (Assert $page) => $page->component('Collector/Records/Index')->has('records.data', 1));
        $this->actingAs($this->collector)->get(route('collector.records.create'))
            ->assertInertia(fn (Assert $page) => $page->component('Collector/Records/Create'));
    }

    public function test_admin_assigns_a_collector_and_resolves_a_dispute(): void
    {
        $request = CollectionRequest::factory()->create(['household_id' => $this->household->id]);
        $notCollector = User::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.collections.assign', $request), ['collector_id' => $notCollector->id])
            ->assertSessionHasErrors('collector_id');
        $this->actingAs($this->admin)->post(route('admin.collections.assign', $request), ['collector_id' => $this->collector->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($this->collector->id, $request->fresh()->collector_id);
        $this->assertSame($this->admin->id, $request->fresh()->assigned_by);

        $record = CollectionRecord::factory()->forRequest($this->inProgressRequest())->disputed()->weighing('2')->create();

        $this->actingAs($this->admin)->get(route('admin.collections.index'))
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Collections/Index')
                ->has('disputes', 1)->has('collectors', 1)->where('counts.assigned', 1));

        $this->actingAs($this->admin)->post(route('admin.collections.resolve', $record), ['actual_weight_kg' => '4', 'note' => 'Checked the photo'])
            ->assertSessionHasNoErrors();
        $record->refresh();
        $this->assertSame(CollectionRecordStatus::Confirmed, $record->status);
        $this->assertSame('4.000', $record->actual_weight_kg);
        $this->assertStringContainsString('Checked the photo', $record->notes);
    }

    public function test_unanswered_records_are_confirmed_after_72_hours(): void
    {
        $request = $this->inProgressRequest();
        $old = CollectionRecord::factory()->forRequest($request)->create(['created_at' => now()->subHours(73)]);
        $new = CollectionRecord::factory()->forRequest($request)->create();

        dispatch_sync(new ConfirmStaleCollectionRecords);

        $this->assertSame(CollectionRecordStatus::Confirmed, $old->fresh()->status);
        $this->assertSame(CollectionRecordStatus::PendingConfirmation, $new->fresh()->status);
    }

    public function test_contact_details_are_hidden_from_other_collectors(): void
    {
        $request = CollectionRequest::factory()->create(['household_id' => $this->household->id]);

        $this->actingAs($this->collector)->get(route('collections.show', $request))
            ->assertInertia(fn (Assert $page) => $page->where('can.accept', true)->where('can.see_contact', false));
    }
}
