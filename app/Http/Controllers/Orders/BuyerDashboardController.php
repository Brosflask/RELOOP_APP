<?php

namespace App\Http\Controllers\Orders;

use App\Enums\ComplaintStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Conversation;
use App\Services\Orders\OrderSummary;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BuyerDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $conversations = Conversation::query()
            ->where('buyer_id', $user->id)
            ->with(['listing:id,title', 'seller:id,name'])
            ->withCount(['messages as unread_count' => fn ($q) => $q->whereNull('read_at')->where('sender_id', '!=', $user->id)])
            ->orderByDesc('last_message_at')
            ->limit(5)
            ->get()
            ->map(fn (Conversation $c) => [
                'id' => $c->id,
                'listing_title' => $c->listing?->title,
                'seller' => $c->seller->only('id', 'name'),
                'unread_count' => $c->unread_count,
                'last_message_at' => $c->last_message_at?->toIso8601String(),
            ]);

        return Inertia::render('Buyer/Dashboard', [
            'counts' => OrderSummary::countsFor($user, 'buyer'),
            'awaiting_payment' => OrderSummary::list($user, 'buyer', [OrderStatus::AwaitingPayment->value]),
            'in_progress' => OrderSummary::list($user, 'buyer', [
                OrderStatus::Paid->value, OrderStatus::Shipped->value, OrderStatus::Delivered->value, OrderStatus::Disputed->value,
            ]),
            'conversations' => $conversations,
            'open_complaints' => Complaint::query()
                ->where('complainant_id', $user->id)
                ->whereIn('status', [ComplaintStatus::Open, ComplaintStatus::UnderReview])
                ->count(),
        ]);
    }
}
