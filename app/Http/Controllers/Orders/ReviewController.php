<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    /** Public list of reviews a user has received, with their average rating. */
    public function index(User $user): Response
    {
        $visible = Review::query()->where('reviewee_id', $user->id)->where('is_hidden', false);

        return Inertia::render('Reviews/Index', [
            'user' => $user->only('id', 'name'),
            'average_rating' => ($avg = (clone $visible)->avg('rating')) ? round((float) $avg, 2) : null,
            'review_count' => (clone $visible)->count(),
            'reviews' => $visible->with('reviewer:id,name')->latest('id')->paginate(20)->through(fn (Review $r) => [
                'id' => $r->id,
                'rating' => $r->rating,
                'comment' => $r->comment,
                'reviewer' => $r->reviewer->only('id', 'name'),
                'created_at' => $r->created_at->toIso8601String(),
            ]),
        ]);
    }

    public function store(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('review', $order);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();

        try {
            $order->reviews()->create([
                'reviewer_id' => $user->id,
                'reviewee_id' => $user->id === $order->buyer_id ? $order->seller_id : $order->buyer_id,
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? null,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['rating' => 'You have already reviewed this order.']);
        }

        return back()->with('success', 'Thanks for your review.');
    }
}
