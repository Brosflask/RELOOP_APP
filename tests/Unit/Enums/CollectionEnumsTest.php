<?php

namespace Tests\Unit\Enums;

use App\Enums\BatchStatus;
use App\Enums\CollectionRecordStatus;
use App\Enums\CollectionStatus;
use PHPUnit\Framework\TestCase;

/** Developer 2. The allowed status changes. */
class CollectionEnumsTest extends TestCase
{
    public function test_collection_request_transitions(): void
    {
        $this->assertTrue(CollectionStatus::Pending->canTransitionTo(CollectionStatus::Assigned));
        $this->assertTrue(CollectionStatus::Assigned->canTransitionTo(CollectionStatus::Pending));
        $this->assertTrue(CollectionStatus::InProgress->canTransitionTo(CollectionStatus::Completed));
        $this->assertFalse(CollectionStatus::Pending->canTransitionTo(CollectionStatus::Completed));
        $this->assertFalse(CollectionStatus::Completed->canTransitionTo(CollectionStatus::Cancelled));
        $this->assertSame([], CollectionStatus::Cancelled->allowedNext());
        $this->assertTrue(CollectionStatus::InProgress->isOpen());
        $this->assertFalse(CollectionStatus::Completed->isOpen());
    }

    public function test_collection_record_transitions(): void
    {
        $this->assertTrue(CollectionRecordStatus::PendingConfirmation->canTransitionTo(CollectionRecordStatus::Disputed));
        $this->assertTrue(CollectionRecordStatus::Disputed->canTransitionTo(CollectionRecordStatus::Confirmed));
        $this->assertFalse(CollectionRecordStatus::Confirmed->canTransitionTo(CollectionRecordStatus::Disputed));
    }

    public function test_batch_transitions(): void
    {
        $this->assertTrue(BatchStatus::Open->canTransitionTo(BatchStatus::Dispatched));
        $this->assertTrue(BatchStatus::Dispatched->canTransitionTo(BatchStatus::Received));
        $this->assertTrue(BatchStatus::Rejected->canTransitionTo(BatchStatus::Open));
        $this->assertFalse(BatchStatus::Open->canTransitionTo(BatchStatus::Received));
        $this->assertFalse(BatchStatus::Received->canTransitionTo(BatchStatus::Open));
        $this->assertTrue(BatchStatus::Open->isEditable());
        $this->assertFalse(BatchStatus::Dispatched->isEditable());
    }

    public function test_every_status_has_a_label_for_the_ui(): void
    {
        foreach ([CollectionStatus::class, CollectionRecordStatus::class, BatchStatus::class] as $enum) {
            foreach ($enum::options() as $option) {
                $this->assertNotSame('', $option['label']);
            }
        }
    }
}
