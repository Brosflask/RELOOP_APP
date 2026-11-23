<?php

namespace App\Http\Controllers\Recycling;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recycling\StoreInventoryAdjustmentRequest;
use App\Models\MaterialLot;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;

/** Developer 3. */
class InventoryAdjustmentController extends Controller
{
    public function store(StoreInventoryAdjustmentRequest $request, MaterialLot $lot, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validated();
        $inventory->adjust($lot, (string) $data['quantity'], $data['reason'], $request->user());

        return back()->with('success', 'Stock adjusted.');
    }
}
