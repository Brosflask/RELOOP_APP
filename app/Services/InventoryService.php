<?php

namespace App\Services;

use App\Contracts\InventoryReservations;
use App\Enums\ListingStatus;
use App\Enums\MaterialLotStatus;
use App\Enums\MovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\InventoryMovement;
use App\Models\Listing;
use App\Models\MaterialLot;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Developer 3. The only code that writes inventory_movements.
 *
 * Stock lives in "buckets": a material lot (raw material, shared by every
 * listing of that lot) or a finished-product listing (no lot). Each ledger row
 * changes a bucket's stock on hand (quantity_change) and its reserved stock
 * (reserved_change). Available = on hand - reserved.
 *
 * Also implements Developer 4's InventoryReservations contract. Those methods
 * are called inside Developer 4's open transaction; DB::transaction() below
 * nests safely as a savepoint.
 */
class InventoryService implements InventoryReservations
{
    // ---------------------------------------------------------------
    // Developer 4 contract
    // ---------------------------------------------------------------

    public function availableQuantity(Listing $listing): string
    {
        $bucket = $this->bucketFor($listing);
        if ($bucket === null) {
            return Money::quantity(0);
        }

        return Money::quantity(max(0, $this->balancesMilli($bucket)['available']) / 1000);
    }

    public function reserve(Order $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                if ($this->outstandingReservationMilli($item) > 0 || $this->hasMovement($item, MovementType::Sale)) {
                    continue; // already reserved or sold: calling twice is harmless
                }

                $listing = $this->listingForItem($item);
                $bucket = $this->lockBucket($listing);
                if ($bucket === null) {
                    throw new BusinessRuleException('This listing has no stock to sell.', 'quantity');
                }

                $wanted = Money::toMilli((string) $item->quantity);
                $available = $this->balancesMilli($bucket)['available'];
                if ($wanted > $available) {
                    throw new BusinessRuleException(
                        'Only '.Money::quantity(max(0, $available) / 1000).' '.$listing->unit().' available.',
                        'quantity'
                    );
                }

                $this->write($bucket, $listing, MovementType::Reservation, 0, $wanted, $item, 'Order '.$order->code);
                $this->refreshListings($bucket);
            }
        });
    }

    public function release(Order $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                $listing = $this->listingForItem($item);
                $bucket = $this->lockBucket($listing);
                $outstanding = $this->outstandingReservationMilli($item);
                if ($bucket === null || $outstanding <= 0) {
                    continue; // nothing held for this line
                }

                $this->write($bucket, $listing, MovementType::Reversal, 0, -$outstanding, $item, 'Order '.$order->code.' cancelled');
                $this->refreshListings($bucket);
            }
        });
    }

    public function commitSale(Order $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                if ($this->hasMovement($item, MovementType::Sale)) {
                    continue; // already sold: calling twice is harmless
                }

                $listing = $this->listingForItem($item);
                $bucket = $this->lockBucket($listing);
                if ($bucket === null) {
                    Log::alert('Paid order line has no stock bucket', ['order_item_id' => $item->id]);
                    continue;
                }

                $quantity = Money::toMilli((string) $item->quantity);
                $outstanding = $this->outstandingReservationMilli($item);

                if ($outstanding <= 0) {
                    // Paid without a live reservation. Money has moved, so record the sale and flag it.
                    Log::alert('Sale recorded without a reservation', ['order_item_id' => $item->id]);
                }

                $this->write($bucket, $listing, MovementType::Sale, -$quantity, -max(0, $outstanding), $item, 'Order '.$order->code.' paid');

                if ($this->balancesMilli($bucket)['on_hand'] < 0) {
                    Log::alert('Stock went below zero after a sale', ['order_item_id' => $item->id]);
                }

                if ($bucket instanceof MaterialLot) {
                    $this->transferToBuyer($bucket, $item, (int) $order->buyer_id, $quantity);
                }

                $this->refreshListings($bucket);
            }
        });
    }

    // ---------------------------------------------------------------
    // Stock in, adjustments, balances (used by Developer 3's own pages)
    // ---------------------------------------------------------------

    /** Opening ledger row for a lot produced by a processing record. */
    public function recordProcessingOutput(MaterialLot $lot, ?User $by = null): InventoryMovement
    {
        return DB::transaction(fn () => $this->write(
            $lot, null, MovementType::ProcessingOutput, Money::toMilli((string) $lot->initial_quantity), 0, null,
            'Processing output', $by
        ));
    }

    /** Opening stock for a finished-product listing (pieces made). */
    public function recordInitialStock(Listing $listing, string $quantity, ?User $by = null): InventoryMovement
    {
        if ($listing->isRawMaterial()) {
            throw new BusinessRuleException('Raw-material stock comes from its lot, not from the listing.', 'quantity');
        }

        return DB::transaction(function () use ($listing, $quantity, $by) {
            Listing::query()->lockForUpdate()->findOrFail($listing->id);
            $movement = $this->write($listing, $listing, MovementType::InitialStock, Money::toMilli($quantity), 0, null, 'Opening stock', $by);
            $this->refreshListings($listing);

            return $movement;
        });
    }

    /**
     * Manual correction, such as damage or a recount. $change is signed ("-2.500").
     * Stock on hand can never drop below what is reserved for unpaid orders.
     */
    public function adjust(MaterialLot|Listing $bucket, string $change, string $reason, ?User $by = null): InventoryMovement
    {
        if (trim($reason) === '') {
            throw new BusinessRuleException('Give a reason for the adjustment.', 'reason');
        }
        if ($bucket instanceof Listing && $bucket->isRawMaterial()) {
            throw new BusinessRuleException('Adjust the material lot, not the listing.', 'quantity');
        }

        return DB::transaction(function () use ($bucket, $change, $reason, $by) {
            $bucket = $bucket::query()->lockForUpdate()->findOrFail($bucket->id);
            $delta = Money::toMilli($change);
            if ($delta === 0) {
                throw new BusinessRuleException('The adjustment must change the stock.', 'quantity');
            }

            $balances = $this->balancesMilli($bucket);
            if ($balances['on_hand'] + $delta < $balances['reserved']) {
                throw new BusinessRuleException(
                    'Only '.Money::quantity(max(0, $balances['available']) / 1000).' can be removed; the rest is reserved for orders.',
                    'quantity'
                );
            }

            $movement = $this->write($bucket, $bucket instanceof Listing ? $bucket : null, MovementType::Adjustment, $delta, 0, null, $reason, $by);
            $this->refreshListings($bucket);

            return $movement;
        });
    }

    /** @return array{on_hand: string, reserved: string, available: string} */
    public function balances(MaterialLot|Listing $bucket): array
    {
        $milli = $this->balancesMilli($bucket);

        return [
            'on_hand' => Money::quantity($milli['on_hand'] / 1000),
            'reserved' => Money::quantity($milli['reserved'] / 1000),
            'available' => Money::quantity($milli['available'] / 1000),
        ];
    }

    /** Available quantity of one lot, never below zero. */
    public function availableQuantityForLot(MaterialLot $lot): string
    {
        return Money::quantity(max(0, $this->balancesMilli($lot)['available']) / 1000);
    }

    /**
     * Available quantity for many lots in one query (lists and dashboards).
     *
     * @param  list<int>  $lotIds
     * @return array<int, string> lot id => available, never below zero
     */
    public function availableForLots(array $lotIds): array
    {
        if ($lotIds === []) {
            return [];
        }
        $rows = InventoryMovement::query()
            ->whereIn('material_lot_id', $lotIds)
            ->groupBy('material_lot_id')
            ->selectRaw('material_lot_id, COALESCE(SUM(quantity_change), 0) - COALESCE(SUM(reserved_change), 0) AS available')
            ->pluck('available', 'material_lot_id');

        $result = [];
        foreach ($lotIds as $id) {
            $result[$id] = Money::quantity(max(0, Money::toMilli((string) ($rows[$id] ?? 0))) / 1000);
        }

        return $result;
    }

    /** Ledger rows for one bucket, newest first. */
    public function history(MaterialLot|Listing $bucket)
    {
        return $this->bucketQuery($bucket)->latest('id');
    }

    // ---------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------

    /** The stock bucket a listing sells from, or null when a raw listing has no lot yet. */
    private function bucketFor(Listing $listing): MaterialLot|Listing|null
    {
        if (! $listing->isRawMaterial()) {
            return $listing;
        }
        $lotId = $listing->rawMaterialDetail?->material_lot_id;

        return $lotId ? MaterialLot::find($lotId) : null;
    }

    private function lockBucket(Listing $listing): MaterialLot|Listing|null
    {
        $bucket = $this->bucketFor($listing);
        if ($bucket === null) {
            return null;
        }

        return $bucket::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($bucket->id);
    }

    private function bucketQuery(MaterialLot|Listing $bucket)
    {
        return $bucket instanceof MaterialLot
            ? InventoryMovement::query()->where('material_lot_id', $bucket->id)
            : InventoryMovement::query()->where('listing_id', $bucket->id)->whereNull('material_lot_id');
    }

    /** @return array{on_hand: int, reserved: int, available: int} in thousandths */
    private function balancesMilli(MaterialLot|Listing $bucket): array
    {
        $row = $this->bucketQuery($bucket)
            ->selectRaw('COALESCE(SUM(quantity_change), 0) AS on_hand, COALESCE(SUM(reserved_change), 0) AS reserved')
            ->first();
        $onHand = Money::toMilli((string) $row->on_hand);
        $reserved = Money::toMilli((string) $row->reserved);

        return ['on_hand' => $onHand, 'reserved' => $reserved, 'available' => $onHand - $reserved];
    }

    private function outstandingReservationMilli(OrderItem $item): int
    {
        return Money::toMilli((string) InventoryMovement::query()
            ->where('order_item_id', $item->id)
            ->sum('reserved_change'));
    }

    private function hasMovement(OrderItem $item, MovementType $type): bool
    {
        return InventoryMovement::query()
            ->where('order_item_id', $item->id)
            ->where('movement_type', $type->value)
            ->exists();
    }

    private function listingForItem(OrderItem $item): Listing
    {
        return Listing::withTrashed()->with(['rawMaterialDetail', 'productDetail'])->findOrFail($item->listing_id);
    }

    private function write(
        MaterialLot|Listing $bucket,
        ?Listing $listing,
        MovementType $type,
        int $quantityMilli,
        int $reservedMilli,
        ?OrderItem $item,
        ?string $note,
        ?User $by = null,
    ): InventoryMovement {
        return InventoryMovement::create([
            'material_lot_id' => $bucket instanceof MaterialLot ? $bucket->id : null,
            'listing_id' => $listing?->id,
            'order_id' => $item?->order_id,
            'order_item_id' => $item?->id,
            'movement_type' => $type,
            'quantity_change' => Money::quantity($quantityMilli / 1000),
            'reserved_change' => Money::quantity($reservedMilli / 1000),
            'unit' => $bucket instanceof MaterialLot ? $bucket->unit : $bucket->unit(),
            'note' => $note ? Str::limit($note, 250) : null,
            'created_by' => $by?->id ?? auth()->id(),
        ]);
    }

    /** A raw-material sale moves the kilograms into a new lot owned by the buyer, linked to the seller's lot. */
    private function transferToBuyer(MaterialLot $sourceLot, OrderItem $item, int $buyerId, int $quantityMilli): void
    {
        $lot = MaterialLot::create([
            'code' => 'TMP-'.Str::uuid(),
            'owner_id' => $buyerId,
            'processing_record_id' => $sourceLot->processing_record_id,
            'parent_lot_id' => $sourceLot->id,
            'source_order_item_id' => $item->id,
            'material_type' => $sourceLot->material_type,
            'form' => $sourceLot->form,
            'grade' => $sourceLot->grade,
            'initial_quantity' => Money::quantity($quantityMilli / 1000),
            'unit' => $sourceLot->unit,
            'status' => MaterialLotStatus::Available,
        ]);
        $lot->update(['code' => self::lotCode($lot)]);

        $this->write($lot, null, MovementType::Transfer, $quantityMilli, 0, $item, 'Bought from lot '.$sourceLot->code);
    }

    public static function lotCode(MaterialLot $lot): string
    {
        return sprintf('LOT-%s-%06d', ($lot->created_at ?? now())->format('Y'), $lot->id);
    }

    /** Keep cached available_quantity, lot status and sold-out listings in step with the ledger. */
    private function refreshListings(MaterialLot|Listing $bucket): void
    {
        $milli = $this->balancesMilli($bucket);
        $available = Money::quantity(max(0, $milli['available']) / 1000);

        if ($bucket instanceof MaterialLot) {
            $bucket->update([
                'status' => $milli['on_hand'] > 0 ? MaterialLotStatus::Available : MaterialLotStatus::Depleted,
            ]);
            $listingIds = DB::table('raw_material_listing_details')->where('material_lot_id', $bucket->id)->pluck('listing_id');
            DB::table('raw_material_listing_details')->where('material_lot_id', $bucket->id)
                ->update(['available_quantity' => $available, 'updated_at' => now()]);
        } else {
            $listingIds = collect([$bucket->id]);
            DB::table('product_listing_details')->where('listing_id', $bucket->id)
                ->update(['available_quantity' => $available, 'updated_at' => now()]);
        }

        // Nothing left on hand: an active listing becomes sold out. Stock back: sold out becomes active again.
        if ($milli['on_hand'] <= 0) {
            Listing::query()->whereIn('id', $listingIds)->where('status', ListingStatus::Active->value)
                ->update(['status' => ListingStatus::SoldOut->value]);
        } elseif ($milli['available'] > 0) {
            Listing::query()->whereIn('id', $listingIds)->where('status', ListingStatus::SoldOut->value)
                ->update(['status' => ListingStatus::Active->value]);
        }
    }
}
