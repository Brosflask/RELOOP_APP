<?php

namespace App\Http\Controllers\Messaging;

use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\StoreConversationRequest;
use App\Models\Conversation;
use App\Models\Listing;
use App\Services\MessagingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function __construct(private readonly MessagingService $messaging)
    {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();

        $conversations = Conversation::query()
            ->where(fn ($q) => $q->where('buyer_id', $user->id)->orWhere('seller_id', $user->id))
            ->with(['listing:id,title,price', 'buyer:id,name', 'seller:id,name', 'latestOffer'])
            ->withCount(['messages as unread_count' => fn ($q) => $q->whereNull('read_at')->where('sender_id', '!=', $user->id)])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->through(fn (Conversation $c) => [
                'id' => $c->id,
                'listing' => $c->listing ? ['id' => $c->listing->id, 'title' => $c->listing->title] : null,
                'other_party' => $c->buyer_id === $user->id ? $c->seller?->only('id', 'name') : $c->buyer?->only('id', 'name'),
                'role' => $c->buyer_id === $user->id ? 'buyer' : 'seller',
                'latest_offer_status' => $c->latestOffer?->status->value,
                'unread_count' => $c->unread_count,
                'last_message_at' => $c->last_message_at?->toIso8601String(),
            ]);

        return Inertia::render('Messages/Index', ['conversations' => $conversations]);
    }

    public function store(StoreConversationRequest $request): RedirectResponse
    {
        $listing = Listing::findOrFail($request->integer('listing_id'));
        $conversation = $this->messaging->openConversation($request->user(), $listing);

        if ($request->filled('body')) {
            $this->messaging->sendMessage($conversation, $request->user(), $request->string('body'));
        }

        return to_route('messages.show', $conversation);
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        Gate::authorize('view', $conversation);

        $user = $request->user();
        $this->messaging->expireStaleOffers($conversation);
        if ($conversation->isParticipant($user)) {
            $this->messaging->markRead($conversation, $user);
        }

        $conversation->load(['listing:id,title,price,seller_id', 'buyer:id,name', 'seller:id,name', 'orders:id,conversation_id,code,status']);

        $messages = $conversation->messages()->with('sender:id,name')->latest('id')->limit(200)->get()->reverse()->values()
            ->map(fn ($m) => [
                'kind' => 'message',
                'id' => $m->id,
                'sender' => $m->sender->only('id', 'name'),
                'body' => $m->body,
                'created_at' => $m->created_at->toIso8601String(),
            ]);

        $offers = $conversation->offers()->with('maker:id,name')->orderBy('id')->get()
            ->map(fn ($o) => [
                'kind' => 'offer',
                'id' => $o->id,
                'maker' => $o->maker->only('id', 'name'),
                'unit_price' => (string) $o->unit_price,
                'quantity' => (string) $o->quantity,
                'message' => $o->message,
                'status' => $o->status->value,
                'parent_offer_id' => $o->parent_offer_id,
                'expires_at' => $o->expires_at?->toIso8601String(),
                'created_at' => $o->created_at->toIso8601String(),
                'can_respond' => $o->isPending() && $o->made_by !== $user->id && $conversation->isParticipant($user),
                'can_withdraw' => $o->isPending() && $o->made_by === $user->id,
            ]);

        return Inertia::render('Messages/Show', [
            'conversation' => [
                'id' => $conversation->id,
                'status' => $conversation->status->value,
                'listing' => $conversation->listing?->only('id', 'title', 'price'),
                'buyer' => $conversation->buyer->only('id', 'name'),
                'seller' => $conversation->seller->only('id', 'name'),
                'orders' => $conversation->orders->map->only('id', 'code', 'status'),
            ],
            'timeline' => $messages->concat($offers)->sortBy('created_at')->values(),
            'can_participate' => $conversation->isParticipant($user),
        ]);
    }
}
