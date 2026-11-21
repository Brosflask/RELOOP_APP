<?php

namespace App\Http\Controllers\Messaging;

use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\StoreOfferRequest;
use App\Models\Conversation;
use App\Models\Offer;
use App\Services\MessagingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OfferController extends Controller
{
    public function __construct(private readonly MessagingService $messaging)
    {
    }

    /** New offer, or a counter-offer when parent_offer_id is sent. */
    public function store(StoreOfferRequest $request, Conversation $conversation): RedirectResponse
    {
        $parent = $request->filled('parent_offer_id') ? Offer::findOrFail($request->integer('parent_offer_id')) : null;

        $this->messaging->makeOffer(
            $conversation,
            $request->user(),
            (string) $request->input('unit_price'),
            (string) $request->input('quantity'),
            $request->input('message'),
            $parent,
        );

        return back()->with('success', $parent ? 'Counter-offer sent.' : 'Offer sent.');
    }

    public function accept(Request $request, Offer $offer): RedirectResponse
    {
        Gate::authorize('respond', $offer);

        $order = $this->messaging->acceptOffer($offer, $request->user());

        return to_route('orders.show', $order)->with('success', 'Offer accepted. Order '.$order->code.' created.');
    }

    public function reject(Request $request, Offer $offer): RedirectResponse
    {
        Gate::authorize('respond', $offer);

        $this->messaging->rejectOffer($offer, $request->user());

        return back()->with('success', 'Offer rejected.');
    }

    public function withdraw(Request $request, Offer $offer): RedirectResponse
    {
        Gate::authorize('withdraw', $offer);

        $this->messaging->withdrawOffer($offer, $request->user());

        return back()->with('success', 'Offer withdrawn.');
    }
}
