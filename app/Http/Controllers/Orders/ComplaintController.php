<?php

namespace App\Http\Controllers\Orders;

use App\Enums\ComplaintCategory;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Complaints\StoreComplaintRequest;
use App\Models\Complaint;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ComplaintController extends Controller
{
    /** Order statuses where a complaint freezes the order as disputed. */
    private const DISPUTABLE = [OrderStatus::Paid, OrderStatus::Shipped, OrderStatus::Delivered];

    public function __construct(private readonly OrderService $orders)
    {
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Complaints/Index', [
            'complaints' => Complaint::query()
                ->where('complainant_id', $request->user()->id)
                ->with('order:id,code')
                ->latest('id')
                ->paginate(20)
                ->through(fn (Complaint $c) => $this->summary($c)),
        ]);
    }

    public function create(Request $request): Response
    {
        $order = $request->filled('order') ? Order::find($request->integer('order')) : null;
        if ($order && ! $order->isParticipant($request->user())) {
            $order = null;
        }

        return Inertia::render('Complaints/Create', [
            'order' => $order?->only('id', 'code'),
            'categories' => collect(ComplaintCategory::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => ucfirst(str_replace('_', ' ', $c->value)),
            ]),
        ]);
    }

    public function store(StoreComplaintRequest $request): RedirectResponse
    {
        $user = $request->user();

        $complaint = DB::transaction(function () use ($request, $user) {
            $order = $request->filled('order_id') ? Order::query()->lockForUpdate()->find($request->integer('order_id')) : null;
            $against = $request->input('against_user_id')
                ?? ($order ? ($user->id === $order->buyer_id ? $order->seller_id : $order->buyer_id) : null);

            $complaint = Complaint::create([
                'complainant_id' => $user->id,
                'order_id' => $order?->id,
                'against_user_id' => $against,
                'category' => $request->input('category'),
                'subject' => $request->input('subject'),
                'description' => $request->input('description'),
            ]);

            if ($order && in_array($order->status, self::DISPUTABLE, true)) {
                $this->orders->dispute($order, $user, 'Complaint #'.$complaint->id.': '.$complaint->subject);
            }

            return $complaint;
        });

        return to_route('complaints.show', $complaint)->with('success', 'Complaint submitted. An admin will review it.');
    }

    public function show(Complaint $complaint): Response
    {
        Gate::authorize('view', $complaint);

        return Inertia::render('Complaints/Show', [
            'complaint' => $this->summary($complaint->load('order:id,code')) + [
                'description' => $complaint->description,
                'resolution' => $complaint->resolution,
                'resolved_at' => $complaint->resolved_at?->toIso8601String(),
            ],
        ]);
    }

    private function summary(Complaint $c): array
    {
        return [
            'id' => $c->id,
            'subject' => $c->subject,
            'category' => $c->category->value,
            'status' => $c->status->value,
            'order' => $c->order?->only('id', 'code'),
            'created_at' => $c->created_at->toIso8601String(),
        ];
    }
}
